<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Console\Command\KB\Stat;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * KB Stat of Diff command.
 */
class Diff extends Command
{
    private const ARGUMENT_KB_V0_FILE = 'kb_v0_file';
    private const ARGUMENT_KB_V1_FILE = 'kb_v1_file';

    protected function configure(): void
    {
        $this->setName('kb:stat:diff')
            ->addArgument(self::ARGUMENT_KB_V0_FILE, InputArgument::REQUIRED, 'KB V0 file')
            ->addArgument(self::ARGUMENT_KB_V1_FILE, InputArgument::REQUIRED, 'KB V1 file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $kbV0File = $input->getArgument(self::ARGUMENT_KB_V0_FILE);
        $kbV1File = $input->getArgument(self::ARGUMENT_KB_V1_FILE);

        $output->writeln('Reading sources...');
        ['items' => $kbV0Items] = Yaml::parseFile($kbV0File);
        ['items' => $kbV1Items] = Yaml::parseFile($kbV1File);

        $output->writeln('Collecting stat...');
        $this->collectStat($kbV0Items, $kbV1Items, $output);

        return Command::SUCCESS;
    }

    private function collectStat(array $kbV0Items, array $kbV1Items, OutputInterface $output): void
    {
        $sightV0Map = $this->buildSightsMap($kbV0Items);
        $sightV1Map = $this->buildSightsMap($kbV1Items);

        $ids = array_merge(
            array_keys($sightV0Map),
            array_keys($sightV1Map)
        );

        $added = 0;
        $removed = 0;
        $modified = 0;
        $addedPhoto = 0;
        $addedLocation = 0;

        foreach ($ids as $id) {
            $v0 = $sightV0Map[$id] ?? null;
            $v1 = $sightV1Map[$id] ?? null;

            if ($v0 === null) {
                $added++;
                continue;
            }

            if ($v1 === null) {
                $removed++;
                continue;
            }

            if ($v0['meta']['source'][0]['web']['version'] !== $v1['meta']['source'][0]['web']['version']) {
                $modified++;
            }

            $v0Images = $v0['images'] ?? [];
            $v1Images = $v1['images'] ?? [];
            if (count($v0Images) < count($v1Images)) {
                $addedPhoto++;
            }

            $v0Location = $v0['location'] ?? [];
            $v1Location = $v1['location'] ?? [];
            if (count($v0Location) < count($v1Location)) {
                $addedLocation++;
            }
        }

        $output->writeln(sprintf('Added: %d items', $added));
        $output->writeln(sprintf('Removed: %d items', $removed));
        $output->writeln(sprintf('Modified: %d items', $modified));
        $output->writeln(sprintf('Added photos: %d items', $addedPhoto));
        $output->writeln(sprintf('Added location: %d items', $addedLocation));
    }

    private function buildSightsMap(array $kbItems): array
    {
        $sightItems = array_filter(
            $kbItems,
            function ($item) {
                return str_starts_with($item['id'], 'sight-');
            }
        );

        $map = [];
        foreach ($sightItems as $item) {
            $map[$item['id']] = $item;
        }

        return $map;
    }
}
