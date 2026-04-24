<?php
if (!check_bitrix_sessid()) {
    return;
}
?>
<form action="<?= $APPLICATION->GetCurPage(); ?>">
    <p>Модуль успешно удален.</p>
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID; ?>">
    <input type="submit" name="" value="<?= GetMessage('MOD_BACK') ?: 'Вернуться в список'; ?>">
</form>
