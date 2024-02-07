<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Console\Command\KB\Repo;

use RoRoBy\SecretSpotKbTool\Model\Repo\YamlPack;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Import KB to repository format command.
 */
class Pack extends Command
{
    private const ARGUMENT_KB_FILE = 'kb_file';
    private const ARGUMENT_INPUT_DIR = 'input_dir';
    private const OPTION_MINIMAL = 'minimal';

    public function __construct(
        private readonly YamlPack $yamlPack,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('kb:repo:pack')
            ->addOption(self::OPTION_MINIMAL, 'm', InputOption::VALUE_NONE, 'Minimal mode (without embedded content lie location)')
            ->addArgument(self::ARGUMENT_INPUT_DIR, InputArgument::REQUIRED, 'Input dir')
            ->addArgument(self::ARGUMENT_KB_FILE, InputArgument::REQUIRED, 'KB file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $kbFile = $input->getArgument(self::ARGUMENT_KB_FILE);
        $inputDir = $input->getArgument(self::ARGUMENT_INPUT_DIR);
        $isMinimal = (bool)$input->getOption(self::OPTION_MINIMAL);

        $yaml = $this->yamlPack->execute($inputDir, !$isMinimal);

        $fs = new Filesystem();
        $fs->dumpFile($kbFile, $yaml);

        return Command::SUCCESS;
    }
}
