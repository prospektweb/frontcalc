<?php

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

global $APPLICATION, $USER;

if (!$USER->IsAdmin()) {
    $APPLICATION->AuthForm('Access denied');
}

if (!Loader::includeModule('iblock')) {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    echo '<div class="adm-info-message-wrap"><div class="adm-info-message">Модуль iblock не подключен.</div></div>';
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    return;
}

$moduleId = 'prospektweb.frontcalc';
$elementId = (int)($_REQUEST['ID'] ?? 0);
$iblockId = (int)($_REQUEST['IBLOCK_ID'] ?? 0);
$propertyCode = 'FRONTCALC_CONFIG';

$schema = '';
$propertyRes = CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => $propertyCode]);
$property = $propertyRes->Fetch();
$propertyId = (int)($property['ID'] ?? 0);

if ($elementId > 0 && $propertyId > 0) {
    $propValueRes = CIBlockElement::GetProperty($iblockId, $elementId, [], ['ID' => $propertyId]);
    if ($propValue = $propValueRes->Fetch()) {
        $schema = (string)($propValue['VALUE']['TEXT'] ?? '');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_bitrix_sessid() && $elementId > 0 && $iblockId > 0 && $propertyCode !== '') {
    $schema = trim((string)($_POST['CALC_EDITOR_SCHEMA'] ?? ''));

    CIBlockElement::SetPropertyValuesEx($elementId, $iblockId, [
        $propertyCode => [
            'VALUE' => [
                'TYPE' => 'HTML',
                'TEXT' => $schema,
            ],
        ],
    ]);

    LocalRedirect($APPLICATION->GetCurPageParam('saved=Y', ['saved']));
}

$defaultSchema = Option::get($moduleId, 'CALC_EDITOR_SCHEMA', '');
if ($schema === '' && $defaultSchema !== '') {
    $schema = $defaultSchema;
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>
<?php if (($_GET['saved'] ?? '') === 'Y'): ?>
    <div class="adm-info-message-wrap success"><div class="adm-info-message">Конфигурация сохранена в свойство товара.</div></div>
<?php endif; ?>

<form method="post">
    <?= bitrix_sessid_post() ?>
    <h2>Настроить калькулятор</h2>
    <p>Элемент ID: <b><?= (int)$elementId ?></b>, инфоблок: <b><?= (int)$iblockId ?></b>, свойство: <b><?= htmlspecialcharsbx($propertyCode) ?></b></p>

    <textarea name="CALC_EDITOR_SCHEMA" rows="20" style="width:100%; font-family: monospace;"><?= htmlspecialcharsbx($schema) ?></textarea>

    <p style="margin-top: 12px;">
        <input type="submit" value="Сохранить" class="adm-btn-save">
    </p>
</form>

<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>
