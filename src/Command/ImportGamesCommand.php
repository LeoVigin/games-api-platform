<?php

namespace App\Command;

use App\Entity\Game;
use App\Entity\Genre;
use App\Entity\Platform;
use App\Entity\Publishers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:import-games',
    description: 'Importe des jeux depuis RAWG.io (ETL)'
)]
class ImportGamesCommand extends Command
{
    private const API_URL = 'https://api.rawg.io/api/games';
    private const LIMIT = 40;   // max par page côté RAWG
    private const TARGET = 50;  // total voulu

    /** @var array<string, Publishers> */
    private array $publisherCache = [];
    /** @var array<string, Genre> */
    private array $genreCache = [];
    /** @var array<string, Platform> */
    private array $platformCache = [];

    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $em,
        #[Autowire('%env(RAWG_API_KEY)%')]
        private string $rawgApiKey
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('subject', InputArgument::REQUIRED, 'Sujet/genre à importer (ex: action)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule sans écrire en base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $subject = $input->getArgument('subject');
        $dryRun = (bool) $input->getOption('dry-run');

        // ═══════════════════════════════════════════
        // E - EXTRAIRE (1/2) : la liste, paginée pour atteindre TARGET
        // ═══════════════════════════════════════════
        $io->section('E - EXTRAIRE');

        $results = [];

        try {
            for ($page = 1; count($results) < self::TARGET; $page++) {
                $response = $this->httpClient->request('GET', self::API_URL, [
                    'query' => [
                        'key' => $this->rawgApiKey,
                        'genres' => $subject,
                        'page_size' => self::LIMIT,
                        'page' => $page,
                    ],
                    'timeout' => 30,
                ]);
                $data = $response->toArray(false);

                if (isset($data['error'])) {
                    $io->error('RAWG : ' . $data['error']);
                    return Command::FAILURE;
                }

                $batch = $data['results'] ?? [];
                if (empty($batch)) {
                    break;
                }

                $results = array_merge($results, $batch);
            }
        } catch (\Throwable $e) {
            $io->error('Erreur lors de l\'appel API RAWG : ' . $e->getMessage());
            return Command::FAILURE;
        }

        $results = array_slice($results, 0, self::TARGET);
        $io->text(sprintf('%d jeux listés.', count($results)));

        $imported = 0;
        $skipped = 0;
        $seenIds = [];

        foreach ($results as $doc) {
            $rawgId = isset($doc['id']) ? (string) $doc['id'] : '';
            if ($rawgId === '') {
                $skipped++;
                continue;
            }

            // ═══════════════════════════════════════
            // T2 - DÉDOUBLONNER : si rawgId existe déjà en base, on ignore
            // (fait avant le détail, pour économiser un appel)
            // ═══════════════════════════════════════
            if (isset($seenIds[$rawgId])) {
                $skipped++;
                continue;
            }
            $seenIds[$rawgId] = true;

            if ($this->em->getRepository(Game::class)->findOneBy(['rawgId' => $rawgId]) !== null) {
                $skipped++;
                continue;
            }

            // ═══════════════════════════════════════
            // E - EXTRAIRE (2/2) : le détail, seule source de description_raw
            // ═══════════════════════════════════════
            try {
                $detailResponse = $this->httpClient->request('GET', self::API_URL . '/' . $rawgId, [
                    'query' => ['key' => $this->rawgApiKey],
                    'timeout' => 30,
                ]);
                $detail = $detailResponse->toArray(false);
            } catch (\Throwable $e) {
                $io->warning(sprintf('Détail %s indisponible : %s', $rawgId, $e->getMessage()));
                $skipped++;
                continue;
            }

            // ═══════════════════════════════════════
            // T1 - FILTRER : sans nom, sans description, sans date → on ignore
            // ═══════════════════════════════════════
            $title = trim((string) ($detail['name'] ?? ''));

            $rawDesc = (string) ($detail['description_raw'] ?? '');
            if ($rawDesc === '') {
                $rawDesc = html_entity_decode(
                    strip_tags((string) ($detail['description'] ?? '')),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                );
            }
            $description = trim($rawDesc);

            $releaseDate = $this->parseDate($detail['released'] ?? null);

            if ($title === '' || $description === '' || $releaseDate === null) {
                $skipped++;
                continue;
            }

            $game = new Game();
            $game->setRawgId($rawgId);

            // ═══════════════════════════════════════
            // T3 - RELIER : éditeur par son nom, sinon créé (mémoire de l'import)
            // ═══════════════════════════════════════

            // RAWG liste les éditeurs par région/portage 
            // On retient le premier nom non vide : cela peut donner un éditeur
            // local (ex. Capcom pour GTA San Andreas) plutôt que l'éditeur principal
            $publishers = $detail['publishers'] ?? [];
            if (!empty($publishers) && !empty($publishers[0]['name'])) {
                $publisherName = $this->truncate(trim((string) $publishers[0]['name']), 255);
            } else {
                $publisherName = 'Inconnu';
            }
            $game->setPublishers($this->findOrCreatePublisher($publisherName));

            // ═══════════════════════════════════════
            // T4 - NORMALISER : 4 premiers genres, en minuscules
            // ═══════════════════════════════════════
            $genres = array_slice($detail['genres'] ?? [], 0, 4);
            foreach ($genres as $genre) {
                $genreName = mb_strtolower(trim((string) ($genre['name'] ?? '')));
                if ($genreName === '') {
                    continue;
                }
                $game->addGenre($this->findOrCreateGenre($this->truncate($genreName, 255)));
            }

            // ═══════════════════════════════════════
            // T5 - NORMALISER : 4 premières plateformes, première lettre en majuscule
            // ═══════════════════════════════════════
            $platforms = array_slice($detail['platforms'] ?? [], 0, 4);
            foreach ($platforms as $entry) {
                $platformName = trim((string) ($entry['platform']['name'] ?? ''));
                if ($platformName === '') {
                    continue;
                }
                $platformName = mb_strtoupper(mb_substr($platformName, 0, 1)) . mb_substr($platformName, 1);
                $game->addPlatform($this->findOrCreatePlatform($this->truncate($platformName, 255)));
            }

            // ═══════════════════════════════════════
            // T6 / T7 - TRONQUER
            // ═══════════════════════════════════════
            $game->setName($this->truncate($title, 255));
            $game->setDescription($this->truncateSentence($description, 400));
            $game->setReleaseDate($releaseDate);

            $this->em->persist($game);
            $imported++;
        }

        // ═══════════════════════════════════════════
        // L - CHARGER : un seul flush à la fin
        // ═══════════════════════════════════════════
        $io->section('L - CHARGER');
        if ($dryRun) {
            $io->warning('Mode dry-run : rien n\'a été écrit en base.');
        } else {
            $this->em->flush();
        }

        $io->success(sprintf('%d jeux importés, %d ignorés.', $imported, $skipped));

        return Command::SUCCESS;
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', substr($value, 0, 10));
        return $date !== false ? $date : null;
    }

    private function findOrCreatePublisher(string $name): Publishers
    {
        if (isset($this->publisherCache[$name])) {
            return $this->publisherCache[$name];
        }
        $publisher = $this->em->getRepository(Publishers::class)->findOneBy(['name' => $name]);
        if ($publisher === null) {
            $publisher = new Publishers();
            $publisher->setName($name);
            $this->em->persist($publisher);
        }
        return $this->publisherCache[$name] = $publisher;
    }

    private function findOrCreateGenre(string $name): Genre
    {
        if (isset($this->genreCache[$name])) {
            return $this->genreCache[$name];
        }
        $genre = $this->em->getRepository(Genre::class)->findOneBy(['name' => $name]);
        if ($genre === null) {
            $genre = new Genre();
            $genre->setName($name);
            $this->em->persist($genre);
        }
        return $this->genreCache[$name] = $genre;
    }

    private function findOrCreatePlatform(string $name): Platform
    {
        if (isset($this->platformCache[$name])) {
            return $this->platformCache[$name];
        }
        $platform = $this->em->getRepository(Platform::class)->findOneBy(['name' => $name]);
        if ($platform === null) {
            $platform = new Platform();
            $platform->setName($name);
            $this->em->persist($platform);
        }
        return $this->platformCache[$name] = $platform;
    }

    private function truncate(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }

    private function truncateSentence(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        $cut = mb_substr($value, 0, $max - 3);
        $lastSpace = mb_strrpos($cut, ' ');

        if ($lastSpace !== false && $lastSpace > 0) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, " ,.;:!?-") . '...';
    }
}