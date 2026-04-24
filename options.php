<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

$moduleId = 'prospektweb.frontcalc';
Loader::includeModule($moduleId);

/** @global CMain $APPLICATION */
/** @global CUser $USER */
global $APPLICATION, $USER;

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Access denied');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid()) {
    Option::set($moduleId, 'PRODUCTS_IBLOCK_ID', trim((string)($_POST['PRODUCTS_IBLOCK_ID'] ?? '0')));
    Option::set($moduleId, 'OFFERS_IBLOCK_ID', trim((string)($_POST['OFFERS_IBLOCK_ID'] ?? '0')));
    LocalRedirect($APPLICATION->GetCurPage() . '?mid=' . urlencode($moduleId) . '&lang=' . LANGUAGE_ID . '&saved=Y');
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$products = Option::get($moduleId, 'PRODUCTS_IBLOCK_ID', '0');
$offers = Option::get($moduleId, 'OFFERS_IBLOCK_ID', '0');
?>
<?php if (($_GET['saved'] ?? '') === 'Y'): ?>
    <div class="adm-info-message-wrap success"><div class="adm-info-message">Сохранено</div></div>
<?php endif; ?>

<form method="post" action="<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($moduleId) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post(); ?>
    <table class="adm-detail-content-table edit-table">
        <tr>
            <td width="40%">PRODUCTS_IBLOCK_ID</td>
            <td><input type="text" name="PRODUCTS_IBLOCK_ID" value="<?= htmlspecialcharsbx($products) ?>" size="8"></td>
        </tr>
        <tr>
            <td>OFFERS_IBLOCK_ID</td>
            <td><input type="text" name="OFFERS_IBLOCK_ID" value="<?= htmlspecialcharsbx($offers) ?>" size="8"></td>
        </tr>
    </table>
    <input type="submit" value="Сохранить" class="adm-btn-save">
</form>

<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
