<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Console\Command\KB\Changeset;

use Exception;
use RoRoBy\SecretSpotKbTool\Model\Repo\Yaml\YamlFormatter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Apply changeset command.
 */
class Apply extends Command
{
    private const ARGUMENT_KB_FILE = 'kb_file';
    private const ARGUMENT_CHANGESET_FILE = 'changeset_file';
    private const ARGUMENT_OUTPUT_FILE = 'output_file';

    public function __construct(
        private readonly YamlFormatter $yamlFormatter,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('kb:changeset:apply')
            ->addArgument(self::ARGUMENT_KB_FILE, InputArgument::REQUIRED, 'KB file')
            ->addArgument(self::ARGUMENT_CHANGESET_FILE, InputArgument::REQUIRED, 'Changeset file')
            ->addArgument(self::ARGUMENT_OUTPUT_FILE, InputArgument::REQUIRED, 'Output file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $kbFile = $input->getArgument(self::ARGUMENT_KB_FILE);
        $changesetFilename = $input->getArgument(self::ARGUMENT_CHANGESET_FILE);
        $outputFilename = $input->getArgument(self::ARGUMENT_OUTPUT_FILE);

        $kb = Yaml::parseFile($kbFile);
        $changeset = Yaml::parseFile($changesetFilename);

        $output->writeln('Applying...');

        try {
            $updatedKb = $this->apply($kb, $changeset);

            $formattedKb = $this->yamlFormatter->format($updatedKb);

            $fs = new Filesystem();
            $fs->dumpFile($outputFilename, $formattedKb);

            $output->writeln('Applied successfully.');

            return Command::SUCCESS;
        } catch (Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }

    /**
     * Apply changeset on kb.
     *
     * @param array $kb
     * @param array $changeset
     * @return array
     * @throws Exception
     */
    private function apply(array $kb, array $changeset): array
    {
        $kbIdPosMap = [];
        foreach ($kb['items'] as $i => $item) {
            $kbIdPosMap[$item['id']] = $i;
        }

        foreach ($changeset['changes'] as $id => $change) {
            $a = $change['a'] ? Yaml::parse($change['a']) : null;
            $b = $change['b'] ? Yaml::parse($change['b']) : null;

            // new item
            if ($a === null) {
                $kb['items'][] = $b;
                continue;
            }

            $kbPos = $kbIdPosMap[$id];
            $kbItem = $kb['items'][$kbPos];

            if (!$this->isItemsEqual($kbItem, $a)) {
                throw new Exception('Invalid source item content.');
            }

            // removed item
            if ($b === null) {
                unset($kb['items'][$kbPos]);
                continue;
            }

            // modified
            $kb['items'][$kbPos] = $b;
        }

        return $kb;
    }

    private function isItemsEqual(?array $a, ?array $b): bool
    {
        return var_export($a, true) === var_export($b, true);
    }
}