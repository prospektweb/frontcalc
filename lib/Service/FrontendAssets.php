<?php

namespace Prospektweb\Frontcalc\Service;

use Bitrix\Main\Page\Asset;

class FrontendAssets
{
    public static function onEpilog(): void
    {
        if (defined('ADMIN_SECTION') && ADMIN_SECTION === true) {
            return;
        }

        $config = new ModuleConfig();
        $hiddenIds = $config->getHiddenOfferValueIds();

        $asset = Asset::getInstance();
        $asset->addCss('/local/modules/prospektweb.frontcalc/assets/css/hide-technical-values.css');
        $asset->addJs('/local/modules/prospektweb.frontcalc/assets/js/hide-technical-values.js');
        $asset->addString('<script>window.myModuleHiddenValueIds=' . 
            \CUtil::PhpToJSObject(array_values($hiddenIds), false, true, true) . ';</script>', true);
    }
}
