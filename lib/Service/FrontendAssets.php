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

        global $USER;

        $isAuthorized = is_object($USER) && method_exists($USER, 'IsAuthorized') && $USER->IsAuthorized();

        $asset = Asset::getInstance();
        $asset->addString('<script>window.FrontcalcAuthConfig=' .
            \CUtil::PhpToJSObject(['isAuthorized' => $isAuthorized], false, true, true) . ';</script>', true);
        $asset->addCss('/local/modules/prospektweb.frontcalc/assets/css/hide-technical-values.css');
        $asset->addCss('/local/modules/prospektweb.frontcalc/assets/css/prices-popup-ext.css');
        $asset->addJs('/local/modules/prospektweb.frontcalc/assets/js/hide-technical-values.js');
        $asset->addJs('/local/modules/prospektweb.frontcalc/assets/js/frontcalc-auth.js');
        $asset->addString('<script>window.myModuleHiddenValueIds=' . 
            \CUtil::PhpToJSObject(array_values($hiddenIds), false, true, true) . ';</script>', true);
    }
}
