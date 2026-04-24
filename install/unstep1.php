<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @global CMain $APPLICATION */
global $APPLICATION;

$moduleId = 'prospektweb.frontcalc';
?>
<form action="<?= $APPLICATION->GetCurPage() ?>" method="post">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">
    <input type="hidden" name="id" value="<?= htmlspecialcharsbx($moduleId) ?>">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">

    <p><b>Удаление модуля <?= htmlspecialcharsbx($moduleId) ?></b></p>

    <label>
        <input type="checkbox" name="remove_data" value="Y">
        Удалить данные модуля (опции и созданные сущности)
    </label>

    <br><br>
    <input type="submit" value="Удалить" class="adm-btn">
</form>
