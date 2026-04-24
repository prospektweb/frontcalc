<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}
$moduleId = 'prospektweb.frontcalc';
?>
<div class="adm-info-message-wrap success">
    <div class="adm-info-message">
        Модуль <?= htmlspecialcharsbx($moduleId) ?> удалён.
    </div>
</div>
<p><a href="/bitrix/admin/partner_modules.php?lang=<?= LANGUAGE_ID ?>">К списку модулей</a></p>
