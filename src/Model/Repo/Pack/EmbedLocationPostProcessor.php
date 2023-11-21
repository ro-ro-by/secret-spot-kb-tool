<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Repo\Pack;

use Exception;
use Symfony\Component\Finder\Finder;

/**
 * Embed location post-processor.
 *
 * Replace links to geojson location files to file content.
 */
class EmbedLocationPostProcessor implements PostProcessorInterface
{
    public function process(array $items, string $dir): array
    {
        $geoJsonFileMap = $this->getGeoJsonFilesMap($dir);

        foreach ($items as &$item) {
            if (empty($item['location'])) {
                continue;
            }

            foreach ($item['location'] as &$location) {
                if (!isset($location['geojson']['file'])) {
                    continue;
                }
                $fileLink = $location['geojson']['file'];
                $filename = str_replace('repo://', '', $fileLink);

                if (!isset($geoJsonFileMap[$filename])) {
                    throw new Exception('Undefined file');
                }

                $location['geojson'] = [
                    'content' => $geoJsonFileMap[$filename],
                ];
            }

        }

        return $items;
    }

    private function getGeoJsonFilesMap(string $dir): array
    {
        $finder = new Finder();
        $finder->files()
            ->in($dir)
            ->name('*.geojson');

        $map = [];
        foreach ($finder as $file) {
            $map[$file->getFilename()] = file_get_contents($file->getRealPath());
        }

        return $map;
    }
}
