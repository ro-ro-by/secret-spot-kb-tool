<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Console\Command\KB;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * KB Stat command.
 */
class Stat extends Command
{
    private const ARGUMENT_KB_FILE = 'kb_file';
    private const ARGUMENT_FGB_FILE = 'fgb_file';

    protected function configure(): void
    {
        $this->setName('kb:stat')
            ->addArgument(self::ARGUMENT_KB_FILE, InputArgument::REQUIRED, 'KB file')
            ->addArgument(self::ARGUMENT_FGB_FILE, InputArgument::REQUIRED, 'FGB file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $kbFile = $input->getArgument(self::ARGUMENT_KB_FILE);
        $fgbFilename = $input->getArgument(self::ARGUMENT_FGB_FILE);;

        $output->writeln('Reading sources...');
        ['items' => $kbItems] = Yaml::parseFile($kbFile);
        ['items' => $fgbItems] = Yaml::parseFile($fgbFilename);

        $output->writeln('Collecting stat...');
        $this->collectStat($kbItems, $fgbItems, $output);

        return Command::SUCCESS;
    }

    private function collectStat(array $kbItems, array $fgbItems, OutputInterface $output): void
    {
        $fgbIdMap = [];
        foreach ($fgbItems as $item) {
            $fgbIdMap[$item['id']] = $item;
        }

        $sightItems = array_filter(
            $kbItems,
            function ($item) {
                return str_starts_with($item['id'], 'sight-');
            }
        );

        $byType = [];

        foreach ($sightItems as $kbItem) {
            $fgbItem = $fgbIdMap[$kbItem['meta']['fgb']['post_id']];

            $byType[$kbItem['type']][] = $kbItem;
        }

        foreach ($byType as $type => $items) {
            $withLocation = $this->getStatLocation($items);
            $withLocationPercent = $withLocation / count($items);

            $output->writeln(sprintf(
                '%s: %d (%d, %d%%)',
                $type,
                count($items),
                $this->getStatLocation($items),
                $withLocationPercent * 100
            ));
        }
    }

    private function getStatLocation(array $items): int
    {
        $hasLocation = 0;
        foreach ($items as $item) {
            $hasLocation += (int)isset($item['location']);
        }

        return $hasLocation;
    }
}