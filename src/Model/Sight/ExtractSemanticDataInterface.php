<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Sight;

/**
 * Service to extract semantic data from post data.
 */
interface ExtractSemanticDataInterface
{
    /**
     * Extract.
     *
     * @param array $postData
     * @return mixed
     */
    public function extract(array $postData): array;
}