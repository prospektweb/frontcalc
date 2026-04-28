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
.frontcalc-popup-shell{width:480px;max-width:calc(100vw - 32px);}
.frontcalc-popup-shell.form.popup{display:block;padding:0;background:#fff;}
.frontcalc-popup-shell .form-header{padding:32px 32px 0;}
.frontcalc-popup-shell .form-header .title{margin:0;}
.frontcalc-popup-content{min-height:220px;padding:16px 32px 32px;}
.frontcalc-preloader{display:flex;align-items:center;gap:12px;padding:28px 0;color:#5f6a83;}
.frontcalc-preloader__spinner{width:28px;height:28px;border-radius:50%;border:3px solid rgba(42,101,208,.2);border-top-color:var(--theme-base-color,#2a65d0);animation:frontcalc-spin .8s linear infinite;}
.frontcalc-empty{padding:8px 0;color:#5f6a83;}
.frontcalc-summary{font-size:16px;line-height:24px;color:#555;}
.frontcalc-summary strong{font-weight:600;color:#333;}
.frontcalc-summary ul{margin:8px 0 0;padding-left:18px;}
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

    function appendScript(src){
        if (!src) { return; }
        var script = d.createElement('script');
        script.src = src;
        script.async = true;
        d.head.appendChild(script);
    }

    appendScript(w.FrontcalcPopupConfig.modulePathLocal);
    appendScript(w.FrontcalcPopupConfig.modulePathBitrix);
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
