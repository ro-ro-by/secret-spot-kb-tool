<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Changeset\Format;

use RoRoBy\SecretSpotKbTool\Model\Changeset\Data\Change;
use RoRoBy\SecretSpotKbTool\Model\Changeset\Data\ChangeSet;
use RoRoBy\SecretSpotKbTool\Model\Repo\Yaml\YamlFormatter;
use Symfony\Component\Yaml\Yaml;

/**
 * Yaml export implementation.
 */
class YamlExport
{
    public function __construct(
        private readonly YamlFormatter $yamlFormatter
    ) {
    }

    public function export(ChangeSet $changeSet): string
    {
        $itemsData = $this->convertToArray($changeSet);

        return $this->changesetDataToYaml($itemsData);
    }

    private function convertToArray(ChangeSet $changeSet): array
    {
        $changesMap = [];
        foreach ($changeSet->changes as $change) {
            $changesMap[$change->id] = $this->convertChangeToArray($change);
        }

        return [
            'meta' => [
                'generatedAt' => date('r'),
            ],
            'changes' => $changesMap,
        ];
    }

    private function convertChangeToArray(Change $change): array
    {
        return [
            'a' => $this->yamlFormatter->format($change->aData),
            'b' => $this->yamlFormatter->format($change->bData),
        ];
    }

    private function changesetDataToYaml(array $data): string
    {
        return Yaml::dump($data, 6, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
}