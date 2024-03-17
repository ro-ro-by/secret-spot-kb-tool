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
            $features = array_merge($features, $this->buildFeaturesByItem($item, $geometries));
        }

        $featureCollection = [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];

        $output->write(json_encode($featureCollection));

        return Command::SUCCESS;
    }

    /**
     * Build features with geometries for sight item.
     *
     * Initially this method designed to generate single feature with `GeometryCollection`,
     * but this type of geometry isn't supported by many viewers
     *
     * @param array $item
     * @param array $geometries
     * @return array
     */
    private function buildFeaturesByItem(array $item, array $geometries): array
    {
        $shortId = explode('-', $item['id'])[2];
        $titleLong = sprintf(
            '[%s] %s (%s)',
            $shortId,
            $item['title'],
            $item['type']
        );
        $description = sprintf(
            "id: %s\ntitle: %s\ntype: %s",
            $shortId,
            $item['title'],
            $item['type']
        );

        return array_map(
            function (array $geometry) use ($item, $titleLong, $description): array {
                return [
                    'type' => 'Feature',
                    'geometry' => $geometry,
                    'properties' => [
                        'id' => $item['id'],
                        'type' => $item['type'],
                        'title' => $item['title'],
                        'title_long' => $titleLong,
                        'description' => $description,
                    ],
                ];
            },
            $geometries
        );
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
