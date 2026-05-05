<?php

namespace Prospektweb\Frontcalc\Service;

use Bitrix\Main\Config\Option;

class ModuleConfig
{
    private const MODULE_ID = 'prospektweb.frontcalc';

    public function getHiddenOfferValueIds(): array
    {
        $raw = (string)Option::get(self::MODULE_ID, 'HIDDEN_OFFER_VALUE_IDS', '');
        $parts = preg_split('/[,\s;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) {
            return [];
        }

        $result = [];
        foreach ($parts as $part) {
            $id = (int)$part;
            if ($id > 0) {
                $result[$id] = $id;
            }
        }

        return array_values($result);
    }
}
