<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Repo\Unpack;

use RoRoBy\SecretSpotKbTool\Model\Repo\Yaml\YamlFormatter;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Strategy to unpack sight items.
 */
class SightStrategy
{
    public function __construct(
        private readonly YamlFormatter $yamlFormatter
    ) {
    }

    public function execute(array $items, string $dir): void
    {
        foreach ($items as $item) {
            $this->unpackItem($item, $dir);
        }
    }

    private function unpackItem(array $item, string $dir): void
    {
        $fs = new Filesystem();

        $itemDir = $this->buildItemDirPath($item);
        $itemDirAbsPath = "{$dir}/{$itemDir}";

        $itemFilename = $this->buildItemFilename($item);
        $itemAbsPath = "{$itemDirAbsPath}/{$itemFilename}";

        $itemContent = $this->yamlFormatter->format($item);
        $fs->dumpFile($itemAbsPath, $itemContent);
    }

    private function buildItemDirPath(array $item): string
    {
        return sprintf(
            '%s/%s',
            $item['belongsTo']['region'],
            $item['belongsTo']['subregion']
        );
    }

    private function buildItemFilename(array $item): string
    {
        return sprintf('%s.yaml', $item['id']);
    }
}
