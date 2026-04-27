<?php

namespace Prospektweb\Frontcalc\Admin;

use Bitrix\Main\Config\Option;

class ProductCardButton
{
    public static function onAdminContextMenuShow(&$items)
    {
        if (!defined('ADMIN_SECTION') || ADMIN_SECTION !== true) {
            return;
        }

        $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
        if (mb_strpos($script, '/bitrix/admin/iblock_element_edit.php') === false) {
            return;
        }

        $elementId = (int)($_REQUEST['ID'] ?? 0);
        $iblockId = (int)($_REQUEST['IBLOCK_ID'] ?? 0);
        $productsIblockId = (int)Option::get('prospektweb.frontcalc', 'PRODUCTS_IBLOCK_ID', '0');

        if ($elementId <= 0 || $iblockId <= 0 || $productsIblockId <= 0 || $iblockId !== $productsIblockId) {
            return;
        }

        $url = '/local/modules/prospektweb.frontcalc/admin/editor.php?IBLOCK_ID=' . $iblockId . '&ID=' . $elementId . '&lang=' . urlencode((string)($_REQUEST['lang'] ?? 'ru'));

        $items[] = [
            'TEXT' => 'Настроить калькулятор',
            'TITLE' => 'Настроить калькулятор',
            'LINK' => $url,
            'ICON' => 'btn_new',
        ];
    }
}
