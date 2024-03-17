<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Console\Command\KB\Export;

use RoRoBy\SecretSpotKbTool\Model\Sight\Location\ExtractGeometries;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Export kb as GeoJSON.
 */
class GeoJSON extends Command
{
    private const ARGUMENT_KB_FILE = 'kb_file';

    public function __construct(
        private readonly ExtractGeometries $extractGeometries,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('kb:export:geojson')
            ->addArgument(self::ARGUMENT_KB_FILE, InputArgument::REQUIRED, 'KB file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $kbFile = $input->getArgument(self::ARGUMENT_KB_FILE);

        ['items' => $items] = Yaml::parseFile($kbFile);

        $features = [];
        foreach ($this->getItemsWithGeometries($items) as [$item, $geometries]) {
            $features[] = $this->buildFeatureByItem($item, $geometries);
        }

        $featureCollection = [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];

        $output->write(json_encode($featureCollection));

        return Command::SUCCESS;
    }

    /**
     * Build feature with geometries for sight item.
     *
     * @param array $item
     * @param array $geometries
     * @return array
     */
    private function buildFeatureByItem(array $item, array $geometries): array
    {
        return [
            'type' => 'Feature',
            'id' => 'secret-spot-' . $item['id'],
            'geometry' => [
                'type' => 'GeometryCollection',
                'geometries' => $geometries,
            ],
            'properties' => [
                'id' => $item['id'],
                'type' => $item['type'],
                'title' => $item['title'],
            ]
        ];
    }

    /**
     * @param array $items
     * @return iterable
     */
    private function getItemsWithGeometries(array $items): iterable
    {
        foreach ($items as $item) {
            $geometries = $this->extractGeometries->execute($item);

            if (count($geometries)) {
                yield [$item, $geometries];
            }
        }
    }
}
