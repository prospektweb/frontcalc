<?php

namespace Prospektweb\Frontcalc\Service;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

class CalculatorAvailability
{
    private const MODULE_ID = 'prospektweb.frontcalc';

    public function isAvailableForProduct(int $productId, int $iblockId): bool
    {
        if ($productId <= 0 || $iblockId <= 0) {
            return false;
        }

        if (!Loader::includeModule('iblock')) {
            return false;
        }

        $propertyCode = trim((string)Option::get(self::MODULE_ID, 'CALC_PROPERTY_CODE', 'FRONTCALC_CONFIG'));
        if ($propertyCode === '') {
            return false;
        }

        $propertyRes = \CIBlockElement::GetProperty($iblockId, $productId, [], ['CODE' => $propertyCode]);
        $property = $propertyRes ? $propertyRes->Fetch() : false;
        if (!$property) {
            return false;
        }

        $rawSchema = $this->extractPropertyValue($property);
        if ($rawSchema === '') {
            return false;
        }

        $decoded = json_decode($rawSchema, true);
        if (!is_array($decoded) || !isset($decoded['fields']) || !is_array($decoded['fields']) || empty($decoded['fields'])) {
            return false;
        }

        foreach ($decoded['fields'] as $field) {
            if (!is_array($field)) {
                continue;
            }

            $propertyCodeValue = trim((string)($field['property_code'] ?? ''));
            if ($propertyCodeValue !== '') {
                return true;
            }
        }

        return false;
    }

    public function getLightPayload(int $productId, int $iblockId, string $ajaxUrl = ''): array
    {
        return [
            'is_available' => $this->isAvailableForProduct($productId, $iblockId),
            'product_id' => max(0, $productId),
            'ajax_url' => $ajaxUrl !== ''
                ? $ajaxUrl
                : (string)Option::get(self::MODULE_ID, 'CALC_AJAX_URL', '/local/ajax/frontcalc.php'),
        ];
    }

    private function extractPropertyValue(array $property): string
    {
        $value = $property['VALUE'] ?? '';

        if (is_array($value)) {
            return trim((string)($value['TEXT'] ?? ''));
        }

        return trim((string)$value);
    }
}
