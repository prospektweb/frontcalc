<?php

namespace Aspro\Premier\Product;

use Aspro\Premier\Vendor\Include;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use CPremier as Solution;

class Prices
{
    protected $options;
    protected $item;
    protected $params;

    protected $currentPrice;
    protected $itemPrices;
    protected $matrixPrices;
    protected $simplePrices;
    protected $customPrices;
    protected $propsPrices;

    protected bool $isPopupTemplateCaptured = false;

    public function __construct(?array $item = null, ?array $params = null, ?array $options = null)
    {
        if (isset($item) && $item) {
            $this->item = $item;
        }

        if (isset($params) && $params) {
            $this->params = $params;
        }

        $this->resetOptions();
        $this->setOptions($options);

        $this->mkPrices();

        return $this;
    }

    public function __get(string $variable)
    {
        return $this->$variable ?? null;
    }

    public function __set(string $variable, $value)
    {
        switch ($variable) {
            case 'item':
                if (isset($value) && is_array($value) && $value) {
                    $this->$variable = $value;
                    $this->mkPrices();
                }

                break;
            case 'params':
                if (isset($value) && is_array($value) && $value) {
                    $this->$variable = $value;
                }

                break;
            case 'options':
                $this->setOptions($value);

                break;
        }

        return $this->$variable ?? null;
    }

    public function resetOptions(): static
    {
        $this->options = [
            'WRAPPER_CLASS' => '',
            'PRICE_BLOCK_CLASS' => 'color_dark',
            'PRICE_FONT' => 16,
            'PRICE_WEIGHT' => 500,
            'PRICEOLD_FONT' => 12,
            'PRICEOLD_WEIGHT' => 500,
            'SHOW_SCHEMA' => true,
            'SHOW_TABLE' => true,
            'SHOW_POPUP_DETAIL_BUTTON' => true,
            'PRICES' => [], // for custom prices
            'POPOVER_POSITION' => 'bottom',
            'SHOW_POPUP_PRICE_TEMPLATE' => 'N',
            'EXTENDED_INFO' => '',
            'DISPLAY_VAT_INFO' => 'N',
        ];

        return $this;
    }

    public function setOptions(?array $options = []): static
    {
        if (isset($options) && is_array($options) && $options) {
            $this->options = array_merge($this->options, $options);
            $this->mkPrices(); // for custom prices
        }

        return $this;
    }

    public function mkPrices(): static
    {
        $this->currentPrice = $this->itemPrices = $this->matrixPrices = $this->simplePrices = $this->customPrices = $this->propsPrices = null;

        // collect prices
        if (
            $this->item
            && !empty($this->item['ITEM_PRICES'])
            && is_array($this->item['ITEM_PRICES'])
        ) {
            $this->itemPrices = $this->item['ITEM_PRICES'];

            // sort by QUANTITY_FROM => asc
            uasort($this->itemPrices, function ($a, $b) {
                return $a['QUANTITY_FROM'] <=> $b['QUANTITY_FROM'];
            });
        }

        if (
            $this->item
            && !empty($this->item['PRICES'])
            && is_array($this->item['PRICES'])
        ) {
            $this->simplePrices = $this->item['PRICES'];

            // only accessible prices
            $this->simplePrices = array_filter($this->simplePrices, function ($price) {
                return $price['CAN_ACCESS'] === 'Y';
            });

            // fill item PRICE_MATRIX
            if (
                $this->isUseCount()
                && $this->item
                && (
                    !array_key_exists('ITEM_PRICE_MODE', $this->item)
                    || $this->item['ITEM_PRICE_MODE'] === 'Q'
                )
                && empty($this->item['PRICE_MATRIX'])
                && function_exists('\CatalogGetPriceTableEx')
            ) {
                $simplePricesTypes = array_column($this->simplePrices, 'PRICE_ID');
                if ($simplePricesTypes) {
                    // convert currency
                    $convertCurrency = $this->getConvertCurrency();

                    $this->item['PRICE_MATRIX'] = \CatalogGetPriceTableEx(
                        $this->item['ID'],
                        0,
                        $simplePricesTypes,
                        $this->params['PRICE_VAT_INCLUDE'] ? 'Y' : 'N',
                        $convertCurrency ? ['CURRENCY_ID' => $convertCurrency['CURRENCY']] : []
                    );
                }
            }
        }

        if (
            $this->item
            && !empty($this->item['PRICE_MATRIX'])
            && is_array($this->item['PRICE_MATRIX'])
        ) {
            $this->matrixPrices = $this->item['PRICE_MATRIX'];

            // sort ROWS (range) by QUANTITY_FROM => asc
            uasort($this->matrixPrices['ROWS'], function ($a, $b) {
                return $a['QUANTITY_FROM'] <=> $b['QUANTITY_FROM'];
            });
        }

        if (
            $this->options
            && !empty($this->options['PRICES'])
            && is_array($this->options['PRICES'])
        ) {
            $this->customPrices = $this->options['PRICES'];

            if (array_key_exists('VALUE', $this->customPrices)) {
                $this->customPrices = [$this->customPrices];
            }

            // only accessible prices
            $this->customPrices = array_filter(
                $this->customPrices,
                function ($price) {
                    if (
                        (
                            !array_key_exists('CAN_ACCESS', $price)
                            || $price['CAN_ACCESS'] === 'Y'
                        )
                        && array_key_exists('VALUE', $price)
                        && strlen($price['VALUE'])
                    ) {
                        return true;
                    }

                    return false;
                }
            );
        }

        if (
            $this->item
            && ($props = $this->item['PROPERTIES'] ?? $this->item['DISPLAY_PROPERTIES'])
            && !empty($props)
            && is_array($props)
            && array_key_exists('PRICE', $props)
            && is_array($props['PRICE'])
            && array_key_exists('VALUE', $props['PRICE'])
        ) {
            $this->propsPrices = [
                'PRICE_CURRENCY' => $props['PRICE_CURRENCY'] ?? $props['CURRENCY'] ?? [],
                'PRICE' => $props['PRICE'] ?? [],
                'PRICEOLD' => $props['PRICEOLD'] ?? [],
                'FILTER_PRICE' => $props['FILTER_PRICE'] ?? [],
                'ECONOMY' => $props['ECONOMY'] ?? [],
            ];
        }

        $showCount = $this->getShowCount();

        // make current price to buy for count = $showCount
        if ($this->hasOffers()) {
            if (empty($this->item['MIN_PRICE'])) {
                if (!empty($this->item['OFFERS'])) {
                    $this->item['MIN_PRICE'] = Price::getMinPriceFromOffersExt($this->item['OFFERS']);
                } elseif (!empty($this->item['SKU']['OFFERS'])) {
                    $this->item['MIN_PRICE'] = Price::getMinPriceFromOffersExt($this->item['SKU']['OFFERS']);
                }
            }

            $minPrice = empty($this->item['MIN_PRICE']) ? [] : $this->item['MIN_PRICE'];
            if ($minPrice) {
                $measureId = $this->item['ITEM_MEASURE']['ID'] ?? $this->item['CATALOG_MEASURE'] ?? '';
                $measure = $this->item['ITEM_MEASURE']['NAME'] ?? trim($measureId ? Common::showMeasure(Common::getMeasureById($measureId)) : '', '/');

                $this->currentPrice = [
                    'CURRENCY' => $minPrice['CURRENCY'],
                    'VALUE' => $minPrice['VALUE'],
                    'PRINT_VALUE' => $minPrice['PRINT_VALUE'],
                    'DISCOUNT_VALUE' => $minPrice['DISCOUNT_VALUE'],
                    'PRINT_DISCOUNT_VALUE' => $minPrice['PRINT_DISCOUNT_VALUE'],
                    'DISCOUNT_DIFF' => $minPrice['DISCOUNT_DIFF'],
                    'PRINT_DISCOUNT_DIFF' => $minPrice['PRINT_DISCOUNT_DIFF'],
                    'DISCOUNT_DIFF_PERCENT' => $minPrice['DISCOUNT_DIFF_PERCENT'],
                    'PRICE_ID' => $minPrice['ID'],
                    'PRICE_TYPE_ID' => $minPrice['PRICE_ID'],
                    'CATALOG_MEASURE' => $measureId,
                    'MEASURE' => $measure,
                ];
            }
        } elseif ($prices = $this->getItemPrices()) {
            foreach ($prices as $price) {
                if (
                    $price['QUANTITY_FROM'] <= $showCount
                    && (
                        !$price['QUANTITY_TO']
                        || $price['QUANTITY_TO'] >= $showCount
                    )
                ) {
                    $measureId = $this->item['ITEM_MEASURE']['ID'] ?? $this->item['CATALOG_MEASURE'] ?? '';
                    $measure = $this->item['ITEM_MEASURE']['NAME'] ?? trim($measureId ? Common::showMeasure(Common::getMeasureById($measureId)) : '', '/');

                    $this->currentPrice = [
                        'CURRENCY' => $price['CURRENCY'],
                        'VALUE' => $price['BASE_PRICE'],
                        'PRINT_VALUE' => $price['PRINT_BASE_PRICE'],
                        'DISCOUNT_VALUE' => $price['PRICE'],
                        'PRINT_DISCOUNT_VALUE' => $price['PRINT_PRICE'],
                        'DISCOUNT_DIFF' => $price['DISCOUNT'],
                        'PRINT_DISCOUNT_DIFF' => $price['PRINT_DISCOUNT'],
                        'DISCOUNT_DIFF_PERCENT' => $price['PERCENT'],
                        'PRICE_ID' => $price['ID'],
                        'PRICE_TYPE_ID' => $price['PRICE_TYPE_ID'],
                        'CATALOG_MEASURE' => $measureId,
                        'MEASURE' => $measure,
                    ];

                    break;
                }
            }
        } elseif ($prices = $this->getMatrixPrices()) {
            if ($minPrice = $this->getMinMatrixPrice($showCount)) {
                $measureId = $this->item['ITEM_MEASURE']['ID'] ?? $this->item['CATALOG_MEASURE'] ?? '';
                $measure = $this->item['ITEM_MEASURE']['NAME'] ?? trim($measureId ? Common::showMeasure(Common::getMeasureById($measureId)) : '', '/');

                $discountDiff = $discountPercent = 0;
                if ($minPrice['PRICE'] > 0) {
                    $discountDiff = $minPrice['PRICE'] - $minPrice['DISCOUNT_PRICE'];
                    $discountPercent = round(($discountDiff / $minPrice['PRICE']) * 100, 0);
                }

                $this->currentPrice = [
                    'CURRENCY' => $minPrice['CURRENCY'],
                    'VALUE' => $minPrice['PRICE'],
                    'PRINT_VALUE' => \CCurrencyLang::CurrencyFormat($minPrice['PRICE'], $minPrice['CURRENCY'], true),
                    'DISCOUNT_VALUE' => $minPrice['DISCOUNT_PRICE'],
                    'PRINT_DISCOUNT_VALUE' => \CCurrencyLang::CurrencyFormat($minPrice['DISCOUNT_PRICE'], $minPrice['CURRENCY'], true),
                    'DISCOUNT_DIFF' => $discountDiff,
                    'PRINT_DISCOUNT_DIFF' => \CCurrencyLang::CurrencyFormat($discountDiff, $minPrice['CURRENCY'], true),
                    'DISCOUNT_DIFF_PERCENT' => $discountPercent,
                    'PRICE_ID' => $minPrice['ID'],
                    'PRICE_TYPE_ID' => $minPrice['PRICE_ID'],
                    'CATALOG_MEASURE' => $measureId,
                    'MEASURE' => $measure,
                ];
            }
        } elseif ($prices = $this->getSimplePrices()) {
            if ($minPrice = $this->getMinSimplePrice()) {
                $measureId = $this->item['ITEM_MEASURE']['ID'] ?? $this->item['CATALOG_MEASURE'] ?? '';
                $measure = $this->item['ITEM_MEASURE']['NAME'] ?? trim($measureId ? Common::showMeasure(Common::getMeasureById($measureId)) : '', '/');

                $this->currentPrice = [
                    'CURRENCY' => $minPrice['CURRENCY'],
                    'VALUE' => $minPrice['VALUE'],
                    'PRINT_VALUE' => $minPrice['PRINT_VALUE'],
                    'DISCOUNT_VALUE' => $minPrice['DISCOUNT_VALUE'],
                    'PRINT_DISCOUNT_VALUE' => $minPrice['PRINT_DISCOUNT_VALUE'],
                    'DISCOUNT_DIFF' => $minPrice['DISCOUNT_DIFF'],
                    'PRINT_DISCOUNT_DIFF' => $minPrice['PRINT_DISCOUNT_DIFF'],
                    'DISCOUNT_DIFF_PERCENT' => $minPrice['DISCOUNT_DIFF_PERCENT'],
                    'PRICE_ID' => $minPrice['ID'],
                    'PRICE_TYPE_ID' => $minPrice['PRICE_ID'],
                    'CATALOG_MEASURE' => $measureId,
                    'MEASURE' => $measure,
                ];
            }
        } elseif ($prices = $this->getCustomPrices()) {
            if ($minPrice = $this->getMinCustomPrice()) {
                $currency = $minPrice['CURRENCY'] ?? $minPrice['PRICE_CURRENCY'] ?? '';

                $discountDiff = $minPrice['DISCOUNT_DIFF'] ?? (($minPrice['VALUE'] * 1) > 0 ? ($minPrice['VALUE'] - $minPrice['DISCOUNT_VALUE']) : 0);
                $discountPercent = $minPrice['DISCOUNT_DIFF_PERCENT'] ?? (($minPrice['VALUE'] * 1) > 0 ? round(($discountDiff / $minPrice['VALUE']) * 100, 0) : 0);

                if (Loader::includeModule('currency')) {
                    $minPrice['PRINT_VALUE'] = $minPrice['PRINT_VALUE'] ?? \CCurrencyLang::CurrencyFormat($minPrice['VALUE'], $currency, true);
                    $minPrice['PRINT_DISCOUNT_VALUE'] = $minPrice['PRINT_DISCOUNT_VALUE'] ?? \CCurrencyLang::CurrencyFormat($minPrice['DISCOUNT_VALUE'], $currency, true);
                    $minPrice['PRINT_DISCOUNT_DIFF'] = $minPrice['PRINT_DISCOUNT_DIFF'] ?? \CCurrencyLang::CurrencyFormat($discountDiff, $currency, true);
                }

                $this->currentPrice = [
                    'CURRENCY' => $currency,
                    'VALUE' => $minPrice['VALUE'],
                    'PRINT_VALUE' => $minPrice['PRINT_VALUE'],
                    'DISCOUNT_VALUE' => $minPrice['DISCOUNT_VALUE'],
                    'PRINT_DISCOUNT_VALUE' => $minPrice['PRINT_DISCOUNT_VALUE'],
                    'DISCOUNT_DIFF' => $discountDiff,
                    'PRINT_DISCOUNT_DIFF' => $minPrice['PRINT_DISCOUNT_DIFF'],
                    'DISCOUNT_DIFF_PERCENT' => $discountPercent,
                ];

                if (isset($this->item['ITEM_MEASURE']['ID']) || isset($this->item['CATALOG_MEASURE'])) {
                    $measureId = $this->item['ITEM_MEASURE']['ID'] ?? $this->item['CATALOG_MEASURE'] ?? '';
                    $measure = $this->item['ITEM_MEASURE']['NAME'] ?? trim($measureId ? Common::showMeasure(Common::getMeasureById($measureId)) : '', '/');

                    $this->currentPrice['CATALOG_MEASURE'] = $measureId;
                    $this->currentPrice['MEASURE'] = $measure;
                }
            }
        } elseif ($prices = $this->getPropsPrices()) {
            $this->currentPrice = [
                'VALUE' => $prices['PRICEOLD']['DISPLAY_VALUE'] ?? $prices['PRICEOLD']['~VALUE'] ?? $prices['PRICEOLD']['VALUE'],
                'PRINT_VALUE' => $prices['PRICEOLD']['DISPLAY_VALUE'] ?? $prices['PRICEOLD']['~VALUE'] ?? $prices['PRICEOLD']['VALUE'],
                'DISCOUNT_VALUE' => $prices['PRICE']['DISPLAY_VALUE'] ?? $prices['PRICE']['~VALUE'] ?? $prices['PRICE']['VALUE'],
                'PRINT_DISCOUNT_VALUE' => $prices['PRICE']['DISPLAY_VALUE'] ?? $prices['PRICE']['~VALUE'] ?? $prices['PRICE']['VALUE'],
                'DISCOUNT_DIFF_PERCENT' => $prices['ECONOMY']['DISPLAY_VALUE'] ?? $prices['ECONOMY']['~VALUE'] ?? $prices['ECONOMY']['VALUE'],
            ];

            if (!strlen($this->currentPrice['VALUE']) && strlen($this->currentPrice['DISCOUNT_VALUE'])) {
                $this->currentPrice['VALUE'] = $this->currentPrice['DISCOUNT_VALUE'];
                $this->currentPrice['PRINT_VALUE'] = $this->currentPrice['PRINT_DISCOUNT_VALUE'];
            }
        }

        return $this;
    }

    /**
     * The price is filled, you need to show it, for example 0.00.
     */
    public function isFilled(): bool
    {
        return (bool) $this->currentPrice && strlen($this->currentPrice['VALUE'] ?? '');
    }

    /**
     * The price is not filled.
     */
    public function isEmpty(): bool
    {
        return !$this->isFilled();
    }

    /**
     * The price is filled and greater than zero.
     */
    public function isGreaterThanZero(): bool
    {
        return $this->isFilled() && $this->currentPrice['VALUE'] > 0;
    }

    public function isCatalogFilled(): bool
    {
        return $this->isFilled() && ($this->hasItemPrices() || $this->hasMatrixPrices() || $this->hasSimplePrices());
    }

    public function getCurrentPrice(): ?array
    {
        return $this->currentPrice;
    }

    public function hasOffers(): bool
    {
        return $this->item && (!empty($this->item['OFFERS']) || !empty($this->item['SKU']['OFFERS']));
    }

    public function getItemPrices(): ?array
    {
        return $this->itemPrices;
    }

    public function hasItemPrices(): bool
    {
        return (bool) $this->getItemPrices();
    }

    public function getMatrixPrices(): ?array
    {
        return $this->matrixPrices;
    }

    public function getMinMatrixPrice(int $showCount = 1): ?array
    {
        $minPrice = null;

        if ($prices = $this->getMatrixPrices()) {
            foreach ($prices['ROWS'] as $rowKey => $row) {
                if (
                    $row['QUANTITY_FROM'] <= $showCount
                    && (
                        !$row['QUANTITY_TO']
                        || $row['QUANTITY_TO'] >= $showCount
                    )
                ) {
                    $min = 0;
                    foreach ($prices['MATRIX'] as $colkey => $matrixCol) {
                        if (in_array($colkey, $prices['CAN_BUY'])) {
                            if (isset($matrixCol[$rowKey])) {
                                $matrixCol[$rowKey]['PRICE_ID'] = $colkey;
                                $compare = $matrixCol[$rowKey]['DISCOUNT_PRICE'] ?? $matrixCol[$rowKey]['PRICE'];

                                if (
                                    !$min
                                    || $min > $compare
                                ) {
                                    $min = $compare;
                                    $minPrice = $matrixCol[$rowKey];
                                }
                            }
                        }
                    }

                    break;
                }
            }
        }

        return $minPrice;
    }

    public function hasMatrixPrices(): bool
    {
        return (bool) $this->getMatrixPrices();
    }

    public function getSimplePrices(): ?array
    {
        return $this->simplePrices;
    }

    public function getMinSimplePrice(): ?array
    {
        $minPrice = null;

        if ($prices = $this->getSimplePrices()) {
            foreach ($prices as $i => $price) {
                if ($price['CAN_BUY'] === 'Y') {
                    if (isset($price['MIN_PRICE']) && $price['MIN_PRICE'] === 'Y') {
                        $minPrice = $price[$i];
                    }
                }
            }

            if (!$minPrice) {
                // convert currency
                $convertCurrency = $this->getConvertCurrency();

                $min = 0;
                foreach ($prices as $i => $price) {
                    if ($price['CAN_BUY'] === 'Y') {
                        $compare = $price['DISCOUNT_VALUE'] ?? $price['VALUE'];

                        if (
                            $convertCurrency
                            && is_array($convertCurrency)
                            && isset($price['CURRENCY'])
                            && $price['CURRENCY']
                            && $price['CURRENCY'] != $convertCurrency['CURRENCY']
                        ) {
                            $compare = \CCurrencyRates::ConvertCurrency($compare, $price['CURRENCY'], $convertCurrency['CURRENCY']);
                        }

                        if (
                            !$min
                            || $min > $compare
                        ) {
                            $min = $compare;
                            $minPrice = $prices[$i];
                        }
                    }
                }
            }
        }

        return $minPrice;
    }

    public function hasSimplePrices(): bool
    {
        return (bool) $this->getSimplePrices();
    }

    public function getCustomPrices(): ?array
    {
        return $this->customPrices;
    }

    public function getMinCustomPrice(): ?array
    {
        $minPrice = null;

        if ($prices = $this->getCustomPrices()) {
            foreach ($prices as $i => $price) {
                if (
                    !array_key_exists('CAN_BUY', $price)
                    || $price['CAN_BUY'] === 'Y'
                ) {
                    if (isset($price['MIN_PRICE']) && $price['MIN_PRICE'] === 'Y') {
                        $minPrice = $price[$i];
                    }
                }
            }

            if (!$minPrice) {
                $min = 0;
                foreach ($prices as $i => $price) {
                    if (
                        !array_key_exists('CAN_BUY', $price)
                        || $price['CAN_BUY'] === 'Y'
                    ) {
                        $compare = $price['DISCOUNT_VALUE'] ?? $price['VALUE'];

                        if (
                            !$min
                            || $min > $compare
                        ) {
                            $min = $compare;
                            $minPrice = $prices[$i];
                        }
                    }
                }
            }
        }

        return $minPrice;
    }

    public function hasCustomPrices(): bool
    {
        return (bool) $this->getCustomPrices();
    }

    public function getPropsPrices(): ?array
    {
        return $this->propsPrices;
    }

    public function hasPropsPrices(): bool
    {
        return (bool) $this->getPropsPrices();
    }

    public function isWithTable(): bool
    {
        if (
            $this->options
            && isset($this->options['SHOW_TABLE'])
            && (
                $this->options['SHOW_TABLE'] === 'N'
                || $this->options['SHOW_TABLE'] === false
            )
        ) {
            return false;
        }

        if (!$this->hasOffers()) {
            if ($this->isUseCompatible()) {
                if ($prices = $this->getMatrixPrices()) {
                    return count($prices['COLS']) > 1 || count($prices['ROWS']) > 1;
                } elseif ($prices = $this->getSimplePrices()) {
                    return count($prices) > 1;
                }
            } else {
                if (
                    $this->isUseCount()
                    && $prices = $this->getItemPrices()
                ) {
                    return count($prices) > 1;
                }
            }
        }

        return false;
    }

    public function isWithPopupTable(): bool
    {
        if ($this->isWithTable()) {
            return static::getPopupPriceShowConditionFromParams($this->params);
        }

        return false;
    }

    public static function getPopupPriceShowConditionFromParams(array $params): bool
    {
        if ($params && array_key_exists('SHOW_POPUP_PRICE', $params)) {
            return $params['SHOW_POPUP_PRICE'] !== false && $params['SHOW_POPUP_PRICE'] !== 'N' && $params['SHOW_POPUP_PRICE'] !== 'NO';
        } else {
            return Solution::getFrontParametrValue('SHOW_POPUP_PRICE') != 'NO';
        }
    }

    public function isWithInlineTable(): bool
    {
        return $this->isWithTable() && !$this->isWithPopupTable();
    }

    public function isUseCompatible(): bool
    {
        // false by default
        if (
            $this->params
            && array_key_exists('COMPATIBLE_MODE', $this->params)
        ) {
            return $this->params['COMPATIBLE_MODE'] === true || $this->params['COMPATIBLE_MODE'] === 'Y';
        } else {
            return Solution::getFrontParametrValue('CATALOG_COMPATIBLE_MODE') === 'Y';
        }
    }

    public function isUseCount(): bool
    {
        // true by default
        if (
            $this->params
            && array_key_exists('USE_PRICE_COUNT', $this->params)
        ) {
            return $this->params['USE_PRICE_COUNT'] !== false && $this->params['USE_PRICE_COUNT'] !== 'N';
        } else {
            return Solution::getFrontParametrValue('USE_PRICE_COUNT') !== 'N';
        }
    }

    public function getShowCount(): int
    {
        $count = 1;

        if ($this->isUseCount()) {
            if (
                $this->params
                && array_key_exists('SHOW_PRICE_COUNT', $this->params)
            ) {
                $tmp = intval($this->params['SHOW_PRICE_COUNT'] ?? 1);
            } else {
                $tmp = intval(Solution::getFrontParametrValue('SHOW_PRICE_COUNT') ?? 1);
            }

            if ($tmp > 1) {
                $count = $tmp;
            }
        }

        return $count;
    }

    public function isShowSchema(): bool
    {
        if ($this->params && ($this->params['SHOW_PRICE'] === true || $this->params['SHOW_PRICE'] === 'Y')) {
            return false;
        }

        // true by default
        return array_key_exists('SHOW_SCHEMA', $this->options) && $this->options['SHOW_SCHEMA'] !== false && $this->options['SHOW_SCHEMA'] !== 'N';
    }

    public function isShowOldPrice(): bool
    {
        // true by default
        if (
            $this->params
            && array_key_exists('SHOW_OLD_PRICE', $this->params)
        ) {
            return $this->params['SHOW_OLD_PRICE'] !== false && $this->params['SHOW_OLD_PRICE'] !== 'N';
        } else {
            return Solution::getFrontParametrValue('SHOW_OLD_PRICE') != 'N';
        }
    }

    public function isShowDiscountDiff(): bool
    {
        // false by default
        if (
            $this->params
            && array_key_exists('SHOW_DISCOUNT_DIFF', $this->params)
        ) {
            return $this->params['SHOW_DISCOUNT_DIFF'] === true || $this->params['SHOW_DISCOUNT_DIFF'] === 'Y';
        } else {
            return Solution::getFrontParametrValue('SHOW_DISCOUNT_DIFF') === 'Y';
        }
    }

    public function isShowDiscountPercent(): bool
    {
        // true by default
        if (
            $this->params
            && array_key_exists('SHOW_DISCOUNT_PERCENT', $this->params)
        ) {
            return $this->params['SHOW_DISCOUNT_PERCENT'] !== false && $this->params['SHOW_DISCOUNT_PERCENT'] !== 'N';
        } else {
            return Solution::getFrontParametrValue('SHOW_DISCOUNT_PERCENT') != 'N';
        }
    }

    public function isConvertCurrency(): bool
    {
        if (
            $this->params
            && array_key_exists('CONVERT_CURRENCY', $this->params)
        ) {
            return $this->params['CONVERT_CURRENCY'] === true || $this->params['CONVERT_CURRENCY'] === 'Y';
        } else {
            return Solution::getFrontParametrValue('CONVERT_CURRENCY') != 'N';
        }
    }

    public function getConvertCurrencyId(): ?string
    {
        if (
            $this->params
            && array_key_exists('CURRENCY_ID', $this->params)
        ) {
            return $this->params['CURRENCY_ID'] ?: null;
        } else {
            return Solution::getFrontParametrValue('CURRENCY_ID') ?: null;
        }
    }

    public function getConvertCurrency(): ?array
    {
        if ($this->isConvertCurrency()) {
            $convertCurrencyId = $this->getConvertCurrencyId();
            if ($convertCurrencyId) {
                $convertCurrency = static::getCurrency($convertCurrencyId);
                if ($convertCurrency && is_array($convertCurrency)) {
                    return $convertCurrency;
                }
            }
        }

        return null;
    }

    public function isDetailPage(): bool
    {
        return $this->params
            && (
                (array_key_exists('ELEMENT_ID', $this->params) && array_key_exists('ELEMENT_CODE', $this->params))
                || (array_key_exists('IS_DETAIL', $this->params) && $this->params['IS_DETAIL'])
            );
    }

    public function isDetailPageSku2List(): bool
    {
        return $this->params && array_key_exists('REPLACED_DETAIL_LINK', $this->params);
    }

    public function isShowPopupDetailButton(): bool
    {
        if (
            $this->item
            && $this->item['DETAIL_PAGE_URL']
            && !$this->isDetailPage()
            && !$this->isDetailPageSku2List()
        ) {
            if (
                $this->options
                && isset($this->options['SHOW_POPUP_DETAIL_BUTTON'])
            ) {
                return $this->options['SHOW_POPUP_DETAIL_BUTTON'] !== false && $this->options['SHOW_POPUP_DETAIL_BUTTON'] !== 'N';
            }
        }

        return false;
    }

    public function showPopupDetailButton()
    {
        ?>
        <a href="<?=$this->item['DETAIL_PAGE_URL'];?>" class="btn btn-default btn-sm width-100"><?=Loc::getMessage('PRICE_POPUP_BUTTON_DETAIL');?></a>
        <?php
    }

    public function showRow(?array $row, ?bool $bWithPopup = false)
    {
        if ($row && is_array($row)) {
            $bWithTitle = isset($row['TITLE']) && strlen($row['TITLE']);
            $bWithRange = isset($row['RANGE_TITLE']) && strlen($row['RANGE_TITLE']);
            $bDel = isset($row['IS_DISCOUNT']) && $row['IS_DISCOUNT'];
            $isCurrent = $this->currentPrice['PRICE_ID'] === $row['PRICE_ID'];

            $priceClasses = ['price', $this->options['PRICE_BLOCK_CLASS']];
            if ($bWithPopup) {
                $priceClasses[] = 'price--with-popup price__row';
            }
            if ($bWithTitle) {
                $priceClasses[] = 'price--with-title';
            }
            if ($bWithRange) {
                $priceClasses[] = 'price--with-range';
            }
            if ($this->isDetailPage() && $isCurrent) {
                $priceClasses[] = 'price--current';
            }
            $priceClasses = trim(implode(' ', array_unique($priceClasses)));

            $bCatalogPrices = isset($row['CATALOG_MEASURE']);
            ?>
            <div class="<?=$priceClasses;?>">
                <?if ($bWithTitle):?>
                    <div class="price__title font_12 <?= $bWithRange ? 'dark-color fw-500 mb mb--8' : 'secondary-color';?>"><?=$row['TITLE'];?></div>
                <?endif;?>

                <?if ($bWithRange):?>
                    <div class="price__range font_12 secondary-color"><span><?=$row['RANGE_TITLE'];?></span></div>
                <?endif;?>

                <?if (!$bWithPopup):?>
                    <div class="price__row">
                <?endif;?>

                    <?if ($bWithPopup):?>
                        <?php
                        $pricesTablePopover = new \Aspro\Premier\Popover\PricesTable($this);
                        ?>

                        <button
                            type="button"
                            class="price__popup-toggle xpopover-toggle secondary-color rounded-4"
                            <?$pricesTablePopover->showToggleAttrs();?>
                        >
                            <?$pricesTablePopover->showContent();?>
                        </button>
                    <?endif;?>

                    <div class="price__new fw-<?=$this->options['PRICE_WEIGHT'];?>">
                        <?='<'.($bDel ? 'del' : 'span').' class="price__new-val font_'.($bDel ? $this->options['PRICEOLD_FONT'] : $this->options['PRICE_FONT']).'">';?>
                            <?if ($bCatalogPrices):?>
                                <?=Price::formatWithSchemaByTypes([
                                    'PRICE' => $row,
                                    'CATALOG_MEASURE' => $row['CATALOG_MEASURE'],
                                    'SHOW_SCHEMA' => $this->isShowSchema(),
                                ]);?>
                            <?else:?>
                                <?=Price::formatWithSchemaByProps(
                                    $row['DISCOUNT_VALUE'],
                                    $this->isShowSchema(),
                                    $this->getPropsPrices() ?: [],
                                    $row
                                );?>
                            <?endif;?>
                        <?='</'.($bDel ? 'del' : 'span').'>';?>
                    </div>

                    <?if (
                        isset($row['VALUE'])
                        && (
                            $bCatalogPrices
                            && $row['VALUE'] > $row['DISCOUNT_VALUE']
                        )
                        || (
                            !$bCatalogPrices
                            && isset($row['DISCOUNT_VALUE'])
                            && $row['VALUE'] != $row['DISCOUNT_VALUE']
                        )
                    ):?>
                        <?if ($this->isShowOldPrice()):?>
                            <div class="price__old fw-<?=$this->options['PRICEOLD_WEIGHT'];?>">
                                <del class="price__old-val font_<?=$this->options['PRICEOLD_FONT'];?> secondary-color">
                                    <?if ($bCatalogPrices):?>
                                        <?=$row['PRINT_VALUE'];?>
                                    <?else:?>
                                        <?=Price::formatWithSchemaByProps(
                                            $row['VALUE'],
                                            false,
                                            $this->item ? ($this->item['PROPERTIES'] ?: $this->item['DISPLAY_PROPERTIES'] ?: $this->item) : [],
                                            $row
                                        );?>
                                    <?endif;?>
                                </del>
                            </div>
                        <?endif;?>

                        <?if ($this->isShowDiscountPercent()):?>
                            <?php
                            $discountDiff = $row['DISCOUNT_DIFF'] ?? ($bCatalogPrices && $row['VALUE'] > 0 ? ($row['VALUE'] - $row['DISCOUNT_VALUE']) : 0);
                            $discountPercent = $row['DISCOUNT_DIFF_PERCENT'] ?? ($bCatalogPrices && $row['VALUE'] > 0 ? round(($discountDiff / $row['VALUE']) * 100, 0) : 0);
                            ?>
                            <?if ($this->isShowDiscountDiff()):?>
                                <?if ($discountPercent || $discountDiff):?>
                                    <?if (!$bWithPopup):?>
                                        </div><?// class="price__row"?>
                                        <div class="price__row">
                                    <?endif;?>

                                    <div class="price__economy sticker price__economy--with-diff">
                                        <?if ($discountPercent):?>
                                            <span class="price__economy-percent sticker__item sticker__item--sale-text font_12">-<?= $discountPercent.' %';?></span>
                                        <?endif;?>

                                        <?if ($discountDiff):?>
                                            <span class="price__economy-val sticker__item sticker__item--sale font_12">
                                                <?if ($bCatalogPrices):?>
                                                    -<?=$row['PRINT_DISCOUNT_DIFF'];?>
                                                <?else:?>
                                                    -<?=Price::formatWithSchemaByProps(
                                                        $discountDiff,
                                                        false,
                                                        $this->item ? ($this->item['PROPERTIES'] ?: $this->item['DISPLAY_PROPERTIES'] ?: $this->item) : [],
                                                        $row
                                                    );?>
                                                <?endif;?>
                                            </span>
                                        <?endif;?>
                                    </div>
                                <?endif;?>
                            <?else:?>
                                <?if ($discountPercent):?>
                                    <div class="price__economy sticker">
                                        <span class="price__economy-percent sticker__item sticker__item--sale-text font_12">
                                            <?if ($bCatalogPrices):?>
                                                -<?= $discountPercent.'%';?>
                                            <?else:?>
                                                -<?=Price::formatWithSchemaByProps(
                                                    $discountPercent,
                                                    false,
                                                    $this->item ? ($this->item['PROPERTIES'] ?: $this->item['DISPLAY_PROPERTIES'] ?: $this->item) : [],
                                                    $row
                                                );?>
                                            <?endif;?>
                                        </span>
                                    </div>
                                <?endif;?>
                            <?endif;?>
                        <?else:?>

                        <?endif;?>
                    <?endif;?>

                <?if (!$bWithPopup):?>
                        <?=$this->options['EXTENDED_INFO'];?>
                    </div><?// class="price__row"?>
                <?endif;?>
            </div>
            <?php
        }
    }

    public function captureTable(): string
    {
        $html = '';

        if ($this->item['PRODUCT_ANALOG']) {
            return $html;
        }

        ob_start();
        ?>
        <div class="prices__table flexbox flexbox--direction-column gap gap--8">
            <?php
            if ($this->isUseCompatible()) {
                if ($prices = $this->getMatrixPrices()) {
                    $priceClasses = ['price', $this->options['PRICE_BLOCK_CLASS']];
                    $priceClasses = trim(implode(' ', $priceClasses));

                    foreach ($prices['MATRIX'] as $colkey => $matrixCol) {
                        $priceTitle = $prices['COLS'][$colkey]['NAME_LANG'] ?? '';
                        $priceCode = $prices['COLS'][$colkey]['NAME'] ?? '';
                        $bDiscountPrice = $this->checkOldPrice($priceCode);

                        $i = 0;
                        foreach ($matrixCol as $rowkey => $price) {
                            $measureId = $this->item['ITEM_MEASURE']['ID'] ?? $this->item['CATALOG_MEASURE'] ?? '';
                            $measure = $this->item['ITEM_MEASURE']['NAME'] ?? trim($measureId ? Common::showMeasure(Common::getMeasureById($measureId)) : '', '/');

                            $minQnt = $prices['ROWS'][$rowkey]['QUANTITY_FROM'];
                            $bWithRange = $minQnt > 0;
                            if ($bWithRange) {
                                $maxQnt = $prices['ROWS'][$rowkey]['QUANTITY_TO'];
                                $rangeTitle = Loc::getMessage('PRICE_RANGE_FROM').' '.$minQnt.($maxQnt ? ' '.Loc::getMessage('PRICE_RANGE_TO').' '.$maxQnt : '').' '.$measure;
                            }

                            $discountDiff = $discountPercent = 0;
                            if ($price['PRICE'] > 0) {
                                $discountDiff = $price['PRICE'] - $price['DISCOUNT_PRICE'];
                                $discountPercent = round(($discountDiff / $price['PRICE']) * 100, 0);
                            }

                            $row = [
                                'CURRENCY' => $price['CURRENCY'],
                                'VALUE' => $price['PRICE'],
                                'PRINT_VALUE' => \CCurrencyLang::CurrencyFormat($price['PRICE'], $price['CURRENCY'], true),
                                'DISCOUNT_VALUE' => $price['DISCOUNT_PRICE'],
                                'PRINT_DISCOUNT_VALUE' => \CCurrencyLang::CurrencyFormat($price['DISCOUNT_PRICE'], $price['CURRENCY'], true),
                                'DISCOUNT_DIFF' => $discountDiff,
                                'PRINT_DISCOUNT_DIFF' => \CCurrencyLang::CurrencyFormat($discountDiff, $price['CURRENCY'], true),
                                'DISCOUNT_DIFF_PERCENT' => $discountPercent,
                                'PRICE_ID' => $price['ID'],
                                'PRICE_TYPE_ID' => $colkey,
                                'CATALOG_MEASURE' => $measureId,
                                'MEASURE' => $measure,
                                'IS_DISCOUNT' => $bDiscountPrice,
                            ];

                            $bWithTitle = strlen($priceTitle) && !$i++;
                            if ($bWithTitle) {
                                // show only once for price type (col)
                                $row['TITLE'] = $priceTitle;
                            }

                            if ($bWithRange) {
                                $row['RANGE_TITLE'] = $rangeTitle;
                            }
                            ?>
                            <?$this->showRow($row);?>
                            <?php
                        }
                    }
                } elseif ($prices = $this->getSimplePrices()) {
                    $priceClasses = ['price', $this->options['PRICE_BLOCK_CLASS']];
                    $priceClasses = trim(implode(' ', $priceClasses));

                    foreach ($prices as $priceCode => $price) {
                        $measureId = $this->item['ITEM_MEASURE']['ID'] ?? $this->item['CATALOG_MEASURE'] ?? '';
                        $measure = $this->item['ITEM_MEASURE']['NAME'] ?? trim($measureId ? Common::showMeasure(Common::getMeasureById($measureId)) : '', '/');

                        $priceTitle = $this->item['CATALOG_GROUP_NAME_'.$price['PRICE_ID']] ?? '';
                        $bDiscountPrice = $this->checkOldPrice($priceCode);

                        $row = [
                            'CURRENCY' => $price['CURRENCY'],
                            'VALUE' => $price['VALUE'],
                            'PRINT_VALUE' => $price['PRINT_VALUE'],
                            'DISCOUNT_VALUE' => $price['DISCOUNT_VALUE'],
                            'PRINT_DISCOUNT_VALUE' => $price['PRINT_DISCOUNT_VALUE'],
                            'DISCOUNT_DIFF' => $price['DISCOUNT_DIFF'],
                            'PRINT_DISCOUNT_DIFF' => $price['PRINT_DISCOUNT_DIFF'],
                            'DISCOUNT_DIFF_PERCENT' => $price['DISCOUNT_DIFF_PERCENT'],
                            'PRICE_ID' => $price['ID'],
                            'PRICE_TYPE_ID' => $price['PRICE_ID'],
                            'CATALOG_MEASURE' => $measureId,
                            'MEASURE' => $measure,
                            'IS_DISCOUNT' => $bDiscountPrice,
                        ];

                        if (strlen($priceTitle)) {
                            // show only once for price type (col)
                            $row['TITLE'] = $priceTitle;
                        }
                        ?>
                        <?$this->showRow($row);?>
                        <?php
                    }
                }
            } else {
                if (
                    $this->isUseCount()
                    && $prices = $this->getItemPrices()
                ) {
                    $priceClasses = ['price', $this->options['PRICE_BLOCK_CLASS']];
                    $priceClasses = trim(implode(' ', $priceClasses));

                    foreach ($prices as $i => $price) {
                        $measureId = $this->item['ITEM_MEASURE']['ID'] ?? $this->item['CATALOG_MEASURE'];
                        $measure = $this->item['ITEM_MEASURE']['NAME'] ?? trim($measureId ? Common::showMeasure(Common::getMeasureById($measureId)) : '', '/');

                        $minQnt = $price['QUANTITY_FROM'];
                        $bWithRange = $minQnt > 0;
                        if ($bWithRange) {
                            $maxQnt = $price['QUANTITY_TO'];
                            $rangeTitle = Loc::getMessage('PRICE_RANGE_FROM').' '.$minQnt.($maxQnt ? ' '.Loc::getMessage('PRICE_RANGE_TO').' '.$maxQnt : '').' '.$measure;
                        }

                        $row = [
                            'CURRENCY' => $price['CURRENCY'],
                            'VALUE' => $price['BASE_PRICE'],
                            'PRINT_VALUE' => $price['PRINT_BASE_PRICE'],
                            'DISCOUNT_VALUE' => $price['PRICE'],
                            'PRINT_DISCOUNT_VALUE' => $price['PRINT_PRICE'],
                            'DISCOUNT_DIFF' => $price['DISCOUNT'],
                            'PRINT_DISCOUNT_DIFF' => $price['PRINT_DISCOUNT'],
                            'DISCOUNT_DIFF_PERCENT' => $price['PERCENT'],
                            'PRICE_ID' => $price['ID'],
                            'PRICE_TYPE_ID' => $price['PRICE_TYPE_ID'],
                            'CATALOG_MEASURE' => $measureId,
                            'MEASURE' => $measure,
                        ];

                        if ($bWithRange) {
                            $row['RANGE_TITLE'] = $rangeTitle;
                        }
                        ?>
                        <?$this->showRow($row);?>
                        <?php
                    }
                }
            }

            if (!$prices) {
                if ($prices = $this->getCustomPrices()) {
                    $priceClasses = ['price', $this->options['PRICE_BLOCK_CLASS']];
                    $priceClasses = trim(implode(' ', $priceClasses));

                    foreach ($prices as $price) {
                        $currency = $price['CURRENCY'] ?? $price['PRICE_CURRENCY'] ?? '';
                        $discountDiff = $price['DISCOUNT_DIFF'] ?? ($price['PRICE'] > 0 ? ($price['PRICE'] - $price['DISCOUNT_VALUE']) : 0);
                        $discountPercent = $price['DISCOUNT_DIFF_PERCENT'] ?? ($price['VALUE'] > 0 ? round(($discountDiff / $price['VALUE']) * 100, 0) : 0);

                        if (Loader::includeModule('currency')) {
                            $price['PRINT_VALUE'] = $price['PRINT_VALUE'] ?? \CCurrencyLang::CurrencyFormat($price['VALUE'], $currency, true);
                            $price['PRINT_DISCOUNT_VALUE'] = $price['PRINT_DISCOUNT_VALUE'] ?? \CCurrencyLang::CurrencyFormat($price['DISCOUNT_VALUE'], $currency, true);
                            $price['PRINT_DISCOUNT_DIFF'] = $price['PRINT_DISCOUNT_DIFF'] ?? \CCurrencyLang::CurrencyFormat($discountDiff, $currency, true);
                        }

                        $row = [
                            'CURRENCY' => $currency,
                            'VALUE' => $price['VALUE'],
                            'PRINT_VALUE' => $price['PRINT_VALUE'],
                            'DISCOUNT_VALUE' => $price['DISCOUNT_VALUE'],
                            'PRINT_DISCOUNT_VALUE' => $price['PRINT_DISCOUNT_VALUE'],
                            'DISCOUNT_DIFF' => $discountDiff,
                            'PRINT_DISCOUNT_DIFF' => $price['PRINT_DISCOUNT_DIFF'],
                            'DISCOUNT_DIFF_PERCENT' => $discountPercent,
                        ];

                        if (isset($this->item['ITEM_MEASURE']['ID']) || isset($this->item['CATALOG_MEASURE'])) {
                            $measureId = $this->item['ITEM_MEASURE']['ID'] ?? $this->item['CATALOG_MEASURE'] ?? '';
                            $measure = $this->item['ITEM_MEASURE']['NAME'] ?? trim($measureId ? Common::showMeasure(Common::getMeasureById($measureId)) : '', '/');

                            $row['CATALOG_MEASURE'] = $measureId;
                            $row['MEASURE'] = $measure;
                        }
                        ?>
                            <?$this->showRow($row);?>
                            <?php
                    }
                }
            }
            ?>
        </div>
        <?php
        $html = trim(ob_get_clean());

        return $html;
    }

    public function showTable(): static
    {
        echo $this->captureTable();

        return $this;
    }

    protected function checkOldPrice(string $priceCode) : bool
    {
        return $this->params['DISCOUNT_PRICE'] && $this->params['DISCOUNT_PRICE'] === $priceCode;
    }

    public function showPopup(): static
    {
        $popupClasses = ['price__popup'];
        if ($this->isDetailPage()) {
            $popupClasses[] = 'price__popup--detail';
        }
        $popupClasses = trim(implode(' ', $popupClasses));
        ?>
        <div class="<?=$popupClasses;?>">
            <div class="price__popup-inner">
                <?$this->showTable();?>
            </div>

            <?if ($this->isShowPopupDetailButton()):?>
                <div class="price__popup-bottom pt pt--16">
                    <?$this->showPopupDetailButton();?>
                </div>
            <?endif;?>
        </div>
        <?php

        return $this;
    }

    protected function getVatMessage(): string
    {
        $vatRate = $this->item['PRODUCT']['VAT_RATE'];
        $isVatIncluded = $this->params['PRICE_VAT_INCLUDE'];

        if ($vatRate <= 0) {
            return Loc::getMessage('VAT_NOT_INCLUDED');
        }

        if ($isVatIncluded) {
            return Loc::getMessage('VAT_INCLUDED', ['#VAT_RATE#' => $vatRate]);
        }

        return Loc::getMessage('VAT_DIFF_WARNING', ['#VAT_RATE#' => $vatRate]);
    }

    protected function showVat(): void
    {
        echo '<div class="vat font_13 color_555 mt mt--8 ">' . $this->getVatMessage() . '</div>';
    }


    public function capture(): string
    {
        $html = '';
        if ($this->item['PRODUCT_ANALOG']) {
            $html = Price::formatEmptyPriceWithSchema([
                'SHOW_SCHEMA' => $this->isShowSchema(),
            ]);

            return $html;
        }

        $html .= '<div class="hidden"></div>';

        if ($this->isFilled()) {
            $pricesClasses = ['prices'];
            if ($this->isWithTable()) {
                $pricesClasses[] = 'prices--with-table';
                $pricesClasses[] = $this->isWithPopupTable() ? 'prices--with-popup-table' : 'prices--with-inline-table';
            }
            $pricesClasses = trim(implode(' ', $pricesClasses));

            ob_start();
            ?>
            <?if (strlen($this->options['WRAPPER_CLASS'])):?>
                <div class="<?=$this->options['WRAPPER_CLASS'];?>">
            <?endif;?>

            <div class="<?=$pricesClasses;?>">
                <?if ($this->isWithInlineTable()):?>
                    <?$this->showTable();?>
                <?else:?>
                    <?$this->showRow($this->currentPrice, $this->isWithPopupTable());?>
                <?endif;?>
                <?if($this->options['DISPLAY_VAT_INFO'] === 'Y'):?>
                    <?$this->showVat();?>
                <?endif;?>
                <?Include\Component::bonusesShow(params: ['ID' => $this->item['ID']]);?>
            </div>

            <?if (strlen($this->options['WRAPPER_CLASS'])):?>
                </div>
            <?endif;?>

            <?php
            $html = trim(ob_get_clean());
        }
        $this->resolveWhenNullOrMissingPrice($html);

        $this->resolvePopupTemplate($html);

        return $html;
    }

    protected function isShowPopupPriceTemplate()
    {
        return array_key_exists('SHOW_POPUP_PRICE_TEMPLATE', $this->options)
            && $this->options['SHOW_POPUP_PRICE_TEMPLATE'] === 'Y';
    }

    protected function resolvePopupTemplate(string &$html)
    {
        if (!$this->isShowPopupPriceTemplate()) {
            return;
        }

        if ($this->isWithPopupTable() || $this->isPopupTemplateCaptured) {
            return;
        }

        $this->isPopupTemplateCaptured = true;

        $stashedParams = $this->params;

        $this->params['SHOW_POPUP_PRICE'] = 'Y';
        $this->params['SHOW_SCHEMA'] = false;

        $html .= $this->captureWithPopupTemplate();

        $this->params = $stashedParams;
        unset($stashedParams);
    }

    protected function captureWithPopupTemplate(): string
    {
        return '<!-- noindex --><div class="template-popup-price" hidden aria-hidden="true">'.$this->capture().'</div><!-- /noindex -->';
    }

    private function resolveWhenNullOrMissingPrice(&$html)
    {
        if ($this->hasOffers()) {
            return;
        }

        $missingGoodsPriceDisplay = Solution::GetFrontParametrValue('MISSING_GOODS_PRICE_DISPLAY');
        $arConfig = $this->options;

        $missingGoodsPriceDisplayText = '<div class="price '.$arConfig['PRICE_BLOCK_CLASS'].'"><div class="price__new"><span class="price__new-val font_'.$arConfig['PRICE_FONT'].'">'.Solution::GetFrontParametrValue('MISSING_GOODS_PRICE_TEXT').'</span></div></div>';

        if ($this->isFilled()) {
            if (!$this->isGreaterThanZero()) {
                if ($missingGoodsPriceDisplay === 'NOTHING') {
                    $html = '<div class="hidden"></div>';
                    $html .= Price::formatEmptyPriceWithSchema([
                        'SHOW_SCHEMA' => $this->isShowSchema(),
                    ]);
                }
                if ($missingGoodsPriceDisplay === 'TEXT') {
                    $html = $missingGoodsPriceDisplayText;
                    $html .= Price::formatEmptyPriceWithSchema([
                        'SHOW_SCHEMA' => $this->isShowSchema(),
                    ]);
                }
            }
        } elseif ($missingGoodsPriceDisplay === 'TEXT') {
            $html = $missingGoodsPriceDisplayText;
            $html .= Price::formatEmptyPriceWithSchema([
                'SHOW_SCHEMA' => $this->isShowSchema(),
            ]);
        } else {
            $html .= Price::formatEmptyPriceWithSchema([
                'SHOW_SCHEMA' => $this->isShowSchema(),
            ]);
        }
    }

    public function show(): static
    {
        echo $this->capture();

        return $this;
    }

    public static function getCurrency(string $currencyId): array
    {
        static $arCurrencies;

        if (!isset($arCurrencies)) {
            $arCurrencies = [];
        }

        if (
            !$currencyId
            || !Loader::includeModule('currency')
        ) {
            return [];
        }

        if (!isset($arCurrencies[$currencyId])) {
            $arCurrencies[$currencyId] = \CCurrency::GetByID($currencyId);
        }

        return $arCurrencies[$currencyId];
    }

    public static function fixOffersMinPrice(&$arOffers, array $arParams = [], ?bool $bUseCount = null, ?int $showCount = null)
    {
        if (!isset($bUseCount)) {
            if (
                array_key_exists('USE_PRICE_COUNT', $arParams)
            ) {
                $bUseCount = $arParams['USE_PRICE_COUNT'] !== false && $arParams['USE_PRICE_COUNT'] !== 'N';
            }
        }

        if (!isset($showCount)) {
            $showCount = 1;

            if (
                $bUseCount
                && array_key_exists('SHOW_PRICE_COUNT', $arParams)
            ) {
                $tmp = intval($arParams['SHOW_PRICE_COUNT'] ?? 1);
                if ($tmp > 1) {
                    $showCount = $tmp;
                }
            }
        }

        if (
            $arOffers
            && is_array($arOffers)
            && $bUseCount
            && $showCount > 1
        ) {
            foreach ($arOffers as &$arOffer) {
                $offerPrices = new static(
                    $arOffer,
                    $arParams
                );

                $arOffer['MIN_PRICE'] = $offerPrices->getCurrentPrice();
            }
            unset($arOffer);
        }
    }
}
