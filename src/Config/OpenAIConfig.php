<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * OpenAI config.
 */
class OpenAIConfig
{
    public function getApiKey(): string
    {
        return $this->getConfigData()['openai']['api_key'];
    }

    public function getPromptSight(): string
    {
        return $this->getConfigData()['openai']['prompt']['sight'];
    }

    private function getConfigData(): array
    {
        return Yaml::parseFile(BP . '/config/openai.yaml');
    }
}
