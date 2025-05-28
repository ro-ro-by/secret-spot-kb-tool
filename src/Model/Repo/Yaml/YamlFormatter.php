<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Repo\Yaml;

use Symfony\Component\Yaml\Yaml;

/**
 * Yaml formatter.
 */
class YamlFormatter
{
    public function format(array|null $data): string|null
    {
        if (!is_array($data)) {
            return null;
        }

        return Yaml::dump($data, 6, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
    }
}
