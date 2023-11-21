<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Repo\Unpack\Sight;

use RoRoBy\SecretSpotKbTool\Model\Repo\Yaml\YamlFormatter;

/**
 * Sight item content formatter.
 */
class Formatter
{
    private const FIELD_SORT_ORDER = [
        'id' => -100,
        'title' => -10,
        'type' => -9,
        'location' => 10,
        'osm' => 20,
        'meta' => 100,
    ];

    public function __construct(
        private readonly YamlFormatter $yamlFormatter
    ) {
    }

    public function format(array $item): string
    {
        return $this->yamlFormatter->format(
            $this->sortItemData($item)
        );
    }

    private function sortItemData(array $item): array
    {
        uksort(
            $item,
            function ($a, $b): int {
                $byOrder = ($this->getSortOrderByKey($a) <=> $this->getSortOrderByKey($b));
                return $byOrder !== 0 ? $byOrder : ($a <=> $b);
            }
        );

        return $item;
    }

    private function getSortOrderByKey(string $key): int
    {
        return self::FIELD_SORT_ORDER[$key] ?? 0;
    }
}
