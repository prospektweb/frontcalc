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

        return '<script>(function(){if(window.__frontcalcReady){return;}window.__frontcalcReady=true;document.addEventListener("click",function(e){var btn=e.target&&e.target.closest?e.target.closest(".js-frontcalc-calculate"):null;if(!btn){return;}e.preventDefault();var productId=btn.getAttribute("data-frontcalc-product-id")||"0";var ajaxUrl=btn.getAttribute("data-frontcalc-ajax-url")||"";if(window.BX&&BX.onCustomEvent){BX.onCustomEvent("Frontcalc:open",[{"product_id":productId,"ajax_url":ajaxUrl,"button":btn}]);}if(window.BX&&BX.SidePanel&&BX.SidePanel.Instance&&ajaxUrl){BX.SidePanel.Instance.open(ajaxUrl+(ajaxUrl.indexOf("?")===-1?"?":"&")+"product_id="+encodeURIComponent(productId),{cacheable:false,width:980});return;}if(window.BX&&BX.PopupWindowManager){var popup=BX.PopupWindowManager.create("frontcalc-unavailable",null,{content:"Открытие калькулятора не настроено.",autoHide:true,closeByEsc:true,overlay:true,buttons:[new BX.PopupWindowButton({text:"Закрыть",events:{click:function(){this.popupWindow.close();}}})]});popup.show();return;}if(window.alert){window.alert("Открытие калькулятора не настроено.");}});})();</script>';
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
            '<button type="button" class="frontcalc-calculate-button js-frontcalc-calculate" data-frontcalc-product-id="%d" data-frontcalc-ajax-url="%s">%s</button>',
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
