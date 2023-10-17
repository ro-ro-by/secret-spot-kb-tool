<?php

return [
    'console.command.list' => [
        \DI\get(\RoRoBy\SecretSpotKbTool\Console\Command\KB\Repo\Unpack::class),
        \DI\get(\RoRoBy\SecretSpotKbTool\Console\Command\KB\Repo\Pack::class),
    ],
    \RoRoBy\SecretSpotKbTool\Console\CommandList::class => DI\autowire()->constructor(
        defaultList: \DI\get('console.command.list')
    ),
];
