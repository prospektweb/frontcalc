<?php

namespace Aspro\Premier\Product;

use Aspro\Functions\CAsproPremier as SolutionFunctions;
use Aspro\Premier\Utils as SolutionUtils;
use Bitrix\Main\Config\Option;
use CPremier as Solution;

class Basket
{
    public static $catalogInclude;
    public static $btnClasses = [
        'BTN_CLASS' => 'btn-sm btn-wide',
        'BTN_IN_CART_CLASS' => 'btn-sm btn-wide',
        'BTN_CLASS_MORE' => 'btn-sm',
        'BTN_CLASS_SUBSCRIBE' => 'btn-wide btn-transparent',
        'BTN_ORDER_CLASS' => 'btn-sm btn-wide btn-transparent-bg',
        'BTN_CALLBACK_CLASS' => 'btn-sm btn-transparent-bg',
        'BTN_OCB_CLASS' => 'btn-sm btn-wide btn-transparent-bg',
        'TO_BASKET_LINK_CLASS' => 'btn-sm',
    ];

    public const ADD = 'ADD';
    public const MORE = 'MORE';
    public const SUBSCRIBE = 'SUBSCRIBE';
    public const ORDER = 'ORDER';

    private static $productData = [
        'TOTAL_COUNT' => 0,
        'PRICE' => null,
    ];

    public static function checkCatalogModule()
    {
        if (self::$catalogInclude === null) {
            self::$catalogInclude = Solution::isSaleMode();
        }
    }

    public static function getConfig()
    {
        static $arAddToBasketOptions;
        if($arAddToBasketOptions === null) {
            $arAddToBasketOptions = [
                'SHOW_BASKET_ONADDTOCART' => Option::get(Solution::moduleID, 'SHOW_BASKET_ONADDTOCART', 'Y', SITE_ID) == 'Y',
                'BUYNOPRICEGGOODS' => Option::get(Solution::moduleID, 'BUYNOPRICEGGOODS', 'NOTHING', SITE_ID),
                'BUYMISSINGGOODS' => Option::get(Solution::moduleID, 'BUYMISSINGGOODS', self::ADD, SITE_ID),
                'EXPRESSION_ORDER_BUTTON' => Option::get(Solution::moduleID, 'EXPRESSION_ORDER_BUTTON', GetMessage('EXPRESSION_ORDER_BUTTON_DEFAULT'), SITE_ID),
                'EXPRESSION_ORDER_TEXT' => Option::get(Solution::moduleID, 'EXPRESSION_ORDER_TEXT', GetMessage('EXPRESSION_ORDER_TEXT_DEFAULT'), SITE_ID),
                'EXPRESSION_SUBSCRIBE_BUTTON' => Option::get(Solution::moduleID, 'EXPRESSION_SUBSCRIBE_BUTTON', GetMessage('EXPRESSION_SUBSCRIBE_BUTTON_DEFAULT'), SITE_ID),
                'EXPRESSION_SUBSCRIBED_BUTTON' => Option::get(Solution::moduleID, 'EXPRESSION_SUBSCRIBED_BUTTON', GetMessage('EXPRESSION_SUBSCRIBED_BUTTON_DEFAULT'), SITE_ID),
                'EXPRESSION_ADDTOBASKET_BUTTON' => Option::get(Solution::moduleID, 'EXPRESSION_ADDTOBASKET_BUTTON_DEFAULT', GetMessage('EXPRESSION_ADDTOBASKET_BUTTON_DEFAULT'), SITE_ID),
                'EXPRESSION_ADDEDTOBASKET_BUTTON' => Option::get(Solution::moduleID, 'EXPRESSION_ADDEDTOBASKET_BUTTON_DEFAULT', GetMessage('EXPRESSION_ADDEDTOBASKET_BUTTON_DEFAULT'), SITE_ID),
                'EXPRESSION_READ_MORE_OFFERS' => Option::get(Solution::moduleID, 'EXPRESSION_READ_MORE_OFFERS_DEFAULT', GetMessage('EXPRESSION_READ_MORE_OFFERS_DEFAULT'), SITE_ID),
                'EXPRESSION_ONE_CLICK_BUY' => Option::get(Solution::moduleID, 'EXPRESSION_ONE_CLICK_BUY', GetMessage('EXPRESSION_ONE_CLICK_BUY_DEFAULT'), SITE_ID),
                'EXPRESSION_PRODUCT_ANALOG_FILTER' => Option::get(Solution::moduleID, 'EXPRESSION_PRODUCT_ANALOG_FILTER', GetMessage('EXPRESSION_PRODUCT_ANALOG_FILTER_DEFAULT'), SITE_ID),
                'IS_AUTORIZED' => $GLOBALS['USER']->IsAuthorized(),
            ];

            if (!strlen($arAddToBasketOptions['EXPRESSION_ORDER_BUTTON'])) {
                $arAddToBasketOptions['EXPRESSION_ORDER_BUTTON'] = GetMessage('EXPRESSION_ORDER_BUTTON_DEFAULT');
            }
            if (!strlen($arAddToBasketOptions['EXPRESSION_SUBSCRIBE_BUTTON'])) {
                $arAddToBasketOptions['EXPRESSION_SUBSCRIBE_BUTTON'] = GetMessage('EXPRESSION_SUBSCRIBE_BUTTON_DEFAULT');
            }
            if (!strlen($arAddToBasketOptions['EXPRESSION_SUBSCRIBED_BUTTON'])) {
                $arAddToBasketOptions['EXPRESSION_SUBSCRIBED_BUTTON'] = GetMessage('EXPRESSION_SUBSCRIBED_BUTTON_DEFAULT');
            }
            if (!strlen($arAddToBasketOptions['EXPRESSION_READ_MORE_OFFERS'])) {
                $arAddToBasketOptions['EXPRESSION_READ_MORE_OFFERS'] = GetMessage('EXPRESSION_READ_MORE_OFFERS_DEFAULT');
            }
        }

        return $arAddToBasketOptions;
    }

    /**
     * @param array $arOptions
     *
     * @var 'TYPE'               => 'catalog-block'
     * @var 'WRAPPER'            => false
     * @var 'WRAPPER_CLASS'      => '',
     * @var 'BASKET'             => false,
     * @var 'DETAIL_PAGE'        => false,
     * @var 'ORDER_BTN'          => false,
     * @var 'ONE_CLICK_BUY'      => false,
     * @var 'QUESTION_BTN'       => false,
     * @var 'DISPLAY_COMPARE'    => false,
     * @var 'INFO_BTN_ICONS'     => false,
     * @var 'SHOW_COUNTER'       => true,
     * @var 'RETURN'             => false,
     * @var 'JS_CLASS'           => false,
     * @var 'BASKET_URL'         => SITE_DIR.'cart/',
     * @var 'BTN_CLASS'          => 'btn-md btn-transparent-bg',
     * @var 'BTN_IN_CART_CLASS'  => 'btn-md',
     * @var 'BTN_CLASS_MORE'     => '',
     * @var 'BTN_CALLBACK_CLASS' => 'btn-sm btn-transparent-bg',
     * @var 'BTN_OCB_CLASS'      => 'btn-sm btn-transparent',
     * @var 'ORDER_FORM_ID'      => 'aspro_premier_order_product',
     * @var 'ITEM'               => [],
     * @var 'PARAMS'             => [],
     * @var 'TOTAL_COUNT'        => 0,
     * @var 'SHOW_MORE'          => false,
     * @var 'CONFIG'             => [],
     * @var 'JS_EVENT'           => Y,
     * @var 'SHOW_NOTIFICATION'  => Y,
     * @var 'SHOW_BASKET_LINK'   => N,
     *
     * @return array [
     *               'HTML' => $basketHTML,
     *               'CAN_BUY' => can buy
     *               'ACTION' => $basketButton
     *               ]
     */
    public static function getOptions($arOptions = [])
    {
        $arAddToBasketOptions = self::getConfig();
        self::checkCatalogModule();

        $arDefaultOptions = array_merge(
            [
                'TYPE' => 'catalog-block',
                'WRAPPER' => false,
                'WRAPPER_CLASS' => '',
                'BASKET' => false,
                'DETAIL_PAGE' => false,
                'ORDER_BTN' => false,
                'ONE_CLICK_BUY' => false,
                'QUESTION_BTN' => false,
                'DISPLAY_COMPARE' => false,
                'INFO_BTN_ICONS' => false,
                'SHOW_COUNTER' => true,
                'RETURN' => false,
                'JS_CLASS' => false,
                'BASKET_URL' => SITE_DIR.'basket/',
                'ORDER_FORM_ID' => 'aspro_premier_order_product',
                'ONE_CLICK_BUY_FORM_ID' => 'aspro_premier_quick_buy',
                'ITEM' => [],
                'IS_OFFER' => false,
                'PARAMS' => [],
                'TOTAL_COUNT' => 0,
                'SHOW_MORE' => false,
                'TARGET' => '',
                'NOINDEX' => false,
                'CONFIG' => $arAddToBasketOptions,
                'JS_EVENT' => 'Y',
                'SHOW_NOTIFICATION' => 'Y',
                'SHOW_BASKET_LINK' => 'N',
                'OUTER_BUTTON' => 'N',
            ],
            self::$btnClasses
        );
        $arConfig = array_merge($arDefaultOptions, $arOptions);

        if ($handler = SolutionFunctions::getCustomFunc(__FUNCTION__)) {
            return call_user_func_array($handler, [$arConfig]);
        }

        $arConfig['CAN_BUY'] = self::getCanBuy($arConfig);
        if (!array_key_exists('HAS_PRICE', $arConfig)) {
            $prices = self::getPrices($arConfig);

            $arConfig['HAS_PRICE'] = $prices->isGreaterThanZero();
            $arConfig['EMPTY_PRICE'] = $prices->isEmpty();
        }

        $bOrderViewBasket = $arConfig['BASKET'] !== false && $arConfig['BASKET'] !== 'N' && $arConfig['BASKET'] !== 'false';
        $bShowAnalogProduct = array_key_exists('PRODUCT_ANALOG', $arConfig['ITEM']) && $arConfig['ITEM']['PRODUCT_ANALOG'];
        $bShowAnalogProductFilter = $bShowAnalogProduct && array_key_exists('PRODUCT_ANALOG_FILTER', $arConfig['ITEM']) && strlen(trim($arConfig['ITEM']['PRODUCT_ANALOG_FILTER']));

        $arConfig['ONE_CLICK_BUY'] = $arConfig['ONE_CLICK_BUY'] === true || $arConfig['ONE_CLICK_BUY'] === 'true';

        $basketButton = $buttonACTION = '';
        if ($bShowAnalogProduct && ($bShowAnalogProductFilter || $arConfig['DETAIL_PAGE'])) {
            if ($bShowAnalogProductFilter) {
                $basketButton = static::getProductAnalogFilterLink($arConfig);
            }
        } elseif ($bOrderViewBasket) {
            if ($arConfig['HAS_PRICE']) {
                if (self::canBuyWithQuantity($arConfig['CAN_BUY'], $arConfig['TOTAL_COUNT'])) {
                    $buttonACTION = self::ADD;
                    $basketButton = self::getToCartButton($arConfig);
                } else {
                    if ($arConfig['SHOW_MORE']) {
                        $buttonACTION = self::MORE;
                        $basketButton = self::getMoreButton($arConfig);
                    } else {
                        $buttonACTION = $arConfig['CONFIG']['BUYMISSINGGOODS'];
                        if (self::isBuyMissingGoodsOptionEqualToCart($arConfig['CONFIG'])) {
                            if (self::canBuyMissingGoods($arConfig)) {
                                $buttonACTION = self::ADD;
                                $basketButton = self::getToCartButton($arConfig);
                            } else {
                                if ($arConfig['CONFIG']['BUYMISSINGGOODS'] === self::SUBSCRIBE && $arConfig['ITEM']['CATALOG_SUBSCRIBE'] === 'Y') {
                                    $basketButton = self::getSubsribeButton($arConfig);
                                } else {
                                    $basketButton = self::getOrderButton($arConfig);
                                }
                            }
                        } elseif ($arConfig['CONFIG']['BUYMISSINGGOODS'] == self::SUBSCRIBE && $arConfig['ITEM']['CATALOG_SUBSCRIBE'] == 'Y') {
                            $basketButton = self::getSubsribeButton($arConfig);
                        } elseif ($arConfig['CONFIG']['BUYMISSINGGOODS'] == self::ORDER) {
                            $basketButton = self::getOrderButton($arConfig);
                        }
                    }
                }
            } else {
                if ($arConfig['SHOW_MORE']) {
                    $buttonACTION = self::MORE;
                    $basketButton = self::getMoreButton($arConfig);
                } else {
                    $buttonACTION = $arConfig['CONFIG']['BUYNOPRICEGGOODS'];
                    if($buttonACTION == self::ORDER) {
                        $basketButton = self::getOrderButton($arConfig);
                    }
                }
            }
        } else {
            $buttonACTION = self::ORDER;
            $basketButton = self::getOrderButton($arConfig);
        }

        if (self::isShopWindowMode()) {
            $arInfo = self::showMarketUrl([
                'config' => $arConfig,
                'basketButton' => $basketButton,
                'buttonACTION' => $buttonACTION,
            ]);
            $basketButton = $arInfo['basketButton'];
            $buttonACTION = $arInfo['buttonACTION'];
            $arConfig = $arInfo['config'];
        }

        if ($basketButton) {?>
            <?ob_start(); ?>
                <?if ($arConfig['JS_CLASS']):?>
                    <div class="<?= $arConfig['JS_CLASS']; ?>">
                <?endif; ?>
                <?if ($arConfig['WRAPPER']):?>
                    <div class="footer-button btn-actions<?= $arConfig['INFO_BTN_ICONS'] ? ' btn-actions--with-icons' : ''; ?> <?= $arConfig['WRAPPER_CLASS']; ?>">
                <?endif; ?>
                <?= $basketButton; ?>
                <?if ($arConfig['WRAPPER']):?>
                </div>
                <?endif; ?>
                <?if ($arConfig['JS_CLASS']):?>
                </div>
                <?endif; ?>
            <?$basketButton = ob_get_contents();
            ob_end_clean(); ?>
        <?}

        $basketButton = trim($basketButton);

        return [
            'HTML' => $basketButton,
            'CAN_BUY' => $arConfig['CAN_BUY'],
            'ACTION' => $buttonACTION,
        ];
    }

    public static function setProductData(array $arData = [])
    {
        foreach ($arData as $key => $value) {
            if (array_key_exists($key, self::$productData)) {
                self::$productData[$key] = $value;
            }
        }
    }

    public static function getProductData()
    {
        return self::$productData;
    }

    public static function canBuyWithQuantity($canBuy, $totalCount) : bool
    {
        return $canBuy && $totalCount;
    }


    public static function canBuyMissingGoods(array $arConfig)
    {
        return self::isCanBuy($arConfig) && self::isBuyMissingGoodsOptionEqualToCart($arConfig['CONFIG']);
    }

    public static function isBuyMissingGoodsOptionEqualToCart(array $arConfig) : bool
    {
       return $arConfig['BUYMISSINGGOODS'] === self::ADD /* || $arItem["CAN_BUY"] */;
    }

    public static function isCanBuy(array $arConfig)
    {
       return $arConfig['CAN_BUY'];
    }

    public static function getToCartButton($arConfig)
    {
        if ($arConfig['ADD_SERVICE'] ?? false) {
            return static::getAddServiceButton($arConfig);
        }

        $maxQuantity = 0;

        $totalCount = $arConfig['TOTAL_COUNT'];
        $quantity = $ratio = ($arConfig['ITEM']['CATALOG_MEASURE_RATIO'] ?? $arConfig['ITEM']['ITEM_MEASURE_RATIOS'][$arConfig['ITEM']['ITEM_MEASURE_RATIO_SELECTED']]['RATIO'] ?? 1);
        $bFloatRatio = is_double($ratio);

        if ($arConfig['ITEM']['CATALOG_QUANTITY_TRACE'] === 'Y') {
            if ($totalCount < $quantity) {
                $quantity = ($totalCount > $ratio ? $totalCount : $ratio);
            }

            if ($arConfig['ITEM']['CATALOG_CAN_BUY_ZERO'] !== 'Y') {
                $maxQuantity = $totalCount;
            }
        }

        $minQuantity = '';
        if(isset($arConfig['ITEM']['ITEM_PRICE_SELECTED'])) {
            $minQuantity = $arConfig['ITEM']['ITEM_PRICES'][$arConfig['ITEM']['ITEM_PRICE_SELECTED']]['MIN_QUANTITY'];
            if($quantity < $minQuantity) {
                $quantity = $minQuantity;
            }
        }

        $arItemProps = $arConfig['IS_OFFER'] ? ($arConfig['PARAMS']['OFFERS_CART_PROPERTIES'] ? implode(';', $arConfig['PARAMS']['OFFERS_CART_PROPERTIES']) : '') : ($arConfig['PARAMS']['PRODUCT_PROPERTIES'] ? implode(';', $arConfig['PARAMS']['PRODUCT_PROPERTIES']) : '');
        $addProp = $arConfig['PARAMS']['ADD_PROPERTIES_TO_BASKET'] === 'Y' ? 'Y' : 'N';
        $partProp = $arConfig['PARAMS']['PARTIAL_PRODUCT_PROPERTIES'] === 'Y' ? 'Y' : 'N';
        $emptyProp = $arConfig['ITEM']['PRODUCT_PROPERTIES'] ? 'N' : 'Y';

        // show "unsubscribe" button with "to cart" button in list of /personal/subscribe/ page
        $bShowUnsubscribe = $arConfig['PARAMS']['DISPLAY_UNSUBSCRIBE'] === 'Y' && $arConfig['ITEM']['CATALOG_SUBSCRIBE'] === 'Y';

        $html = '';
        ob_start();

        $buttonClassList = ['btn btn-default to_cart animate-load'];
        if ($arConfig['BTN_CLASS']) {
            $buttonClassList[] = $arConfig['BTN_CLASS'];
        }
        if ($arConfig['EXTERNAL_BUTTON'] === 'Y') {
            $buttonClassList[] = 'js-external-button';
        }
        if ($arConfig['JS_EVENT'] === 'Y') {
            $buttonClassList[] = 'js-item-action';
        } else {
            $buttonClassList[] = 'js-offer-modal';
        }

        $buttonClass = SolutionUtils::implodeClasses($buttonClassList);
        ?>
        <div class="buy_block btn-actions__inner">
            <?if ($arConfig['SHOW_COUNTER']):?>
                <div class="counter">
                    <div class="wrap">
                        <span class="minus ctrl bgtransition"></span>
                        <div class="input"><input type="text" value="<?= $quantity; ?>" class="count" maxlength="20" /></div>
                        <span class="plus ctrl bgtransition"></span>
                    </div>
                </div>
            <?endif; ?>
            <div class="buttons">
                <div class="line-block line-block--gap line-block--gap-8 line-block--align-normal flexbox--direction-column">
                    <div class="line-block__item btn-actions__primary-button">

                        <?if ($bShowUnsubscribe):?>
                            <div class="line-block line-block--gap line-block--gap-8 line-block--flex-wrap flexbox--direction-row">
                                <div class="line-block__item">
                        <?endif; ?>

                                    <span class="item-action item-action--basket">
                                        <button type="button" class="<?= $buttonClass; ?>" data-action="basket" data-id="<?= $arConfig['ITEM']['ID']; ?>" data-ratio="<?= $ratio; ?>" data-float_ratio="<?= $bFloatRatio; ?>" data-quantity="<?= $quantity; ?>"<?= $minQuantity && $minQuantity != $ratio ? ' data-min="'.$minQuantity.'"' : ''; ?><?= $maxQuantity ? ' data-max="'.$maxQuantity.'"' : ''; ?> data-bakset_div="bx_basket_div_<?= $arConfig['ITEM']['ID']; ?>" data-props="<?= $arItemProps; ?>" data-add_props="<?= $addProp; ?>" data-part_props="<?= $partProp; ?>" data-empty_props="<?= $emptyProp; ?>" data-offers="<?= $arConfig['IS_OFFER'] ? 'Y' : ''; ?>" title="<?= htmlspecialcharsbx($arConfig['CONFIG']['EXPRESSION_ADDTOBASKET_BUTTON']); ?>" data-title="<?= htmlspecialcharsbx($arConfig['CONFIG']['EXPRESSION_ADDTOBASKET_BUTTON']); ?>" data-title_added="<?= htmlspecialcharsbx($arConfig['CONFIG']['EXPRESSION_ADDEDTOBASKET_BUTTON']); ?>" data-notice="<?= $arConfig['SHOW_NOTIFICATION'] === 'Y'; ?>"><?= $arConfig['CONFIG']['EXPRESSION_ADDTOBASKET_BUTTON']; ?></button>
                                    </span>
                                    <div class="btn btn-default in_cart <?= $arConfig['BTN_IN_CART_CLASS']; ?>">
                                        <div class="counter js-ajax">
                                            <span class="counter__action counter__action--minus"<?= $minQuantity && $minQuantity != $ratio ? ' data-min="'.$minQuantity.'"' : ''; ?>></span>
                                            <div class="counter__count-wrapper">
                                                <input type="text" value="<?= $quantity; ?>" class="counter__count" maxlength="6">
                                            </div>
                                            <span class="counter__action counter__action--plus"<?= $maxQuantity ? ' data-max="'.$maxQuantity.'"' : ''; ?>></span>
                                        </div>
                                    </div>
                                    <div>Рассчитать стоимость</div>
                                    <?if ($arConfig['SHOW_BASKET_LINK'] === 'Y'):?>
                                        <?= static::getBasketLink($arConfig); ?>
                                    <?endif; ?>

                        <?if ($bShowUnsubscribe):?>
                                </div>
                                <?= self::getSubsribeButton($arConfig, true); ?>
                            </div>
                        <?endif; ?>

                    </div>
                    <?= self::getOneClickBuyButton($arConfig); ?>
                </div>
            </div>
        </div>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    public static function getAddServiceButton($arConfig)
    {
        $quantity = $ratio = 1;
        $bFloatRatio = false;

        $html = '';
        ob_start();
        ?>
        <div class="buy_block btn-actions__inner">
            <div class="buttons">
                <div class="line-block line-block--gap line-block--gap-8 line-block--align-normal flexbox--direction-column">
                    <div class="line-block__item">
                        <span class="item-action item-action--basket">
                            <button type="button" class="btn btn-default <?= $arConfig['BTN_CLASS']; ?> to_cart animate-load js-item-action" data-action="service" data-id="<?= $arConfig['ITEM']['ID']; ?>" data-ratio="<?= $ratio; ?>" data-float_ratio="<?= $bFloatRatio; ?>" data-quantity="<?= $quantity; ?>" data-bakset_div="bx_basket_div_<?= $arConfig['ITEM']['ID']; ?>" title="<?= htmlspecialcharsbx($arConfig['CONFIG']['EXPRESSION_ADDTOBASKET_BUTTON']); ?>" data-title="<?= htmlspecialcharsbx($arConfig['CONFIG']['EXPRESSION_ADDTOBASKET_BUTTON']); ?>" data-title_added="<?= htmlspecialcharsbx($arConfig['CONFIG']['EXPRESSION_ADDEDTOBASKET_BUTTON']); ?>"><?= $arConfig['CONFIG']['EXPRESSION_ADDTOBASKET_BUTTON']; ?></button>
                        </span>
                        <div class="btn btn-default in_cart <?= $arConfig['BTN_IN_CART_CLASS']; ?>">
                            <div class="counter js-ajax">
                                <span class="counter__action counter__action--minus"></span>
                                <div class="counter__count-wrapper">
                                    <input type="text" value="<?= $quantity; ?>" class="counter__count" maxlength="6">
                                </div>
                                <span class="counter__action counter__action--plus"></span>
                            </div>
                        </div>
                        <div>Рассчитать стоимость</div>
                    </div>
                    <?= self::getOneClickBuyButton($arConfig); ?>
                </div>
            </div>
        </div>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    protected static function getProductAnalogFilterLink(array $arConfig): string
    {
        $html = '';

        ob_start();
            ?>
            <div class="buy_block product-analog-filter btn-actions__inner">
                <div class="buttons">
                    <div class="line-block line-block--gap line-block--align-normal line-block--column">
                        <div class="line-block__item">
                            <!-- noindex -->
                            <a class="btn btn-default <?=$arConfig['BTN_CLASS_MORE'];?>" href="<?=$arConfig['ITEM']['PRODUCT_ANALOG_FILTER'];?>" rel="nofollow">
                                <?=$arConfig['CONFIG']['EXPRESSION_PRODUCT_ANALOG_FILTER'];?>
                            </a>
                            <!-- /noindex -->
                        </div>
                    </div>
                </div>
            </div>
            <?php
        $html .= ob_get_clean();

        return $html;
    }

    public static function getOrderButton($arConfig)
    {?>
        <?$html = ''; ?>
        <?ob_start(); ?>
            <div class="buy_block btn-actions__inner">
                <div class="buttons">
                    <div class="line-block line-block--gap line-block--align-normal line-block--column">
                        <div class="line-block__item">
                            <button type="button" class="btn btn-default <?= $arConfig['BTN_ORDER_CLASS']; ?> animate-load" data-event="jqm" data-param-id="<?= Solution::getFormID($arConfig['ORDER_FORM_ID']); ?>" data-autoload-product="<?= Solution::formatJsName($arConfig['ITEM']['NAME']); ?>" data-autoload-service="<?= Solution::formatJsName($arConfig['ITEM']['NAME']); ?>" data-autoload-project="<?= Solution::formatJsName($arConfig['ITEM']['NAME']); ?>" data-autoload-news="<?= Solution::formatJsName($arConfig['ITEM']['NAME']); ?>" data-autoload-sale="<?= Solution::formatJsName($arConfig['ITEM']['NAME']); ?>" data-name="order_product_<?= $arConfig['ITEM']['ID']; ?>">
                                <?= $arConfig['CONFIG']['EXPRESSION_ORDER_BUTTON']; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    public static function getOneClickBuyButton(array $arConfig)
    {
        if (!$arConfig['CONFIG']) {
            $arConfig['CONFIG'] = self::getConfig();
        }
        if (!isset($arConfig['BTN_OCB_CLASS'])) {
            $arConfig += self::$btnClasses;
        }

        $wrapperClassList = ['ocb-wrapper'];
        if ($arConfig['BTN_OCB_WRAPPER_HIDE_MOBILE'] !== 'N') {
            $wrapperClassList[] = 'hide-600';
        }
        $wrapperClass = SolutionUtils::implodeClasses($wrapperClassList);

        $buttonClassList = ['btn btn-default animate-load'];
        if ($arConfig['BTN_OCB_CLASS']) {
            $buttonClassList[] = $arConfig['BTN_OCB_CLASS'];
        }
        if($arConfig['JS_EVENT'] === 'N'){
             $buttonClassList[] = 'js-offer-modal';
        }

        $buttonClass = SolutionUtils::implodeClasses($buttonClassList);

        $html = '';
        ob_start();
        ?>
        <?if ($arConfig['ONE_CLICK_BUY']):?>
            <div class="<?=$wrapperClass; ?>">
                <button
                    type="button"
                    class="<?=$buttonClass; ?>"
                    data-event="<?= $arConfig['JS_EVENT'] === 'Y' ? 'jqm' : '' ?>"
                    data-name="ocb"
                    data-param-form_id="ocb"
                    data-id="<?=$arConfig['ITEM']['ID']?>"
                    >
                    <?= $arConfig['CONFIG']['EXPRESSION_ONE_CLICK_BUY']; ?>
                </button>
            </div>
        <?endif; ?>
        <?php
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }


    public static function getOneClickBuyIcon(array $arOptions = []): string
    {
        if(Solution::GetFrontParametrValue('ORDER_VIEW') !== 'Y') {
            return '';
        }

        $arDefaultOptions = [
            'CLASS' => 'sm',
            'JS_EVENT' => 'Y',
        ];

        $arConfig = array_merge($arDefaultOptions, $arOptions);
        self::checkCatalogModule();

        $configProduct = self::getProductData();
        $arConfig['CONFIG'] = self::getConfig();
        $arConfig['CAN_BUY'] = self::getCanBuy(array_merge($arConfig, $configProduct));

        if (!$configProduct['PRICE']->isGreaterThanZero()) {
            return '';
        }

        if (!self::canBuyWithQuantity($arConfig['CAN_BUY'], $configProduct['TOTAL_COUNT'])) {
            if ($arConfig['ITEM']['SHOW_MORE']) {
                return '';
            }

            if(!self::canBuyMissingGoods($arConfig)) {
                return '';
            }
        }

        $buttonClassList = ["ocb-button-icon btn--no-btn-appearance item-action__inner item-action__inner--{$arConfig['CLASS']} item-action__inner--sm-to-600"];
        if($arConfig['JS_EVENT'] === 'N'){
             $buttonClassList[] = 'js-offer-modal';
        }

        $html = '';
        ob_start();
            ?>
            <div class="item-action item-action--vertical item-action--ocb <?=$arConfig['ONE_CLICK_ICON_CLASSES']?>">
                <button  class="<?=SolutionUtils::implodeClasses($buttonClassList)?>"
                    type="button"
                    data-event="<?= $arConfig['JS_EVENT'] === 'Y' ? 'jqm' : '' ?>"
                    data-name="ocb"
                    data-param-form_id="ocb"
                    data-id="<?=$arConfig['ITEM']['ID']?>"
                    title="<?=Solution::GetFrontParametrValue('EXPRESSION_ONE_CLICK_BUY')?>"
                >
                    <?= Solution::showIconSvg(' item-action__wrapper', SITE_TEMPLATE_PATH.'/images/svg/catalog/ocb/ocb_white.svg'); ?>
                    <?= Solution::showIconSvg(' item-action__normal', SITE_TEMPLATE_PATH.'/images/svg/catalog/ocb/ocb.svg'); ?>
                </button>
            </div>
            <?$html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    public static function getSubsribeButton($arConfig, $bExt = false)
    {
        static $bUserAuthorized;

        if (!isset($bUserAuthorized)) {
            $bUserAuthorized = $GLOBALS['USER']->IsAuthorized();
        }

        if ($arConfig['EXTERNAL_BUTTON'] === 'Y') {
            $arConfig['BTN_CLASS_SUBSCRIBE'] .= ' js-external-button';
        }
        ?>
        <?$html = ''; ?>
        <?ob_start(); ?>
            <?if (!$bExt):?>
                <div class="buy_block btn-actions__inner">
                    <div class="buttons">
                        <div class="line-block line-block--gap line-block--column line-block--align-normal">
            <?endif; ?>
                            <div class="line-block__item">
                                <span class="item-action item-action--subscribe">
                                    <button type="button" class="btn btn-transparent <?= $arConfig['BTN_CLASS_SUBSCRIBE']; ?> animate-load <?= $bUserAuthorized ? 'js-item-action' : 'auth'; ?>" <?= $bUserAuthorized ? 'data-action="subscribe" data-id="'.$arConfig['ITEM']['ID'].'" title="'.htmlspecialcharsbx($arConfig['CONFIG']['EXPRESSION_SUBSCRIBE_BUTTON']).'" data-title="'.htmlspecialcharsbx($arConfig['CONFIG']['EXPRESSION_SUBSCRIBE_BUTTON']).'" data-title_added="'.htmlspecialcharsbx($arConfig['CONFIG']['EXPRESSION_SUBSCRIBED_BUTTON']).'"' : 'data-event="jqm" data-name="subscribe" data-param-form_id="subscribe" data-param-id="'.$arConfig['ITEM']['ID'].'" data-item="'.$arConfig['ITEM']['ID'].'"'; ?>>
                                        <?= $arConfig['CONFIG']['EXPRESSION_SUBSCRIBE_BUTTON']; ?>
                                    </button>
                                </span>
                            </div>
            <?if (!$bExt):?>
                        </div>
                    </div>
                </div>
            <?endif; ?>
        <?$html = ob_get_contents();
        ob_end_clean(); ?>

        <?return $html;
    }

    public static function getMoreButton(array $arConfig)
    {
        if (!$arConfig['CONFIG']) {
            $arConfig['CONFIG'] = self::getConfig();
        }
        if (!isset($arConfig['BTN_CLASS_MORE'])) {
            $arConfig += self::$btnClasses;
        }

        $buttonClassList = ['btn btn-default btn-actions__inner btn-wide js-replace-more'];
        if ($arConfig['BTN_CLASS_MORE']) {
            $buttonClassList[] = $arConfig['BTN_CLASS_MORE'];
        }
        if ($arConfig['JS_EVENT'] !== 'Y') {
            $buttonClassList[] = 'js-offer-modal';
        }

        $buttonClass = SolutionUtils::implodeClasses($buttonClassList);
        ?>
        <?$html = ''; ?>
        <?ob_start(); ?>
            <?if ($arConfig['NOINDEX']):?>
                <!--noindex-->
            <?endif; ?>
            <?if ($arConfig['JS_EVENT'] !== 'N'):?>
                <a href="<?= $arConfig['ITEM']['DETAIL_PAGE_URL']; ?>" class="<?= $buttonClass; ?>" <?= $arConfig['TARGET']; ?>>
                    <?= $arConfig['CONFIG']['EXPRESSION_READ_MORE_OFFERS']; ?>
                </a>
            <?else:?>
                <button type="button" class="<?= $buttonClass; ?>" data-action="basket" data-id="<?= $arConfig['ITEM']['ID']; ?>">
                    <?= $arConfig['CONFIG']['EXPRESSION_ADDTOBASKET_BUTTON']; ?>
                </button>
            <?endif; ?>
            <?if ($arConfig['NOINDEX']):?>
                <!--/noindex-->
            <?endif; ?>
        <?$html = ob_get_contents();
        ob_end_clean(); ?>

        <?return $html;
    }

    public static function getBasketLink(array $arConfig = []): string
    {
        $arAddToBasketOptions = self::getConfig();
        $buttonClass = ['to_cart_link btn btn-default', $arConfig['TO_BASKET_LINK_CLASS']];
        $buttonClass = SolutionUtils::implodeClasses($buttonClass);
        ?>
        <?ob_start(); ?>
            <a class="<?= $buttonClass; ?>" href="<?= $arConfig['BASKET_URL']; ?>" title="<?= $arAddToBasketOptions['EXPRESSION_ADDTOBASKET_BUTTON']; ?>">
                <?= Solution::showSpriteIconSvg(SITE_TEMPLATE_PATH.'/images/svg/arrows.svg#right-pointer', 'wrapper stroke-use-fff', ['HEIGHT' => 13, 'WIDTH' => 14]); ?>
            </a>
        <?php
        $html = trim(ob_get_clean());

        return $html;
    }

    public static function getAnchorButton($arProps)
    {
        SolutionFunctions::showBlockHtml([
            'TYPE' => 'CATALOG',
            'FILE' => 'catalog/sku2_anchor_button.php',
            'PARAMS' => $arProps,
        ]);
    }

    public static function getCanBuy($arOptions)
    {
        $arItem = $arOptions['ITEM'];
        $totalCount = $arOptions['TOTAL_COUNT'];
        $arParams = $arOptions['PARAMS'];

        if (self::$catalogInclude) {
            if (!array_key_exists('CAN_BUY', $arItem)) {
                $arItem['CAN_BUY'] = ($totalCount > 0) || ($arItem['CATALOG_QUANTITY_TRACE'] == 'N') || ($arItem['CATALOG_QUANTITY_TRACE'] == 'Y' && $arItem['CATALOG_CAN_BUY_ZERO'] == 'Y');
            }

            $canBuy = $arItem['CAN_BUY'];

            if ($arParams['USE_REGION'] === 'Y' && $arParams['STORES']) {
                $canBuy = (
                    ($totalCount && ($arItem['OFFERS'] || $arItem['CAN_BUY']))
                    || (
                        (!$totalCount && $arItem['CATALOG_QUANTITY_TRACE'] == 'N')
                        || (
                            !$totalCount
                            && $arItem['CATALOG_QUANTITY_TRACE'] == 'Y'
                            && $arItem['CATALOG_CAN_BUY_ZERO'] == 'Y'
                        )
                    )
                );
            }

            return $canBuy;
        }

        return
            isset($arItem['DISPLAY_PROPERTIES']) && isset($arItem['DISPLAY_PROPERTIES']['FORM_ORDER'])
            ? ($arItem['DISPLAY_PROPERTIES']['FORM_ORDER']['VALUE_XML_ID'] == 'YES')
            : ($arItem['PROPERTIES']['FORM_ORDER']['VALUE_XML_ID'] == 'YES')
        ;
    }

    public static function getPrices(array $arConfig) {
        return ($arConfig['PRICES'] ?? null) instanceof Prices
            ? $arConfig['PRICES']
            : new Prices($arConfig['ITEM']);
    }

    public static function checkAllowDelivery($summ, $currency)
    {
        $ERROR = false;
        $min_price = Option::get(Solution::moduleID, 'MIN_ORDER_PRICE', 1000, SITE_ID);
        $error_text = '';
        if ($summ < $min_price) {
            $ERROR = true;
            $error_text = Option::get(Solution::moduleID, 'MIN_ORDER_PRICE_TEXT', GetMessage('MIN_ORDER_PRICE_TEXT_EXAMPLE'));
            $error_text = str_replace('#PRICE#', SaleFormatCurrency($min_price, $currency), $error_text);
            if($currency) {
                $error_text = str_replace('#PRICE#', SaleFormatCurrency($min_price, $currency), $error_text);
            } else {
                $error_text = str_replace('#PRICE#', $min_price, $error_text);
            }
        }

        return [
            'ERROR' => $ERROR,
            'TEXT' => $error_text,
        ];
    }

    public static function isShopWindowMode()
    {
        return Solution::GetFrontParametrValue('SHOP_WINDOW_MODE') === 'Y';
    }

    public static function showMarketUrl($config)
    {
        $arConfig = $config['config'];
        $basketButton = $config['basketButton'];
        $buttonACTION = $config['buttonACTION'];

        $buttonACTION = 'MORE';
        $basketButton = self::getMoreButton($arConfig);

        if ($arConfig['DETAIL_PAGE']) {
            $replacedUrl = (isset($arConfig['ITEM']['PROPERTIES']['OZON_FBS']['VALUE']) ? $arConfig['ITEM']['PROPERTIES']['OZON_FBS']['VALUE'] : '');
            if ($replacedUrl) {
                $arConfig['ITEM']['DETAIL_PAGE_URL'] = 'https://www.ozon.ru/product/'.$replacedUrl;
                $arConfig['ITEM']['DETAIL_PAGE_URL'] .= '?oos_search=false';
                $arConfig['TARGET'] = 'target="_blank" rel="nofollow"';
                $arConfig['NOINDEX'] = true;
                $arConfig['CONFIG']['EXPRESSION_READ_MORE_OFFERS'] = Option::get(Solution::moduleID, 'EXPRESSION_MORE_TEXT', GetMessage('EXPRESSION_MORE_TEXT_VALUE'), SITE_ID);

                $buttonACTION = 'MORE';
                $basketButton = self::getMoreButton($arConfig);
            } else {
                $buttonACTION = 'NONE';
                $basketButton = '';
            }
        }

        return [
            'basketButton' => $basketButton,
            'buttonACTION' => $buttonACTION,
            'config' => $arConfig,
        ];
    }
}
?>
