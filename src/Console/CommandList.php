<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Console;

use Symfony\Component\Console\Command\Command;

class CommandList
{
    public function __construct(private readonly array $defaultList = [])
    {
    }

    /**
     * @return Command[]
     */
    public function getList(): array
    {
        return $this->defaultList;
    }
}
