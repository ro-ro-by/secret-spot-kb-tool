<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Console\Command\KB\Changeset;

use RoRoBy\SecretSpotKbTool\Model\Changeset\Data\Change;
use RoRoBy\SecretSpotKbTool\Model\Changeset\Data\ChangeSet;
use RoRoBy\SecretSpotKbTool\Model\Changeset\Format\YamlExport;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Create changeset command.
 */
class Create extends Command
{
    private const ARGUMENT_KB_FILE = 'kb_file';
    private const ARGUMENT_FGB_FILE = 'fgb_file';
    private const ARGUMENT_OUTPUT_FILE = 'output_file';

    public function __construct(
        private readonly YamlExport $yamlExport,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('kb:changeset:create')
            ->addArgument(self::ARGUMENT_KB_FILE, InputArgument::REQUIRED, 'KB file')
            ->addArgument(self::ARGUMENT_FGB_FILE, InputArgument::REQUIRED, 'FGB file')
            ->addArgument(self::ARGUMENT_OUTPUT_FILE, InputArgument::REQUIRED, 'Output file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $kbFile = $input->getArgument(self::ARGUMENT_KB_FILE);
        $fgbFilename = $input->getArgument(self::ARGUMENT_FGB_FILE);
        $outputFilename = $input->getArgument(self::ARGUMENT_OUTPUT_FILE);

        $output->writeln('Reading sources...');
        ['items' => $kbItems] = Yaml::parseFile($kbFile);
        ['items' => $fgbItems] = Yaml::parseFile($fgbFilename);

        $output->writeln('Creating changeset...');
        $changeset = $this->buildChangeset($kbItems, $fgbItems, $output);

        $output->writeln('Exporting changeset...');
        $fs = new Filesystem();
        $fs->dumpFile($outputFilename, $this->yamlExport->export($changeset));

        $output->writeln('Finished.');

        return Command::SUCCESS;
    }

    private function buildChangeset(array $kbItems, array $fgbItems, OutputInterface $output): ChangeSet
    {
        $fgbIdMap = [];
        foreach ($fgbItems as $item) {
            $fgbIdMap[$item['id']] = $item;
        }
        $kbIdMap = [];
        foreach ($kbItems as $item) {
            $kbIdMap[$item['id']] = $item;
        }

        $changes = [];

        $sightItems = array_filter(
            $kbItems,
            function ($item) {
                return str_starts_with($item['id'], 'sight-');
            }
        );


        foreach ($sightItems as $kbItem) {
            $postIds = $kbItem['meta']['fgb']['post_id'];
            $postId = is_array($postIds) ? current($postIds) : $postIds;

            $fgbItem = $fgbIdMap[$postId];

            $targetItem = $kbItem;

            if (str_contains($fgbItem['content'], '°') && !isset($targetItem['location'])) {
                $targetItem['location'] = [
                    [
                        'type' => 'point',
                        'coordinates' => [
                            'lat' => 11111,
                            'lon' => 22222,
                        ],
                        'source' => 'fgb',
                    ],
                ];
            }

            if (var_export($targetItem, true) !== var_export($kbItem, true)) {
                $changes[] = new Change($kbItem['id'], $kbItem, $targetItem);
            }
        }

//
////
//        foreach ($sightItems as $i => $kbItemA) {
//            foreach ($sightItems as $kbItemB) {
//                $fgbItemA = $fgbIdMap[$kbItemA['meta']['fgb']['post_id']];
//                $fgbItemB = $fgbIdMap[$kbItemB['meta']['fgb']['post_id']];
//
//                if ($kbItemA['id'] === $kbItemB['id'] || $kbItemA['title'] !== $kbItemB['title']) {
//                    continue;
//                }
//
//                $similarity = 0;
//                $matchLen = similar_text($fgbItemA['content'], $fgbItemB['content'], $similarity);
//
//                if ($similarity < 90 || $matchLen < 500) {
//                    continue;
//                }
//
//                $duplicates[$kbItemA['title']] = $duplicates[$kbItemA['title']] ?? [];
//                $duplicates[$kbItemA['title']][] = $kbItemA['id'];
//                $duplicates[$kbItemA['title']][] = $kbItemB['id'];
//
//                $output->writeln('POS ' . $i);
//                $output->writeln(sprintf(
//                    '%s, %s === %s, similarity = %d, match = %d',
//                    $kbItemA['title'],
//                    $kbItemA['id'],
//                    $kbItemB['id'],
//                    $similarity,
//                    $matchLen
//                ));
//            }
//        }
//
//        foreach ($duplicates as $title => &$ids) {
//            $ids = array_unique($ids);
//        }
//
//        $data = [];
//        foreach ($duplicates as $title => $duplicate) {
//            $data[] = [
//                'title' => $title,
//                'items' => array_map(
//                    function ($id) use ($kbIdMap) {
//                        $kbItem = $kbIdMap[$id];
//
//                        return [
//                            'id' => $kbItem['id'],
//                            'title' => $kbItem['title'],
//                            'url' => $kbItem['meta']['source'][0]['web']['url'],
//                        ];
//                    },
//                    $duplicate
//                )
//            ];
//        }
//
//        $str = Yaml::dump($data, 6, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
//        file_put_contents('duplicates.yaml', $str);


//
//
//
//            $targetItem = $kbItem;
//
//            $name = $kbItem['title'];
//
//            $byName[$name] = $byName[$name] ?? [];
//            $byName[$name][] = $kbItem;

//            if (empty($targetItem['location'])) {
//                continue;
//            }
//
//            if (str_contains($fgbItem['content'], '°')) {
//                $targetItem['location'] = 'LOCATION';
//            }
//
//            $targetItem['location'] = [
//                [
//                    ...$targetItem['location'],
//                    'source' => 'fgb'
//                ]
//            ];

//            if (strlen($fgbItem['content']) < 2000) {
//                continue;
//            }
//
//            $targetItem['osm'] = [
//                [
//                    'type' => 'relation',
//                    'id' => 'RELATION',
//                ],
//            ];
//
//            var_dump(strlen($fgbItem['content']));

//            if (var_export($targetItem, true) !== var_export($kbItem, true)) {
//                $changes[] = new Change($kbItem['id'], $kbItem, $targetItem);
//            }
//        }
//
//        uasort(
//            $byName,
//            function ($a, $b): int {
//                return count($a) <=> count($b);
//            }
//        );
//
//        $byName = array_filter(
//            $byName,
//            function ($a) use ($fgbIdMap)  {
//                foreach ($a as $i){
//                    if (!str_contains($fgbIdMap[$i['meta']['fgb']['post_id']]['content'], 'га</span>')) {
//                        return false;
//                    }
//                }
//
//                return count($a) > 1;
//            }
//        );
//
//
//        foreach ($byName as $key => $items) {
//            var_dump($key);
//            var_dump(count($items));
//        }

        return new ChangeSet($changes);
    }
}