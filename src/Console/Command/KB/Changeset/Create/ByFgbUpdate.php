<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Console\Command\KB\Changeset\Create;

use RoRoBy\SecretSpotKbTool\Model\Changeset\Data\Change;
use RoRoBy\SecretSpotKbTool\Model\Changeset\Data\ChangeSet;
use RoRoBy\SecretSpotKbTool\Model\Changeset\Format\YamlExport;
use RoRoBy\SecretSpotKbTool\Model\Sight\ExtractSemanticData\OpenAI;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Create changeset by FGB update
 */
class ByFgbUpdate extends Command
{
    private const ARGUMENT_KB_FILE = 'kb_file';
    private const ARGUMENT_FGB_FILE_V0 = 'fgb_file_v0';
    private const ARGUMENT_FGB_FILE_V1 = 'fgb_file_v1';
    private const ARGUMENT_OUTPUT_FILE = 'output_file';

    public function __construct(
        private readonly YamlExport $yamlExport,
        private readonly OpenAI $openAIExtractSemantic,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('kb:changeset:create:by-fgb-update')
            ->addArgument(self::ARGUMENT_KB_FILE, InputArgument::REQUIRED, 'KB file')
            ->addArgument(self::ARGUMENT_FGB_FILE_V0, InputArgument::REQUIRED, 'FGB file V0')
            ->addArgument(self::ARGUMENT_FGB_FILE_V1, InputArgument::REQUIRED, 'FGB file V1')
            ->addArgument(self::ARGUMENT_OUTPUT_FILE, InputArgument::REQUIRED, 'Output file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $kbFile = $input->getArgument(self::ARGUMENT_KB_FILE);
        $fgbV0Filename = $input->getArgument(self::ARGUMENT_FGB_FILE_V0);
        $fgbV1Filename = $input->getArgument(self::ARGUMENT_FGB_FILE_V1);
        $outputFilename = $input->getArgument(self::ARGUMENT_OUTPUT_FILE);

        $output->writeln('Reading kb...');
        ['items' => $kbItems] = Yaml::parseFile($kbFile);

        $output->writeln('Reading fgb v0...');
        ['items' => $fgbV0Items] = Yaml::parseFile($fgbV0Filename);

        $output->writeln('Reading fgb v1...');
        ['items' => $fgbV1Items] = Yaml::parseFile($fgbV1Filename);

        $output->writeln('Creating changeset...');
        $changeset = $this->buildChangeset($kbItems, $fgbV0Items, $fgbV1Items, $output);

        $output->writeln('Exporting changeset...');
        $fs = new Filesystem();
        $fs->dumpFile($outputFilename, $this->yamlExport->export($changeset));

        $output->writeln('Finished.');

        return Command::SUCCESS;
    }

    private function buildChangeset(
        array $kbItems,
        array $fgbV0Items,
        array $fgbV1Items,
        OutputInterface $output
    ): ChangeSet {
        $kbMap = $this->buildMapById($kbItems);
        $fgbV0Map = $this->buildMapById($fgbV0Items);
        $fgbV1Map = $this->buildMapById($fgbV1Items);

        $fgbV0Ids = array_keys($fgbV0Map);
        $fgbV1Ids = array_keys($fgbV1Map);

        $addedIds = array_diff($fgbV1Ids, $fgbV0Ids);
        $removedIds = array_diff($fgbV0Ids, $fgbV1Ids);
        $modifiedIds = $this->getModifiedItemsIds($fgbV0Map, $fgbV1Map);

        $sightItems = array_filter(
            $kbItems,
            function ($item) {
                return str_starts_with($item['id'], 'sight-');
            }
        );

        $fgbIdKbIdMap = [];
        foreach ($sightItems as $kbItem) {
            $postId = $kbItem['meta']['fgb']['post_id'];
            $postIds = is_array($postId) ? $postId : [$postId];

            foreach ($postIds as $postId) {
                $fgbIdKbIdMap[$postId] = $kbItem['id'];
            }
        }

        $changes = [];

        // processing removed items
        $output->writeln(sprintf('Processing removed items... (%d changes)', count($changes)));
        foreach ($removedIds as $fgbId) {
            $kbId = $fgbIdKbIdMap[$fgbId];
            $kbItem = $kbMap[$kbId];
            $changes[] = new Change($kbId, $kbItem, null);
        }

        // processing added items
        $output->writeln(sprintf('Processing added items... (%d changes)', count($changes)));

        foreach ($addedIds as $fgbId) {
            $kbId = sprintf('sight-fgb-%s', $fgbId);
            $fgbItem = $fgbV1Map[$fgbId];

            $output->writeln(sprintf('Building new item %s', $fgbId));
            $newKbItem = $this->buildNewItem($fgbItem, $kbItems);

            $changes[] = new Change($kbId, null, $newKbItem);
        }

        // processing modified items
        $output->writeln(sprintf('Processing modified items... (%d changes)', count($changes)));

        foreach ($modifiedIds as $fgbId) {
            $kbId = sprintf('sight-fgb-%s', $fgbId);
            $fgbItem = $fgbV1Map[$fgbId];
            $kbItem = $kbMap[$kbId];

            $output->writeln(sprintf('Building modified item #%d %s', count($changes), $fgbId));
            $modifiedKbItem = $this->buildModifiedItem($kbItem, $fgbItem, $kbItems);

            $changes[] = new Change($kbId, $kbItem, $modifiedKbItem);
        }

        return new ChangeSet($changes);
    }

    /**
     * @param array[] $items
     * @return array[]
     */
    private function buildMapById(array $items): array
    {
        $map = [];

        foreach ($items as $item) {
            $map[$item['id']] = $item;
        }

        return $map;
    }

    private function buildNewItem(array $fgbItem, array $kbItems): array
    {
        $suggestedData = $this->openAIExtractSemantic->extract($fgbItem);

        $result = [];

        $result['id'] = sprintf('sight-fgb-%s', $fgbItem['id']);

        if ($suggestedData['title'] ?? null) {
            $result['title'] = $suggestedData['title'];
        }

        if ($suggestedData['type'] ?? null) {
            $result['type'] = $suggestedData['type'];
        }

        if (($suggestedData['type'] ?? null === 'tree') && ($suggestedData['tree']['type'] ?? null)) {
            $result['tree'] = [
                'type' => $suggestedData['tree']['type'],
            ];
        }

        $result['belongsTo'] = $this->buildBelongsTo($fgbItem, $kbItems);

        if ($suggestedData['location'][0]['type'] ?? null === 'point') {
            $lat = $suggestedData['location'][0]['coordinates']['lat'] ?? null;
            $lon = $suggestedData['location'][0]['coordinates']['lon'] ?? null;

            if (is_numeric($lat) && is_numeric($lon)) {
                $result['location'] = [
                    [
                        'type' => 'point',
                        'coordinates' => [
                            'lat' => $lat,
                            'lon' => $lon,
                        ],
                        'source' => 'fgb',
                    ],
                ];
            }
        }

        if ($images = $this->buildImages($fgbItem, $suggestedData)) {
            $result['images'] = $images;
        }

        $result['meta'] = [
            'fgb' => [
                'post_id' => $fgbItem['id'],
                'author' => [
                    'name' => $fgbItem['meta']['author']['name'],
                ]
            ],
            'source' => [
                [
                    'type' => 'web',
                    'web' => [
                        'url' => $fgbItem['meta']['url'],
                        'version' => $fgbItem['meta']['updatedAt'],
                    ],
                ],
            ],
        ];

        return $result;
    }

    private function buildImages(array $fgbItem, array $suggestedData): array
    {
        $mapByUrl = $this->buildImagesMapByUrl($fgbItem);
        $suggestedMetaByUrl = $this->buildSuggestedImagesMetaMapByUrl($suggestedData);

        $images = [];
        foreach ($mapByUrl as $url => $image) {
            $kbImage = [
                'file' => $image['uid'],
                'source' => [
                    'type' => 'fgb',
                    'fgb' => [
                        'post_id' => $fgbItem['id'],
                    ],
                ],
            ];

            if (isset($suggestedMetaByUrl[$url])) {
                if (isset($suggestedMetaByUrl[$url]['author'])) {
                    $kbImage['meta']['author'] = $suggestedMetaByUrl[$url]['author'];
                }
                if (isset($suggestedMetaByUrl[$url]['version'])) {
                    $kbImage['meta']['version'] = $suggestedMetaByUrl[$url]['version'];
                }
            }

            $images[] = $kbImage;
        }

        return $images;
    }

    private function buildSuggestedImagesMetaMapByUrl(array $suggestedData): array
    {
        $map = [];

        $images = $suggestedData['images'] ?? [];
        foreach ($images as $image) {
            $map[$image['url'] ?? ''] = $image;
        }

        return $map;
    }

    private function buildImagesMapByUrl(array $fgbItem): array
    {
        $map = [];

        $images = $fgbItem['images'] ?? [];
        foreach ($images as $image) {
            $map[$image['url']] = $image;
        }

        return $map;
    }

    private function buildImagesMapByUid(array $item): array
    {
        $map = [];

        $images = $item['images'] ?? [];
        foreach ($images as $image) {
            $map[$image['file']] = $image;
        }

        return $map;
    }

    private function buildBelongsTo(array $fgbItem, array $kbItems): array
    {
        $regionMap = [];
        $subregionMap = [];

        foreach ($kbItems as $item) {
            if (str_starts_with($item['id'], 'region-')) {
                $regionMap[$item['title']] = $item['id'];
            }
            if (str_starts_with($item['id'], 'subregion-')) {
                $subregionMap[$item['title']] = $item['id'];
            }
        }

        return [
            'region' => $regionMap[$fgbItem['meta']['context']['region']],
            'subregion' => $subregionMap[$fgbItem['meta']['context']['subregion']],
        ];
    }

    private function buildModifiedItem(array $kbItem, array $fgbItem, array $kbItems): array
    {
        $suggestedKbItem = $this->buildNewItem($fgbItem, $kbItems);

        $kbItem['title'] = $suggestedKbItem['title'];

        if (!isset($kbItem['location']) && isset($suggestedKbItem['location'])) {
            $kbItem['location'] = $suggestedKbItem['location'];
        }

        $kbItem['meta']['source'][0]['web']['version'] = $fgbItem['meta']['updatedAt'];

        $oldImagesMap = $this->buildImagesMapByUid($kbItem);
        $newImagesMap = $this->buildImagesMapByUid($suggestedKbItem);

        $images = [];
        foreach ($newImagesMap as $uid => $image) {
            if (isset($oldImagesMap[$uid])) {
                $images[] = $oldImagesMap[$uid];
            } else {
                $images[] = $image;
            }
        }

        if (count($images)) {
            $kbItem['images'] = $images;
        }

        return $kbItem;
    }

    /**
     * @param array[] $v0Map
     * @param array[] $v1Map
     * @return string[]
     */
    private function getModifiedItemsIds(array $v0Map, array $v1Map): array
    {
        $modifiedIds = [];

        foreach ($v0Map as $id => $v0Item) {
            $v1Item = $v1Map[$id] ?? null;
            if (!$v1Item) {
                continue;
            }

            $isModified = $v1Item['content'] !== $v0Item['content'];

            if ($isModified) {
                $modifiedIds[] = $id;
            }
        }

        return $modifiedIds;
    }
}
