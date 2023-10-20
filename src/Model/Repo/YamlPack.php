<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Repo;

use RoRoBy\SecretSpotKbTool\Model\Repo\Yaml\YamlFormatter;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Pack repository-formatted KB to YAML KB format.
 */
class YamlPack
{
    public function __construct(
        private readonly YamlFormatter $yamlFormatter
    ) {
    }

    /**
     * @param string $dir repo directory
     * @return string packed KB
     */
    public function execute(string $dir): string
    {
        $items = $this->scanSourceFiles($dir);

        $outputData = [
            'items' => $items,
        ];

        return $this->yamlFormatter->format($outputData);
    }

    /**
     * Scan source files.
     *
     * @param string $dir
     * @return array
     */
    private function scanSourceFiles(string $dir): array
    {
        $finder = new Finder();
        $finder->files()
            ->in($dir)
            ->name('*.yaml');

        $items = [];
        foreach ($finder as $file) {
            $items = array_merge(
                $items,
                $this->extractItemsFromSourceFile($file->getRealPath()),
            );
        }

        return $items;
    }

    private function extractItemsFromSourceFile(string $path): array
    {
        $source = Yaml::parseFile($path);

        return $this->extractItemsFromSource($source);
    }

    private function extractItemsFromSource(array $source): array
    {
        return $source['items'] ?? [$source];
    }
}