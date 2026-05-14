<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    exit;
}

$this->setFrameMode(true);

global $arTheme;
use Bitrix\Main\Localization\Loc;

$bUseSchema = !(isset($arParams['NO_USE_SHCEMA_ORG']) && $arParams['NO_USE_SHCEMA_ORG'] == 'Y');
$bOrderViewBasket = $arParams['ORDER_VIEW'];
$basketURL = TSolution::GetFrontParametrValue('BASKET_PAGE_URL');
$dataItem = TSolution::getDataItem($arResult);
$bOrderButton = $arResult['PROPERTIES']['FORM_ORDER']['VALUE_XML_ID'] == 'YES';
$bAskButton = $arResult['PROPERTIES']['FORM_QUESTION']['VALUE_XML_ID'] == 'YES';
$bOcbButton = $arParams['SHOW_ONE_CLICK_BUY'] != 'N';
$bGallerythumbVertical = $arParams['GALLERY_THUMB_POSITION'] === 'vertical';
$cntVisibleChars = $arParams['VISIBLE_PROP_COUNT'];

$bShowRating = $arParams['SHOW_RATING'] == 'Y';
$bShowCompare = $arParams['DISPLAY_COMPARE'] == 'Y';
$bShowFavorit = $arParams['SHOW_FAVORITE'] == 'Y';
$bUseShare = $arParams['USE_SHARE'] == 'Y';
$bShowSendGift = $arParams['SHOW_SEND_GIFT'] === 'Y' && !$arResult['PRODUCT_ANALOG'];
$bShowCheaperForm = $arParams['SHOW_CHEAPER_FORM'] === 'Y' && !$arResult['PRODUCT_ANALOG'];
$bShowReview = $arParams['SHOW_REVIEW'] !== 'N';
$hasPopupVideo = (bool) $arResult['POPUP_VIDEO'];
$bShowCalculateDelivery = $arParams['CALCULATE_DELIVERY'] === 'Y' && !$arResult['PRODUCT_ANALOG'];
$bShowSKUDescription = $arParams['SHOW_SKU_DESCRIPTION'] === 'Y';

$templateData['USE_OFFERS_SELECT'] = false;

$arSkuTemplateData = [];
$bSKU2 = $arParams['TYPE_SKU'] === 'TYPE_2';
$bShowSkuProps = !$bSKU2;

$arSKUSetsData = [];
if ($arResult['SKU']['SKU_GROUP']) {
    $arSKUSetsData = [
        'IBLOCK_ID' => $arResult['SKU']['CURRENT']['IBLOCK_ID'],
        'ITEMS' => $arResult['SKU']['SKU_GROUP_VALUES'],
        'CURRENT_ID' => $arResult['SKU']['CURRENT']['ID'],
    ];
}

$bCrossAssociated = isset($arParams['CROSS_LINK_ITEMS']['ASSOCIATED']['VALUE']) && !empty($arParams['CROSS_LINK_ITEMS']['ASSOCIATED']['VALUE']);
$bCrossExpandables = isset($arParams['CROSS_LINK_ITEMS']['EXPANDABLES']['VALUE']) && !empty($arParams['CROSS_LINK_ITEMS']['EXPANDABLES']['VALUE']);

$templateData = [
    'DETAIL_PAGE_URL' => $arResult['DETAIL_PAGE_URL'],
    'IBLOCK_SECTION_ID' => $arResult['IBLOCK_SECTION_ID'],
    'INCLUDE_FOLDER_PATH' => $arResult['INCLUDE_FOLDER_PATH'],
    'ORDER' => $bOrderViewBasket,
    'TIZERS' => [
        'IBLOCK_ID' => $arParams['IBLOCK_TIZERS_ID'],
        'VALUE' => $arResult['TIZERS'],
    ],
    'SALE' => TSolution\Functions::getCrossLinkedItems($arResult, ['LINK_SALE'], ['LINK_GOODS', 'LINK_GOODS_FILTER'], $arParams),
    'ARTICLES' => TSolution\Functions::getCrossLinkedItems($arResult, ['LINK_ARTICLES'], ['LINK_GOODS', 'LINK_GOODS_FILTER'], $arParams),
    'SERVICES' => TSolution\Functions::getCrossLinkedItems($arResult, ['SERVICES'], ['LINK_GOODS', 'LINK_GOODS_FILTER'], $arParams),
    'FAQ' => TSolution\Functions::getCrossLinkedItems($arResult, ['LINK_FAQ']),
    'ASSOCIATED' => $arParams['USE_ASSOCIATED_CROSS'] ? [] : TSolution\Functions::getCrossLinkedItems($arResult, ['ASSOCIATED', 'ASSOCIATED_FILTER']),
    'EXPANDABLES' => $arParams['USE_EXPANDABLES_CROSS'] ? [] : TSolution\Functions::getCrossLinkedItems($arResult, ['EXPANDABLES', 'EXPANDABLES_FILTER']),
    'CATALOG_SETS' => [
        'SET_ITEMS' => $arResult['SET_ITEMS'],
        'SKU_SETS' => $arSKUSetsData,
    ],
    'POPUP_VIDEO' => $hasPopupVideo,
    'RATING' => floatval($arResult['PROPERTIES']['EXTENDED_REVIEWS_RAITING'] ? $arResult['PROPERTIES']['EXTENDED_REVIEWS_RAITING']['VALUE'] : 0),
    'REVIEWS_COUNT' => intval($arResult['PROPERTIES']['EXTENDED_REVIEWS_COUNT'] ? $arResult['PROPERTIES']['EXTENDED_REVIEWS_COUNT']['VALUE'] : 0),
    'USE_SHARE' => $arParams['USE_SHARE'] === 'Y',
    'SHOW_REVIEW' => $bShowReview,
    'CALCULATE_DELIVERY' => $bShowCalculateDelivery,
    'BRAND' => $arResult['BRAND_ITEM'],
    'CUSTOM_BLOCKS_DATA' => [
        'PROPERTIES' => TSolution\Product\Blocks::getPropertiesByParams($arParams['CUSTOM_PROPERTY_DATA'], $arResult['PROPERTIES']),
    ],
    'SHOW_CHARACTERISTICS' => false,
    'PRODUCT_ANALOG' => $arResult['PRODUCT_ANALOG'] ?? false,
];
?>

<?if (TSolution::isSaleMode()):?>
    <div class="basket_props_block" id="bx_basket_div_<?=$arResult['ID']; ?>" hidden>
        <?if (!empty($arResult['PRODUCT_PROPERTIES_FILL'])):?>
            <?foreach ($arResult['PRODUCT_PROPERTIES_FILL'] as $propID => $propInfo):?>
                <input type="hidden" name="<?=$arParams['PRODUCT_PROPS_VARIABLE']; ?>[<?=$propID; ?>]" value="<?=htmlspecialcharsbx($propInfo['ID']); ?>">
                <?php
                if (isset($arResult['PRODUCT_PROPERTIES'][$propID])) {
                    unset($arResult['PRODUCT_PROPERTIES'][$propID]);
                }
                ?>
            <?endforeach; ?>
        <?endif; ?>
        <?if ($arResult['PRODUCT_PROPERTIES']):?>
            <div class="wrapper">
                <?foreach($arResult['PRODUCT_PROPERTIES'] as $propID => $propInfo):?>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group fill-animate">
                                <?if($arResult['PROPERTIES'][$propID]['PROPERTY_TYPE'] == 'L' && $arResult['PROPERTIES'][$propID]['LIST_TYPE'] == 'C'):?>
                                    <label class="font_14"><span><?=$arResult['PROPERTIES'][$propID]['NAME']; ?></span></label>
                                    <?foreach($propInfo['VALUES'] as $valueID => $value):?>
                                        <div class="form-radiobox">
                                            <label class="form-radiobox__label">
                                                <input class="form-radiobox__input" type="radio" name="<?=$arParams['PRODUCT_PROPS_VARIABLE']; ?>[<?=$propID; ?>]" value="<?=$valueID; ?>">
                                                <span class="bx_filter_input_checkbox"><span><?=$value; ?></span></span>
                                                <span class="form-radiobox__box"></span>
                                            </label>
                                        </div>
                                    <?endforeach; ?>
                                <?else:?>
                                    <label class="font_14"><span><?=$arResult['PROPERTIES'][$propID]['NAME']; ?></span></label>
                                    <div class="input">
                                        <select class="form-control" name="<?=$arParams['PRODUCT_PROPS_VARIABLE']; ?>[<?=$propID; ?>]">
                                            <?foreach($propInfo['VALUES'] as $valueID => $value):?>
                                                <option value="<?=$valueID; ?>" <?= $valueID == $propInfo['SELECTED'] ? 'selected' : ''; ?>><?=$value; ?></option>
                                            <?endforeach; ?>
                                        </select>
                                    </div>
                                <?endif; ?>
                            </div>
                        </div>
                    </div>
                <?endforeach; ?>
            </div>
        <?endif; ?>
    </div>
<?endif; ?>

<?php
$templateData['SECTION_BNR_CONTENT'] = isset($arResult['PROPERTIES']['BNR_TOP']) && $arResult['PROPERTIES']['BNR_TOP']['VALUE_XML_ID'] == 'YES';
if ($templateData['SECTION_BNR_CONTENT']) {
    $templateData['SECTION_BNR_UNDER_HEADER'] = $arResult['PROPERTIES']['BNR_TOP_UNDER_HEADER']['VALUE_XML_ID'];
    $templateData['SECTION_BNR_COLOR'] = $arResult['PROPERTIES']['BNR_TOP_COLOR']['VALUE_XML_ID'];
    $atrTitle = $arResult['PROPERTIES']['BNR_TOP_BG']['DESCRIPTION'] ?: $arResult['PROPERTIES']['BNR_TOP_BG']['TITLE'] ?: $arResult['NAME'];
    $atrAlt = $arResult['PROPERTIES']['BNR_TOP_BG']['DESCRIPTION'] ?: $arResult['PROPERTIES']['BNR_TOP_BG']['ALT'] ?: $arResult['NAME'];
    $bannerButtons = [[
        'TITLE' => $arResult['PROPERTIES']['BUTTON1TEXT']['VALUE'] ?? '',
        'CLASS' => 'btn choise '.($arResult['PROPERTIES']['BUTTON1CLASS']['VALUE_XML_ID'] ?? 'btn-default').' '.($arResult['PROPERTIES']['BUTTON1COLOR']['VALUE_XML_ID'] ?? ''),
        'ATTR' => [$arResult['PROPERTIES']['BUTTON1TARGET']['VALUE_XML_ID'] === 'scroll' || !$arResult['PROPERTIES']['BUTTON1TARGET']['VALUE_XML_ID'] ? 'data-block=".right_block .detail"' : 'target="'.$arResult['PROPERTIES']['BUTTON1TARGET']['VALUE_XML_ID'].'"'],
        'LINK' => $arResult['PROPERTIES']['BUTTON1LINK']['VALUE'],
        'TYPE' => $arResult['PROPERTIES']['BUTTON1TARGET']['VALUE_XML_ID'] === 'scroll' || !$arResult['PROPERTIES']['BUTTON1TARGET']['VALUE_XML_ID'] ? 'anchor' : 'link',
    ]];
    if ($arResult['PROPERTIES']['BUTTON2TEXT']['VALUE'] && $arResult['PROPERTIES']['BUTTON2LINK']['VALUE']) {
        $bannerButtons[] = [
            'TITLE' => $arResult['PROPERTIES']['BUTTON2TEXT']['VALUE'],
            'CLASS' => 'btn choise '.($arResult['PROPERTIES']['BUTTON2CLASS']['VALUE_XML_ID'] ?? 'btn-default').' '.($arResult['PROPERTIES']['BUTTON2COLOR']['VALUE_XML_ID'] ?? ''),
            'ATTR' => [$arResult['PROPERTIES']['BUTTON2TARGET']['VALUE_XML_ID'] ? 'target="'.$arResult['PROPERTIES']['BUTTON2TARGET']['VALUE_XML_ID'].'"' : ''],
            'LINK' => $arResult['PROPERTIES']['BUTTON2LINK']['VALUE'],
            'TYPE' => 'link',
        ];
    }
    $this->SetViewTarget('section_bnr_content');
    TSolution\Functions::showBlockHtml([
        'FILE' => '/images/detail_banner.php',
        'PARAMS' => [
            'TITLE' => $arResult['NAME'],
            'COLOR' => $templateData['SECTION_BNR_COLOR'],
            'TEXT' => [
                'TOP' => $arResult['SECTION'] ? reset($arResult['SECTION']['PATH'])['NAME'] : '',
                'PREVIEW' => ['TYPE' => $arResult['PREVIEW_TEXT_TYPE'], 'VALUE' => $arResult['PREVIEW_TEXT']],
            ],
            'PICTURES' => [
                'BG' => CFile::GetFileArray($arResult['PROPERTIES']['BNR_TOP_BG']['VALUE']),
                'IMG' => CFile::GetFileArray($arResult['PROPERTIES']['BNR_TOP_IMG']['VALUE']),
            ],
            'BUTTONS' => $bannerButtons,
            'ATTR' => ['ALT' => $atrAlt, 'TITLE' => $atrTitle],
            'TOP_IMG' => $bTopImg,
        ],
    ]);
    $this->EndViewTarget();
}

$article = $arResult['DISPLAY_PROPERTIES']['CML2_ARTICLE']['VALUE'];
$totalCount = TSolution\Product\Quantity::getTotalCount(['ITEM' => $arResult, 'PARAMS' => $arParams]);
$arStatus = TSolution\Product\Quantity::getStatus([
    'ITEM' => $arResult,
    'PARAMS' => $arResult['HAS_SKU2'] ? array_merge($arParams, ['CATALOG_SHOW_AMOUNT_STORES' => 'N']) : $arParams,
    'TOTAL_COUNT' => $totalCount,
    'IS_DETAIL' => true,
]);

$arCurrentOffer = $arResult['SKU']['CURRENT'];
$elementName = TSolution\Product\Common::getElementName($arResult);
$bShowSelectOffer = $arCurrentOffer && $bShowSkuProps;
$templateData['CURRENT_OFFER_ID'] = $bShowSelectOffer ? $arCurrentOffer['ID'] : null;

if ($bShowSelectOffer) {
    $arResult['PARENT_IMG'] = $arResult['PREVIEW_PICTURE'] ?: ($arResult['DETAIL_PICTURE'] ?: '');
    $arResult['DETAIL_PAGE_URL'] = $arCurrentOffer['DETAIL_PAGE_URL'];
    if ($arParams['SHOW_GALLERY'] === 'Y') {
        if (!$arCurrentOffer['DETAIL_PICTURE'] && $arCurrentOffer['PREVIEW_PICTURE']) {
            $arCurrentOffer['DETAIL_PICTURE'] = $arCurrentOffer['PREVIEW_PICTURE'];
        }
        $arOfferGallery = TSolution\Functions::getSliderForItem([
            'TYPE' => 'catalog_block',
            'PROP_CODE' => $arParams['OFFER_ADD_PICT_PROP'],
            'ITEM' => $arCurrentOffer,
            'PARAMS' => $arParams,
        ]);
        if ($arOfferGallery) {
            $arResult['GALLERY'] = array_merge($arOfferGallery, $arResult['GALLERY']);
        }
    } elseif ($arCurrentOffer['PREVIEW_PICTURE'] || $arCurrentOffer['DETAIL_PICTURE']) {
        $arResult['PREVIEW_PICTURE'] = $arCurrentOffer['PREVIEW_PICTURE'] ?: $arCurrentOffer['DETAIL_PICTURE'];
    }
    if (!$arCurrentOffer['PREVIEW_PICTURE'] && !$arCurrentOffer['DETAIL_PICTURE']) {
        $arCurrentOffer['PREVIEW_PICTURE'] = $arResult['PREVIEW_PICTURE'] ?: $arResult['DETAIL_PICTURE'];
    }
    if ($arCurrentOffer['DISPLAY_PROPERTIES']['CML2_ARTICLE']['VALUE'] || $arCurrentOffer['DISPLAY_PROPERTIES']['ARTICLE']['VALUE']) {
        $article = $arCurrentOffer['DISPLAY_PROPERTIES']['CML2_ARTICLE']['VALUE'] ?? $arCurrentOffer['DISPLAY_PROPERTIES']['ARTICLE']['VALUE'];
    }
    $arResult['DISPLAY_PROPERTIES']['FORM_ORDER'] = $arCurrentOffer['DISPLAY_PROPERTIES']['FORM_ORDER'];
    $arResult['DISPLAY_PROPERTIES']['PRICE'] = $arCurrentOffer['DISPLAY_PROPERTIES']['PRICE'];
    if ($arParams['SET_SKU_TITLE'] !== 'N') {
        $arResult['NAME'] = $arCurrentOffer['NAME'];
        $elementName = TSolution\Product\Common::getElementName($arCurrentOffer);
        $templateData['OFFER_INFO'] = ['NAME' => $arCurrentOffer['NAME'], 'IPROPERTY_VALUES' => !empty($arCurrentOffer['IPROPERTY_VALUES']) ? $arCurrentOffer['IPROPERTY_VALUES'] : []];
    }
    $arResult['OFFER_PROP'] = TSolution::PrepareItemProps($arCurrentOffer['DISPLAY_PROPERTIES']);
    TSolution\LinkableProperty::resolve($arResult['OFFER_PROP'], $arCurrentOffer['IBLOCK_ID'], $arResult['IBLOCK_SECTION_ID']);
    $dataItem = TSolution::getDataItem($arCurrentOffer);
    $totalCount = TSolution\Product\Quantity::getTotalCount(['ITEM' => $arCurrentOffer, 'PARAMS' => $arParams]);
    $arStatus = TSolution\Product\Quantity::getStatus(['ITEM' => $arCurrentOffer, 'PARAMS' => $arParams, 'TOTAL_COUNT' => $totalCount, 'IS_DETAIL' => true]);
}

$status = $arStatus['NAME'];
$statusCode = $arStatus['CODE'];

$bSKUDescription = $bShowSKUDescription && strlen($arResult['SKU']['CURRENT']['DETAIL_TEXT']);
$templateData['DETAIL_TEXT'] = boolval(strlen($arResult['DETAIL_TEXT']) || $bSKUDescription);
if ($templateData['DETAIL_TEXT']) {
    $this->SetViewTarget('PRODUCT_DETAIL_TEXT_INFO');
    ?><div class="content content--max-width js-detail-description" itemprop="description"><?if($bSKUDescription):?><?=$arResult['SKU']['CURRENT']['DETAIL_TEXT']; ?><?else:?><?=$arResult['DETAIL_TEXT']; ?><?endif; ?></div><?php
    $this->EndViewTarget();
}

$templateData['DOCUMENTS'] = boolval($arResult['DOCUMENTS']);
if ($templateData['DOCUMENTS']) {
    $this->SetViewTarget('PRODUCT_FILES_INFO');
    TSolution\Functions::showBlockHtml(['FILE' => '/documents.php', 'PARAMS' => ['ITEMS' => $arResult['DOCUMENTS']]]);
    $this->EndViewTarget();
}

$templateData['BIG_GALLERY'] = boolval($arResult['BIG_GALLERY']);
if ($arResult['BIG_GALLERY']) {
    $this->SetViewTarget('PRODUCT_BIG_GALLERY_INFO');
    TSolution\Functions::showGallery($arResult['BIG_GALLERY'], ['CONTAINER_CLASS' => 'gallery-detail font_13']);
    $this->EndViewTarget();
}

$templateData['VIDEO'] = boolval($arResult['VIDEO']);
$bOneVideo = count((array) $arResult['VIDEO']) == 1;
if ($arResult['VIDEO']) {
    $this->SetViewTarget('PRODUCT_VIDEO_INFO');
    TSolution\Functions::showBlockHtml(['FILE' => 'video/detail_video_block.php', 'PARAMS' => ['VIDEO' => $arResult['VIDEO']]]);
    $this->EndViewTarget();
}
?>

<?if($bAskButton):?>
    <?if($arParams['LEFT_BLOCK_CATALOG_DETAIL'] === 'N'):?><?$this->SetViewTarget('PRODUCT_SIDE_INFO'); ?><?else:?><?$this->SetViewTarget('under_sidebar_content'); ?><?endif; ?>
        <div class="ask-block bordered rounded-4">
            <div class="ask-block__container">
                <div class="ask-block__icon"><?=TSolution::showIconSvg('ask colored', SITE_TEMPLATE_PATH.'/images/svg/Question_lg.svg'); ?></div>
                <div class="ask-block__text text-block color_666 font_14"><?=$arResult['INCLUDE_ASK']; ?></div>
                <div class="ask-block__button">
                    <div class="btn btn-default btn-transparent-bg animate-load" data-event="jqm" data-param-id="<?=TSolution::getFormID(VENDOR_PARTNER_NAME.'_'.VENDOR_SOLUTION_NAME.'_question'); ?>" data-autoload-need_product="<?=TSolution::formatJsName($arResult['NAME']); ?>" data-name="question">
                        <span><?=htmlspecialcharsbx(TSolution::GetFrontParametrValue('EXPRESSION_FOR_ASK_QUESTION')); ?></span>
                    </div>
                </div>
            </div>
        </div>
    <?$this->EndViewTarget(); ?>
<?endif; ?>

<?php
if ($arParams['USE_GIFTS_DETAIL'] === 'Y') {
    $templateData['GIFTS'] = [
        'ADD_URL_TEMPLATE' => $arResult['~ADD_URL_TEMPLATE'],
        'BUY_URL_TEMPLATE' => $arResult['~BUY_URL_TEMPLATE'],
        'SUBSCRIBE_URL_TEMPLATE' => $arResult['~SUBSCRIBE_URL_TEMPLATE'],
        'POTENTIAL_PRODUCT_TO_BUY' => [
            'ID' => $arResult['ID'],
            'MODULE' => $arResult['MODULE'] ?? 'catalog',
            'PRODUCT_PROVIDER_CLASS' => $arResult['PRODUCT_PROVIDER_CLASS'] ?? 'CCatalogProductProvider',
            'QUANTITY' => $arResult['QUANTITY'] ?? '',
            'IBLOCK_ID' => $arResult['IBLOCK_ID'],
            'PRIMARY_OFFER_ID' => $arResult['OFFERS'][0]['ID'] ?? '',
            'SECTION' => [
                'ID' => $arResult['SECTION']['ID'] ?? '',
                'IBLOCK_ID' => $arResult['SECTION']['IBLOCK_ID'] ?? '',
                'LEFT_MARGIN' => $arResult['SECTION']['LEFT_MARGIN'] ?? '',
                'RIGHT_MARGIN' => $arResult['SECTION']['RIGHT_MARGIN'] ?? '',
            ],
        ],
    ];
}

$frontcalcProductId = (int)$arResult['ID'];
$frontcalcIblockId = (int)$arResult['IBLOCK_ID'];
$frontcalcAjaxUrl = '/local/ajax/frontcalc.php';
$frontcalcOfferId = (int)($arCurrentOffer['ID'] ?? 0);
$frontcalcInclude = $_SERVER['DOCUMENT_ROOT'].'/local/modules/prospektweb.frontcalc/template_include.php';
if (is_file($frontcalcInclude)) {
    require_once $frontcalcInclude;
    if (function_exists('frontcalc_get_light_payload')) {
        $frontcalcPayload = frontcalc_get_light_payload($frontcalcProductId, $frontcalcIblockId, $frontcalcAjaxUrl);
        $frontcalcAjaxUrl = (string)($frontcalcPayload['ajax_url'] ?? $frontcalcAjaxUrl);
    }
}
?>
<link rel="stylesheet" href="/local/modules/prospektweb.frontcalc/assets/css/frontcalc-jqm-popup.css">
<script>
window.FrontcalcPopupConfig = window.FrontcalcPopupConfig || {};
window.FrontcalcPopupConfig.modulePath = '/local/modules/prospektweb.frontcalc/assets/js/frontcalc-jqm-popup.js';
window.FrontcalcPopupConfig.jqModalPath = window.FrontcalcPopupConfig.jqModalPath || (window.arAsproOptions && window.arAsproOptions.SITE_TEMPLATE_PATH ? window.arAsproOptions.SITE_TEMPLATE_PATH + '/js/jqModal.js' : '/bitrix/modules/aspro.popup/install/js/jqModal.js');
</script>
<script src="/local/modules/prospektweb.frontcalc/assets/js/frontcalc-jqm-popup.js" defer></script>

<div class="catalog-detail__top-info flexbox flexbox--direction-row flexbox--wrap-nowrap gap gap--40">
    <?php TSolution\Product\Common::addViewed(['ITEM' => $arCurrentOffer ?: $arResult]); ?>

    <?if ($arResult['SKU_CONFIG']):?><div class="js-sku-config hidden" data-value='<?=str_replace('\'', '"', CUtil::PhpToJSObject($arResult['SKU_CONFIG'], false, true)); ?>'></div><?endif; ?>
    <?if ($arResult['SKU']['PROPS']):?>
        <template class="offers-template-json"><?=TSolution\SKU::getOfferTreeJson($arResult['SKU']['OFFERS']); ?></template>
        <?$templateData['USE_OFFERS_SELECT'] = true; ?>
    <?endif; ?>

    <?$topGalleryClassList = $hasPopupVideo ? ' detail-gallery-big--with-video' : ''; ?>
    <div class="detail-gallery-big <?=$topGalleryClassList; ?> swipeignore image-list__link">
        <div class="sticky-block">
            <div class="detail-gallery-big-wrapper">
                <?php
                $countPhoto = count($arResult['GALLERY']);
                $isMoreThanOnePhoto = ($countPhoto > 1);
                $arFirstPhoto = reset($arResult['GALLERY']);
                $urlFirstPhoto = $arFirstPhoto['BIG']['src'] ? $arFirstPhoto['BIG']['src'] : $arFirstPhoto['SRC'];
                ?>
                <link href="<?=$urlFirstPhoto; ?>" itemprop="image"/>
                <?php
                $gallerySetting = [
                    'MAIN' => [
                        'SLIDE_CLASS_LIST' => 'detail-gallery-big__item detail-gallery-big__item--big swiper-slide',
                        'PLUGIN_OPTIONS' => [
                            'direction' => 'horizontal', 'init' => false, 'keyboard' => ['enabled' => true], 'loop' => false,
                            'pagination' => ['enabled' => true, 'el' => '.detail-gallery-big-slider-main .swiper-pagination'],
                            'navigation' => ['nextEl' => '.detail-gallery-big-slider-main .swiper-button-next', 'prevEl' => '.detail-gallery-big-slider-main .swiper-button-prev'],
                            'slidesPerView' => 1, 'thumbs' => ['swiper' => '.gallery-slider-thumb'], 'type' => 'detail_gallery_main', 'preloadImages' => false,
                        ],
                    ],
                    'THUMBS' => [
                        'SLIDE_CLASS_LIST' => 'gallery__item gallery__item--thumb swiper-slide rounded-x pointer',
                        'PLUGIN_OPTIONS' => [
                            'direction' => ($bGallerythumbVertical ? 'vertical' : 'horizontal'), 'init' => false, 'spaceBetween' => 4, 'loop' => false,
                            'navigation' => ['nextEl' => '.gallery-slider-thumb-button--next', 'prevEl' => '.gallery-slider-thumb-button--prev'],
                            'pagination' => false, 'slidesPerView' => 'auto', 'type' => 'detail_gallery_thumb', 'watchSlidesProgress' => true, 'preloadImages' => false,
                        ],
                    ],
                ];
                TSolution\Functions::showBlockHtml([
                    'FILE' => '/catalog/images/detail_top_gallery.php',
                    'ITEM' => $arResult,
                    'PARAMS' => [
                        'arParams' => $arParams,
                        'gallerySetting' => $gallerySetting,
                        'countPhoto' => $countPhoto,
                        'isMoreThanOnePhoto' => $isMoreThanOnePhoto,
                        'bShowSelectOffer' => $bShowSelectOffer,
                        'hasPopupVideo' => $hasPopupVideo,
                    ],
                ]);
                ?>
            </div>
        </div>
    </div>

    <div class="catalog-detail__main">
        <?ob_start(); ?>
        <?if ($arParams['SHOW_DISCOUNT_TIME'] === 'Y' && $arParams['SHOW_DISCOUNT_TIME_IN_LIST'] !== 'N'):?>
            <?php
            $discountDateTo = '';
            if (TSolution::isSaleMode()) {
                $arDiscount = TSolution\Product\Price::getDiscountByItemID($arResult['ID']);
                $discountDateTo = $arDiscount ? $arDiscount['ACTIVE_TO'] : '';
            } else {
                $discountDateTo = $arResult['DISPLAY_PROPERTIES']['DATE_COUNTER']['VALUE'];
            }
            if ($discountDateTo) {
                $templateData['USE_COUNTDOWN'] = true;
                TSolution\Functions::showDiscountCounter(['ICONS' => true, 'DATE' => $discountDateTo, 'ITEM' => $arResult]);
            }
            ?>
        <?endif; ?>
        <?$itemDiscount = ob_get_clean(); ?>

        <div class="catalog-detail__main-parts line-block line-block--gap line-block--gap-40 js-frontcalc-inline"
             data-id="<?=$arResult['ID']; ?>"
             data-item="<?=$dataItem; ?>"
             data-frontcalc-product-id="<?=$frontcalcProductId; ?>"
             data-frontcalc-offer-id="<?=$frontcalcOfferId; ?>"
             data-frontcalc-ajax-url="<?=htmlspecialcharsbx($frontcalcAjaxUrl); ?>">
            <div class="catalog-detail__main-part catalog-detail__main-part--left flex-1 width-100 line-block__item js-frontcalc-inline-selectors"></div>
            <div class="catalog-detail__main-part catalog-detail__main-part--right sticky-block flex-1 line-block__item grid-list--fill-bg">
                <?php $isShowBrandBlock = false; ?>
                <aside class="frontcalc-price-panel js-frontcalc-inline-price"></aside>
            </div>
        </div>
    </div>

    <?TSolution\Vendor\Include\Component::bonusesCalculate(params: ['ITEMS' => [$arResult]]);?>
</div>
<?if ($templateData['SHOW_CHARACTERISTICS']):?>
    <?$this->SetViewTarget('PRODUCT_PROPS_INFO');?>
    <?TSolution\Functions::showBlockHtml([
        'FILE' => '/chars.php',
        'PARENT_COMPONENT' => $this->getComponent(),
        'PARAMS' => [
            'GRUPPER_PROPS' => $arParams['GRUPPER_PROPS'],
            'IBLOCK_ID' => $arResult['IBLOCK_ID'],
            'IBLOCK_TYPE' => $arResult['IBLOCK_TYPE'],
            'CHARACTERISTICS' => $arResult['CHARACTERISTICS'],
            'SKU_IBLOCK_ID' => $arParams['SKU_IBLOCK_ID'],
            'OFFER_PROP' => $arResult['OFFER_PROP'],
            'SHOW_HINTS' => $arParams['SHOW_HINTS'],
            'PROPERTIES_DISPLAY_TYPE' => $arParams['PROPERTIES_DISPLAY_TYPE'],
            'USE_SCHEMA' => 'N',
        ],
    ]);?>
    <?$this->EndViewTarget();?>
<?endif;?>

<?TSolution\Product\Template::showPredictions($arResult, $arCurrentOffer, $arParams, $component);?>

<?php
$arPriceConfig = [
    'PRICE_CODE' => $arParams['PRICE_CODE'],
    'PRICE_FONT' => 24,
    'PRICEOLD_FONT' => 15,
    'SHOW_POPUP_PRICE_TEMPLATE' => 'Y',
    'SHOW_SCHEMA' => true,
    'DISPLAY_VAT_INFO' => !$arResult['HAS_SKU2'] ? $arParams['DISPLAY_VAT_INFO'] : false,
];
$prices = new TSolution\Product\Prices($arCurrentOffer ?: $arResult, $arParams, $arPriceConfig);

if ($bUseSchema) {
    $schema = new TSolution\Scheme\Product(
        result: $arResult,
        prices: $prices,
        props: [
            'SKU' => $article,
            'STATUS' => $statusCode,
            'PRICE_VALID_UNTIL' => $discountDateTo,
        ],
        options: [
            'SHOW_BRAND' => (bool)$isShowBrandBlock,
        ],
    );
    $templateData['SCHEMA_ORG'] = $schema->getArraySchema();
}

TSolution\Template\Page::showCountdown($templateData['USE_COUNTDOWN']);
