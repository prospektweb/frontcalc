<?php

namespace Prospektweb\Frontcalc\Admin;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

class ProductCardButton
{
    protected const MODULE_ID = 'prospektweb.frontcalc';
    protected const BUTTON_ID = 'frontcalc-product-editor-btn';

    public static function onAdminContextMenuShow(&$items)
    {
        $context = static::resolveContext();
        if (!$context['canShow']) {
            static::debug('context menu skipped: ' . $context['reason'], $context);
            return;
        }

        $url = $context['url'];
        $jsOpen = static::buildOpenHandlerJs($url);

        if (is_array($items)) {
            foreach ($items as $item) {
                $itemLink = (string)($item['LINK'] ?? '');
                if (mb_strpos($itemLink, '/bitrix/admin/prospektweb_frontcalc_editor.php') !== false) {
                    static::debug('context menu skipped: duplicate link in items', $context);
                    return;
                }
            }
        }

        $items[] = [
            'TEXT' => 'Настроить калькулятор',
            'TITLE' => 'Настроить калькулятор',
            'LINK' => $url,
            'ONCLICK' => $jsOpen,
            'ICON' => 'btn_new',
        ];

        static::debug('context menu button added', $context);
    }

    public static function onEpilog()
    {
        $context = static::resolveContext();
        if (!$context['canShow']) {
            static::debug('fallback skipped: ' . $context['reason'], $context);
            return;
        }

        global $APPLICATION;
        if (!is_object($APPLICATION) || !method_exists($APPLICATION, 'AddHeadString')) {
            return;
        }

        $script = static::buildFallbackScript($context);
        $APPLICATION->AddHeadString($script, true);
        static::debug('fallback script injected', $context);
    }

    protected static function resolveContext()
    {
        $result = [
            'canShow' => false,
            'reason' => 'unknown',
            'script' => (string)($_SERVER['SCRIPT_NAME'] ?? ''),
            'elementId' => 0,
            'iblockId' => 0,
            'lang' => (string)($_REQUEST['lang'] ?? 'ru'),
            'url' => '',
        ];

        if (!defined('ADMIN_SECTION') || ADMIN_SECTION !== true) {
            $result['reason'] = 'not admin section';
            return $result;
        }

        $isIblockEdit = mb_strpos($result['script'], '/bitrix/admin/iblock_element_edit.php') !== false;
        $isCatalogEdit = mb_strpos($result['script'], '/bitrix/admin/cat_product_edit.php') !== false;
        if (!$isIblockEdit && !$isCatalogEdit) {
            $result['reason'] = 'not a product edit page';
            return $result;
        }

        $elementId = (int)($_REQUEST['ID'] ?? 0);
        $iblockId = (int)($_REQUEST['IBLOCK_ID'] ?? 0);
        $result['elementId'] = $elementId;

        $iblockModuleLoaded = Loader::includeModule('iblock');
        if ($elementId > 0 && $iblockId <= 0 && $iblockModuleLoaded && class_exists('\\CIBlockElement')) {
            $iblockId = (int)\CIBlockElement::GetIBlockByID($elementId);
        }

        $result['iblockId'] = $iblockId;

        if ($elementId <= 0) {
            $result['reason'] = 'empty element id';
            return $result;
        }

        if ($iblockId <= 0) {
            $result['reason'] = 'empty iblock id';
            return $result;
        }

        $productsIblockId = (int)Option::get(self::MODULE_ID, 'PRODUCTS_IBLOCK_ID', '0');
        $calcPropertyCode = (string)Option::get(self::MODULE_ID, 'CALC_PROPERTY_CODE', 'FRONTCALC_CONFIG');
        $strictIblock = (string)Option::get(self::MODULE_ID, 'ADMIN_BUTTON_STRICT_IBLOCK', 'N') === 'Y';

        $matchesConfiguredIblock = ($productsIblockId > 0 && $iblockId === $productsIblockId);
        $hasCalculatorProperty = false;

        if ($iblockId > 0 && $calcPropertyCode !== '' && $iblockModuleLoaded && class_exists('\\CIBlockProperty')) {
            $property = \CIBlockProperty::GetList(
                ['ID' => 'ASC'],
                ['IBLOCK_ID' => $iblockId, 'CODE' => $calcPropertyCode]
            )->Fetch();
            $hasCalculatorProperty = is_array($property);
        }

        if ($strictIblock && !$matchesConfiguredIblock) {
            $result['reason'] = 'strict iblock mode and iblock mismatch';
            return $result;
        }

        if (!$strictIblock && $productsIblockId > 0 && !$matchesConfiguredIblock && !$hasCalculatorProperty) {
            $result['reason'] = 'iblock mismatch and calc property missing';
            return $result;
        }

        $result['url'] = '/bitrix/admin/prospektweb_frontcalc_editor.php?IBLOCK_ID=' . $iblockId
            . '&ID=' . $elementId
            . '&lang=' . urlencode($result['lang']);

        $result['canShow'] = true;
        $result['reason'] = 'ok';

        return $result;
    }

    protected static function buildOpenHandlerJs($url)
    {
        $safeUrl = \CUtil::JSEscape($url);

        return "if (window.BX && BX.SidePanel && BX.SidePanel.Instance) {"
            . "BX.SidePanel.Instance.open('{$safeUrl}', {cacheable:false, width:980}); return false; }"
            . "window.location.href='{$safeUrl}'; return false;";
    }

    protected static function buildFallbackScript(array $context)
    {
        $buttonId = self::BUTTON_ID;
        $title = 'Настроить калькулятор';
        $url = $context['url'];
        $debugEnabled = static::isDebugEnabled();

        $jsonButtonId = json_encode($buttonId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jsonTitle = json_encode($title, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jsonUrl = json_encode($url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $jsonDebug = $debugEnabled ? 'true' : 'false';

        return "<script>(function(){\n"
            . "if (window.__frontcalcButtonScriptLoaded) { return; }\n"
            . "window.__frontcalcButtonScriptLoaded = true;\n"
            . "var buttonId = {$jsonButtonId};\n"
            . "var buttonText = {$jsonTitle};\n"
            . "var editorUrl = {$jsonUrl};\n"
            . "var debug = {$jsonDebug};\n"
            . "var maxAttempts = 8;\n"
            . "var attempts = 0;\n"
            . "var observer = null;\n"
            . "var observerStopTimer = null;\n"
            . "function log(msg){ if (debug && window.console) { console.log('[frontcalc-button] ' + msg); } }\n"
            . "function openEditor(){ if (window.BX && BX.SidePanel && BX.SidePanel.Instance) { BX.SidePanel.Instance.open(editorUrl, {cacheable:false, width:980}); return false; } window.location.href = editorUrl; return false; }\n"
            . "function hasAnyEditorButton(){ return !!document.querySelector('#' + buttonId + ', a[href*=\"prospektweb_frontcalc_editor.php\"]'); }\n"
            . "function findToolbar(){\n"
            . "  var selectors = [\n"
            . "    '#bx-admin-context-toolbar',\n"
            . "    '.adm-detail-toolbar',\n"
            . "    '.ui-btn-container',\n"
            . "    '.main-ui-toolbar',\n"
            . "    '[data-role=\"bx-ui-toolbar\"]'\n"
            . "  ];\n"
            . "  for (var i = 0; i < selectors.length; i++) {\n"
            . "    var node = document.querySelector(selectors[i]);\n"
            . "    if (node) { return node; }\n"
            . "  }\n"
            . "  return null;\n"
            . "}\n"
            . "function ensureButton(){\n"
            . "  if (hasAnyEditorButton()) { log('button already exists'); return true; }\n"
            . "  var toolbar = findToolbar();\n"
            . "  if (!toolbar) { log('toolbar not found'); return false; }\n"
            . "  var button = document.createElement('a');\n"
            . "  button.id = buttonId;\n"
            . "  button.href = editorUrl;\n"
            . "  button.className = 'adm-btn';\n"
            . "  button.textContent = buttonText;\n"
            . "  button.onclick = function(e){ if (e && e.preventDefault) { e.preventDefault(); } return openEditor(); };\n"
            . "  toolbar.appendChild(button);\n"
            . "  log('fallback button appended');\n"
            . "  return true;\n"
            . "}\n"
            . "function runWithRetry(){\n"
            . "  attempts++;\n"
            . "  if (ensureButton()) { return; }\n"
            . "  if (attempts >= maxAttempts) { log('max attempts reached'); return; }\n"
            . "  setTimeout(runWithRetry, attempts < 3 ? 250 : 900);\n"
            . "}\n"
            . "function initObserver(){\n"
            . "  if (observer || typeof MutationObserver === 'undefined') { return; }\n"
            . "  var target = findToolbar() || document.body;\n"
            . "  if (!target) { return; }\n"
            . "  observer = new MutationObserver(function(){ ensureButton(); });\n"
            . "  observer.observe(target, {childList: true, subtree: true});\n"
            . "  observerStopTimer = setTimeout(function(){ if (observer) { observer.disconnect(); observer = null; log('observer stopped'); } }, 20000);\n"
            . "  log('observer started');\n"
            . "}\n"
            . "if (document.readyState === 'loading') {\n"
            . "  document.addEventListener('DOMContentLoaded', function(){ runWithRetry(); initObserver(); });\n"
            . "} else {\n"
            . "  runWithRetry(); initObserver();\n"
            . "}\n"
            . "window.addEventListener('beforeunload', function(){ if (observer) { observer.disconnect(); } if (observerStopTimer) { clearTimeout(observerStopTimer); } });\n"
            . "})();</script>";
    }

    protected static function isDebugEnabled()
    {
        $optionDebug = (string)Option::get(self::MODULE_ID, 'ADMIN_BUTTON_DEBUG', 'N') === 'Y';
        $requestDebug = (string)($_REQUEST['frontcalc_button_debug'] ?? '') === 'Y';

        return $optionDebug || $requestDebug;
    }

    protected static function debug($message, array $context = [])
    {
        if (!static::isDebugEnabled()) {
            return;
        }

        $parts = ['[frontcalc-button] ' . $message];
        $summary = [
            'script' => (string)($context['script'] ?? ''),
            'ID' => (int)($context['elementId'] ?? 0),
            'IBLOCK_ID' => (int)($context['iblockId'] ?? 0),
            'reason' => (string)($context['reason'] ?? ''),
        ];
        $parts[] = 'context=' . json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        AddMessage2Log(implode(' ', $parts), self::MODULE_ID);
    }
}
