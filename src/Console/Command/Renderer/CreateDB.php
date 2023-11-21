<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Console\Command\Renderer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Table;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Create DB for rendering
 */
class CreateDB extends Command
{
    private const ARGUMENT_KB_FILE = 'kb_file';

    public function __construct(
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('renderer:db:create')
            ->addArgument(self::ARGUMENT_KB_FILE, InputArgument::REQUIRED, 'KB file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $kbFile = $input->getArgument(self::ARGUMENT_KB_FILE);

        ['items' => $items] = Yaml::parseFile($kbFile);

        $connection = $this->buildConnection();

        $this->createTable($connection);

        foreach ($items as $item) {
            $uid = $item['id'];
            $title = $item['title'];
            $type = $item['type'];
            $locations = $item['location'] ?? [];

            foreach ($locations as $location) {
                $geometries = [];
                if ($location['type'] === 'point') {
                    $geometries = [[
                        'type' => 'Point',
                        'coordinates' => [
                            $location['coordinates']['lon'],
                            $location['coordinates']['lat'],
                        ]
                    ]];
                } elseif ($location['type'] === 'geojson') {
                    $geometries = $this->extractGeometries($location['geojson']['content']);
                }

                if (empty($geometries)) continue;

                foreach ($geometries as $geometry) {
                    $stmt = $connection->prepare('INSERT into points (uid, type, title, location) VALUES (:uid, :type, :title, ST_Transform(ST_GeomFromGeoJSON(:location), 3857))');
                    $stmt->bindValue('uid', $uid);
                    $stmt->bindValue('title', $title);
                    $stmt->bindValue('type', $type);
                    $stmt->bindValue('location', json_encode($geometry));

                    $stmt->executeStatement();

                    $output->writeln(sprintf('Inserted %s', $uid));
                }
            }

        }

        return Command::SUCCESS;
    }

    private function extractGeometries(string $geojson): array
    {
        $featureCollection = json_decode($geojson, true);

        $geometries = [];
        foreach ($featureCollection['features'] as $feature) {
            $geometries[] = $feature['geometry'];
        }

        return $geometries;
    }

    private function buildConnection(): Connection
    {
        $connectionParams = [
            'dbname' => 'postgres',
            'user' => 'postgres',
            'host' => 'db',
            'port' => '5432',
            'driver' => 'pdo_pgsql',
        ];

        return DriverManager::getConnection($connectionParams);
    }

    private function createTable(Connection $connection): void
    {
        if ($connection->createSchemaManager()->tablesExist('points')) {
            $connection->executeQuery('DELETE from points');
        }

        $createTable = <<<SQL
-- Table: public.points

-- DROP TABLE IF EXISTS public.points;

CREATE TABLE IF NOT EXISTS public.points
(
    uid character varying(255) COLLATE pg_catalog."default" NOT NULL,
    location geometry,
    title character varying(255) COLLATE pg_catalog."default",
    type character varying(255) COLLATE pg_catalog."default"
)

TABLESPACE pg_default;

ALTER TABLE IF EXISTS public.points
    OWNER to postgres;
-- Index: location_idx

-- DROP INDEX IF EXISTS public.location_idx;

CREATE INDEX IF NOT EXISTS location_idx
    ON public.points USING gist
    (location)
    TABLESPACE pg_default;
SQL;

        $connection->executeStatement($createTable);
    }
}