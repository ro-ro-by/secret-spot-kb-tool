<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Console\Command\Renderer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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

        $connection = $this->buildConnection();

        $output->writeln('Creating table...');
        $this->createTable($connection);

        $output->writeln('Reading kb...');
        ['items' => $items] = Yaml::parseFile($kbFile);

        $output->writeln('Processing items...');

        $itemsCount = 0;
        $rowsCount = 0;

        foreach ($items as $item) {
            $output->writeln(sprintf('Processing item %s', $item['id']));
            $rows = $this->extractItemLocationsRows($item);

            $itemsCount += (int)!empty($rows);

            foreach ($rows as $i => $row) {
                $output->writeln(sprintf('Saving location %s #%d', $item['id'], $i));
                $this->saveItemLocationRow($connection, $row);
                $rowsCount++;
            }
        }

        $output->writeln(
            sprintf('Added %d locations geometries for %d items.', $rowsCount, $itemsCount)
        );

        return Command::SUCCESS;
    }

    /**
     * Save item location row to DB.
     *
     * @param Connection $connection
     * @param array $row
     * @return void
     * @throws Exception
     */
    private function saveItemLocationRow(Connection $connection, array $row): void
    {
        $stmt = $connection->prepare(<<<SQL
INSERT into points (id, short_id, type, title, location)
VALUES (:id, :short_id, :type, :title, ST_Transform(ST_GeomFromGeoJSON(:location), 3857))
SQL);

        foreach ($row as $param => $value) {
            $stmt->bindValue($param, $value);
        }

        $stmt->executeStatement();
    }

    /**
     * Extract db rows by item.
     *
     * @param array $item
     * @return array[]
     */
    private function extractItemLocationsRows(array $item): array
    {
        return array_map(
            fn(array $geometry) => $this->buildItemLocationRow($item, $geometry),
            $this->extractItemGeometries($item)
        );
    }

    /**
     * Build db row by item and geometry.
     *
     * @param array $item
     * @param array $geometry
     * @return array
     */
    private function buildItemLocationRow(array $item, array $geometry): array
    {
        return [
            'id' => $item['id'],
            'short_id' => explode('-', $item['id'])[2],
            'title' => $item['title'],
            'type' => $item['type'],
            'location' => json_encode($geometry),
        ];
    }

    /**
     * Extract geometries from sight item.
     *
     * @param array $item
     * @return array
     */
    private function extractItemGeometries(array $item): array
    {
        $locations = $item['location'] ?? [];
        $geometries = [];
        foreach ($locations as $location) {
            $geometries = array_merge($geometries, $this->extractItemLocationGeometries($location));
        }

        return $geometries;
    }

    /**
     * Extract geometries from sight item location data.
     *
     * @param array $location
     * @return array[]
     */
    private function extractItemLocationGeometries(array $location): array
    {
        if ($location['type'] === 'point') {
            return [
                [
                    'type' => 'Point',
                    'coordinates' => [
                        $location['coordinates']['lon'],
                        $location['coordinates']['lat'],
                    ]
                ]
            ];
        }

        if ($location['type'] === 'geojson') {
            return $this->extractGeometries($location['geojson']['content']);
        }

        return [];
    }

    /**
     * Extract geometries from GeoJSON features.
     *
     * @param string $geojson
     * @return array[]
     */
    private function extractGeometries(string $geojson): array
    {
        $featureCollection = json_decode($geojson, true);

        $geometries = array_column($featureCollection['features'], 'geometry');

        // skip non-polygon geometries as workaround for correct rendering of areas
        $geometries = array_filter(
            $geometries,
            fn(array $geom) => in_array($geom['type'], ['Polygon', 'MultiPolygon'])
        );

        return $geometries;
    }

    /**
     * Build PostgreSQL connection.
     *
     * @return Connection
     * @throws Exception
     */
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

    /**
     * Init new table.
     *
     * @param Connection $connection
     * @return void
     * @throws Exception
     */
    private function createTable(Connection $connection): void
    {
        if ($connection->createSchemaManager()->tablesExist('points')) {
            $connection->createSchemaManager()->dropTable('points');
        }

        $createTable = <<<SQL
-- Table: public.points

-- DROP TABLE IF EXISTS public.points;

CREATE TABLE IF NOT EXISTS public.points
(
    id character varying(255) COLLATE pg_catalog."default" NOT NULL,
    location geometry,
    short_id character varying(255) COLLATE pg_catalog."default",
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