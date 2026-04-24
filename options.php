<?php

use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

$moduleId = 'prospektweb.frontcalc';

if ($REQUEST_METHOD === 'POST' && check_bitrix_sessid()) {
    if (isset($_POST['RestoreDefaults']) && $_POST['RestoreDefaults'] === 'Y') {
        COption::RemoveOption($moduleId);
    }
}
?>
<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>?mid=<?= urlencode($moduleId) ?>&lang=<?= LANGUAGE_ID ?>">
    <?= bitrix_sessid_post(); ?>
    <p><?= htmlspecialcharsbx('Настройки модуля сохраняются автоматически при установке.') ?></p>
    <input type="hidden" name="RestoreDefaults" value="Y">
    <input type="submit" class="adm-btn" value="<?= htmlspecialcharsbx(GetMessage('MAIN_RESTORE_DEFAULTS') ?: 'Сбросить настройки') ?>">
</form>
