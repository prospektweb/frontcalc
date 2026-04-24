<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @global CMain $APPLICATION */
global $APPLICATION;

$moduleId = 'prospektweb.frontcalc';
$step = (int)($_REQUEST['step'] ?? 1);
$isDone = ($step >= 2);

$iblocks = [];
$autoProductsIblockId = 0;
$autoOffersIblockId = 0;

if (\Bitrix\Main\Loader::includeModule('iblock')) {
    $rs = \CIBlock::GetList(['SORT' => 'ASC'], ['ACTIVE' => 'Y']);
    while ($row = $rs->Fetch()) {
        $iblocks[] = [
            'ID' => (int)$row['ID'],
            'NAME' => (string)$row['NAME'],
            'IBLOCK_TYPE_ID' => (string)$row['IBLOCK_TYPE_ID'],
        ];
    }
}

if (\Bitrix\Main\Loader::includeModule('catalog')) {
    $row = \Bitrix\Catalog\CatalogIblockTable::getList([
        'select' => ['IBLOCK_ID', 'PRODUCT_IBLOCK_ID'],
        'filter' => ['!=PRODUCT_IBLOCK_ID' => 0],
        'order' => ['IBLOCK_ID' => 'ASC'],
        'limit' => 1,
    ])->fetch();

    if ($row) {
        $autoOffersIblockId = (int)$row['IBLOCK_ID'];
        $autoProductsIblockId = (int)$row['PRODUCT_IBLOCK_ID'];
    }

    if ($autoOffersIblockId > 0 && $autoProductsIblockId <= 0) {
        $sku = \CCatalogSKU::GetInfoByOfferIBlock($autoOffersIblockId);
        if (is_array($sku) && !empty($sku['PRODUCT_IBLOCK_ID'])) {
            $autoProductsIblockId = (int)$sku['PRODUCT_IBLOCK_ID'];
        }
    }
}

if (($autoProductsIblockId <= 0 || $autoOffersIblockId <= 0) && \Bitrix\Main\Loader::includeModule('iblock')) {
    $propRes = \CIBlockProperty::GetList(
        ['ID' => 'ASC'],
        ['ACTIVE' => 'Y', 'PROPERTY_TYPE' => 'E', 'USER_TYPE' => 'SKU']
    );

    if ($prop = $propRes->Fetch()) {
        if ($autoOffersIblockId <= 0) {
            $autoOffersIblockId = (int)$prop['IBLOCK_ID'];
        }
        if ($autoProductsIblockId <= 0) {
            $autoProductsIblockId = (int)$prop['LINK_IBLOCK_ID'];
        }
    }
}

$selectedProductsIblockId = (int)($_REQUEST['PRODUCTS_IBLOCK_ID'] ?? $autoProductsIblockId);
$selectedOffersIblockId = (int)($_REQUEST['OFFERS_IBLOCK_ID'] ?? $autoOffersIblockId);
?>
<?php if (!$isDone): ?>
    <form action="<?= $APPLICATION->GetCurPage() ?>" method="post">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
        <input type="hidden" name="id" value="<?= htmlspecialcharsbx($moduleId) ?>">
        <input type="hidden" name="install" value="Y">
        <input type="hidden" name="step" value="2">

        <p><b>Установка модуля <?= htmlspecialcharsbx($moduleId) ?></b></p>

        <table class="adm-detail-content-table edit-table">
            <tr>
                <td width="40%">PRODUCTS_IBLOCK_ID (товары)</td>
                <td>
                    <select name="PRODUCTS_IBLOCK_ID" style="min-width: 340px;">
                        <option value="0">— Не выбрано —</option>
                        <?php foreach ($iblocks as $ib): ?>
                            <option value="<?= (int)$ib['ID'] ?>"<?= ((int)$ib['ID'] === $selectedProductsIblockId ? ' selected' : '') ?>>
                                [<?= (int)$ib['ID'] ?>] <?= htmlspecialcharsbx($ib['NAME']) ?> (<?= htmlspecialcharsbx($ib['IBLOCK_TYPE_ID']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <td>OFFERS_IBLOCK_ID (SKU)</td>
                <td>
                    <select name="OFFERS_IBLOCK_ID" style="min-width: 340px;">
                        <option value="0">— Не выбрано —</option>
                        <?php foreach ($iblocks as $ib): ?>
                            <option value="<?= (int)$ib['ID'] ?>"<?= ((int)$ib['ID'] === $selectedOffersIblockId ? ' selected' : '') ?>>
                                [<?= (int)$ib['ID'] ?>] <?= htmlspecialcharsbx($ib['NAME']) ?> (<?= htmlspecialcharsbx($ib['IBLOCK_TYPE_ID']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <p style="margin-top:8px; color:#6b6b6b;">
            Значения предзаполнены по автоопределению. При необходимости можно выбрать вручную.
        </p>

        <input type="submit" value="Установить" class="adm-btn-save">
    </form>
<?php else: ?>
    <div class="adm-info-message-wrap success">
        <div class="adm-info-message">Модуль <?= htmlspecialcharsbx($moduleId) ?> успешно установлен.</div>
    </div>
    <p><a href="/bitrix/admin/settings.php?mid=<?= urlencode($moduleId) ?>&lang=<?= LANGUAGE_ID ?>">Перейти в настройки</a></p>
    <p><a href="/bitrix/admin/partner_modules.php?lang=<?= LANGUAGE_ID ?>">К списку модулей</a></p>
<?php endif; ?>
