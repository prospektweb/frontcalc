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

if (!Loader::includeModule($moduleId) || !Loader::includeModule('iblock')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Не удалось подключить модуль калькулятора.',
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

if ($productsIblockId <= 0 || $propertyCode === '') {
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

$fields = is_array($config['fields'] ?? null) ? $config['fields'] : [];
$offerPropertyCodes = [];
foreach ($fields as $field) {
    if (!is_array($field)) {
        continue;
    }

    $code = trim((string)($field['property_code'] ?? ''));
    if ($code === '') {
        continue;
    }

    $offerPropertyCodes[$code] = $code;
}

$offers = [];
if ($offersIblockId > 0 && !empty($offerPropertyCodes) && Loader::includeModule('catalog')) {
    $offersMap = CCatalogSKU::getOffersList(
        [$productId],
        $productsIblockId,
        ['ACTIVE' => 'Y'],
        ['ID', 'IBLOCK_ID', 'NAME', 'XML_ID'],
        array_values($offerPropertyCodes)
    );

    if (!empty($offersMap[$productId]) && is_array($offersMap[$productId])) {
        foreach ($offersMap[$productId] as $offer) {
            $offerData = [
                'id' => (int)($offer['ID'] ?? 0),
                'name' => (string)($offer['NAME'] ?? ''),
                'xml_id' => (string)($offer['XML_ID'] ?? ''),
                'properties' => [],
            ];

            foreach ($offerPropertyCodes as $propCode) {
                $offerData['properties'][$propCode] = isset($offer[$propCode . '_VALUE'])
                    ? $offer[$propCode . '_VALUE']
                    : null;
            }

            $offers[] = $offerData;
        }
    }
}

echo json_encode([
    'success' => true,
    'data' => [
        'product_id' => $productId,
        'config' => $config,
        'offers' => $offers,
    ],
], JSON_UNESCAPED_UNICODE);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
