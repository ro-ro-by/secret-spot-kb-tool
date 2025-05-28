<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Sight\ExtractSemanticData;

use RoRoBy\SecretSpotKbTool\Config\OpenAIConfig;
use RoRoBy\SecretSpotKbTool\Model\Sight\ExtractSemanticDataInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Service to extract semantic data from post data via OpenAI.
 */
class OpenAI implements ExtractSemanticDataInterface
{
    public function __construct(
        private readonly OpenAIConfig $openAIConfig
    ) {
    }

    /**
     * @inheritdoc
     */
    public function extract(array $postData): array
    {
        $content = $postData['content'] ?? '';

        if ($result = $this->getFromCache($content)) {
            return $result;
        }

        $client = $this->buildClient();
        $systemPrompt = $this->openAIConfig->getPromptSight();

        $response = $client->responses()
            ->create([
                'model' => 'gpt-4.1',
                'input' => [
                    [
                        'role' => 'system',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => $systemPrompt,
                        ]]
                    ],
                    [
                        'role' => 'user',
                        'content' => [[
                            'type' => 'input_text',
                            'text' => $content,
                        ]]
                    ]
                ]
            ]);

        $yaml = $response->output[0]->content[0]->text;

        $this->cache($content, $yaml);

        return Yaml::parse($yaml);
    }

    private function getFromCache(string $content): array|null
    {
        $file = sys_get_temp_dir() . '/' . sprintf('openai-%s.yaml', md5($content));

        if (!is_file($file)) {
            return null;
        }

        try {
            return Yaml::parse(file_get_contents($file));
        } catch (\Throwable) {
            return null;
        }
    }

    private function cache(string $content, string $response): void
    {
        $file = sys_get_temp_dir() . '/' . sprintf('openai-%s.yaml', md5($content));

        file_put_contents($file, $response);
    }

    private function buildClient()
    {
        $key = $this->openAIConfig->getApiKey();

        return \OpenAI::client($key);
    }
}