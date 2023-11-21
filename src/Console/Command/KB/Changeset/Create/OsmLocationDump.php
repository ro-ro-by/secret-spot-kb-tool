<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Console\Command\KB\Changeset\Create;

use Exception;
use GuzzleHttp\Client;
use RoRoBy\SecretSpotKbTool\Model\Changeset\Data\Change;
use RoRoBy\SecretSpotKbTool\Model\Changeset\Data\ChangeSet;
use RoRoBy\SecretSpotKbTool\Model\Changeset\Format\YamlExport;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Create changeset with OSM Location dump command.
 */
class OsmLocationDump extends Command
{
    private const ARGUMENT_KB_FILE = 'kb_file';
    private const ARGUMENT_OUTPUT_FILE = 'output_file';

    public function __construct(
        private readonly YamlExport $yamlExport,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('kb:changeset:create:osm:location-dump')
            ->addArgument(self::ARGUMENT_KB_FILE, InputArgument::REQUIRED, 'KB file')
            ->addArgument(self::ARGUMENT_OUTPUT_FILE, InputArgument::REQUIRED, 'Output file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $kbFile = $input->getArgument(self::ARGUMENT_KB_FILE);
        $outputFilename = $input->getArgument(self::ARGUMENT_OUTPUT_FILE);

        $output->writeln('Reading sources...');
        ['items' => $kbItems] = Yaml::parseFile($kbFile);

        $output->writeln('Creating changeset...');
        $changeset = $this->buildChangeset($kbItems, $output);

        $output->writeln('Exporting changeset...');
        $fs = new Filesystem();
        $fs->dumpFile($outputFilename, $this->yamlExport->export($changeset));

        $output->writeln('Finished.');

        return Command::SUCCESS;
    }

    private function buildChangeset(array $kbItems, OutputInterface $output): ChangeSet
    {
        $changes = [];

        $sightItems = array_filter(
            $kbItems,
            function (array $item): bool {
                return str_starts_with($item['id'], 'sight:');
            }
        );

        $sightItemWithOsm = array_filter(
            $sightItems,
            function (array $item): bool {
                return isset($item['osm']);
            }
        );

        foreach ($sightItemWithOsm as $sourceItem) {
            $targetItem = $this->dumpToItem($sourceItem);

            $changes[] = new Change($sourceItem['id'], $sourceItem, $targetItem);
        }

        return new ChangeSet($changes);
    }

    private function dumpToItem(array $item): array
    {
        $links = $item['osm'] ?? [];

        $osmLocations = array_map(
            fn(array $link): array => $this->getOsmLocation($link),
            $links
        );

        $item['location'] = $this->mergeLocation($item['location'] ?? [], $osmLocations);

        return $item;
    }

    private function mergeLocation(array $a, array $b): array
    {
        $a = array_filter($a, function (array $location): bool {
            return ($location['source']['type'] ?? null) !== 'osm';
        });

        return array_merge($a, $b);
    }

    private function getOsmLocation(array $link): array
    {
        ['type' => $type, 'id' => $id] = $link;

        $geojson = $this->getOsmGeoJson($type, $id);

        if ($type === 'node') {
            return $this->formatOsmNodeLocation($type, $id, $geojson);
        }

        return [
            'type' => 'geojson',
            'geojson' => [
                'content' => $geojson,
            ],
            'source' => [
                'type' => 'osm',
                'osm' => [
                    'type' => $type,
                    'id' => $id
                ]
            ],
        ];
    }

    /**
     * @param string $type
     * @param string $id
     * @param string $geojson
     * @return array
     * @throws Exception
     */
    private function formatOsmNodeLocation(string $type, int $id, string $geojson): array
    {
        $featureCollection = json_decode($geojson, true);

        $point = $this->extractPointFromFeatureCollection($featureCollection);
        if ($point === null) {
            throw new Exception('Point feature not found');
        }
        [$lon, $lat] = $point['coordinates'];

        return [
            'type' => 'point',
            'coordinates' => [
                'lat' => (float)$lat,
                'lon' => (float)$lon,
            ],
            'source' => [
                'type' => 'osm',
                'osm' => [
                    'type' => $type,
                    'id' => $id
                ]
            ],
        ];
    }

    private function extractPointFromFeatureCollection(array $collection): ?array
    {
        foreach ($collection['features'] as $feature) {
            if ($feature['geometry']['type'] === 'Point') {
                return $feature['geometry'];
            }
        }

        return null;
    }

    private function getOsmGeoJson(string $type, int $id): string
    {
        $overpassData = $this->getOverpassData($type, $id);

        $tempFile = tmpfile();
        $tempFilePath = stream_get_meta_data($tempFile)['uri'];
        (new Filesystem())->dumpFile($tempFilePath, $overpassData);

        $process = new Process(['osmtogeojson', $tempFilePath]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

    private function getOverpassData(string $type, int $id): string
    {
        $query = "[out:json][timeout:25];{$type}(id:{$id});(._;>;);out body;";

        return $this->queryOverpass($query);
    }

    private function queryOverpass(string $query): string
    {
        $client = new Client([
            'base_uri' => 'https://overpass-api.de/api/interpreter',
            'timeout'  => 25,
        ]);

        $response = $client->post('', [
            'form_params' => [
                'data' => $query,
            ],
        ]);

        return $response->getBody()->getContents();
    }
}