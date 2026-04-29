<?php

use Bitrix\Main\Loader;

$moduleId = 'prospektweb.frontcalc';
$moduleBasePath = null;
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\') : '';

if ($docRoot !== '') {
    $localModulePath = $docRoot . '/local/modules/' . $moduleId;
    $bitrixModulePath = $docRoot . '/bitrix/modules/' . $moduleId;

    if (is_dir($localModulePath)) {
        $moduleBasePath = $localModulePath;
    } elseif (is_dir($bitrixModulePath)) {
        $moduleBasePath = $bitrixModulePath;
    }
}

if ($moduleBasePath === null) {
    $moduleBasePath = __DIR__;
}

Loader::registerAutoLoadClasses($moduleId, [
    'Prospektweb\\Frontcalc\\Admin\\ProductCardButton' => $moduleBasePath . '/lib/Admin/ProductCardButton.php',
    'Prospektweb\\Frontcalc\\Service\\CalculatorAvailability' => $moduleBasePath . '/lib/Service/CalculatorAvailability.php',
]);
