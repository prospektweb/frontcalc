<?php

use Bitrix\Main\Loader;

Loader::registerAutoloadClasses('prospektweb.frontcalc', [
    '\\Prospektweb\\Frontcalc\\Admin\\ProductCardButton' => 'lib/Admin/ProductCardButton.php',
    '\\Prospektweb\\Frontcalc\\Service\\ModuleConfig' => 'lib/Service/ModuleConfig.php',
    '\\Prospektweb\\Frontcalc\\Service\\FrontendAssets' => 'lib/Service/FrontendAssets.php',
]);
