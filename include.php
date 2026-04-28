<?php

use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;

Loader::registerAutoloadClasses('prospektweb.frontcalc', [
    '\\Prospektweb\\Frontcalc\\Admin\\ProductCardButton' => 'lib/Admin/ProductCardButton.php',
    '\\Prospektweb\\Frontcalc\\Service\\CalculatorAvailability' => 'lib/Service/CalculatorAvailability.php',
]);

EventManager::getInstance()->addEventHandlerCompatible(
    'main',
    'OnAdminContextMenuShow',
    '\\Prospektweb\\Frontcalc\\Admin\\ProductCardButton',
    'onAdminContextMenuShow'
);
