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

        return str_replace(
            ['{{MODULE_SCRIPT_PATH}}'],
            [$moduleScriptPath],
            <<<'HTML'
<style>
.frontcalc-calculate-button{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:auto;
    flex:0 0 auto;
    margin:0 8px 0 0;
    border:2px solid var(--theme-base-color,#2a65d0);
    background:#fff;
    color:var(--theme-base-color,#2a65d0);
    font-weight:600;
    white-space:nowrap;
    vertical-align:top;
    cursor:pointer;
    transition:color .2s ease,background-color .2s ease,border-color .2s ease,box-shadow .2s ease;
    box-shadow:none;
}
.frontcalc-calculate-button:hover,
.frontcalc-calculate-button:focus,
.frontcalc-calculate-button:active{
    background:var(--theme-base-color,#2a65d0);
    border-color:var(--theme-base-color,#2a65d0);
    color:var(--button_color_text,#fff);
}
.frontcalc-calculate-button:disabled{opacity:.65;cursor:wait;}
#popup_iframe_wrapper .frontcalc_frame{width:min(1320px,calc(100vw - 32px)) !important;max-width:calc(100vw - 32px) !important;}
#popup_iframe_wrapper .frontcalc_frame .scrollbar{max-height:calc(100vh - 40px);overflow:auto;}
.frontcalc-popup-shell{width:100%;max-width:100%;box-sizing:border-box;}
.frontcalc-popup-shell.form.popup{display:block;padding:0;background:#fff;}
.frontcalc-popup-content{min-height:220px;padding:24px;}
.frontcalc-preloader{display:flex;align-items:center;gap:12px;padding:28px 0;color:#5f6a83;}
.frontcalc-preloader__spinner{width:28px;height:28px;border-radius:50%;border:3px solid rgba(42,101,208,.2);border-top-color:var(--theme-base-color,#2a65d0);animation:frontcalc-spin .8s linear infinite;}
.frontcalc-empty{padding:8px 0;color:#5f6a83;}
.frontcalc-summary{font-size:16px;line-height:24px;color:#555;}
.frontcalc-summary strong{font-weight:600;color:#333;}
.frontcalc-summary ul{margin:8px 0 0;padding-left:18px;}
.frontcalc-layout{display:grid;grid-template-columns:minmax(0,2fr) minmax(320px,1fr);gap:20px;align-items:start;}
.frontcalc-selectors{display:flex;flex-direction:column;gap:20px;}
.frontcalc-field{display:flex;flex-direction:column;gap:8px;}
.frontcalc-field__title{font-size:16px;line-height:1.35;color:#2a3348;}
.frontcalc-input-control{display:flex;align-items:center;max-width:200px;border:1px solid #d9dee7;border-radius:8px;overflow:hidden;}
.frontcalc-input-control-wrap{display:flex;align-items:center;gap:8px;}
.frontcalc-input-unit{font-size:14px;line-height:1;color:#5f6a83;white-space:nowrap;}
.frontcalc-step-btn{width:38px;height:38px;border:0;background:#f7f8fb;font-size:20px;line-height:1;color:#4d5b76;cursor:pointer;}
.frontcalc-num-input{flex:1;min-width:0;height:38px;border:0;text-align:center;font-size:18px;font-weight:600;color:#1a2236;}
.frontcalc-presets{display:flex;flex-wrap:wrap;gap:8px;}
.frontcalc-chip{min-height:34px;padding:6px 12px;border:1px solid #d9dee7;border-radius:8px;background:#fff;color:#1a2236;font-size:14px;line-height:1.2;cursor:pointer;}
.frontcalc-chip:hover{border-color:#2f3a52;}
.frontcalc-chip.is-active{border-color:#2f3a52;box-shadow:inset 0 0 0 1px #2f3a52;}
.frontcalc-chip.is-disabled,.frontcalc-chip:disabled{opacity:.45;cursor:not-allowed;border-color:#d9dee7;box-shadow:none;}
.frontcalc-input-group{display:flex;flex-wrap:wrap;gap:12px;}
.frontcalc-input-group-divider{display:inline-flex;align-items:center;justify-content:center;min-height:38px;font-size:20px;line-height:1;color:#5f6a83;}
.frontcalc-input-group .frontcalc-field__title{font-size:20px;color:#5f6a83;}
.frontcalc-price-panel__inner{position:sticky;top:12px;border:1px solid #d9dee7;border-radius:12px;background:#fafbff;padding:16px;}
.frontcalc-price-groups{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;}
.frontcalc-price-group{min-height:34px;padding:6px 12px;border:1px solid #d9dee7;border-radius:999px;background:#fff;color:#33405a;font-size:14px;line-height:1.2;cursor:pointer;}
.frontcalc-price-group:hover{border-color:#2f3a52;}
.frontcalc-price-group.is-active{border-color:#2f3a52;background:#101933;color:#fff;font-weight:600;}
.frontcalc-volume-input{display:flex;gap:8px;align-items:center;margin-bottom:12px;}
.frontcalc-table-input{width:120px;height:44px;border:1px solid #d9dee7;border-radius:10px;padding:0 12px;font-size:22px;font-weight:600;}
.frontcalc-volume-btns{display:flex;gap:6px;}
.frontcalc-volume-btn {
    width: 44px;
    height: -webkit-fill-available;
    border: none;
    background: #f2f4f8;
    font-size: 24px;
    cursor: pointer;
}
.frontcalc-table-head{display:grid;grid-template-columns:1.1fr 1fr 1fr;gap:8px;margin-bottom:8px;font-weight:600;color:#1a2236;}
.frontcalc-table-head>div{display:flex;align-items:center;gap:6px;}
.frontcalc-tip svg{fill:#8591aa;}
.frontcalc-table-body{display:flex;flex-direction:column;gap:6px;max-height:320px;overflow:auto;}
.frontcalc-table-row{display:grid;grid-template-columns:1.1fr 1fr 1fr;gap:8px;}
.frontcalc-cell{border:1px solid #d9dee7;border-radius:10px;background:#fff;min-height:52px;padding:6px 10px;display:flex;flex-direction:column;align-items:flex-start;justify-content:center;cursor:pointer;}
.frontcalc-cell-main{font-size:16px;line-height:1.2;color:#212a3f;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.frontcalc-cell-sub{font-size:12px;color:#8b93a6;margin-top:2px;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.frontcalc-table-row:hover .frontcalc-cell,.frontcalc-cell:hover{border-color:#4f7bd9;background:#f8fbff;}
.frontcalc-table-row.is-selected .frontcalc-cell{border-color:#2f3a52;box-shadow:inset 0 0 0 1px #2f3a52;}
.frontcalc-cell.is-hover-row,.frontcalc-cell.is-hover-col{border-color:#4f7bd9;background:#f8fbff;}
.frontcalc-cell.is-picked{border-color:#2f3a52 !important;box-shadow:inset 0 0 0 1px #2f3a52;}

.frontcalc-price-value{font-size:34px;font-weight:700;line-height:1.1;color:#101933;}
.frontcalc-price-offer{margin-top:6px;font-size:15px;color:#5f6a83;}
.frontcalc-price-meta{margin-top:12px;font-size:13px;color:#8b93a6;}
.frontcalc-price-empty{font-size:14px;color:#8b93a6;}
@media (max-width: 991px){
    .frontcalc-layout{grid-template-columns:1fr;}
    .frontcalc-price-panel{order:-1;}
    .frontcalc-field__title{font-size:15px;}
    .frontcalc-chip{font-size:13px;min-height:32px;padding:4px 10px;}
    .frontcalc-num-input{font-size:16px;}
}
@keyframes frontcalc-spin{to{transform:rotate(360deg);}}
</style>
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

if (!function_exists('frontcalc_render_detail_button')) {
    function frontcalc_render_detail_button(int $productId, int $iblockId, string $ajaxUrl = ''): string
    {
        return frontcalc_render_calculate_button($productId, $iblockId, 'Рассчитать стоимость', $ajaxUrl, 'btn-elg');
    }
}
