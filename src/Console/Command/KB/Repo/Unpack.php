<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Console\Command\KB\Repo;

use RoRoBy\SecretSpotKbTool\Model\Repo\YamlUnpack;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Export KB to repository format command.
 */
class Unpack extends Command
{
    private const ARGUMENT_KB_FILE = 'kb_file';
    private const ARGUMENT_OUTPUT_DIR = 'output_dir';

    public function __construct(
        private readonly YamlUnpack $yamlUnpack,
        string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('kb:repo:unpack')
            ->addArgument(self::ARGUMENT_KB_FILE, InputArgument::REQUIRED, 'KB file')
            ->addArgument(self::ARGUMENT_OUTPUT_DIR, InputArgument::REQUIRED, 'Output dir');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $kbFile = $input->getArgument(self::ARGUMENT_KB_FILE);
        $outputDir = $input->getArgument(self::ARGUMENT_OUTPUT_DIR);

        $kbFileContent = file_get_contents($kbFile);

        $this->yamlUnpack->execute($kbFileContent, $outputDir);

        return Command::SUCCESS;
    }
}
