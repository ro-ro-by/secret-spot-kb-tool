<?php

return [
    'console.command.list' => [
        \DI\get(\RoRoBy\SecretSpotKbTool\Console\Command\KB\Repo\Unpack::class),
        \DI\get(\RoRoBy\SecretSpotKbTool\Console\Command\KB\Repo\Pack::class),
        \DI\get(\RoRoBy\SecretSpotKbTool\Console\Command\KB\Changeset\Apply::class),
        \DI\get(\RoRoBy\SecretSpotKbTool\Console\Command\KB\Changeset\Create::class),
        \DI\get(\RoRoBy\SecretSpotKbTool\Console\Command\KB\Changeset\Create\OsmLocationDump::class),
        \DI\get(\RoRoBy\SecretSpotKbTool\Console\Command\Renderer\CreateDB::class),
    ],
    \RoRoBy\SecretSpotKbTool\Console\CommandList::class => DI\autowire()->constructor(
        defaultList: \DI\get('console.command.list')
    ),
    'repo.pack.postprocessor.composite' => \DI\create(\RoRoBy\SecretSpotKbTool\Model\Repo\Pack\CompositePostProcessor::class)
        ->constructor(
            processors: [
                \DI\get(\RoRoBy\SecretSpotKbTool\Model\Repo\Pack\EmbedLocationPostProcessor::class),
            ],
        ),
    \RoRoBy\SecretSpotKbTool\Model\Repo\YamlPack::class => DI\autowire()->constructor(
        postProcessor: \DI\get('repo.pack.postprocessor.composite')
    ),
];
