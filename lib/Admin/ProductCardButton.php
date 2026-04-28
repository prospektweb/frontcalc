<?php

namespace Prospektweb\Frontcalc\Admin;

class ProductCardButton
{
    protected const BUTTON_ID = 'pw-frontcalc-btn';

    public static function onAdminContextMenuShow(&$items)
    {
        if (!self::isTargetPage()) {
            return;
        }
        $url = self::getEditorUrl();
        $jsOpen = self::getOpenJs($url);

        $items[] = [
            'TEXT' => 'Настроить калькулятор',
            'TITLE' => 'Настроить калькулятор',
            'LINK' => $url,
            'ONCLICK' => $jsOpen,
            'ICON' => 'btn_new',
        ];
    }

    public static function onEpilog()
    {
        if (!self::isTargetPage()) {
            return;
        }

        global $APPLICATION;
        if (!isset($APPLICATION) || !method_exists($APPLICATION, 'AddHeadString')) {
            return;
        }

        $url = self::getEditorUrl();
        $jsOpen = self::getOpenJs($url);
        $buttonId = self::BUTTON_ID;

        $script = "<script>(function(){"
            . "if(document.getElementById('{$buttonId}')){return;}"
            . "function createBtn(container){"
            . "if(!container||document.getElementById('{$buttonId}')){return;}"
            . "var a=document.createElement('a');"
            . "a.id='{$buttonId}';"
            . "a.href='{$url}';"
            . "a.className='adm-btn';"
            . "a.style.marginLeft='8px';"
            . "a.innerHTML='Настроить калькулятор';"
            . "a.onclick=function(){ {$jsOpen} };"
            . "container.appendChild(a);"
            . "}"
            . "var bar=document.querySelector('.adm-detail-toolbar')||document.querySelector('.adm-context')||document.querySelector('.adm-workarea .adm-btns');"
            . "createBtn(bar);"
            . "})();</script>";

        $APPLICATION->AddHeadString($script, true);
    }

    protected static function isTargetPage(): bool
    {
        if (!defined('ADMIN_SECTION') || ADMIN_SECTION !== true) {
            return false;
        }

        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $isIblockEdit = mb_strpos($script, '/bitrix/admin/iblock_element_edit.php') !== false;
        $isCatalogEdit = mb_strpos($script, '/bitrix/admin/cat_product_edit.php') !== false;
        if (!$isIblockEdit && !$isCatalogEdit) {
            return false;
        }

        $elementId = (int)($_REQUEST['ID'] ?? 0);
        $iblockId = (int)($_REQUEST['IBLOCK_ID'] ?? 0);
        if ($elementId <= 0 || $iblockId <= 0) {
            return false;
        }

        // Кнопка должна быть стабильной и показываться на карточке товара.
        return true;
    }

    protected static function getEditorUrl(): string
    {
        $iblockId = (int)($_REQUEST['IBLOCK_ID'] ?? 0);
        $elementId = (int)($_REQUEST['ID'] ?? 0);
        $lang = urlencode((string)($_REQUEST['lang'] ?? 'ru'));

        return '/bitrix/admin/prospektweb_frontcalc_editor.php?IBLOCK_ID=' . $iblockId . '&ID=' . $elementId . '&lang=' . $lang;
    }

    protected static function getOpenJs(string $url): string
    {
        return "if (window.BX && BX.SidePanel && BX.SidePanel.Instance) { BX.SidePanel.Instance.open('{$url}', {cacheable:false, width:980}); return false; } window.location.href='{$url}'; return false;";
    }
}
