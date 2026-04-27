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
(function(){
    if(window.__frontcalcReady){return;}
    window.__frontcalcReady = true;

    var frontcalcPopupInstance = null;

    function escapeHtml(str){
        return String(str || '').replace(/[&<>"']/g, function(ch){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch] || ch;
        });
    }

    function closePopup(){
        if (!frontcalcPopupInstance) {
            return;
        }

        if (frontcalcPopupInstance.windowEl && frontcalcPopupInstance.windowEl.parentNode) {
            frontcalcPopupInstance.windowEl.parentNode.removeChild(frontcalcPopupInstance.windowEl);
        }
        if (frontcalcPopupInstance.overlayEl && frontcalcPopupInstance.overlayEl.parentNode) {
            frontcalcPopupInstance.overlayEl.parentNode.removeChild(frontcalcPopupInstance.overlayEl);
        }

        document.body.classList.remove('jqm-initied');
        document.body.classList.remove('swipeignore');
        frontcalcPopupInstance = null;
    }

    function ensurePopup(){
        if (frontcalcPopupInstance) { return frontcalcPopupInstance; }

        var wrapper = document.getElementById('popup_iframe_wrapper');
        if (!wrapper) {
            wrapper = document.createElement('div');
            wrapper.id = 'popup_iframe_wrapper';
            document.body.appendChild(wrapper);
        }
        wrapper.style.zIndex = '3000';
        wrapper.style.display = 'flex';

        var overlayEl = document.createElement('div');
        overlayEl.className = 'jqmOverlay';
        overlayEl.style.height = '100%';
        overlayEl.style.width = '100%';
        overlayEl.style.position = 'fixed';
        overlayEl.style.left = '0';
        overlayEl.style.top = '0';
        overlayEl.style.zIndex = '2999';
        overlayEl.style.opacity = '0.5';

        var windowEl = document.createElement('div');
        windowEl.className = 'question_frame jqmWindow jqmWindow--mobile-fill popup jqm-init show';
        windowEl.setAttribute('data-popup', '0');
        windowEl.style.zIndex = '3000';
        windowEl.style.opacity = '1';

        windowEl.innerHTML = ''
            + '<span class="jqmClose top-close fill-grey-hover" title="Закрыть"><i class="svg inline inline" aria-hidden="true">×</i></span>'
            + '<div class="scrollbar">'
            + '  <div class="flexbox">'
            + '    <div class="form popup frontcalc-popup-shell">'
            + '      <div class="form-header"><div class="text"><div class="title switcher-title font_24 color_222">Калькулятор стоимости</div></div></div>'
            + '      <div class="frontcalc-popup-content js-frontcalc-popup-content"></div>'
            + '    </div>'
            + '  </div>'
            + '</div>';

        wrapper.appendChild(overlayEl);
        wrapper.appendChild(windowEl);

        overlayEl.addEventListener('click', closePopup);
        windowEl.querySelector('.jqmClose').addEventListener('click', function(event){
            event.preventDefault();
            closePopup();
        });

        document.body.classList.add('jqm-initied');
        document.body.classList.add('swipeignore');

        frontcalcPopupInstance = {
            wrapper: wrapper,
            overlayEl: overlayEl,
            windowEl: windowEl,
            contentNode: windowEl.querySelector('.js-frontcalc-popup-content'),
            show: function(){},
            close: closePopup
        };

        return frontcalcPopupInstance;
    }

    function findCalculateButton(target){
        if (!target) {
            return null;
        }

        if (target.closest) {
            return target.closest('.js-frontcalc-calculate');
        }

        var node = target;
        while (node && node !== document) {
            if (node.classList && node.classList.contains('js-frontcalc-calculate')) {
                return node;
            }
            node = node.parentNode;
        }

        return null;
    }

    function setLoading(contentNode){
        contentNode.innerHTML = '<div class="frontcalc-preloader"><span class="frontcalc-preloader__spinner"></span><span>Загружаем данные калькулятора...</span></div>';
    }

    function renderError(contentNode, message){
        contentNode.innerHTML = '<div class="frontcalc-empty">' + escapeHtml(message || 'Не удалось загрузить данные калькулятора.') + '</div>';
    }

    function renderData(contentNode, payload){
        var data = payload && payload.data ? payload.data : {};
        var config = data.config || {};
        var fields = Array.isArray(config.fields) ? config.fields : [];
        var offers = Array.isArray(data.offers) ? data.offers : [];

        if (!fields.length) {
            contentNode.innerHTML = '<div class="frontcalc-empty">Конфигурация калькулятора для товара не заполнена.</div>';
            return;
        }

        var html = '<div class="frontcalc-summary">';
        html += '<div><strong>Товар ID:</strong> ' + escapeHtml(data.product_id) + '</div>';
        html += '<div style="margin-top:6px;"><strong>Полей в конфиге:</strong> ' + escapeHtml(fields.length) + '</div>';
        html += '<div style="margin-top:6px;"><strong>Торговых предложений:</strong> ' + escapeHtml(offers.length) + '</div>';

        html += '<div style="margin-top:16px;"><strong>Поля конфигурации:</strong></div><ul style="margin-top:8px;">';
        for (var i = 0; i < fields.length; i++) {
            html += '<li>' + escapeHtml(fields[i].property_code || ('Поле ' + (i + 1))) + '</li>';
        }
        html += '</ul></div>';

        contentNode.innerHTML = html;
    }

    function requestData(url, onSuccess, onError){
        if (window.fetch) {
            fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(response){
                    if(!response.ok){ throw new Error('HTTP ' + response.status); }
                    return response.json();
                })
                .then(onSuccess)
                .catch(function(error){ onError(error && error.message ? error.message : 'fetch_failed'); });
            return;
        }

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function(){
            if (xhr.readyState !== 4) { return; }
            if (xhr.status < 200 || xhr.status >= 300) {
                onError('HTTP ' + xhr.status);
                return;
            }

            try {
                onSuccess(JSON.parse(xhr.responseText));
            } catch (e) {
                onError('bad_json');
            }
        };
        xhr.send();
    }

    document.addEventListener('click', function(event){
        var button = findCalculateButton(event.target);
        if (!button) { return; }

        event.preventDefault();

        var productId = button.getAttribute('data-frontcalc-product-id') || '0';
        var ajaxUrl = button.getAttribute('data-frontcalc-ajax-url') || '';

        if (!ajaxUrl) {
            if (window.alert) { window.alert('Не задан URL для запроса калькулятора.'); }
            return;
        }

        var divider = ajaxUrl.indexOf('?') === -1 ? '?' : '&';
        var requestUrl = ajaxUrl + divider + 'product_id=' + encodeURIComponent(productId);

        button.disabled = true;

        var currentPopup = ensurePopup();
        if (!currentPopup) {
            button.disabled = false;
            if (window.alert) { window.alert('Не удалось открыть popup калькулятора.'); }
            return;
        }

        currentPopup.show();
        var contentNode = currentPopup.contentNode;
        if (!contentNode) {
            button.disabled = false;
            return;
        }

        setLoading(contentNode);

        requestData(requestUrl, function(payload){
            button.disabled = false;
            if (!payload || payload.success !== true) {
                renderError(contentNode, payload && payload.message ? payload.message : 'Сервер вернул ошибку.');
                return;
            }
            renderData(contentNode, payload);
        }, function(errorMessage){
            button.disabled = false;
            renderError(contentNode, 'Ошибка запроса: ' + errorMessage);
        });
    });
})();
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
