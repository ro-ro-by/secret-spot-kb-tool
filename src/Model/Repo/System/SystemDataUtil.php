<?php

declare(strict_types=1);

namespace RoRoBy\SecretSpotKbTool\Model\Repo\System;

/**
 * Util to add/extract system data from items.
 */
class SystemDataUtil
{
    private const SYSTEM_DATA_KEY = '__system__';

    /**
     * Add system data to item.
     *
     * @param array $item
     * @param array $data
     * @return array
     */
    public function addData(array $item, array $data): array
    {
        return array_merge($item, [
            self::SYSTEM_DATA_KEY => $data,
        ]);
    }

    /**
     * Extract system data from item.
     *
     * @param array $item
     * @return array|null
     */
    public function extractData(array $item): array|null
    {
        return $item[self::SYSTEM_DATA_KEY] ?? null;
    }

    /**
     * Clean system data from item.
     *
     * @param array $item
     * @return array
     */
    public function cleanData(array $item): array
    {
        return array_diff_key($item, [
            self::SYSTEM_DATA_KEY => [],
        ]);
    }
}
