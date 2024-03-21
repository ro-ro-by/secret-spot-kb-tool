<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Repo;

use RoRoBy\SecretSpotKbTool\Model\Repo\Pack\PostProcessorInterface;
use RoRoBy\SecretSpotKbTool\Model\Repo\System\SystemDataUtil;
use RoRoBy\SecretSpotKbTool\Model\Repo\Yaml\YamlFormatter;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;

/**
 * Pack repository-formatted KB to YAML KB format.
 */
class YamlPack
{
    private const SYSTEM_KEY_SOURCE_FILE = 'source';

    public function __construct(
        private readonly YamlFormatter $yamlFormatter,
        private readonly PostProcessorInterface $postProcessor,
        private readonly SystemDataUtil $systemDataUtil
    ) {
    }

    /**
     * @param string $dir repo directory
     * @param bool $full
     * @return string packed KB
     */
    public function execute(string $dir, bool $full = true): string
    {
        $items = $this->scanSourceFiles($dir);

        if ($full) {
            $items = $this->postProcessor->process($items, $dir);
        }

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
            ->ignoreDotFiles(false)
            ->in($dir)
            ->name('*.yaml');

        $items = [];
        foreach ($finder as $file) {
            $fileItems = $this->extractItemsFromSourceFile($file->getRealPath());
            $fileItems = $this->addSourceSystemDataToItems($fileItems, $file);

            $items = array_merge(
                $items,
                $fileItems,
            );
        }

        return $items;
    }

    private function addSourceSystemDataToItems(array $items, SplFileInfo $sourceFile): array
    {
        return array_map(
            function (array $item) use ($sourceFile): array {
                return $this->systemDataUtil->addData($item, [
                    self::SYSTEM_KEY_SOURCE_FILE => $sourceFile->getRelativePathname(),
                ]);
            },
            $items
        );
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
