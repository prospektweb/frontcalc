<?
namespace Aspro\Premier\Product;

use Bitrix\Main\Loader,
	Bitrix\Main\Localization\Loc;

use CPremier as Solution,
	Aspro\Functions\CAsproPremier as SolutionFunctions,
	CPremierRegionality as Regionality;

class Price {
	static $catalogInclude = true;

	public static function checkCatalogModule() {
		return true;
	}

	public static function check(array $arItem = []) {
		if (
			!$arItem ||
			!is_array($arItem)
		) {
			return false;
		}

		if (isset($arItem['PRICES']) && $arItem['PRICES']) {
			$arPrice = self::getMinPrice($arItem['PRICES']);
			$price = $arPrice['PRICE'] ?? $arPrice['VALUE'];
			if ($price > 0) {
				return true;
			}
		}

		return false;
	}

	public static function hasPriceMatrix(array $arItem = []) {
		return !empty($arItem['PRICE_MATRIX']);
	}

	public static function getPricesByFilter(array $arPrices = [], array $arCondition = []) {
		if (
			!$arPrices ||
			!is_array($arPrices)
		) {
			return [];
		}

		if (is_array($arCondition) && $arCondition) {
			return array_filter($arPrices, function($value) use ($arCondition) {
				return array_intersect($arCondition, (array)$value) === $arCondition;
			}, ARRAY_FILTER_USE_BOTH);
		}
		return $arPrices;
	}

	public static function getCatalogPrice(array $arPrices = []) {
		if (
			!$arPrices ||
			!is_array($arPrices)
		) {
			return [];
		}

		$arPrice = self::getMinPrice($arPrices);

		return $arPrice;
	}

	public static function getMinMaxPrice(array $arPrices = [], string $type = 'min') {
		if (
			!$arPrices ||
			!is_array($arPrices)
		) {
			return [];
		}

		$arResultPrice = [];

		// type
		$type = $type === 'max' ? 'max' : 'min';

		// only accessible prices
		$arPrices = array_filter($arPrices, function($arPrice) {
			// !isset() is fallback for custom prices
			return !isset($arPrice['CAN_ACCESS']) || $arPrice['CAN_ACCESS'] === 'Y';
		});

		// optimized
		if ($type === 'min') {
			// search minPrice
			foreach ($arPrices as $i => $arPrice) {
				// !isset() is fallback for custom prices
				if (!isset($arPrice['CAN_BUY']) || $arPrice['CAN_BUY'] === 'Y') {
					if (isset($arPrice['MIN_PRICE']) && $arPrice['MIN_PRICE'] === 'Y') {
						$arResultPrice = $arPrices[$i];
					}
				}
			}
		}

		if (!$arResultPrice) {
			// convert currency
			$bConvertCurrency = Solution::getFrontParametrValue('CONVERT_CURRENCY') != 'N';
			$convertCurrencyId = Solution::getFrontParametrValue('CURRENCY_ID');
			$convertCurrency = null;

			if (
				$bConvertCurrency &&
				$convertCurrencyId &&
				Loader::includeModule('currency')
			) {
				$arCurrency = \CCurrency::GetByID($convertCurrencyId);
				if ($arCurrency && is_array($arCurrency)) {
					$convertCurrency = $arCurrency['CURRENCY'];
				}
			}

			$resultPrice = 0;
			foreach ($arPrices as $i => $arPrice) {
				if (!isset($arPrice['CAN_BUY']) || $arPrice['CAN_BUY'] === 'Y') {
					// isset() is fallback for custom prices
					$comparePrice = $arPrice['DISCOUNT_VALUE'] ?? $arPrice['VALUE'];

					if (
						$convertCurrency &&
						isset($arPrice['CURRENCY']) &&
						$arPrice['CURRENCY'] &&
						$arPrice['CURRENCY'] != $convertCurrency
					) {
						$comparePrice = \CCurrencyRates::ConvertCurrency($comparePrice, $arPrice['CURRENCY'], $convertCurrency);
					}

					if (
						!$resultPrice ||
						($type === 'min' && $resultPrice > $comparePrice) ||
						($type === 'max' && $resultPrice < $comparePrice)
					) {
						$resultPrice = $comparePrice;
						$arResultPrice = $arPrices[$i];
					}
				}
			}
		}

		return $arResultPrice;
	}

	public static function getMinPrice(array $arPrices = []) {
	   return self::getMinMaxPrice($arPrices, 'min');
	}

	public static function getMaxPrice(array $arPrices = []) {
		return self::getMinMaxPrice($arPrices, 'max');
	}

	/**
	 * use for offers without catalog
	 */
	public static function getPriceTypeFromOffersProperties(array $arOptions = []) {
		$arDefaultOptions = [
			'OFFERS' => [],
			'TYPE' => 'max',
			'STATIC' => false,
		];
		$arConfig = array_merge($arDefaultOptions, $arOptions);

		$listTypes = ['max', 'min'];
		if (!$arConfig['OFFERS'] || !in_array($arConfig['TYPE'], $listTypes)) {
			return ['VALUE' => 0, 'CURRENCY' => ''];
		}

		static $arPrices;
		if (!isset($arPrices) || !$arConfig['STATIC']) {
			$arPrices = array_map(fn($arFields) => [
				'VALUE' => $arFields['DISPLAY_PROPERTIES']['FILTER_PRICE']['VALUE'],
				'CURRENCY' => $arFields['DISPLAY_PROPERTIES']['PRICE_CURRENCY']['VALUE_XML_ID'],
			], $arConfig['OFFERS']);
		}

		return $arConfig['TYPE'] === 'min' ? static::getMinPrice($arPrices) : static::getMaxPrice($arPrices);
	}

	public static function getPriceFromOffersExt(array &$offers, array $arOptions) {
		$arDefaultOptions = [
			'REPLACE_PRICE' => true,
			'CURRENCY' => '',
			'IS_PRICE_MIN' => true,
		];
		$arConfig = array_merge($arDefaultOptions, $arOptions);
		$replacePrice = $arConfig['REPLACE_PRICE'];
		$currency = $arConfig['CURRENCY'];
		$result = false;
		$minPrice = 0;

		if (!$currency) {
			$currency = \Bitrix\Currency\CurrencyManager::getBaseCurrency();
		}

		if (!empty($offers) && is_array($offers)) {
			$doubles = [];
			foreach ($offers as $oneOffer) {
				if (!$oneOffer["MIN_PRICE"]) {
					$offerPrices = new Prices(
						$oneOffer
					);

					$oneOffer['MIN_PRICE'] = $offerPrices->getCurrentPrice();
				}

				if (!$oneOffer["MIN_PRICE"]) continue;
				$oneOffer['ID'] = (int)$oneOffer['ID'];

				if (isset($doubles[$oneOffer['ID']])) continue;
				if (!$oneOffer['CAN_BUY']) continue;

				\CIBlockPriceTools::setRatioMinPrice($oneOffer, $replacePrice);

				$oneOffer['MIN_PRICE']['CATALOG_MEASURE_RATIO'] = $oneOffer['CATALOG_MEASURE_RATIO'];
				$oneOffer['MIN_PRICE']['CATALOG_MEASURE'] = $oneOffer['CATALOG_MEASURE'];
				$oneOffer['MIN_PRICE']['CATALOG_MEASURE_NAME'] = $oneOffer['CATALOG_MEASURE_NAME'];
				$oneOffer['MIN_PRICE']['~CATALOG_MEASURE_NAME'] = $oneOffer['~CATALOG_MEASURE_NAME'];
				if (empty($result)) {
					$minPrice = $oneOffer['MIN_PRICE']['CURRENCY'] == $currency
						? $oneOffer['MIN_PRICE']['DISCOUNT_VALUE']
						: \CCurrencyRates::ConvertCurrency($oneOffer['MIN_PRICE']['DISCOUNT_VALUE'], $oneOffer['MIN_PRICE']['CURRENCY'], $currency);

					$result = $oneOffer['MIN_PRICE'];
				} else {
					$comparePrice = $oneOffer['MIN_PRICE']['CURRENCY'] == $currency
						? $oneOffer['MIN_PRICE']['DISCOUNT_VALUE']
						: \CCurrencyRates::ConvertCurrency($oneOffer['MIN_PRICE']['DISCOUNT_VALUE'], $oneOffer['MIN_PRICE']['CURRENCY'], $currency);

					$bCompareCondition = $arConfig['IS_PRICE_MIN']
						? $minPrice > $comparePrice // min price condition
						: $minPrice < $comparePrice; // max price condition
					if (
						$bCompareCondition
						// && $oneOffer['MIN_PRICE']['CAN_BUY'] == 'Y'
					) {
						$minPrice = $comparePrice;
						$result = $oneOffer['MIN_PRICE'];
					}
				}

				$doubles[$oneOffer['ID']] = true;
			}
		}

		//add CAN_ACCESS for TSolution\Product\Price::check
		if ($result && is_array($result)) {
			if ($result['VALUE']) {
				$result['PRINT_DISCOUNT_VALUE'] = self::addFromTextBeforePrice($result['PRINT_DISCOUNT_VALUE']);
			}
			if (!isset($result['CAN_ACCESS'])) {
				$result['CAN_ACCESS'] = 'Y';
			}
		}
		return $result;
	}

	public static function getMinPriceFromOffersExt(&$offers, $currency = '', $replaceMinPrice = true) {
		return static::getPriceFromOffersExt($offers, [
			'CURRENCY' => $currency,
			'REPLACE_PRICE' => $replaceMinPrice,
			'IS_PRICE_MIN' => true,
		]);
	}

	public static function getMaxPriceFromOffersExt(&$offers, $currency = '', $replaceMinPrice = true) {
		return static::getPriceFromOffersExt($offers, [
			'CURRENCY' => $currency,
			'REPLACE_PRICE' => $replaceMinPrice,
			'IS_PRICE_MIN' => false,
		]);
	}

	public static function addFromTextBeforePrice($price) {
		if ($price) {
			return Loc::getMessage('PRICE_FROM').$price;
		}
	}

	public static function getPricesID(array $arPricesID = [], bool $bUsePriceCode = false) {
        $arPriceIDs = array();
        if ($arPricesID) {
            global $USER;
            $arUserGroups = $USER->GetUserGroupArray();

             if (!is_array($arUserGroups) && (int)$arUserGroups.'|' == (string)$arUserGroups.'|')
                $arUserGroups = array((int)$arUserGroups);

            if (!is_array($arUserGroups))
                $arUserGroups = array();

            if (!in_array(2, $arUserGroups))
                $arUserGroups[] = 2;
            \Bitrix\Main\Type\Collection::normalizeArrayValuesByInt($arUserGroups);

            $cacheKey = 'U'.implode('_', $arUserGroups).implode('_', $arPricesID);
            if (!isset($priceTypeCache[$cacheKey])) {
                if($bUsePriceCode)
                {
                    $dbPriceType = \CCatalogGroup::GetList(
                        array("SORT" => "ASC"),
                        array("NAME" => $arPricesID)
                        );
                    while($arPriceType = $dbPriceType->Fetch())
                    {
                        $arPricesID[] = $arPriceType["ID"];
                    }
                }
                $priceTypeCache[$cacheKey] = array();
                $priceIterator = \Bitrix\Catalog\GroupAccessTable::getList(array(
                    'select' => array('CATALOG_GROUP_ID'),
                    'filter' => array('@GROUP_ID' => $arUserGroups, 'CATALOG_GROUP_ID' => $arPricesID, 'ACCESS' => array(\Bitrix\Catalog\GroupAccessTable::ACCESS_BUY, \Bitrix\Catalog\GroupAccessTable::ACCESS_VIEW)),
                    'order' => array('CATALOG_GROUP_ID' => 'ASC')
                ));
                while ($priceType = $priceIterator->fetch())
                {
                    $priceTypeId = (int)$priceType['CATALOG_GROUP_ID'];
                    $priceTypeCache[$cacheKey][$priceTypeId] = $priceTypeId;
                    unset($priceTypeId);
                }
                unset($priceType, $priceIterator);
            }
            $arPriceIDs = $priceTypeCache[$cacheKey];
        }
        return $arPriceIDs;
    }

	public static function formatWithSchemaByTypes(array $arOptions = []) {
		$arDefaultOptions = [
			'PRICE' => [],
			'SHOW_SCHEMA' => true,
			'FIELD' => 'DISCOUNT_VALUE',
			'CATALOG_MEASURE' => '',
			'MEASURE' => '',
		];
		$arOptions = array_merge($arDefaultOptions, $arOptions);

		$strPrice = '';

		if (is_array($arOptions['PRICE']) && $arOptions['PRICE']) {
			$price = $arOptions['PRICE'][$arOptions['FIELD']];
			$strPrice = $arOptions['PRICE']['PRINT_'.$arOptions['FIELD']];

			$strMeasure = '';
			if ($arOptions['MEASURE']) {
				$strMeasure = $arOptions['MEASURE'].'/';
			}
			elseif ($arOptions['CATALOG_MEASURE']) {
				$strMeasure = Common::showMeasure(Common::getMeasureById($arOptions['CATALOG_MEASURE']));
			}
			$strPrice .= $strMeasure;

			if ($arOptions['SHOW_SCHEMA']) {
				if ($price || $price == 0) {
					$strPrice .= '<meta itemprop="price" content="'.$price.'">';
				}

				if ($arOptions['PRICE']['CURRENCY']) {
					$strPrice .= '<meta itemprop="priceCurrency" content="'.$arOptions['PRICE']['CURRENCY'].'">';
				}
			}
		}

		return $strPrice;
	}

	public static function formatWithSchemaByProps(string $strPrice = '', bool $bShowSchema = true, array $arElementProps = []){
		if (strlen($strPrice = trim($strPrice))) {
			if (
				isset($arElementProps["PRICE_CURRENCY"]) &&
				$arElementProps["PRICE_CURRENCY"]["VALUE_XML_ID"] != NULL
			) {
				$strPrice = str_replace('#CURRENCY#', $arElementProps["PRICE_CURRENCY"]["VALUE"], $strPrice);
			}

			if (
				isset($arElementProps["FILTER_PRICE"]) &&
				$arElementProps["FILTER_PRICE"]["VALUE"] !== '' &&
				$arElementProps["FILTER_PRICE"]["VALUE"] >= 0 &&
				$bShowSchema
			) {
				$strPrice.= '<meta itemprop="price" content="'.$arElementProps["FILTER_PRICE"]["VALUE"].'">';
			}

			if (
				isset($arElementProps["PRICE_CURRENCY"]) &&
				$arElementProps["PRICE_CURRENCY"]["VALUE_XML_ID"] != NULL
			) {
				if ($bShowSchema) {
					$strPrice.= '<meta itemprop="priceCurrency" content="'.$arElementProps["PRICE_CURRENCY"]["VALUE_XML_ID"].'">';
				}
			}
			else {
				$arCur = array(
					'$' => 'USD',
					GetMessage('PREMIER_CUR_EUR1') => 'EUR',
					GetMessage('PREMIER_CUR_RUB1') => 'RUB',
					GetMessage('PREMIER_CUR_RUB2') => 'RUB',
					GetMessage('PREMIER_CUR_UAH1') => 'UAH',
					GetMessage('PREMIER_CUR_UAH2') => 'UAH',
					GetMessage('PREMIER_CUR_RUB3') => 'RUB',
					GetMessage('PREMIER_CUR_RUB4') => 'RUB',
					GetMessage('PREMIER_CUR_RUB5') => 'RUB',
					GetMessage('PREMIER_CUR_RUB6') => 'RUB',
					GetMessage('PREMIER_CUR_RUB3') => 'RUB',
					GetMessage('PREMIER_CUR_UAH3') => 'UAH',
					GetMessage('PREMIER_CUR_RUB5') => 'RUB',
					GetMessage('PREMIER_CUR_UAH6') => 'UAH',
				);

				foreach ($arCur as $curStr => $curCode) {
					if (strpos($strPrice, $curStr) !== false) {
						$priceVal = str_replace($curStr, '', $strPrice);
						if ($bShowSchema) {
							return str_replace(array($curStr, $priceVal), array('<span class="currency" itemprop="priceCurrency" content="'.$curCode.'">'.$curStr.'</span>', '<span itemprop="price" content="'.$priceVal.'">'.$priceVal.'</span>'), $strPrice);
						}
						else {
							return str_replace(array($curStr, $priceVal), array('<span class="currency">'.$curStr.'</span>', '<span>'.$priceVal.'</span>'), $strPrice);
						}
					}
				}
			}
		}

		return $strPrice;
	}

    public static function formatEmptyPriceWithSchema(array $arOptions = []){
        $arDefaultOptions = [
		    'SHOW_SCHEMA' => true,
        ];
        $strPrice = '';
        $currencyDefault = \Bitrix\Main\Config\Option::get("sale", "default_currency");
        $currency = Solution::GetFrontParametrValue('CONVERT_CURRENCY') === 'Y' ? Solution::GetFrontParametrValue('CURRENCY_ID') :  $currencyDefault;

        if ($arOptions['SHOW_SCHEMA']) {
            $strPrice = '<meta itemprop="price" content="0">';
            $strPrice .= '<meta itemprop="priceCurrency" content="'.$currency.'">';
		}
        return $strPrice;
    }

	public static function getDiscountByItemID($item_id = 0) {
		$arDiscount = [];
		if($item_id) {
			global $USER;
			$arUserGroups = $USER->GetUserGroupArray();
			$arDiscounts = \CCatalogDiscount::GetDiscountByProduct($item_id, $arUserGroups, "N", array(), SITE_ID);
			if ($arDiscounts) {
				$arDiscount=current($arDiscounts);
			}
		}
		return $arDiscount;
	}
}
