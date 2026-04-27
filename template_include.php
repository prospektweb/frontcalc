<?php

use Prospektweb\Frontcalc\Service\CalculatorAvailability;

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

        return sprintf(
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
