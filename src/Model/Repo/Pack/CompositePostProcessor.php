<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Repo\Pack;

/**
 * Composite post-processor.
 */
class CompositePostProcessor implements PostProcessorInterface
{
    /**
     * @param PostProcessorInterface[] $processors
     */
    public function __construct(private readonly array $processors = [])
    {
    }

    public function process(array $items, string $dir): array
    {
        foreach ($this->processors as $processor) {
            $items = $processor->process($items, $dir);
        }

        return $items;
    }
}
