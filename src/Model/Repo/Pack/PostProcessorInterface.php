<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Repo\Pack;

/**
 * Interface of post-processor, which modify items data after loading from repo.
 */
interface PostProcessorInterface
{
    public function process(array $items, string $dir): array;
}
