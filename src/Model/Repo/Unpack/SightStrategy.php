<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Repo\Unpack;

use RoRoBy\SecretSpotKbTool\Model\Repo\Unpack\Sight\DumpGeoJson;
use RoRoBy\SecretSpotKbTool\Model\Repo\Unpack\Sight\Formatter;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Strategy to unpack sight items.
 */
class SightStrategy
{
    public function __construct(
        private readonly Formatter $formatter,
        private readonly DumpGeoJson $dumpGeoJson
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

        $item = $this->dumpGeoJson->execute($item, $itemDir, $dir);

        $itemContent = $this->formatter->format($item);
        $fs->dumpFile($itemAbsPath, $itemContent);
    }

    private function buildItemDirPath(array $item): string
    {
        $regionCode = explode(':', $item['belongsTo']['region'])[1];
        $subregionCode = explode(':', $item['belongsTo']['subregion'])[1];

        return sprintf(
            '%s/%s',
            $regionCode,
            $subregionCode
        );
    }

    private function buildItemFilename(array $item): string
    {
        return sprintf('%s.yaml', $item['id']);
    }
}
