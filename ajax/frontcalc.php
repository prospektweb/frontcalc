<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

const STOP_STATISTICS = true;
const NO_KEEP_STATISTIC = true;
const NO_AGENT_STATISTIC = true;
const NO_AGENT_CHECK = true;
const PUBLIC_AJAX_MODE = true;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=UTF-8');

$moduleId = 'prospektweb.frontcalc';

if (!Loader::includeModule($moduleId) || !Loader::includeModule('iblock') || !Loader::includeModule('catalog')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Не удалось подключить необходимые модули (prospektweb.frontcalc, iblock, catalog).',
    ], JSON_UNESCAPED_UNICODE);
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
    return;
}

$productId = (int)($_REQUEST['product_id'] ?? 0);
if ($productId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Не указан product_id.',
    ], JSON_UNESCAPED_UNICODE);
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
    return;
}

$productsIblockId = (int)Option::get($moduleId, 'PRODUCTS_IBLOCK_ID', '0');
$offersIblockId = (int)Option::get($moduleId, 'OFFERS_IBLOCK_ID', '0');
$propertyCode = trim((string)Option::get($moduleId, 'CALC_PROPERTY_CODE', 'FRONTCALC_CONFIG'));

if ($productsIblockId <= 0 || $offersIblockId <= 0 || $propertyCode === '') {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Не настроены параметры инфоблока/свойства калькулятора.',
    ], JSON_UNESCAPED_UNICODE);
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
    return;
}

$schemaText = '';
$propertyRes = CIBlockElement::GetProperty($productsIblockId, $productId, [], ['CODE' => $propertyCode]);
if ($propertyRes && ($property = $propertyRes->Fetch())) {
    $value = $property['VALUE'] ?? '';
    $schemaText = is_array($value) ? trim((string)($value['TEXT'] ?? '')) : trim((string)$value);
}

$config = [];
if ($schemaText !== '') {
    $decoded = json_decode($schemaText, true);
    if (is_array($decoded)) {
        $config = $decoded;
    }
}

$calcPropertyCodes = [];
$propertyMeta = [];
$propertyListRes = CIBlockProperty::GetList(['SORT' => 'ASC'], ['IBLOCK_ID' => $offersIblockId, 'ACTIVE' => 'Y']);
while ($prop = $propertyListRes->Fetch()) {
    $code = trim((string)($prop['CODE'] ?? ''));
    if ($code === '' || strpos($code, 'CALC_PROP_') !== 0) {
        continue;
    }

    $calcPropertyCodes[$code] = $code;
    $propertyMeta[$code] = [
        'code' => $code,
        'name' => (string)($prop['NAME'] ?? $code),
        'sort' => (int)($prop['SORT'] ?? 500),
    ];
}

$offers = [];
$presetBuckets = [];
$hasXmlIdErrors = false;
$xmlIdErrors = [];

$offersMap = CCatalogSKU::getOffersList(
    [$productId],
    $productsIblockId,
    ['ACTIVE' => 'Y'],
    ['ID', 'IBLOCK_ID', 'NAME', 'XML_ID'],
    array_values($calcPropertyCodes)
);

if (!empty($offersMap[$productId]) && is_array($offersMap[$productId])) {
    foreach ($offersMap[$productId] as $offerRow) {
        $offerId = (int)($offerRow['ID'] ?? 0);
        if ($offerId <= 0) {
            continue;
        }

        $offerProps = [];
        $offerPropRes = CIBlockElement::GetProperty($offersIblockId, $offerId, ['SORT' => 'ASC'], []);
        while ($offerProp = $offerPropRes->Fetch()) {
            $code = trim((string)($offerProp['CODE'] ?? ''));
            if ($code === '' || strpos($code, 'CALC_PROP_') !== 0) {
                continue;
            }

            $value = trim((string)($offerProp['VALUE'] ?? ''));
            $xmlId = trim((string)($offerProp['VALUE_XML_ID'] ?? ''));
            if ($value === '' && $xmlId === '') {
                continue;
            }

            if ($xmlId === '') {
                $hasXmlIdErrors = true;
                $xmlIdErrors[] = [
                    'offer_id' => $offerId,
                    'property_code' => $code,
                    'message' => 'У свойства отсутствует VALUE_XML_ID',
                ];
                continue;
            }

            $sort = (int)($offerProp['VALUE_SORT'] ?? $offerProp['SORT'] ?? 500);

            $offerProps[$code] = [
                'value' => $value,
                'xml_id' => $xmlId,
                'sort' => $sort,
            ];

            if (!isset($presetBuckets[$code])) {
                $presetBuckets[$code] = [];
            }
            $presetBuckets[$code][$xmlId] = [
                'value' => $value,
                'xml_id' => $xmlId,
                'sort' => $sort,
            ];
        }

        $prices = [];
        $priceRes = CPrice::GetListEx(
            ['CATALOG_GROUP_ID' => 'ASC', 'QUANTITY_FROM' => 'ASC'],
            ['PRODUCT_ID' => $offerId],
            false,
            false,
            ['ID', 'CATALOG_GROUP_ID', 'PRICE', 'CURRENCY', 'QUANTITY_FROM', 'QUANTITY_TO']
        );
        while ($price = $priceRes->Fetch()) {
            $prices[] = [
                'id' => (int)($price['ID'] ?? 0),
                'catalog_group_id' => (int)($price['CATALOG_GROUP_ID'] ?? 0),
                'price' => (float)($price['PRICE'] ?? 0),
                'currency' => (string)($price['CURRENCY'] ?? ''),
                'quantity_from' => isset($price['QUANTITY_FROM']) ? (int)$price['QUANTITY_FROM'] : null,
                'quantity_to' => isset($price['QUANTITY_TO']) ? (int)$price['QUANTITY_TO'] : null,
            ];
        }

        $catalogProduct = CCatalogProduct::GetByID($offerId);
        $weightGrams = (float)($catalogProduct['WEIGHT'] ?? 0);
        $widthMm = (float)($catalogProduct['WIDTH'] ?? 0);
        $lengthMm = (float)($catalogProduct['LENGTH'] ?? 0);
        $heightMm = (float)($catalogProduct['HEIGHT'] ?? 0);

        $weightKg = $weightGrams > 0 ? round($weightGrams / 1000, 3) : 0.0;
        $volumeM3 = ($widthMm > 0 && $lengthMm > 0 && $heightMm > 0)
            ? round(($widthMm * $lengthMm * $heightMm) / 1000000000, 3)
            : 0.0;

        $offers[] = [
            'id' => $offerId,
            'name' => (string)($offerRow['NAME'] ?? ''),
            'xml_id' => (string)($offerRow['XML_ID'] ?? ''),
            'properties' => $offerProps,
            'catalog' => [
                'prices' => $prices,
                'weight_kg' => $weightKg,
                'dimensions_mm' => [
                    'width' => $widthMm,
                    'length' => $lengthMm,
                    'height' => $heightMm,
                ],
                'volume_m3' => $volumeM3,
            ],
        ];
    }
}

if ($hasXmlIdErrors) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Технические неполадки: у одного или нескольких CALC_PROP_* отсутствует VALUE_XML_ID.',
        'errors' => $xmlIdErrors,
    ], JSON_UNESCAPED_UNICODE);
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
    return;
}

foreach ($presetBuckets as $code => $rows) {
    $presets = array_values($rows);
    usort($presets, static function ($a, $b) {
        return ($a['sort'] ?? 500) <=> ($b['sort'] ?? 500);
    });
    $propertyMeta[$code]['presets'] = $presets;
}

echo json_encode([
    'success' => true,
    'data' => [
        'product_id' => $productId,
        'config' => $config,
        'property_meta' => array_values($propertyMeta),
        'offers' => $offers,
    ],
], JSON_UNESCAPED_UNICODE);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
