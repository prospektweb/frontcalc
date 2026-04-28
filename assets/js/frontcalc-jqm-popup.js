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
      '<div class="frontcalc_frame jqmWindow jqmWindow--mobile-fill popup"><span class="jqmClose top-close fill-grey-hover" title="Закрыть"><i class="svg inline inline" aria-hidden="true">×</i></span><div class="scrollbar"><div class="flexbox"><div class="form popup frontcalc-popup-shell"><div class="frontcalc-popup-content js-frontcalc-popup-content"></div></div></div></div></div>'
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

  function isTruthyFlag(value) {
    return value === true || value === "Y" || value === "1" || value === 1 || value === "true";
  }

  function getFieldCode(field) {
    return String((field && (field.property_code || field.code || field.property || field.prop_code)) || "").trim();
  }

  function getFieldLabel(field, propertyMetaByCode, explicitCode) {
    var code = String(explicitCode || getFieldCode(field)).trim();
    if (code && propertyMetaByCode[code] && propertyMetaByCode[code].name) {
      return propertyMetaByCode[code].name;
    }
    return String((field && (field.label || field.title || field.name)) || "").trim();
  }

  function makeFieldIndexMap(fields) {
    var map = {};
    fields.forEach(function (field) {
      var code = getFieldCode(field);
      if (code) map[code] = field;
    });
    return map;
  }

  function makeCodeOrderMapFromMeta(propertyMetaList) {
    var map = {};
    (Array.isArray(propertyMetaList) ? propertyMetaList : []).forEach(function (meta, index) {
      var code = String((meta && meta.code) || "").trim();
      if (code && map[code] === undefined) {
        map[code] = index;
      }
    });
    return map;
  }

  function makeCodeOrderMapFromFields(fields) {
    var map = {};
    (Array.isArray(fields) ? fields : []).forEach(function (field, index) {
      var code = getFieldCode(field);
      if (code && map[code] === undefined) {
        map[code] = index;
      }
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
      var code = getFieldCode(field);
      if (!code) return;
      var hidden = Array.isArray(field.hide_presets)
        ? field.hide_presets
        : Array.isArray(field.hidden_preset_xml_ids)
        ? field.hidden_preset_xml_ids
        : [];
      if (!hidden.length) return;
      map[code] = {};
      hidden.forEach(function (xmlId) {
        map[code][String(xmlId)] = true;
      });
    });
    return map;
  }

  function gatherAllPropertyCodes(propertyMetaList, offers, fields) {
    var map = {};
    (Array.isArray(propertyMetaList) ? propertyMetaList : []).forEach(function (meta) {
      var code = String((meta && meta.code) || "").trim();
      if (code) map[code] = true;
    });
    (Array.isArray(offers) ? offers : []).forEach(function (offer) {
      var properties = (offer && offer.properties) || {};
      Object.keys(properties).forEach(function (code) {
        if (code.indexOf("CALC_PROP_") === 0) map[code] = true;
      });
    });
    (Array.isArray(fields) ? fields : []).forEach(function (field) {
      var code = getFieldCode(field);
      if (code) map[code] = true;
    });
    return Object.keys(map);
  }

  function buildParticipatingPropertyMap(offers) {
    var map = {};
    (Array.isArray(offers) ? offers : []).forEach(function (offer) {
      var properties = (offer && offer.properties) || {};
      Object.keys(properties).forEach(function (code) {
        if (code.indexOf("CALC_PROP_") !== 0) return;
        var prop = properties[code] || {};
        var xmlId = String(prop.xml_id || "").trim();
        if (!xmlId) return;
        map[code] = true;
      });
    });
    return map;
  }

  function buildPresetsFromConfigFields(fields, hiddenByProperty) {
    var result = {};
    (Array.isArray(fields) ? fields : []).forEach(function (field) {
      var code = getFieldCode(field);
      if (!code) return;
      var source = field.presets || field.values || field.options || [];
      if (!Array.isArray(source) || !source.length) return;
      result[code] = result[code] || [];
      source.forEach(function (row, idx) {
        var xmlId = "";
        var value = "";
        var sort = 500 + idx;

        if (typeof row === "string" || typeof row === "number") {
          xmlId = String(row);
          value = String(row);
        } else if (row && typeof row === "object") {
          xmlId = String(row.xml_id || row.id || row.code || row.value || "").trim();
          value = String(row.value || row.label || row.name || xmlId).trim();
          sort = parseNumber(row.sort, sort);
        }

        if (!xmlId) return;
        if (hiddenByProperty[code] && hiddenByProperty[code][xmlId]) return;
        result[code].push({ xml_id: xmlId, value: value || xmlId, sort: sort });
      });
    });
    return result;
  }

  function mergePresets(target, incoming) {
    Object.keys(incoming || {}).forEach(function (code) {
      var byXmlId = {};
      (Array.isArray(target[code]) ? target[code] : []).forEach(function (row) {
        byXmlId[String(row.xml_id)] = row;
      });
      (Array.isArray(incoming[code]) ? incoming[code] : []).forEach(function (row) {
        var key = String(row.xml_id || "").trim();
        if (!key) return;
        byXmlId[key] = row;
      });
      var merged = Object.keys(byXmlId).map(function (key) {
        return byXmlId[key];
      });
      merged.sort(function (a, b) {
        return parseNumber(a.sort, 500) - parseNumber(b.sort, 500);
      });
      target[code] = merged;
    });
  }

  function createInputControl(field, initialValue, onCommit) {
    var label = getFieldLabel(field, {}, "");
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

    $input.on("blur", function () {
      commit($input.val());
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
    var propertyMeta = Array.isArray(data.property_meta) ? data.property_meta : [];
    var propertyMetaByCode = {};
    propertyMeta.forEach(function (meta) {
      var code = String((meta && meta.code) || "").trim();
      if (code) propertyMetaByCode[code] = meta;
    });
    if (!offers.length) {
      renderError($content, "Нет доступных торговых предложений для калькулятора.");
      return;
    }

    var fields = Array.isArray(config.fields) ? config.fields : [];
    var fieldByCode = makeFieldIndexMap(fields);
    var metaOrderByCode = makeCodeOrderMapFromMeta(propertyMeta);
    var fieldOrderByCode = makeCodeOrderMapFromFields(fields);
    var hiddenByProperty = buildHiddenPresetMap(config);
    var presetsByCode = buildPresetsByProperty(offers, hiddenByProperty);
    mergePresets(presetsByCode, buildPresetsFromConfigFields(fields, hiddenByProperty));
    Object.keys(propertyMetaByCode).forEach(function (code) {
      if (Array.isArray(propertyMetaByCode[code].presets) && propertyMetaByCode[code].presets.length) {
        mergePresets(presetsByCode, (function () {
          var map = {};
          map[code] = propertyMetaByCode[code].presets;
          return map;
        })());
      }
    });
    var participatingByCode = buildParticipatingPropertyMap(offers);
    var allCodes = gatherAllPropertyCodes(propertyMeta, offers, fields)
      .filter(function (code) {
        return !!participatingByCode[code];
      })
      .sort(function (a, b) {
        var sortA = parseNumber(
          propertyMetaByCode[a] && (propertyMetaByCode[a].sort || propertyMetaByCode[a].SORT),
          parseNumber(fieldByCode[a] && (fieldByCode[a].sort || fieldByCode[a].SORT), 500)
        );
        var sortB = parseNumber(
          propertyMetaByCode[b] && (propertyMetaByCode[b].sort || propertyMetaByCode[b].SORT),
          parseNumber(fieldByCode[b] && (fieldByCode[b].sort || fieldByCode[b].SORT), 500)
        );
        if (sortA === sortB) {
          var metaOrderA = parseNumber(metaOrderByCode[a], Number.POSITIVE_INFINITY);
          var metaOrderB = parseNumber(metaOrderByCode[b], Number.POSITIVE_INFINITY);
          if (metaOrderA !== metaOrderB) return metaOrderA - metaOrderB;

          var fieldOrderA = parseNumber(fieldOrderByCode[a], Number.POSITIVE_INFINITY);
          var fieldOrderB = parseNumber(fieldOrderByCode[b], Number.POSITIVE_INFINITY);
          if (fieldOrderA !== fieldOrderB) return fieldOrderA - fieldOrderB;

          return a.localeCompare(b);
        }
        return sortA - sortB;
      });

    var selectedByProperty = {};
    var hasCustomValues = false;

    allCodes.forEach(function (code) {
      if (Array.isArray(presetsByCode[code]) && presetsByCode[code].length) {
        selectedByProperty[code] = presetsByCode[code][0].xml_id;
      }
    });

    var $layout = $('<div class="frontcalc-layout"></div>');
    var $selectors = $('<div class="frontcalc-selectors"></div>');
    var $price = $('<aside class="frontcalc-price-panel"><div class="frontcalc-price-panel__inner"></div></aside>');
    var $priceInner = $price.find(".frontcalc-price-panel__inner");

    allCodes.forEach(function (code) {
      var fieldConfig = fieldByCode[code] || {};
      var label = getFieldLabel(fieldConfig, propertyMetaByCode, code);
      var $section = $('<section class="frontcalc-field"></section>');
      if (label) {
        $section.append('<div class="frontcalc-field__title">' + escapeHtml(label) + "</div>");
      }

      var presets = Array.isArray(presetsByCode[code]) ? presetsByCode[code] : [];
      var $chips = createPresetButtons(presets, function (preset) {
        selectedByProperty[code] = preset.xml_id;
        hasCustomValues = false;
        updatePrice();
      });
      if (selectedByProperty[code]) {
        $chips.find('.frontcalc-chip[data-xml-id="' + selectedByProperty[code] + '"]').addClass("is-active");
      }

      var hasShowPresetsSetting = Object.prototype.hasOwnProperty.call(fieldConfig, "show_presets");
      var showPresetsBySetting = hasShowPresetsSetting ? isTruthyFlag(fieldConfig.show_presets) : true;
      if (!presets.length || !showPresetsBySetting) $chips.hide();

      var groupItems = Array.isArray(fieldConfig.group_inputs)
        ? fieldConfig.group_inputs
        : Array.isArray(fieldConfig.inputs)
        ? fieldConfig.inputs
        : [];
      var hasInputFlag =
        isTruthyFlag(fieldConfig.enable_input) ||
        isTruthyFlag(fieldConfig.input_enabled) ||
        isTruthyFlag(fieldConfig.allow_input) ||
        isTruthyFlag(fieldConfig.show_input) ||
        isTruthyFlag(fieldConfig.show_inputs) ||
        isTruthyFlag(fieldConfig.custom_input_enabled) ||
        isTruthyFlag(fieldConfig.enable_custom_input) ||
        String(fieldConfig.type || "").toLowerCase() === "input" ||
        Number.isFinite(parseNumber(fieldConfig.min, Number.NaN)) ||
        Number.isFinite(parseNumber(fieldConfig.max, Number.NaN));

      if (hasInputFlag || groupItems.length > 0) {
        var delimiter = fieldConfig.group_delimiter || fieldConfig.split_delimiter || "x";
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
        $section.append($chips);

        if (showPresetsBySetting && presets.length) {
          $chips.hide();
          $section.on("focusin", ".frontcalc-num-input", function () {
            $chips.show();
          });
          $section.on("focusout", ".frontcalc-num-input", function () {
            setTimeout(function () {
              if ($(document.activeElement).hasClass("frontcalc-num-input")) return;
              $chips.hide();
            }, 0);
          });

          $chips.on("click", ".frontcalc-chip", function () {
            $chips.hide();
            this.blur();
          });
        }
      } else {
        $section.append($chips);
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
          var $wrapper = $("#popup_iframe_wrapper");
          if ($wrapper.find(".jqmWindow").length === 0 && $wrapper.find(".jqmOverlay").length === 0) {
            $wrapper.css({ "z-index": "", display: "" });
          }
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
