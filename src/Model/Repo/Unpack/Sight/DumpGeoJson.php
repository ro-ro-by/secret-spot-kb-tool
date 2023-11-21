<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Repo\Unpack\Sight;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Dump sight geojson.
 */
class DumpGeoJson
{
    public function execute(array $item, string $itemDir, string $repoDir): array
    {
        $locations = $item['location'] ?? [];
        $geojsonLocations = array_filter($locations, function (array $location): bool {
            return $location['type'] === 'geojson';
        });

        if (empty($geojsonLocations)) {
            return $item;
        }

        foreach ($geojsonLocations as $i => $geojsonLocation) {
            $locations[$i] = $this->dumpLocation($item['id'], $geojsonLocation, $itemDir, $repoDir);
        }

        $item['location'] = $locations;

        return $item;
    }

    private function dumpLocation(string $itemId, array $location, string $itemDir, string $repoDir): array
    {
        $content = $location['geojson']['content'];

        // @TODO non-osm locations
        ['id' => $osmId, 'type' => $osmType] = $location['source']['osm'];
        $filename = sprintf('location-osm-%s-%s.geojson', $osmType, $osmId);

        $filePath = "{$itemDir}/{$itemId}/{$filename}";
        $filePathAbs = "{$repoDir}/{$filePath}";
        $fs = new Filesystem();
        $fs->dumpFile($filePathAbs, $content);

        return [
            ...$location,
            'geojson' => [
                'file' => 'repo://' . $filename,
            ],
        ];
    }
}
