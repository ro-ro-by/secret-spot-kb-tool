<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Changeset\Data;

class ChangeSet
{
    /**
     * @param Change[] $changes
     */
    public function __construct(
        public readonly array $changes
    ) {
    }
}