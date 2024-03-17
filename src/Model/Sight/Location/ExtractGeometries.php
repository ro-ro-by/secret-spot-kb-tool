<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Sight\Location;

/**
 * Service to extract location geometries from sight item.
 */
class ExtractGeometries
{
    /**
     * Extract geometries from sight item.
     *
     * @param array $item
     * @return array
     */
    public function execute(array $item): array
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
}