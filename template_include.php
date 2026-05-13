<?php

if (!function_exists('frontcalc_can_use_config_from_result')) {
    function frontcalc_can_use_config_from_result(array $arResult): bool
    {
        $moduleId = 'prospektweb.frontcalc';
        $propertyCode = 'FRONTCALC_CONFIG';

        if (class_exists('\Bitrix\Main\Config\Option')) {
            $propertyCode = trim((string)\Bitrix\Main\Config\Option::get($moduleId, 'CALC_PROPERTY_CODE', $propertyCode));
        }

        if ($propertyCode === '') {
            return false;
        }

        $property = $arResult['PROPERTIES'][$propertyCode] ?? null;
        if (!is_array($property)) {
            return false;
        }

        $rawSchema = '';
        foreach ([
            ['~VALUE', 'TEXT'],
            ['VALUE', 'TEXT'],
            ['~VALUE', null],
            ['VALUE', null],
        ] as $path) {
            $key = $path[0];
            $textKey = $path[1];

            if (!array_key_exists($key, $property)) {
                continue;
            }

            $value = $property[$key];
            if ($textKey !== null) {
                if (!is_array($value) || !array_key_exists($textKey, $value)) {
                    continue;
                }
                $value = $value[$textKey];
            }

            if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $rawSchema = trim((string)$value);
                if ($rawSchema !== '') {
                    break;
                }
            }
        }

        if ($rawSchema === '') {
            return false;
        }

        $config = json_decode($rawSchema, true);
        if (!is_array($config)) {
            return false;
        }

        $fields = $config['fields'] ?? null;
        if (!is_array($fields) || empty($fields)) {
            return false;
        }

        $hasPropertyCode = false;
        $hasRequiredVolume = false;
        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $fieldPropertyCode = trim((string)($field['property_code'] ?? ''));
            if ($fieldPropertyCode === '') {
                continue;
            }

            $hasPropertyCode = true;
            if ($fieldPropertyCode === 'CALC_PROP_VOLUME') {
                $hasRequiredVolume = true;
            }
        }

        return $hasPropertyCode && $hasRequiredVolume;
    }
}

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
<script>
(function(w, d){
    if (w.__frontcalcPopupLoaderReady) { return; }
    w.__frontcalcPopupLoaderReady = true;

    w.FrontcalcPopupConfig = {
        modulePath: '{{MODULE_SCRIPT_PATH}}',
        jqModalPath: (w.arAsproOptions && w.arAsproOptions.SITE_TEMPLATE_PATH ? w.arAsproOptions.SITE_TEMPLATE_PATH + '/js/jqModal.js' : '/bitrix/modules/aspro.popup/install/js/jqModal.js')
    };

    function appendScript(src, onload, onerror){
        if (!src) { return; }
        var script = d.createElement('script');
        script.src = src;
        script.async = true;
        if (onload) { script.onload = onload; }
        if (onerror) { script.onerror = onerror; }
        d.head.appendChild(script);
    }

    function findButtonTarget(node){
        var current = node;
        while (current && current !== d) {
            if (current.classList && current.classList.contains('js-frontcalc-calculate')) {
                return current;
            }
            current = current.parentNode;
        }
        return null;
    }

    function hasInlineTargets(){
        return !!(d.querySelector && d.querySelector('.js-frontcalc-inline[data-frontcalc-mode="detail"]'));
    }

    function initInlineTargets(){
        if (!hasInlineTargets()) { return; }
        ensureFrontcalcModule(function(){
            if (w.FrontcalcCalculator && typeof w.FrontcalcCalculator.initInline === 'function') {
                w.FrontcalcCalculator.initInline();
            }
        });
    }

    var isModuleLoaded = false;
    var isModuleLoading = false;
    var loadQueue = [];

    function flushQueue(){
        var callbacks = loadQueue.slice();
        loadQueue = [];
        for (var i = 0; i < callbacks.length; i++) {
            try { callbacks[i](); } catch (e) {}
        }
    }

    function ensureFrontcalcModule(onReady){
        if (isModuleLoaded) {
            onReady && onReady();
            return;
        }
        if (onReady) {
            loadQueue.push(onReady);
        }
        if (isModuleLoading) {
            return;
        }
        isModuleLoading = true;
        appendScript(
            w.FrontcalcPopupConfig.modulePath,
            function(){
                isModuleLoading = false;
                isModuleLoaded = true;
                flushQueue();
            },
            function(){
                isModuleLoading = false;
                flushQueue();
            }
        );
    }

    d.addEventListener('click', function(event){
        var button = findButtonTarget(event.target);
        if (!button || isModuleLoaded) {
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        ensureFrontcalcModule(function(){
            if (button && typeof button.click === 'function') {
                button.click();
            }
        });
    }, true);

    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', initInlineTargets);
    } else {
        initInlineTargets();
    }
})(window, document);
</script>
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

if (!function_exists('frontcalc_render_calculate_button')) {
    function frontcalc_render_calculate_button(int $productId, int $iblockId, string $caption = 'Рассчитать стоимость', string $ajaxUrl = '', string $sizeClass = 'btn-lg'): string
    {
        $payload = frontcalc_get_light_payload($productId, $iblockId, $ajaxUrl);

        if (empty($payload['is_available'])) {
            return '';
        }

        $sizeClass = trim(preg_replace('/[^a-zA-Z0-9_-]+/', ' ', $sizeClass));
        if ($sizeClass === '') {
            $sizeClass = 'btn-lg';
        }

        return frontcalc_render_runtime_assets() . sprintf(
            '<button type="button" class="btn btn-default btn-transparent-bg %s frontcalc-calculate-button js-frontcalc-calculate" data-frontcalc-product-id="%d" data-frontcalc-ajax-url="%s">%s</button>',
            htmlspecialcharsbx($sizeClass),
            (int)$payload['product_id'],
            htmlspecialcharsbx((string)$payload['ajax_url']),
            htmlspecialcharsbx($caption)
        );
    }
}

if (!function_exists('frontcalc_render_catalog_button')) {
    function frontcalc_render_catalog_button(int $productId, int $iblockId, string $ajaxUrl = ''): string
    {
        return frontcalc_render_calculate_button($productId, $iblockId, 'Рассчитать стоимость', $ajaxUrl, 'btn-lg');
    }
}

if (!function_exists('frontcalc_render_detail_inline')) {
    function frontcalc_render_detail_inline(int $productId, int $iblockId, string $ajaxUrl = ''): string
    {
        unset($iblockId);

        $productId = max(0, $productId);
        $ajaxUrl = trim($ajaxUrl);
        if ($ajaxUrl === '') {
            $ajaxUrl = '/local/ajax/frontcalc.php';
        }

        return frontcalc_render_runtime_assets() . sprintf(
            '<div class="frontcalc-inline js-frontcalc-inline" data-frontcalc-product-id="%d" data-frontcalc-ajax-url="%s" data-frontcalc-mode="detail"></div>',
            $productId,
            htmlspecialcharsbx($ajaxUrl)
        );
    }
}

if (!function_exists('frontcalc_render_detail_button')) {
    function frontcalc_render_detail_button(int $productId, int $iblockId, string $ajaxUrl = ''): string
    {
        return frontcalc_render_calculate_button($productId, $iblockId, 'Рассчитать стоимость', $ajaxUrl, 'btn-elg');
    }
}
