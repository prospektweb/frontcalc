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
if (\Bitrix\Main\Loader::includeModule('iblock')) {
    $rs = \CIBlock::GetList(['SORT' => 'ASC'], ['ACTIVE' => 'Y']);
    while ($row = $rs->Fetch()) {
        $iblocks[] = $row;
    }
}
?>
<?php if (!$isDone): ?>
    <form action="<?= $APPLICATION->GetCurPage() ?>" method="post">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
        <input type="hidden" name="id" value="<?= htmlspecialcharsbx($moduleId) ?>">
        <input type="hidden" name="install" value="Y">
        <input type="hidden" name="step" value="2">

        <p><b>Установка модуля <?= htmlspecialcharsbx($moduleId) ?></b></p>
        <p>Можно оставить поля пустыми — установщик попробует автоопределить ID.</p>

        <table class="adm-detail-content-table edit-table">
            <tr>
                <td width="40%">PRODUCTS_IBLOCK_ID (товары)</td>
                <td>
                    <input type="text" name="PRODUCTS_IBLOCK_ID" value="" size="8">
                </td>
            </tr>
            <tr>
                <td>OFFERS_IBLOCK_ID (SKU)</td>
                <td>
                    <input type="text" name="OFFERS_IBLOCK_ID" value="" size="8">
                </td>
            </tr>
        </table>

        <?php if (!empty($iblocks)): ?>
            <p><small>Активные инфоблоки:</small></p>
            <ul>
                <?php foreach ($iblocks as $ib): ?>
                    <li>[<?= (int)$ib['ID'] ?>] <?= htmlspecialcharsbx($ib['NAME']) ?> (<?= htmlspecialcharsbx($ib['IBLOCK_TYPE_ID']) ?>)</li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <input type="submit" value="Установить" class="adm-btn-save">
    </form>
<?php else: ?>
    <div class="adm-info-message-wrap success">
        <div class="adm-info-message">Модуль <?= htmlspecialcharsbx($moduleId) ?> успешно установлен.</div>
    </div>
    <p><a href="/bitrix/admin/settings.php?mid=<?= urlencode($moduleId) ?>&lang=<?= LANGUAGE_ID ?>">Перейти в настройки</a></p>
    <p><a href="/bitrix/admin/partner_modules.php?lang=<?= LANGUAGE_ID ?>">К списку модулей</a></p>
<?php endif; ?>
