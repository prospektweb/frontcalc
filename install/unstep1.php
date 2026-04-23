<?php
if (!check_bitrix_sessid()) {
    return;
}
?>
<form action="<?= $APPLICATION->GetCurPage(); ?>" method="post">
    <?= bitrix_sessid_post(); ?>
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID; ?>">
    <input type="hidden" name="id" value="<?= htmlspecialcharsbx($this->MODULE_ID); ?>">
    <input type="hidden" name="step" value="2">

    <p>
        <label>
            <input type="checkbox" name="delete_calc_property" value="Y">
            Удалить свойство <?= prospektweb_frontcalc::DEFAULT_PROPERTY_CODE; ?> из инфоблока товаров
        </label>
    </p>

    <input type="submit" name="inst" value="<?= GetMessage('MOD_UNINST_DEL') ?: 'Удалить модуль'; ?>">
</form>
