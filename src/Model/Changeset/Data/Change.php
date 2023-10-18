<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Changeset\Data;

class Change
{
    public function __construct(
        public readonly string $id,
        public readonly array|null $aData,
        public readonly array|null $bData
    ) {
    }
}