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
        button.classList.add('is-frontcalc-loading');
        button.setAttribute('aria-busy', 'true');
        if (!button.querySelector('.frontcalc-button-spinner')) {
            var spinner = d.createElement('span');
            spinner.className = 'frontcalc-button-spinner';
            spinner.setAttribute('aria-hidden', 'true');
            button.appendChild(spinner);
        }
        ensureFrontcalcModule(function(){
            if (button && typeof button.click === 'function') {
                button.click();
            }
        });
    }, true);
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
