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

/**
 * @return int[]
 */
function frontcalc_get_current_user_groups(): array
{
    global $USER;
    if (is_object($USER) && method_exists($USER, 'GetUserGroupArray')) {
        $groups = $USER->GetUserGroupArray();
        if (is_array($groups)) {
            return array_values(array_unique(array_map('intval', $groups)));
        }
    }

    return [2];
}

/**
 * @param int[] $userGroups
 * @return array{view:int[],buy:int[]}
 */
function frontcalc_get_catalog_groups_by_rights(array $userGroups): array
{
    $view = [];
    $buy = [];

    if (class_exists('CCatalogGroup2Group')) {
        $groupRes = CCatalogGroup::GetList(['SORT' => 'ASC'], []);
        while ($group = $groupRes->Fetch()) {
            $catalogGroupId = (int)($group['ID'] ?? 0);
            if ($catalogGroupId <= 0) {
                continue;
            }

            $accessRes = CCatalogGroup2Group::GetList(['GROUP_ID' => 'ASC'], ['CATALOG_GROUP_ID' => $catalogGroupId]);
            while ($access = $accessRes->Fetch()) {
                $groupId = (int)($access['GROUP_ID'] ?? 0);
                if ($groupId <= 0 || !in_array($groupId, $userGroups, true)) {
                    continue;
                }
                if (($access['BUY'] ?? 'N') === 'Y') {
                    $buy[$catalogGroupId] = $catalogGroupId;
                }
                if (($access['LIST'] ?? 'N') === 'Y' || ($access['VIEW'] ?? 'N') === 'Y') {
                    $view[$catalogGroupId] = $catalogGroupId;
                }
            }
        }
    } else {
        global $DB;
        if (is_object($DB) && !empty($userGroups)) {
            $groupIdsSql = implode(',', array_map('intval', $userGroups));
            $sql = "SELECT CATALOG_GROUP_ID, BUY FROM b_catalog_group2group WHERE GROUP_ID IN (" . $groupIdsSql . ")";
            $res = $DB->Query($sql);
            while ($row = $res->Fetch()) {
                $catalogGroupId = (int)($row['CATALOG_GROUP_ID'] ?? 0);
                if ($catalogGroupId <= 0) {
                    continue;
                }
                $view[$catalogGroupId] = $catalogGroupId;
                if (($row['BUY'] ?? 'N') === 'Y') {
                    $buy[$catalogGroupId] = $catalogGroupId;
                }
            }
        }
    }

    return [
        'view' => array_values($view),
        'buy' => array_values($buy),
    ];
}


/**
 * Price range payload contract used by ajax/frontcalc.php and assets/js/frontcalc-jqm-popup.js.
 *
 * Each range is intentionally limited to these fields:
 * - catalog_group_id: Bitrix catalog price type ID;
 * - catalog_group_name: localized catalog price type name;
 * - price: rounded numeric price value;
 * - currency: Bitrix currency code;
 * - formatted: formatted price string;
 * - quantity_from: inclusive lower quantity bound, or null for an open lower bound;
 * - quantity_to: inclusive upper quantity bound, or null for an open upper bound.
 *
 * @phpstan-type FrontcalcPriceRange array{catalog_group_id:int,catalog_group_name:string,price:float,currency:string,formatted:string,quantity_from:int|null,quantity_to:int|null}
 */

/**
 * @param mixed $value
 */
function frontcalc_normalize_quantity_bound($value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    return (int)$value;
}

/**
 * @return FrontcalcPriceRange
 */
function frontcalc_make_price_range(
    int $catalogGroupId,
    string $catalogGroupName,
    float $price,
    string $currency,
    string $formatted,
    ?int $quantityFrom,
    ?int $quantityTo
): array
{
    return [
        'catalog_group_id' => $catalogGroupId,
        'catalog_group_name' => $catalogGroupName,
        'price' => $price,
        'currency' => $currency,
        'formatted' => $formatted,
        'quantity_from' => $quantityFrom,
        'quantity_to' => $quantityTo,
    ];
}

/**
 * @param array<int,FrontcalcPriceRange> $rows
 * @return array<string,array{catalog_group_id:int,catalog_group_name:string,prices:array<int,FrontcalcPriceRange>}>
 */
function frontcalc_group_price_ranges_by_catalog_group(array $rows): array
{
    $grouped = [];

    foreach ($rows as $row) {
        $groupId = (int)($row['catalog_group_id'] ?? 0);
        if ($groupId <= 0) {
            continue;
        }

        $groupKey = (string)$groupId;
        if (!isset($grouped[$groupKey])) {
            $grouped[$groupKey] = [
                'catalog_group_id' => $groupId,
                'catalog_group_name' => (string)($row['catalog_group_name'] ?? ('PRICE_' . $groupId)),
                'prices' => [],
            ];
        }

        $grouped[$groupKey]['prices'][] = $row;
    }

    return $grouped;
}

function frontcalc_pick_price_for_quantity(array $rows, int $quantity = 1): ?array
{
    if (empty($rows)) {
        return null;
    }

    foreach ($rows as $row) {
        $from = $row['quantity_from'];
        $to = $row['quantity_to'];
        $fromOk = ($from === null) || ((int)$from <= $quantity);
        $toOk = ($to === null) || ((int)$to >= $quantity);
        if ($fromOk && $toOk) {
            return $row;
        }
    }

    return $rows[0];
}

function frontcalc_round_catalog_price(float $value, int $catalogGroupId, string $currency): float
{
    if (class_exists('\Bitrix\Catalog\Product\Price')) {
        return (float)\Bitrix\Catalog\Product\Price::roundPrice($catalogGroupId, $value, $currency);
    }
    return $value;
}

/**
 * @return array<int,string>
 */
function frontcalc_get_catalog_group_names(): array
{
    $names = [];
    $groupRes = CCatalogGroup::GetList(['SORT' => 'ASC'], []);
    while ($group = $groupRes->Fetch()) {
        $id = (int)($group['ID'] ?? 0);
        if ($id > 0) {
            $names[$id] = (string)($group['NAME_LANG'] ?? $group['NAME'] ?? ('PRICE_' . $id));
        }
    }

    return $names;
}

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
$requestedOfferId = (int)($_REQUEST['offer_id'] ?? $_REQUEST['oid'] ?? 0);
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
$propertyEnumNames = [];
$propertyListRes = CIBlockProperty::GetList(['SORT' => 'ASC'], ['IBLOCK_ID' => $offersIblockId, 'ACTIVE' => 'Y']);
while ($prop = $propertyListRes->Fetch()) {
    $code = trim((string)($prop['CODE'] ?? ''));
    if ($code === '' || strpos($code, 'CALC_PROP_') !== 0) {
        continue;
    }

    $calcPropertyCodes[$code] = $code;
    $propertyId = (int)($prop['ID'] ?? 0);
    $propertyMeta[$code] = [
        'code' => $code,
        'name' => (string)($prop['NAME'] ?? $code),
        'sort' => (int)($prop['SORT'] ?? 500),
    ];

    if ($propertyId > 0) {
        $enumNames = [];
        $enumRes = CIBlockPropertyEnum::GetList(['SORT' => 'ASC'], ['PROPERTY_ID' => $propertyId]);
        while ($enum = $enumRes->Fetch()) {
            $enumXmlId = trim((string)($enum['XML_ID'] ?? ''));
            if ($enumXmlId === '') {
                continue;
            }
            $enumNames[$enumXmlId] = (string)($enum['VALUE'] ?? '');
        }
        if (!empty($enumNames)) {
            $propertyEnumNames[$code] = $enumNames;
        }
    }
}

$offers = [];
$presetBuckets = [];
$hasXmlIdErrors = false;
$xmlIdErrors = [];
$userGroups = frontcalc_get_current_user_groups();
$priceAccess = frontcalc_get_catalog_groups_by_rights($userGroups);
$catalogGroupNames = frontcalc_get_catalog_group_names();
$priceGroupsView = [];
foreach ($priceAccess['view'] as $catalogGroupId) {
    $catalogGroupId = (int)$catalogGroupId;
    if ($catalogGroupId <= 0) {
        continue;
    }
    $priceGroupsView[] = [
        'id' => $catalogGroupId,
        'name' => (string)($catalogGroupNames[$catalogGroupId] ?? ('PRICE_' . $catalogGroupId)),
    ];
}

$offersMap = CCatalogSKU::getOffersList(
    [$productId],
    $productsIblockId,
    ['ACTIVE' => 'Y'],
    ['ID', 'IBLOCK_ID', 'NAME', 'XML_ID', 'SORT'],
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

            $presetText = trim((string)($propertyEnumNames[$code][$xmlId] ?? ''));
            if ($presetText === '') {
                $presetText = $value;
            }

            $offerProps[$code] = [
                'value' => $presetText,
                'xml_id' => $xmlId,
                'sort' => $sort,
            ];

            if (!isset($presetBuckets[$code])) {
                $presetBuckets[$code] = [];
            }
            $presetBuckets[$code][$xmlId] = [
                'value' => $presetText,
                'xml_id' => $xmlId,
                'sort' => $sort,
            ];
        }

        $pricesRaw = [];
        $priceRes = CPrice::GetListEx(
            ['CATALOG_GROUP_ID' => 'ASC', 'QUANTITY_FROM' => 'ASC'],
            ['PRODUCT_ID' => $offerId],
            false,
            false,
            ['ID', 'CATALOG_GROUP_ID', 'PRICE', 'CURRENCY', 'QUANTITY_FROM', 'QUANTITY_TO']
        );
        while ($price = $priceRes->Fetch()) {
            $catalogGroupId = (int)($price['CATALOG_GROUP_ID'] ?? 0);
            $priceValue = (float)($price['PRICE'] ?? 0);
            $currency = (string)($price['CURRENCY'] ?? '');
            $roundedValue = frontcalc_round_catalog_price($priceValue, $catalogGroupId, $currency);
            $pricesRaw[] = frontcalc_make_price_range(
                $catalogGroupId,
                (string)($catalogGroupNames[$catalogGroupId] ?? ('PRICE_' . $catalogGroupId)),
                $roundedValue,
                $currency,
                html_entity_decode((string)CCurrencyLang::CurrencyFormat($roundedValue, $currency, true), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                frontcalc_normalize_quantity_bound($price['QUANTITY_FROM'] ?? null),
                frontcalc_normalize_quantity_bound($price['QUANTITY_TO'] ?? null)
            );
        }

        $pricesViewAll = array_values(array_filter($pricesRaw, static function ($row) use ($priceAccess) {
            return in_array((int)($row['catalog_group_id'] ?? 0), $priceAccess['view'], true);
        }));

        $pricesBuyAll = array_values(array_filter($pricesRaw, static function ($row) use ($priceAccess) {
            return in_array((int)($row['catalog_group_id'] ?? 0), $priceAccess['buy'], true);
        }));

        $pricesViewByGroup = [];
        foreach ($pricesViewAll as $row) {
            $groupId = (int)($row['catalog_group_id'] ?? 0);
            if ($groupId <= 0) {
                continue;
            }
            $pricesViewByGroup[$groupId][] = $row;
        }
        $pricesViewRangesByGroup = frontcalc_group_price_ranges_by_catalog_group($pricesViewAll);
        $pricesView = [];
        foreach ($pricesViewByGroup as $groupRows) {
            $picked = frontcalc_pick_price_for_quantity($groupRows, 1);
            if ($picked !== null) {
                $pricesView[] = $picked;
            }
        }

        $pricesBuyByGroup = [];
        foreach ($pricesBuyAll as $row) {
            $groupId = (int)($row['catalog_group_id'] ?? 0);
            if ($groupId <= 0) {
                continue;
            }
            $pricesBuyByGroup[$groupId][] = $row;
        }
        $pricesBuyRangesByGroup = frontcalc_group_price_ranges_by_catalog_group($pricesBuyAll);
        $pricesBuy = [];
        foreach ($pricesBuyByGroup as $groupRows) {
            $picked = frontcalc_pick_price_for_quantity($groupRows, 1);
            if ($picked !== null) {
                $pricesBuy[] = $picked;
            }
        }

        $primaryBuyPrice = null;
        $optimalPrice = CCatalogProduct::GetOptimalPrice($offerId, 1, $userGroups, 'N', [], SITE_ID);
        if (is_array($optimalPrice) && !empty($optimalPrice['RESULT_PRICE'])) {
            $resultPrice = $optimalPrice['RESULT_PRICE'];
            $optValue = (float)($resultPrice['DISCOUNT_PRICE'] ?? $resultPrice['BASE_PRICE'] ?? 0);
            $optCurrency = (string)($resultPrice['CURRENCY'] ?? '');
            $optGroupId = (int)($resultPrice['PRICE_TYPE_ID'] ?? 0);
            if (in_array($optGroupId, $priceAccess['view'], true)) {
                $optRounded = frontcalc_round_catalog_price($optValue, $optGroupId, $optCurrency);
                $primaryBuyPrice = [
                    'id' => (int)($resultPrice['PRICE_ID'] ?? 0),
                    'catalog_group_id' => $optGroupId,
                    'catalog_group_name' => (string)($catalogGroupNames[$optGroupId] ?? ('PRICE_' . $optGroupId)),
                    'price' => $optRounded,
                    'currency' => $optCurrency,
                    'formatted' => html_entity_decode((string)CCurrencyLang::CurrencyFormat($optRounded, $optCurrency, true), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'quantity_from' => null,
                    'quantity_to' => null,
                ];
            }
        }
        if ($primaryBuyPrice === null && !empty($pricesBuy)) {
            $primaryBuyPrice = $pricesBuy[0];
        } elseif ($primaryBuyPrice === null && !empty($pricesView)) {
            $primaryBuyPrice = $pricesView[0];
        }

        $catalogProduct = CCatalogProduct::GetByID($offerId);
        $weightGrams = (float)($catalogProduct['WEIGHT'] ?? 0);
        $widthMm = (float)($catalogProduct['WIDTH'] ?? 0);
        $lengthMm = (float)($catalogProduct['LENGTH'] ?? 0);
        $heightMm = (float)($catalogProduct['HEIGHT'] ?? 0);

        $weightKg = $weightGrams > 0 ? round($weightGrams / 1000, 3) : 0.0;
        $volumeM3 = ($widthMm > 0 && $lengthMm > 0 && $heightMm > 0)
            ? round(($widthMm * $lengthMm * $heightMm) / 1000000000, 6)
            : 0.0;

        $offers[] = [
            'id' => $offerId,
            'name' => (string)($offerRow['NAME'] ?? ''),
            'xml_id' => (string)($offerRow['XML_ID'] ?? ''),
            'sort' => (int)($offerRow['SORT'] ?? 500),
            'properties' => $offerProps,
            'catalog' => [
                'prices' => $pricesViewAll,
                'price_ranges' => $pricesViewAll,
                'prices_view' => $pricesView,
                'prices_view_all' => $pricesViewAll,
                'prices_buy' => $pricesBuy,
                'prices_buy_all' => $pricesBuyAll,
                'primary_buy_price' => $primaryBuyPrice,
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

$priceGroupsView = [];
foreach ($priceAccess['view'] as $sort => $catalogGroupId) {
    $catalogGroupId = (int)$catalogGroupId;
    if ($catalogGroupId <= 0) {
        continue;
    }

    $priceGroupsView[] = [
        'id' => $catalogGroupId,
        'name' => (string)($catalogGroupNames[$catalogGroupId] ?? ('PRICE_' . $catalogGroupId)),
        'sort' => $sort,
    ];
}

echo json_encode([
    'success' => true,
    'data' => [
        'product_id' => $productId,
        'requested_offer_id' => $requestedOfferId,
        'user_groups' => $userGroups,
        'price_access' => $priceAccess,
        'price_groups_view' => $priceGroupsView,
        'config' => $config,
        'property_meta' => array_values($propertyMeta),
        'offers' => $offers,
    ],
], JSON_UNESCAPED_UNICODE);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
