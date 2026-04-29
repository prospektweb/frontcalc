<?php

namespace Prospektweb\Frontcalc\Admin;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

class ProductCardButton
{
    private const MODULE_ID = 'prospektweb.frontcalc';

    public static function onAdminContextMenuShow(&$items)
    {
        [$url, $reason] = self::resolveButtonContext();
        if ($url === null) {
            self::debugSkip('context_menu', $reason);
            return;
        }

        foreach ((array)$items as $item) {
            $link = (string)($item['LINK'] ?? '');
            if ($link !== '' && mb_strpos($link, 'prospektweb_frontcalc_editor.php') !== false) {
                self::debugSkip('context_menu', 'button_exists');
                return;
            }
        }

        $items[] = [
            'TEXT' => 'Настроить калькулятор',
            'TITLE' => 'Настроить калькулятор',
            'LINK' => $url,
            'ONCLICK' => self::buildOnclick($url),
            'ICON' => 'btn_new',
        ];
    }

    public static function onEpilog()
    {
        [$url, $reason] = self::resolveButtonContext();
        if ($url === null) {
            self::debugSkip('epilog', $reason);
            return;
        }

        $debugEnabled = (string)Option::get(self::MODULE_ID, 'ADMIN_BUTTON_DEBUG_LOG', 'N') === 'Y';

        $escapedUrl = \CUtil::JSEscape($url);
        $escapedOnclick = \CUtil::JSEscape(self::buildOnclick($url));
        $debugFlag = $debugEnabled ? 'true' : 'false';

        echo '<script>(function(){'
            . 'var debug=' . $debugFlag . ';'
            . 'if(window.__frontcalcButtonInjected){return;}'
            . 'if(document.querySelector("a[href*=\"prospektweb_frontcalc_editor.php\"]")){if(debug){console.log("[frontcalc] skip: button_exists");}window.__frontcalcButtonInjected=true;return;}'
            . 'var toolbar=document.querySelector(".adm-detail-toolbar .adm-detail-toolbar-right, .adm-detail-toolbar");'
            . 'if(!toolbar){if(debug){console.log("[frontcalc] skip: toolbar_not_found");}return;}'
            . 'var a=document.createElement("a");'
            . 'a.className="adm-btn";'
            . 'a.href="' . $escapedUrl . '";'
            . 'a.title="Настроить калькулятор";'
            . 'a.textContent="Настроить калькулятор";'
            . 'a.onclick=function(){' . $escapedOnclick . '};'
            . 'toolbar.appendChild(a);'
            . 'window.__frontcalcButtonInjected=true;'
            . '})();</script>';
    }

    private static function resolveButtonContext()
    {
        if (!defined('ADMIN_SECTION') || ADMIN_SECTION !== true) {
            return [null, 'not_admin_section'];
        }

        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $isIblockEdit = mb_strpos($script, '/bitrix/admin/iblock_element_edit.php') !== false;
        $isCatalogEdit = mb_strpos($script, '/bitrix/admin/cat_product_edit.php') !== false;
        if (!$isIblockEdit && !$isCatalogEdit) {
            return [null, 'wrong_page'];
        }

        $elementId = (int)($_REQUEST['ID'] ?? 0);
        if ($elementId <= 0) {
            return [null, 'no_element_id'];
        }

        $iblockId = (int)($_REQUEST['IBLOCK_ID'] ?? 0);
        $productsIblockId = (int)Option::get(self::MODULE_ID, 'PRODUCTS_IBLOCK_ID', '0');

        if ($iblockId <= 0 && Loader::includeModule('iblock') && class_exists('\\CIBlockElement')) {
            $iblockId = (int)\CIBlockElement::GetIBlockByID($elementId);
        }

        if ($iblockId <= 0 || $productsIblockId <= 0 || $iblockId !== $productsIblockId) {
            return [null, 'iblock_mismatch'];
        }

        $url = '/bitrix/admin/prospektweb_frontcalc_editor.php?IBLOCK_ID=' . $iblockId . '&ID=' . $elementId . '&lang=' . urlencode((string)($_REQUEST['lang'] ?? 'ru'));

        return [$url, null];
    }

    private static function buildOnclick($url)
    {
        return "if (window.BX && BX.SidePanel && BX.SidePanel.Instance) { BX.SidePanel.Instance.open('{$url}', {cacheable:false, width:980}); return false; } window.location.href='{$url}'; return false;";
    }

    private static function debugSkip($channel, $reason)
    {
        $enabled = (string)Option::get(self::MODULE_ID, 'ADMIN_BUTTON_DEBUG_LOG', 'N') === 'Y';
        if (!$enabled || $reason === null || $reason === '') {
            return;
        }

        AddMessage2Log('[frontcalc][' . $channel . '] skip: ' . $reason, self::MODULE_ID);
    }
}
