<?php

namespace Prospektweb\Frontcalc\Admin;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

class ProductCardButton
{
    public static function onAdminContextMenuShow(&$items)
    {
        if (!defined('ADMIN_SECTION') || ADMIN_SECTION !== true) {
            return;
        }

        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        $isIblockEdit = mb_strpos($script, '/bitrix/admin/iblock_element_edit.php') !== false;
        $isCatalogEdit = mb_strpos($script, '/bitrix/admin/cat_product_edit.php') !== false;
        if (!$isIblockEdit && !$isCatalogEdit) {
            return;
        }

        $elementId = (int)($_REQUEST['ID'] ?? 0);
        $iblockId = (int)($_REQUEST['IBLOCK_ID'] ?? 0);
        $productsIblockId = (int)Option::get('prospektweb.frontcalc', 'PRODUCTS_IBLOCK_ID', '0');
        $calcPropertyCode = (string)Option::get('prospektweb.frontcalc', 'CALC_PROPERTY_CODE', 'FRONTCALC_CONFIG');
        $iblockModuleLoaded = Loader::includeModule('iblock');

        if ($elementId > 0 && $iblockId <= 0 && $iblockModuleLoaded && class_exists('\CIBlockElement')) {
            $iblockId = (int)\CIBlockElement::GetIBlockByID($elementId);
        }

        $matchesConfiguredIblock = ($productsIblockId > 0 && $iblockId === $productsIblockId);
        $hasCalculatorProperty = false;

        if ($iblockId > 0 && $calcPropertyCode !== '' && $iblockModuleLoaded && class_exists('\CIBlockProperty')) {
            $property = \CIBlockProperty::GetList(
                ['ID' => 'ASC'],
                ['IBLOCK_ID' => $iblockId, 'CODE' => $calcPropertyCode]
            )->Fetch();
            $hasCalculatorProperty = is_array($property);
        }

        if ($elementId <= 0 || $iblockId <= 0 || (!$matchesConfiguredIblock && !$hasCalculatorProperty)) {
            return;
        }

        $url = '/bitrix/admin/prospektweb_frontcalc_editor.php?IBLOCK_ID=' . $iblockId . '&ID=' . $elementId . '&lang=' . urlencode((string)($_REQUEST['lang'] ?? 'ru'));
        $jsOpen = "if (window.BX && BX.SidePanel && BX.SidePanel.Instance) { BX.SidePanel.Instance.open('{$url}', {cacheable:false, width:980}); return false; } window.location.href='{$url}'; return false;";

        $items[] = [
            'TEXT' => 'Настроить калькулятор',
            'TITLE' => 'Настроить калькулятор',
            'LINK' => $url,
            'ONCLICK' => $jsOpen,
            'ICON' => 'btn_new',
        ];
    }
}
