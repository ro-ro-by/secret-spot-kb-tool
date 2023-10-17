<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Repo\Unpack;

use RoRoBy\SecretSpotKbTool\Model\Repo\Yaml\YamlFormatter;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Strategy to unpack metadata items.
 */
class MetadataStrategy
{
    public function __construct(
        private readonly YamlFormatter $yamlFormatter
    ) {
    }

    public function execute(array $items, string $dir, string $type): void
    {
        $filename = sprintf('%s/%s/%s.yaml', $dir, 'meta', $type);
        $content = $this->yamlFormatter->format([
            'items' => $items,
        ]);

        $fs = new Filesystem();

        $fs->dumpFile($filename, $content);
    }
}
