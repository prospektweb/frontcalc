<?php

use Prospektweb\Frontcalc\Service\CalculatorAvailability;

if (!function_exists('frontcalc_render_runtime_assets')) {
    function frontcalc_render_runtime_assets(): string
    {
        static $isRendered = false;
        if ($isRendered) {
            return '';
        }
        $isRendered = true;

        return <<<'HTML'
<style>
.frontcalc-calculate-button{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:100%;
    margin-top:10px;
    min-height:52px;
    padding:0 24px;
    border-radius:var(--theme-button-border-radius,8px);
    border:2px solid var(--theme-base-color,#2a65d0);
    background:#fff;
    color:var(--theme-base-color,#2a65d0);
    font-size:1rem;
    font-weight:600;
    line-height:1.2;
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
.frontcalc-popup-shell{width:1320px;max-width:calc(100vw - 32px);}
.frontcalc-popup-shell.form.popup{display:block;padding:0;background:#fff;}
.frontcalc-popup-content{min-height:220px;padding:24px;}
.frontcalc-preloader{display:flex;align-items:center;gap:12px;padding:28px 0;color:#5f6a83;}
.frontcalc-preloader__spinner{width:28px;height:28px;border-radius:50%;border:3px solid rgba(42,101,208,.2);border-top-color:var(--theme-base-color,#2a65d0);animation:frontcalc-spin .8s linear infinite;}
.frontcalc-empty{padding:8px 0;color:#5f6a83;}
.frontcalc-summary{font-size:16px;line-height:24px;color:#555;}
.frontcalc-summary strong{font-weight:600;color:#333;}
.frontcalc-summary ul{margin:8px 0 0;padding-left:18px;}
.frontcalc-layout{display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:20px;align-items:start;}
.frontcalc-selectors{display:flex;flex-direction:column;gap:20px;}
.frontcalc-field{display:flex;flex-direction:column;gap:8px;}
.frontcalc-field__title{font-size:16px;line-height:1.35;color:#2a3348;}
.frontcalc-input-control{display:flex;align-items:center;max-width:200px;border:1px solid #d9dee7;border-radius:8px;overflow:hidden;}
.frontcalc-step-btn{width:38px;height:38px;border:0;background:#f7f8fb;font-size:20px;line-height:1;color:#4d5b76;cursor:pointer;}
.frontcalc-num-input{flex:1;min-width:0;height:38px;border:0;text-align:center;font-size:18px;font-weight:600;color:#1a2236;}
.frontcalc-presets{display:flex;flex-wrap:wrap;gap:8px;}
.frontcalc-chip{min-height:34px;padding:6px 12px;border:1px solid #d9dee7;border-radius:8px;background:#fff;color:#1a2236;font-size:14px;line-height:1.2;cursor:pointer;}
.frontcalc-chip:hover{border-color:#2f3a52;}
.frontcalc-chip.is-active{border-color:#2f3a52;box-shadow:inset 0 0 0 1px #2f3a52;}
.frontcalc-input-group{display:flex;flex-wrap:wrap;gap:12px;}
.frontcalc-input-group .frontcalc-field__title{font-size:20px;color:#5f6a83;}
.frontcalc-price-panel__inner{position:sticky;top:12px;border:1px solid #d9dee7;border-radius:12px;background:#fafbff;padding:16px;}
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
        modulePathLocal: '/local/modules/prospektweb.frontcalc/assets/js/frontcalc-jqm-popup.js',
        modulePathBitrix: '/bitrix/modules/prospektweb.frontcalc/assets/js/frontcalc-jqm-popup.js',
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

    appendScript(
        w.FrontcalcPopupConfig.modulePathLocal,
        null,
        function(){ appendScript(w.FrontcalcPopupConfig.modulePathBitrix); }
    );
})(window, document);
</script>
HTML;
    }
}

if (!function_exists('frontcalc_get_light_payload')) {
    function frontcalc_get_light_payload(int $productId, int $iblockId, string $ajaxUrl = ''): array
    {
        $service = new CalculatorAvailability();

        return $service->getLightPayload($productId, $iblockId, $ajaxUrl);
    }
}

if (!function_exists('frontcalc_render_calculate_button')) {
    function frontcalc_render_calculate_button(int $productId, int $iblockId, string $caption = 'Рассчитать стоимость', string $ajaxUrl = ''): string
    {
        $payload = frontcalc_get_light_payload($productId, $iblockId, $ajaxUrl);

        if (empty($payload['is_available'])) {
            return '';
        }

        return frontcalc_render_runtime_assets() . sprintf(
            '<button type="button" class="btn btn-default btn-transparent-bg btn-wide frontcalc-calculate-button js-frontcalc-calculate" data-frontcalc-product-id="%d" data-frontcalc-ajax-url="%s">%s</button>',
            (int)$payload['product_id'],
            htmlspecialcharsbx((string)$payload['ajax_url']),
            htmlspecialcharsbx($caption)
        );
    }
}

if (!function_exists('frontcalc_render_catalog_button')) {
    function frontcalc_render_catalog_button(int $productId, int $iblockId, string $ajaxUrl = ''): string
    {
        return frontcalc_render_calculate_button($productId, $iblockId, 'Рассчитать стоимость', $ajaxUrl);
    }
}

if (!function_exists('frontcalc_render_detail_button')) {
    function frontcalc_render_detail_button(int $productId, int $iblockId, string $ajaxUrl = ''): string
    {
        return frontcalc_render_calculate_button($productId, $iblockId, 'Рассчитать стоимость', $ajaxUrl);
    }
}
