<?php

if (!function_exists('frontcalc_render_runtime_assets')) {
    function frontcalc_render_runtime_assets(): string
    {
        static $isRendered = false;
        if ($isRendered) {
            return '';
        }
        $isRendered = true;

        $moduleScriptPath = '/local/modules/prospektweb.frontcalc/assets/js/frontcalc-jqm-popup.js';
        $moduleStylePath = '/local/modules/prospektweb.frontcalc/assets/css/frontcalc-jqm-popup.css';

        return str_replace(
            ['{{MODULE_SCRIPT_PATH}}', '{{MODULE_STYLE_PATH}}'],
            [$moduleScriptPath, $moduleStylePath],
            <<<'HTML'
<link rel="stylesheet" href="{{MODULE_STYLE_PATH}}">
<script src="{{MODULE_SCRIPT_PATH}}"></script>
HTML
        );
    }
}

if (!function_exists('frontcalc_get_light_payload')) {
    function frontcalc_get_light_payload(int $productId, int $iblockId, string $ajaxUrl = ''): array
    {
        $serviceClass = '\\Prospektweb\\Frontcalc\\Service\\CalculatorAvailability';

        if (!class_exists($serviceClass)) {
            if (class_exists('\\Bitrix\\Main\\Loader')) {
                \Bitrix\Main\Loader::includeModule('prospektweb.frontcalc');
            } elseif (class_exists('\\CModule')) {
                \CModule::IncludeModule('prospektweb.frontcalc');
            }
        }

        if (!class_exists($serviceClass)) {
            return [
                'is_available' => $productId > 0,
                'product_id' => $productId,
                'ajax_url' => $ajaxUrl !== '' ? $ajaxUrl : '/local/ajax/frontcalc.php',
            ];
        }

        $service = new $serviceClass();

        return $service->getLightPayload($productId, $iblockId, $ajaxUrl);
    }
}
