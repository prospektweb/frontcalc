(function (window, document, $) {
  if (!$ || window.__frontcalcJqmReady) {
    return;
  }
  window.__frontcalcJqmReady = true;

  function escapeHtml(str) {
    return String(str || "").replace(/[&<>"']/g, function (ch) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;" }[ch] || ch;
    });
  }

  function ensureWrapper() {
    var $wrapper = $("#popup_iframe_wrapper");
    if (!$wrapper.length) {
      $wrapper = $('<div id="popup_iframe_wrapper"></div>').appendTo("body");
    }
    return $wrapper;
  }

  function createFrame() {
    ensureWrapper();
    return $(
      '<div class="frontcalc_frame jqmWindow jqmWindow--mobile-fill popup"><span class="jqmClose top-close fill-grey-hover" title="Закрыть"><i class="svg inline inline" aria-hidden="true">×</i></span><div class="scrollbar"><div class="flexbox"><div class="form popup frontcalc-popup-shell"><div class="form-header"><div class="text"><div class="title switcher-title font_24 color_222">Калькулятор стоимости</div></div></div><div class="frontcalc-popup-content js-frontcalc-popup-content"></div></div></div></div></div>'
    ).appendTo("#popup_iframe_wrapper");
  }

  function setLoading($content) {
    $content.html(
      '<div class="frontcalc-preloader"><span class="frontcalc-preloader__spinner"></span><span>Загружаем данные калькулятора...</span></div>'
    );
  }

  function renderError($content, message) {
    $content.html('<div class="frontcalc-empty">' + escapeHtml(message || "Не удалось загрузить данные калькулятора.") + "</div>");
  }

  function requestData(url, onSuccess, onError) {
    if (window.fetch) {
      fetch(url, { credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } })
        .then(function (response) {
          if (!response.ok) {
            throw new Error("HTTP " + response.status);
          }
          return response.json();
        })
        .then(onSuccess)
        .catch(function (error) {
          onError(error && error.message ? error.message : "fetch_failed");
        });
      return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open("GET", url, true);
    xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      if (xhr.status < 200 || xhr.status >= 300) {
        onError("HTTP " + xhr.status);
        return;
      }
      try {
        onSuccess(JSON.parse(xhr.responseText));
      } catch (e) {
        onError("bad_json");
      }
    };
    xhr.send();
  }

  function loadJqmScript(callback) {
    if ($.fn.jqm) {
      callback();
      return;
    }

    var config = window.FrontcalcPopupConfig || {};
    var src = config.jqModalPath || "/bitrix/modules/aspro.popup/install/js/jqModal.js";
    var script = document.createElement("script");
    script.src = src;
    script.async = true;
    script.onload = callback;
    script.onerror = callback;
    document.head.appendChild(script);
  }

  function parseNumber(raw, fallback) {
    var num = Number(raw);
    return Number.isFinite(num) ? num : fallback;
  }

  function clamp(value, min, max) {
    var result = value;
    if (Number.isFinite(min)) result = Math.max(min, result);
    if (Number.isFinite(max)) result = Math.min(max, result);
    return result;
  }

  function normalizeToStep(value, min, step) {
    if (!Number.isFinite(step) || step <= 0) {
      return value;
    }
    var base = Number.isFinite(min) ? min : 0;
    return base + Math.round((value - base) / step) * step;
  }

  function getFieldLabel(field) {
    return field.label || field.title || field.name || field.property_code || "Параметр";
  }

  function makeFieldIndexMap(fields) {
    var map = {};
    fields.forEach(function (field) {
      var code = String(field.property_code || "").trim();
      if (code) map[code] = field;
    });
    return map;
  }

  function buildPresetsByProperty(offers, hiddenByProperty) {
    var byCode = {};
    offers.forEach(function (offer) {
      var properties = offer.properties || {};
      Object.keys(properties).forEach(function (code) {
        if (code.indexOf("CALC_PROP_") !== 0) return;
        var prop = properties[code] || {};
        var xmlId = String(prop.xml_id || "").trim();
        if (!xmlId) return;

        if (!byCode[code]) byCode[code] = {};
        if (hiddenByProperty[code] && hiddenByProperty[code][xmlId]) return;

        byCode[code][xmlId] = {
          value: prop.value || xmlId,
          xml_id: xmlId,
          sort: parseNumber(prop.sort, 500),
        };
      });
    });

    var result = {};
    Object.keys(byCode).forEach(function (code) {
      var arr = Object.keys(byCode[code]).map(function (xmlId) {
        return byCode[code][xmlId];
      });
      arr.sort(function (a, b) {
        return parseNumber(a.sort, 500) - parseNumber(b.sort, 500);
      });
      result[code] = arr;
    });

    return result;
  }

  function buildHiddenPresetMap(config) {
    var map = {};
    var fields = Array.isArray(config.fields) ? config.fields : [];
    fields.forEach(function (field) {
      var code = String(field.property_code || "").trim();
      if (!code) return;
      var hidden = Array.isArray(field.hidden_preset_xml_ids) ? field.hidden_preset_xml_ids : [];
      if (!hidden.length) return;
      map[code] = {};
      hidden.forEach(function (xmlId) {
        map[code][String(xmlId)] = true;
      });
    });
    return map;
  }

  function createInputControl(field, initialValue, onCommit, onFocus, onBlur) {
    var label = getFieldLabel(field);
    var min = parseNumber(field.min, Number.NaN);
    var max = parseNumber(field.max, Number.NaN);
    var step = parseNumber(field.step, 1);
    var value = parseNumber(initialValue, parseNumber(field.default, 0));
    if (!Number.isFinite(value)) value = 0;
    value = clamp(value, min, max);

    var $field = $('<div class="frontcalc-field frontcalc-field--input"></div>');
    $field.append('<div class="frontcalc-field__title">' + escapeHtml(label) + "</div>");

    var $control = $('<div class="frontcalc-input-control"></div>');
    var $minus = $('<button type="button" class="frontcalc-step-btn">−</button>');
    var $input = $('<input type="text" class="frontcalc-num-input" inputmode="decimal">').val(value);
    var $plus = $('<button type="button" class="frontcalc-step-btn">+</button>');

    function commit(val) {
      var numeric = parseNumber(val, value);
      numeric = normalizeToStep(clamp(numeric, min, max), min, step);
      value = numeric;
      $input.val(value);
      onCommit(value, false);
    }

    $minus.on("click", function () {
      commit(parseNumber($input.val(), value) - step);
    });

    $plus.on("click", function () {
      commit(parseNumber($input.val(), value) + step);
    });

    $input.on("focus", function () {
      onFocus && onFocus();
    });

    $input.on("blur", function () {
      commit($input.val());
      onBlur && onBlur();
    });

    $input.on("wheel", function (event) {
      if (document.activeElement !== $input[0]) return;
      event.preventDefault();
      var delta = event.originalEvent && event.originalEvent.deltaY < 0 ? step : -step;
      commit(parseNumber($input.val(), value) + delta);
    });

    $control.append($minus, $input, $plus);
    $field.append($control);
    return $field;
  }

  function createPresetButtons(presets, onSelect) {
    var $wrap = $('<div class="frontcalc-presets"></div>');
    presets.forEach(function (preset) {
      var $btn = $('<button type="button" class="frontcalc-chip"></button>')
        .text(preset.value || preset.xml_id)
        .attr("data-xml-id", preset.xml_id);

      $btn.on("click", function () {
        $wrap.find(".is-active").removeClass("is-active");
        $btn.addClass("is-active");
        onSelect(preset);
      });
      $wrap.append($btn);
    });
    return $wrap;
  }

  function pickMatchedOffer(offers, selectedByProperty, hasCustomValues) {
    if (hasCustomValues) return null;
    for (var i = 0; i < offers.length; i++) {
      var offer = offers[i];
      var props = offer.properties || {};
      var matched = true;

      for (var code in selectedByProperty) {
        if (!Object.prototype.hasOwnProperty.call(selectedByProperty, code)) continue;
        var selectedXmlId = selectedByProperty[code];
        if (!selectedXmlId) continue;
        var offerProp = props[code];
        if (!offerProp || String(offerProp.xml_id || "") !== String(selectedXmlId)) {
          matched = false;
          break;
        }
      }

      if (matched) return offer;
    }
    return null;
  }

  function renderPriceBlock($block, matchedOffer) {
    if (!matchedOffer) {
      $block.html('<div class="frontcalc-price-empty">Для произвольных значений цена пока не рассчитывается.</div>');
      return;
    }

    var prices = (matchedOffer.catalog && matchedOffer.catalog.prices) || [];
    var firstPrice = prices.length ? prices[0] : null;
    var weightKg = parseNumber(matchedOffer.catalog && matchedOffer.catalog.weight_kg, 0).toFixed(3);
    var volumeM3 = parseNumber(matchedOffer.catalog && matchedOffer.catalog.volume_m3, 0).toFixed(3);

    var html = '<div class="frontcalc-price-main">';
    html += firstPrice
      ? '<div class="frontcalc-price-value">' + escapeHtml(firstPrice.price) + " " + escapeHtml(firstPrice.currency) + "</div>"
      : '<div class="frontcalc-price-value">Цена не найдена</div>';
    html += '<div class="frontcalc-price-offer">ТП: ' + escapeHtml(matchedOffer.name || matchedOffer.id) + "</div>";
    html += "</div>";
    html += '<div class="frontcalc-price-meta">Вес: ' + weightKg + ' кг · Объём: ' + volumeM3 + " м³</div>";
    $block.html(html);
  }

  function renderCalculator($content, payload) {
    var data = payload && payload.data ? payload.data : {};
    var config = data.config || {};
    var offers = Array.isArray(data.offers) ? data.offers : [];
    if (!offers.length) {
      renderError($content, "Нет доступных торговых предложений для калькулятора.");
      return;
    }

    var fields = Array.isArray(config.fields) ? config.fields : [];
    var fieldByCode = makeFieldIndexMap(fields);
    var hiddenByProperty = buildHiddenPresetMap(config);
    var presetsByCode = buildPresetsByProperty(offers, hiddenByProperty);

    var selectedByProperty = {};
    var hasCustomValues = false;

    Object.keys(presetsByCode).forEach(function (code) {
      if (presetsByCode[code].length) {
        selectedByProperty[code] = presetsByCode[code][0].xml_id;
      }
    });

    var $layout = $('<div class="frontcalc-layout"></div>');
    var $selectors = $('<div class="frontcalc-selectors"></div>');
    var $price = $('<aside class="frontcalc-price-panel"><div class="frontcalc-price-panel__inner"></div></aside>');
    var $priceInner = $price.find(".frontcalc-price-panel__inner");

    Object.keys(presetsByCode).forEach(function (code) {
      var fieldConfig = fieldByCode[code] || {};
      var label = getFieldLabel(fieldConfig);
      var $section = $('<section class="frontcalc-field"></section>');
      $section.append('<div class="frontcalc-field__title">' + escapeHtml(label) + "</div>");

      var presets = presetsByCode[code];
      var $chips = createPresetButtons(presets, function (preset) {
        selectedByProperty[code] = preset.xml_id;
        hasCustomValues = false;
        updatePrice();
      });
      $section.append($chips);

      var showPresets = fieldConfig.show_presets === true || fieldConfig.show_presets === "Y" || fieldConfig.show_presets === "1";
      if (!showPresets) {
        $chips.hide();
      }

      if (fieldConfig.enable_input === true || fieldConfig.enable_input === "Y" || fieldConfig.enable_input === "1") {
        var delimiter = fieldConfig.group_delimiter || fieldConfig.split_delimiter || "x";
        var groupItems = Array.isArray(fieldConfig.group_inputs) ? fieldConfig.group_inputs : [];
        if (!groupItems.length) groupItems = [fieldConfig];

        var $group = $('<div class="frontcalc-input-group"></div>');
        groupItems.forEach(function (item, idx) {
          var initial = parseNumber(item.default, 0);
          var $inputField = createInputControl(
            item,
            initial,
            function (numericValue) {
              hasCustomValues = true;
              selectedByProperty[code] = String(numericValue);
              updatePrice();
            },
            function () {
              if (showPresets) $chips.show();
            },
            function () {
              if (showPresets) $chips.hide();
            }
          );
          $group.append($inputField);

          $chips.on("click", ".frontcalc-chip", function () {
            var xmlId = $(this).attr("data-xml-id") || "";
            if (!xmlId) return;
            var parts = String(xmlId).split(delimiter);
            if (parts.length > idx) {
              var $input = $inputField.find(".frontcalc-num-input");
              $input.val(parts[idx]).trigger("blur");
            }
          });
        });
        $section.append($group);
      }

      $selectors.append($section);
    });

    function updatePrice() {
      var matched = pickMatchedOffer(offers, selectedByProperty, hasCustomValues);
      renderPriceBlock($priceInner, matched);
    }

    $layout.append($selectors, $price);
    $content.html($layout);
    updatePrice();
  }

  function openPopup(button) {
    var $button = $(button);
    var productId = $button.data("frontcalc-product-id") || 0;
    var ajaxUrl = $button.data("frontcalc-ajax-url") || "";

    if (!ajaxUrl) {
      if (window.alert) window.alert("Не задан URL для запроса калькулятора.");
      return;
    }

    var divider = ajaxUrl.indexOf("?") === -1 ? "?" : "&";
    var requestUrl = ajaxUrl + divider + "product_id=" + encodeURIComponent(productId);
    $button.prop("disabled", true);

    loadJqmScript(function () {
      var $frame = createFrame();
      $frame.jqm({
        trigger: $button,
        overlay: 50,
        overlayClass: "jqmOverlay",
        closeClass: "jqmClose",
        onHide: function (hash) {
          hash.w.remove();
          hash.o && hash.o.remove();
          $("body").removeClass("jqm-initied swipeignore");
        },
      });

      $frame.jqmShow(button);
      $("body").addClass("jqm-initied swipeignore");
      $frame.closest("#popup_iframe_wrapper").css({ "z-index": 3000, display: "flex" });

      var $content = $frame.find(".js-frontcalc-popup-content");
      setLoading($content);

      requestData(
        requestUrl,
        function (payload) {
          $button.prop("disabled", false);
          if (!payload || payload.success !== true) {
            renderError($content, payload && payload.message ? payload.message : "Сервер вернул ошибку.");
            return;
          }
          renderCalculator($content, payload);
        },
        function (errorMessage) {
          $button.prop("disabled", false);
          renderError($content, "Ошибка запроса: " + errorMessage);
        }
      );
    });
  }

  $(document).on("click", ".js-frontcalc-calculate", function (event) {
    event.preventDefault();
    openPopup(this);
  });
})(window, document, window.jQuery);
