<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Repo;

use RoRoBy\SecretSpotKbTool\Model\Repo\System\SystemDataUtil;
use RoRoBy\SecretSpotKbTool\Model\Repo\Unpack\MetadataStrategy;
use RoRoBy\SecretSpotKbTool\Model\Repo\Unpack\SightStrategy;
use Symfony\Component\Yaml\Yaml;

/**
 * Unpack YAML KB format to repository-formatted.
 */
class YamlUnpack
{
    public function __construct(
        private readonly SightStrategy $sightStrategy,
        private readonly MetadataStrategy $metadataStrategy,
        private readonly SystemDataUtil $systemDataUtil
    ) {
    }

    /**
     * @param string $input input YAML KB
     * @param string $dir output repo directory
     * @return void
     */
    public function execute(string $input, string $dir): void
    {
        ['items' => $items] = Yaml::parse($input);

        $items = array_map(
            fn (array $item): array => $this->systemDataUtil->cleanData($item),
            $items
        );

        $byType = $this->groupItemsByType($items);

        foreach ($byType as $type => $typeItems) {
            $this->unpackTypeItems($type, $typeItems, $dir);
        }
    }

    private function unpackTypeItems(string $type, array $items, string $dir): void
    {
        switch ($type) {
            case 'sight':
                $this->sightStrategy->execute($items, $dir);
                break;
            default:
                $this->metadataStrategy->execute($items, $dir, $type);
        }
    }

    private function groupItemsByType(array $items): array
    {
        $map = [];

        foreach ($items as $item) {
            ['id' => $id] = $item;
            $type = $this->getItemTypeById($id);

            $map[$type] = $map[$type] ?? [];
            $map[$type][] = $item;
        }

        return $map;
    }

    private function getItemTypeById(string $id): string
    {
        $parts = explode('-', $id);

        return $parts[0];
    }
}
