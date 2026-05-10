(function (window, document, $) {
  if (!$ || window.__frontcalcJqmReady) {
    return;
  }
  window.__frontcalcJqmReady = true;

  ensurePopupStyles();


  function ensurePopupStyles() {
    if (document.getElementById("frontcalc-jqm-popup-runtime-styles")) {
      return;
    }

    var style = document.createElement("style");
    style.id = "frontcalc-jqm-popup-runtime-styles";
    style.textContent = [
      "#popup_iframe_wrapper .frontcalc_frame{width:min(1320px,calc(100vw - 32px)) !important;max-width:calc(100vw - 32px) !important;}",
      "#popup_iframe_wrapper .frontcalc_frame .scrollbar{max-height:calc(100vh - 40px);overflow:auto;}",
      ".frontcalc-popup-shell{width:100%;max-width:100%;box-sizing:border-box;}",
      ".frontcalc-popup-shell.form.popup{display:block;padding:0;background:#fff;}",
      ".frontcalc-popup-content{min-height:220px;padding:24px;}",
      ".frontcalc-layout{display:grid;grid-template-columns:minmax(0,2fr) minmax(320px,1fr);gap:20px;align-items:start;}",
      ".frontcalc-selectors{display:flex;flex-direction:column;gap:20px;}",
      ".frontcalc-offer-title{margin:0 0 4px;font-size:24px;line-height:1.25;font-weight:700;color:#101933;}",
      ".frontcalc-price-panel__inner{position:sticky;top:12px;border:1px solid #d9dee7;border-radius:12px;background:#fafbff;padding:16px;}",
      ".frontcalc-price-groups{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;}",
      ".frontcalc-price-group{min-height:34px;padding:6px 12px;border:1px solid #d9dee7;border-radius:999px;background:#fff;color:#33405a;font-size:14px;line-height:1.2;cursor:pointer;}",
      ".frontcalc-price-group:hover{border-color:#2f3a52;}",
      ".frontcalc-price-group.is-active{border-color:#2f3a52;background:#101933;color:#fff;font-weight:600;}",
      ".frontcalc-volume-input{display:flex;gap:8px;align-items:center;margin-bottom:12px;}",
      ".frontcalc-table-input{width:120px;height:44px;border:1px solid #d9dee7;border-radius:10px;padding:0 12px;font-size:22px;font-weight:600;box-sizing:border-box;}",
      ".frontcalc-volume-btns{display:flex;gap:6px;}",
      ".frontcalc-volume-btn{width:40px;height:40px;border:1px solid #d9dee7;border-radius:10px;background:#f2f4f8;font-size:24px;line-height:1;cursor:pointer;}",
      ".frontcalc-table-head{display:grid;grid-template-columns:1.1fr 1fr 1fr;gap:8px;margin-bottom:8px;font-weight:600;color:#1a2236;}",
      ".frontcalc-table-head>div{display:flex;align-items:center;gap:6px;}",
      ".frontcalc-tip svg{fill:#8591aa;}",
      ".frontcalc-table-body{display:flex;flex-direction:column;gap:6px;max-height:320px;overflow:auto;}",
      ".frontcalc-table-row{display:grid;grid-template-columns:1.1fr 1fr 1fr;gap:8px;}",
      ".frontcalc-cell{border:1px solid #d9dee7;border-radius:10px;background:#fff;min-height:52px;padding:6px 10px;display:flex;flex-direction:column;align-items:flex-start;justify-content:center;cursor:pointer;box-sizing:border-box;font:inherit;text-align:left;}",
      ".frontcalc-cell-main{font-size:16px;line-height:1.2;color:#212a3f;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}",
      ".frontcalc-cell-sub{font-size:12px;color:#8b93a6;margin-top:2px;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}",
      ".frontcalc-table-row:hover .frontcalc-cell,.frontcalc-cell:hover{border-color:#4f7bd9;background:#f8fbff;}",
      ".frontcalc-table-row.is-selected .frontcalc-cell{border-color:#2f3a52;box-shadow:inset 0 0 0 1px #2f3a52;}",
      ".frontcalc-cell.is-hover-row,.frontcalc-cell.is-hover-col{border-color:#4f7bd9;background:#f8fbff;}",
      ".frontcalc-cell.is-picked{border-color:#2f3a52 !important;box-shadow:inset 0 0 0 1px #2f3a52;}",
      "@media (max-width: 991px){.frontcalc-layout{grid-template-columns:1fr;}.frontcalc-price-panel{order:-1;}}"
    ].join("\n");
    document.head.appendChild(style);
  }

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

    var showUnit = Object.prototype.hasOwnProperty.call(field || {}, "show_unit")
      ? isTruthyFlag(field.show_unit)
      : true;
    var unitText = String((field && field.unit) || "").trim();
    var $controlWrap = $('<div class="frontcalc-input-control-wrap"></div>');
    $controlWrap.append($control);
    if (showUnit && unitText) {
      $controlWrap.append('<span class="frontcalc-input-unit">' + escapeHtml(unitText) + "</span>");
    }

    $field.append($controlWrap);
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

  function pickMatchedOffer(offers, selectedByProperty, customByProperty) {
    for (var customCode in customByProperty) {
      if (!Object.prototype.hasOwnProperty.call(customByProperty, customCode)) continue;
      if (customByProperty[customCode]) return null;
    }

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

  function getFilteredOffers(offers, selectedByProperty, customByProperty, skipCode) {
    var list = Array.isArray(offers) ? offers : [];
    return list.filter(function (offer) {
      var props = (offer && offer.properties) || {};
      for (var code in selectedByProperty) {
        if (!Object.prototype.hasOwnProperty.call(selectedByProperty, code)) continue;
        if (skipCode && code === skipCode) continue;
        if (customByProperty[code]) continue;
        var selectedXmlId = selectedByProperty[code];
        if (!selectedXmlId) continue;
        var offerProp = props[code];
        if (!offerProp || String(offerProp.xml_id || "") !== String(selectedXmlId)) {
          return false;
        }
      }
      return true;
    });
  }


  function pickMatchedOfferIgnoringCustom(offers, selectedByProperty, customByProperty, skipCode) {
    var filtered = getFilteredOffers(offers, selectedByProperty, customByProperty || {}, skipCode || null);
    return filtered.length ? filtered[0] : null;
  }

  function buildAvailableValuesByCode(offers, allCodes, selectedByProperty, customByProperty) {
    var availableByCode = {};
    (Array.isArray(allCodes) ? allCodes : []).forEach(function (code) {
      availableByCode[code] = {};
      var codeIndex = allCodes.indexOf(code);
      var scopedSelection = {};
      var scopedCustom = {};

      // Иерархическая доступность:
      // для текущего свойства учитываем только выборы "выше" по порядку,
      // чтобы можно было переключиться на другую ветку текущего уровня.
      for (var i = 0; i < codeIndex; i++) {
        var prevCode = allCodes[i];
        scopedSelection[prevCode] = selectedByProperty[prevCode];
        scopedCustom[prevCode] = customByProperty[prevCode];
      }

      var filtered = getFilteredOffers(offers, scopedSelection, scopedCustom);
      filtered.forEach(function (offer) {
        var props = (offer && offer.properties) || {};
        var prop = props[code] || {};
        var xmlId = String(prop.xml_id || "").trim();
        if (xmlId) availableByCode[code][xmlId] = true;
      });
    });
    return availableByCode;
  }

  function normalizeValueToken(value) {
    return String(value || "")
      .replace(/\s+/g, "")
      .replace(",", ".")
      .trim();
  }

  function findPresetByInputValue(presets, numericValue) {
    var normalizedInput = normalizeValueToken(numericValue);
    var numericInput = parseNumber(normalizedInput, Number.NaN);
    for (var i = 0; i < presets.length; i++) {
      var preset = presets[i] || {};
      var presetXmlId = normalizeValueToken(preset.xml_id);
      var presetValue = normalizeValueToken(preset.value);
      if (normalizedInput && (presetXmlId === normalizedInput || presetValue === normalizedInput)) {
        return preset;
      }

      var numericXml = parseNumber(presetXmlId, Number.NaN);
      var numericValuePreset = parseNumber(presetValue, Number.NaN);
      if (Number.isFinite(numericInput) && (numericXml === numericInput || numericValuePreset === numericInput)) {
        return preset;
      }
    }
    return null;
  }


  function isCompleteGroupInput(values) {
    if (!Array.isArray(values) || !values.length) return false;
    for (var i = 0; i < values.length; i++) {
      var normalized = normalizeValueToken(values[i]);
      if (!normalized) return false;
      if (!Number.isFinite(parseNumber(normalized, Number.NaN))) return false;
    }
    return true;
  }

  function formatMoneyRow(priceObj) {
    if (!priceObj) return "—";
    return String(priceObj.formatted || ((priceObj.price || 0) + " " + (priceObj.currency || "₽")));
  }

  function getOfferPriceRanges(offer) {
    var catalog = offer && offer.catalog ? offer.catalog : {};
    if (Array.isArray(catalog.price_ranges)) return catalog.price_ranges;
    if (Array.isArray(catalog.prices_view_all)) return catalog.prices_view_all;
    if (Array.isArray(catalog.prices)) return catalog.prices;
    if (Array.isArray(catalog.prices_view)) return catalog.prices_view;
    return [];
  }

  function normalizeRangeBound(value, fallback) {
    if (value === null || typeof value === "undefined" || value === "") return fallback;
    var num = parseNumber(value, Number.NaN);
    return Number.isFinite(num) ? num : fallback;
  }

  function sortPriceRanges(ranges) {
    return (Array.isArray(ranges) ? ranges.slice() : []).sort(function (a, b) {
      var fromA = normalizeRangeBound(a && a.quantity_from, 0);
      var fromB = normalizeRangeBound(b && b.quantity_from, 0);
      if (fromA !== fromB) return fromA - fromB;

      var toA = normalizeRangeBound(a && a.quantity_to, Number.POSITIVE_INFINITY);
      var toB = normalizeRangeBound(b && b.quantity_to, Number.POSITIVE_INFINITY);
      if (toA !== toB) return toA - toB;

      return parseNumber(a && a.price, 0) - parseNumber(b && b.price, 0);
    });
  }

  function getRangesByCatalogGroup(offer, catalogGroupId) {
    var groupId = parseNumber(catalogGroupId, Number.NaN);
    if (!Number.isFinite(groupId)) return [];
    return sortPriceRanges(getOfferPriceRanges(offer).filter(function (row) {
      return parseNumber(row && row.catalog_group_id, Number.NaN) === groupId;
    }));
  }

  function pickStrictRangePrice(ranges) {
    var sorted = sortPriceRanges(ranges);
    for (var i = 0; i < sorted.length; i++) {
      var from = normalizeRangeBound(sorted[i] && sorted[i].quantity_from, 0);
      var to = normalizeRangeBound(sorted[i] && sorted[i].quantity_to, Number.POSITIVE_INFINITY);
      if (from <= 1 && to >= 1) return sorted[i];
    }
    return sorted[0] || null;
  }

  function pickFlexibleRangePrice(ranges) {
    var sorted = sortPriceRanges(ranges);
    return sorted[sorted.length - 1] || null;
  }

  function collectAvailablePriceGroups(priceGroups, offers) {
    var byId = {};
    (Array.isArray(priceGroups) ? priceGroups : []).forEach(function (group) {
      var id = parseNumber(group && group.id, Number.NaN);
      if (Number.isFinite(id)) {
        byId[id] = { id: id, name: String((group && group.name) || ("PRICE_" + id)) };
      }
    });

    (Array.isArray(offers) ? offers : []).forEach(function (offer) {
      getOfferPriceRanges(offer).forEach(function (row) {
        var id = parseNumber(row && row.catalog_group_id, Number.NaN);
        if (!Number.isFinite(id) || byId[id]) return;
        byId[id] = { id: id, name: String((row && row.catalog_group_name) || ("PRICE_" + id)) };
      });
    });

    return Object.keys(byId)
      .map(function (key) { return byId[key]; })
      .filter(function (group) {
        return (Array.isArray(offers) ? offers : []).some(function (offer) {
          return getRangesByCatalogGroup(offer, group.id).length > 0;
        });
      })
      .sort(function (a, b) { return a.id - b.id; });
  }

  function deriveStepFromPresets(presets) {
    var nums = (Array.isArray(presets) ? presets : [])
      .map(function (p) {
        return parseNumber(p && p.xml_id, Number.NaN);
      })
      .filter(function (n) {
        return Number.isFinite(n);
      })
      .sort(function (a, b) {
        return a - b;
      });
    if (nums.length < 2) return 1;
    var minDiff = Number.POSITIVE_INFINITY;
    for (var i = 1; i < nums.length; i++) {
      var diff = nums[i] - nums[i - 1];
      if (diff > 0 && diff < minDiff) minDiff = diff;
    }
    return Number.isFinite(minDiff) ? minDiff : 1;
  }

  function resolveConfiguredStep(fieldConfig) {
    if (!fieldConfig || typeof fieldConfig !== "object") return Number.NaN;
    var direct = parseNumber(fieldConfig.step, Number.NaN);
    if (Number.isFinite(direct) && direct > 0) return direct;
    var keys = ["group_inputs", "inputs", "values"];
    for (var k = 0; k < keys.length; k++) {
      var arr = fieldConfig[keys[k]];
      if (!Array.isArray(arr)) continue;
      for (var i = 0; i < arr.length; i++) {
        var row = arr[i] || {};
        var rowStep = parseNumber(row.step, Number.NaN);
        if (Number.isFinite(rowStep) && rowStep > 0) return rowStep;
      }
    }
    return Number.NaN;
  }


  function getOfferQuantityNumber(offer, volumeCode) {
    var prop = offer && offer.properties ? offer.properties[volumeCode] : null;
    var xmlNum = parseNumber(prop && prop.xml_id, Number.NaN);
    if (Number.isFinite(xmlNum)) return xmlNum;
    return parseNumber(prop && prop.value, Number.NaN);
  }

  function getScopedOffersForQuantity(offers, selectedByProperty, customByProperty, volumeCode) {
    return getFilteredOffers(offers, selectedByProperty, customByProperty, volumeCode)
      .map(function (offer) {
        return { offer: offer, qty: getOfferQuantityNumber(offer, volumeCode) };
      })
      .filter(function (row) {
        return Number.isFinite(row.qty);
      })
      .sort(function (a, b) {
        return a.qty - b.qty;
      });
  }

  function pickNearestQuantityPoints(points, targetQty) {
    if (!Array.isArray(points) || !points.length || !Number.isFinite(targetQty)) return [];

    var exact = points.filter(function (point) {
      return point.qty === targetQty;
    });
    if (exact.length) return [exact[0]];

    if (points.length === 1) return [points[0]];

    var lower = null;
    var upper = null;
    for (var i = 0; i < points.length; i++) {
      if (points[i].qty < targetQty) lower = points[i];
      if (points[i].qty > targetQty) {
        upper = points[i];
        break;
      }
    }

    if (lower && upper) return [lower, upper];
    if (!lower) return [points[0], points[1]];
    return [points[points.length - 2], points[points.length - 1]];
  }

  function interpolateNumberAtQuantity(points, targetQty, valueGetter) {
    var valuePoints = (Array.isArray(points) ? points : [])
      .map(function (point) {
        return {
          offer: point.offer,
          qty: point.qty,
          value: parseNumber(valueGetter(point.offer), Number.NaN)
        };
      })
      .filter(function (point) {
        return Number.isFinite(point.qty) && Number.isFinite(point.value);
      });
    var nearest = pickNearestQuantityPoints(valuePoints, targetQty);
    if (!nearest.length) return Number.NaN;

    var firstValue = nearest[0].value;
    if (nearest.length === 1) return firstValue;

    var secondValue = nearest[1].value;
    var firstQty = nearest[0].qty;
    var secondQty = nearest[1].qty;
    if (firstQty === secondQty) return firstValue;

    return firstValue + ((secondValue - firstValue) * (targetQty - firstQty)) / (secondQty - firstQty);
  }

  function getRangePriceForColumn(offer, catalogGroupId, column) {
    var ranges = getRangesByCatalogGroup(offer, catalogGroupId);
    if (column === "flex") return pickFlexibleRangePrice(ranges) || pickStrictRangePrice(ranges);
    return pickStrictRangePrice(ranges);
  }

  function buildInterpolatedRangePrice(points, targetQty, catalogGroupId, column) {
    var sample = null;
    var amount = interpolateNumberAtQuantity(points, targetQty, function (offer) {
      var range = getRangePriceForColumn(offer, catalogGroupId, column);
      if (!sample && range) sample = range;
      return range && range.price;
    });

    if (!Number.isFinite(amount)) return null;

    var currency = (sample && sample.currency) || "₽";
    return {
      price: amount,
      currency: currency,
      catalog_group_id: catalogGroupId,
      catalog_group_name: sample && sample.catalog_group_name,
      formatted: formatMoneyAmount(amount, currency),
      is_interpolated: true
    };
  }

  function buildQuantityEstimate(points, targetQty, catalogGroupId, column, exactOffer) {
    if (exactOffer) {
      return {
        price: getRangePriceForColumn(exactOffer, catalogGroupId, column),
        weightKg: parseNumber(exactOffer && exactOffer.catalog && exactOffer.catalog.weight_kg, 0),
        volumeM3: parseNumber(exactOffer && exactOffer.catalog && exactOffer.catalog.volume_m3, 0),
        isEstimated: false
      };
    }

    return {
      price: buildInterpolatedRangePrice(points, targetQty, catalogGroupId, column),
      weightKg: interpolateNumberAtQuantity(points, targetQty, function (offer) {
        return offer && offer.catalog && offer.catalog.weight_kg;
      }),
      volumeM3: interpolateNumberAtQuantity(points, targetQty, function (offer) {
        return offer && offer.catalog && offer.catalog.volume_m3;
      }),
      isEstimated: true
    };
  }

  function formatMoneyAmount(amount, currency) {
    var rounded = Math.round(parseNumber(amount, 0));
    var formatted = String(rounded).replace(/\B(?=(\d{3})+(?!\d))/g, " ");
    var currencyText = String(currency || "₽");
    if (currencyText === "RUB" || currencyText === "RUR") currencyText = "₽";
    return formatted + " " + currencyText;
  }

  function formatMetric(value, suffix) {
    var num = parseNumber(value, Number.NaN);
    return Number.isFinite(num) ? num.toFixed(3) + " " + suffix : "—";
  }




  function getPriceDriverType(fieldConfig, code) {
    var type = String((fieldConfig && fieldConfig.price_driver_type) || "").trim();
    if (!type && code === "CALC_PROP_VOLUME") return "quantity";
    return type || "none";
  }

  function getCalcOptions(fieldConfig) {
    return fieldConfig && fieldConfig.calc_options && typeof fieldConfig.calc_options === "object"
      ? fieldConfig.calc_options
      : {};
  }

  function parseCompositeNumbers(value, delimiter) {
    var normalized = String(value || "").replace(/,/g, ".").trim();
    var directParts = normalized
      .split(delimiter || "x")
      .map(function (part) { return parseNumber(normalizeValueToken(part), Number.NaN); })
      .filter(function (num) { return Number.isFinite(num); });
    if (directParts.length >= 2) return directParts;

    var matches = normalized.match(/-?\d+(?:\.\d+)?/g) || [];
    return matches
      .map(function (part) { return parseNumber(part, Number.NaN); })
      .filter(function (num) { return Number.isFinite(num); });
  }

  function getOfferPropertyRawValue(offer, code) {
    var prop = offer && offer.properties ? offer.properties[code] : null;
    return String((prop && (prop.xml_id || prop.value)) || "").trim();
  }

  function getOfferPropertyNumbers(offer, code, delimiter) {
    var prop = offer && offer.properties ? offer.properties[code] : null;
    var xmlNumbers = parseCompositeNumbers(prop && prop.xml_id, delimiter);
    if (xmlNumbers.length >= 2) return xmlNumbers;
    var valueNumbers = parseCompositeNumbers(prop && prop.value, delimiter);
    return valueNumbers.length ? valueNumbers : xmlNumbers;
  }

  function getOfferPropertyScalar(offer, code) {
    var prop = offer && offer.properties ? offer.properties[code] : null;
    var xmlNum = parseNumber(normalizeValueToken(prop && prop.xml_id), Number.NaN);
    if (Number.isFinite(xmlNum)) return xmlNum;
    var xmlNumbers = parseCompositeNumbers(prop && prop.xml_id, "x");
    if (xmlNumbers.length === 1) return xmlNumbers[0];
    var valueNum = parseNumber(normalizeValueToken(prop && prop.value), Number.NaN);
    if (Number.isFinite(valueNum)) return valueNum;
    var valueNumbers = parseCompositeNumbers(prop && prop.value, "x");
    return valueNumbers.length === 1 ? valueNumbers[0] : Number.NaN;
  }

  function getDimensionArea(nums) {
    return Array.isArray(nums) && nums.length >= 2 && nums[0] > 0 && nums[1] > 0 ? nums[0] * nums[1] : Number.NaN;
  }

  function isWithinRange(value, values, allowExtrapolation) {
    if (allowExtrapolation) return true;
    var finite = (Array.isArray(values) ? values : []).filter(function (num) { return Number.isFinite(num); });
    if (!finite.length || !Number.isFinite(value)) return false;
    var min = Math.min.apply(Math, finite);
    var max = Math.max.apply(Math, finite);
    return value >= min && value <= max;
  }

  function canSizeFit(customSize, offerSize, allowRotate) {
    if (!Array.isArray(customSize) || !Array.isArray(offerSize) || customSize.length < 2 || offerSize.length < 2) return false;
    var direct = customSize[0] <= offerSize[0] && customSize[1] <= offerSize[1];
    var rotated = allowRotate && customSize[1] <= offerSize[0] && customSize[0] <= offerSize[1];
    return direct || rotated;
  }

  function clonePriceWithAmount(priceObj, amount, flags) {
    if (!priceObj || !Number.isFinite(amount)) return priceObj || null;
    var result = Object.assign({}, priceObj);
    result.price = amount;
    result.formatted = formatMoneyAmount(amount, result.currency || "₽");
    if (flags && flags.isEstimated) result.is_estimated = true;
    if (flags && flags.drivers) result.drivers = flags.drivers;
    return result;
  }

  function getPriceAmountForDriverOffer(offer, catalogGroupId, column) {
    var range = getRangePriceForColumn(offer, catalogGroupId, column || "strict");
    return parseNumber(range && range.price, Number.NaN);
  }




  function normalizeNonNegativeOption(value, fallback) {
    var raw = typeof value === "string" ? normalizeValueToken(value) : value;
    var num = parseNumber(raw, fallback);
    return Number.isFinite(num) && num >= 0 ? num : fallback;
  }

  function getAdjustedItemSize(size, trimMarginMm) {
    if (!Array.isArray(size) || size.length < 2) return [];
    var margin = normalizeNonNegativeOption(trimMarginMm, 2);
    return [size[0] + margin * 2, size[1] + margin * 2];
  }

  function countItemsInProductionSize(productionSize, itemSize, allowRotate, trimMarginMm, gapMm) {
    if (!Array.isArray(productionSize) || !Array.isArray(itemSize) || productionSize.length < 2 || itemSize.length < 2) return 0;

    var adjustedItemSize = getAdjustedItemSize(itemSize, trimMarginMm);
    var gap = normalizeNonNegativeOption(gapMm, 0);

    function fit(containerWidth, containerHeight, itemWidth, itemHeight) {
      if (containerWidth <= 0 || containerHeight <= 0 || itemWidth <= 0 || itemHeight <= 0) return 0;
      var cols = Math.floor((containerWidth + gap) / (itemWidth + gap));
      var rows = Math.floor((containerHeight + gap) / (itemHeight + gap));
      return Math.max(0, cols) * Math.max(0, rows);
    }

    var direct = fit(productionSize[0], productionSize[1], adjustedItemSize[0], adjustedItemSize[1]);
    var rotated = allowRotate ? fit(productionSize[0], productionSize[1], adjustedItemSize[1], adjustedItemSize[0]) : 0;
    return Math.max(direct, rotated);
  }

  function pickProductionSheetOffer(offers, code, delimiter) {
    var best = null;
    (Array.isArray(offers) ? offers : []).forEach(function (offer) {
      var size = getOfferPropertyNumbers(offer, code, delimiter);
      var area = getDimensionArea(size);
      if (!Number.isFinite(area) || area <= 0) return;
      if (!best || area > best.area) {
        best = { offer: offer, size: size, area: area };
      }
    });
    return best;
  }

  function getOfferPropertyKey(offer, code) {
    var prop = offer && offer.properties ? offer.properties[code] : null;
    return String((prop && (prop.xml_id || prop.value)) || "").trim();
  }

  function getQuantityPointsForReferenceOffer(allOffers, selectedByProperty, customByProperty, referenceOffer, sizeCode, volumeCode) {
    var referenceKey = getOfferPropertyKey(referenceOffer, sizeCode);
    if (!referenceKey) return [];

    var scopedSelection = Object.assign({}, selectedByProperty || {});
    var scopedCustom = Object.assign({}, customByProperty || {});
    scopedSelection[sizeCode] = referenceKey;
    scopedCustom[sizeCode] = false;
    scopedCustom[volumeCode] = true;

    return getScopedOffersForQuantity(allOffers, scopedSelection, scopedCustom, volumeCode);
  }

  function buildPriceAtQuantityForReferenceOffer(allOffers, selectedByProperty, customByProperty, referenceOffer, sizeCode, volumeCode, targetQty, catalogGroupId, column) {
    var qty = parseNumber(targetQty, Number.NaN);
    if (!Number.isFinite(qty) || qty <= 0) return null;

    var points = getQuantityPointsForReferenceOffer(allOffers, selectedByProperty, customByProperty, referenceOffer, sizeCode, volumeCode);
    return buildInterpolatedRangePrice(points, qty, catalogGroupId, column || "strict");
  }

  function interpolateDeltaByArea(points, targetArea) {
    var rows = (Array.isArray(points) ? points : [])
      .filter(function (point) {
        return Number.isFinite(point.area) && point.area > 0 && Number.isFinite(point.delta);
      })
      .sort(function (a, b) { return a.area - b.area; });
    if (!rows.length || !Number.isFinite(targetArea) || targetArea <= 0) return Number.NaN;
    if (rows.length === 1) return rows[0].delta;

    var lower = null;
    var upper = null;
    for (var i = 0; i < rows.length; i++) {
      if (rows[i].area <= targetArea) lower = rows[i];
      if (rows[i].area >= targetArea) {
        upper = rows[i];
        break;
      }
    }
    if (!lower) {
      lower = rows[0];
      upper = rows[1];
    } else if (!upper) {
      lower = rows[rows.length - 2];
      upper = rows[rows.length - 1];
    }
    if (!upper || lower.area === upper.area) return lower.delta;

    var start = Math.log(lower.area);
    var end = Math.log(upper.area);
    var target = Math.log(targetArea);
    if (start === end) return lower.delta;
    var ratio = (target - start) / (end - start);
    return lower.delta + (upper.delta - lower.delta) * ratio;
  }



  function roundProductionVolumeStep(rawStep, configuredStep) {
    var step = parseNumber(rawStep, Number.NaN);
    if (!Number.isFinite(step) || step <= 0) return Number.NaN;

    var baseStep = parseNumber(configuredStep, 0);
    var allowedSteps = [5, 10, 50, 100, 500, 1000].filter(function (candidate) {
      return candidate > baseStep;
    });
    if (!allowedSteps.length) return Number.NaN;

    var best = allowedSteps[0];
    var bestDiff = Math.abs(step - best);
    for (var i = 1; i < allowedSteps.length; i++) {
      var diff = Math.abs(step - allowedSteps[i]);
      if (diff < bestDiff) {
        best = allowedSteps[i];
        bestDiff = diff;
      }
    }
    return best;
  }

  function isSmartVolumeStepEnabled(fieldByCode, volumeCode) {
    var volumeField = fieldByCode && fieldByCode[volumeCode] ? fieldByCode[volumeCode] : null;
    if (getPriceDriverType(volumeField, volumeCode) !== "quantity") return false;
    return isTruthyFlag(getCalcOptions(volumeField).smart_volume_step);
  }

  function resolveProductionSheetCapacityForSelection(offers, selectedByProperty, customByProperty, fieldByCode) {
    var allOffers = Array.isArray(offers) ? offers : [];
    var fields = fieldByCode || {};
    for (var code in fields) {
      if (!Object.prototype.hasOwnProperty.call(fields, code)) continue;
      var fieldConfig = fields[code] || {};
      if (getPriceDriverType(fieldConfig, code) !== "production_sheet_delta") continue;

      var selectedValue = selectedByProperty && selectedByProperty[code];
      var delimiter = fieldConfig.group_delimiter || fieldConfig.split_delimiter || "x";
      var customSize = parseCompositeNumbers(selectedValue, delimiter);
      var customArea = getDimensionArea(customSize);
      if (!Number.isFinite(customArea) || customArea <= 0) continue;

      var scopedSelection = Object.assign({}, selectedByProperty || {});
      var scopedCustom = Object.assign({}, customByProperty || {});
      scopedCustom[code] = true;
      var comparableOffers = getFilteredOffers(allOffers, scopedSelection, scopedCustom, "CALC_PROP_VOLUME");
      var production = pickProductionSheetOffer(comparableOffers, code, delimiter);
      if (!production) continue;

      var options = getCalcOptions(fieldConfig);
      var trimMarginMm = normalizeNonNegativeOption(options.trim_margin_mm, 2);
      var gapMm = normalizeNonNegativeOption(options.gap_mm, 0);
      var allowRotate = Object.prototype.hasOwnProperty.call(options, "allow_rotate") ? isTruthyFlag(options.allow_rotate) : true;
      var fit = countItemsInProductionSize(production.size, customSize, allowRotate, trimMarginMm, gapMm);
      if (fit > 0) return fit;
    }

    return Number.NaN;
  }

  function buildProductionSheetDeltaPrice(context, code, fieldConfig, customRaw, column) {
    var allOffers = Array.isArray(context.allOffers) ? context.allOffers : [];
    var selectedByProperty = context.selectedByProperty || {};
    var customByProperty = context.customByProperty || {};
    var volumeCode = context.volumeCode || "CALC_PROP_VOLUME";
    var targetQty = parseNumber(context.targetQty, Number.NaN);
    var catalogGroupId = context.selectedCatalogGroupId;
    var delimiter = fieldConfig.group_delimiter || fieldConfig.split_delimiter || "x";
    var options = getCalcOptions(fieldConfig);
    var trimMarginMm = normalizeNonNegativeOption(options.trim_margin_mm, 2);
    var gapMm = normalizeNonNegativeOption(options.gap_mm, 0);
    var allowRotate = Object.prototype.hasOwnProperty.call(options, "allow_rotate") ? isTruthyFlag(options.allow_rotate) : true;
    var allowExtrapolation = isTruthyFlag(options.allow_extrapolation);

    if (!Number.isFinite(targetQty) || targetQty <= 0) return null;

    var customSize = parseCompositeNumbers(customRaw, delimiter);
    var customArea = getDimensionArea(customSize);
    if (!Number.isFinite(customArea) || customArea <= 0) return null;

    var comparableOffers = getFilteredOffers(allOffers, selectedByProperty, customByProperty, volumeCode);
    var production = pickProductionSheetOffer(comparableOffers, code, delimiter);
    if (!production) return null;

    var offerAreas = comparableOffers.map(function (offer) {
      return getDimensionArea(getOfferPropertyNumbers(offer, code, delimiter));
    }).filter(function (area) {
      return Number.isFinite(area) && area > 0;
    });
    if (!isWithinRange(customArea, offerAreas, allowExtrapolation)) return null;

    var productionSize = production.size;
    var customFit = countItemsInProductionSize(productionSize, customSize, allowRotate, trimMarginMm, gapMm);
    if (customFit <= 0) return null;

    var sheetQty = targetQty / customFit;
    var basePrice = buildPriceAtQuantityForReferenceOffer(
      allOffers,
      selectedByProperty,
      customByProperty,
      production.offer,
      code,
      volumeCode,
      sheetQty,
      catalogGroupId,
      column
    );
    var baseAmount = parseNumber(basePrice && basePrice.price, Number.NaN);
    if (!Number.isFinite(baseAmount)) return null;

    var bySize = {};
    comparableOffers.forEach(function (offer) {
      var key = getOfferPropertyKey(offer, code);
      if (!key || bySize[key]) return;
      var size = getOfferPropertyNumbers(offer, code, delimiter);
      var area = getDimensionArea(size);
      var fit = countItemsInProductionSize(productionSize, size, allowRotate, trimMarginMm, gapMm);
      if (!Number.isFinite(area) || area <= 0 || fit <= 0) return;
      bySize[key] = { offer: offer, size: size, area: area, fit: fit };
    });

    var deltaPoints = Object.keys(bySize).map(function (key) {
      var row = bySize[key];
      var referenceQty = row.fit * sheetQty;
      var referencePrice = buildPriceAtQuantityForReferenceOffer(
        allOffers,
        selectedByProperty,
        customByProperty,
        row.offer,
        code,
        volumeCode,
        referenceQty,
        catalogGroupId,
        column
      );
      var amount = parseNumber(referencePrice && referencePrice.price, Number.NaN);
      if (!Number.isFinite(amount)) return null;
      return { area: row.area, delta: amount - baseAmount };
    }).filter(Boolean);

    var delta = interpolateDeltaByArea(deltaPoints, customArea);
    if (!Number.isFinite(delta)) return null;

    return clonePriceWithAmount(basePrice, Math.max(0, baseAmount + delta), {
      isEstimated: true,
      drivers: [code + ":production_sheet_delta"]
    });
  }

  function scoreOfferForCustomDriver(offer, code, fieldConfig, customRaw) {
    var type = getPriceDriverType(fieldConfig, code);
    if (!type || type === "none" || type === "quantity") return 0;

    var delimiter = fieldConfig.group_delimiter || fieldConfig.split_delimiter || "x";
    if (type === "size_area" || type === "size_covering" || type === "production_sheet_delta") {
      var customSize = parseCompositeNumbers(customRaw, delimiter);
      var offerSize = getOfferPropertyNumbers(offer, code, delimiter);
      var customArea = getDimensionArea(customSize);
      var offerArea = getDimensionArea(offerSize);
      if (!Number.isFinite(customArea) || customArea <= 0 || !Number.isFinite(offerArea) || offerArea <= 0) {
        return Number.POSITIVE_INFINITY;
      }

      var areaScore = Math.abs(Math.log(offerArea / customArea));
      var customRatio = customSize[1] > 0 ? customSize[0] / customSize[1] : Number.NaN;
      var offerRatio = offerSize[1] > 0 ? offerSize[0] / offerSize[1] : Number.NaN;
      var ratioScore = Number.isFinite(customRatio) && Number.isFinite(offerRatio)
        ? Math.abs(Math.log(offerRatio / customRatio)) * 0.25
        : 0;

      if (type === "size_covering") {
        var options = getCalcOptions(fieldConfig);
        var allowRotate = Object.prototype.hasOwnProperty.call(options, "allow_rotate") ? isTruthyFlag(options.allow_rotate) : true;
        if (!canSizeFit(customSize, offerSize, allowRotate)) {
          areaScore += 1000;
        }
      }

      return areaScore + ratioScore;
    }

    if (type === "pages") {
      var customValue = parseNumber(normalizeValueToken(customRaw), Number.NaN);
      var offerValue = getOfferPropertyScalar(offer, code);
      if (!Number.isFinite(customValue) || customValue <= 0 || !Number.isFinite(offerValue) || offerValue <= 0) {
        return Number.POSITIVE_INFINITY;
      }
      return Math.abs(Math.log(offerValue / customValue));
    }

    return 0;
  }

  function pickBestReferenceOfferForCustomDrivers(offers, selectedByProperty, customByProperty, fieldByCode) {
    var candidates = getFilteredOffers(offers, selectedByProperty, customByProperty || {}, null);
    if (!candidates.length) return null;

    var best = null;
    candidates.forEach(function (offer) {
      var totalScore = 0;
      var hasScore = false;
      Object.keys(customByProperty || {}).forEach(function (code) {
        if (!customByProperty[code]) return;
        var fieldConfig = (fieldByCode && fieldByCode[code]) || {};
        var score = scoreOfferForCustomDriver(offer, code, fieldConfig, selectedByProperty[code]);
        if (!Number.isFinite(score)) return;
        totalScore += score;
        hasScore = true;
      });

      if (!hasScore) totalScore = 0;
      if (!best || totalScore < best.score) {
        best = { offer: offer, score: totalScore };
      }
    });

    return best ? best.offer : candidates[0];
  }

  function buildCustomDriverAdjustment(context) {
    var offers = Array.isArray(context.offers) ? context.offers : [];
    var fieldByCode = context.fieldByCode || {};
    var selectedByProperty = context.selectedByProperty || {};
    var customByProperty = context.customByProperty || {};
    var referenceOffer = context.referenceOffer || context.anchorOffer || null;
    var selectedCatalogGroupId = context.selectedCatalogGroupId;
    var column = context.column || "strict";
    var adjustment = { multiplier: 1, valid: true, messages: [], drivers: [] };

    Object.keys(customByProperty).forEach(function (code) {
      if (!customByProperty[code]) return;
      var fieldConfig = fieldByCode[code] || {};
      var type = getPriceDriverType(fieldConfig, code);
      if (!type || type === "none" || type === "quantity") return;

      var options = getCalcOptions(fieldConfig);
      var allowExtrapolation = isTruthyFlag(options.allow_extrapolation);
      var sensitivity = parseNumber(options.sensitivity, 1);
      if (!Number.isFinite(sensitivity) || sensitivity <= 0) sensitivity = 1;
      var delimiter = fieldConfig.group_delimiter || fieldConfig.split_delimiter || "x";
      var customRaw = selectedByProperty[code];

      if (type === "production_sheet_delta") {
        var productionPrice = buildProductionSheetDeltaPrice(context, code, fieldConfig, customRaw, column);
        if (productionPrice) {
          adjustment.priceOverride = productionPrice;
          adjustment.drivers.push(code + ":production_sheet_delta");
        }
        return;
      }

      if (type === "size_area" || type === "size_covering") {
        var customSize = parseCompositeNumbers(customRaw, delimiter);
        var customArea = getDimensionArea(customSize);
        if (!Number.isFinite(customArea) || customArea <= 0) return;
        var offerAreas = offers.map(function (offer) {
          return getDimensionArea(getOfferPropertyNumbers(offer, code, delimiter));
        }).filter(function (area) { return Number.isFinite(area) && area > 0; });
        if (!isWithinRange(customArea, offerAreas, allowExtrapolation)) {
          adjustment.valid = false;
          adjustment.messages.push("Значение " + code + " вне рамок опорных ТП");
          return;
        }

        if (type === "size_covering") {
          var allowRotate = Object.prototype.hasOwnProperty.call(options, "allow_rotate") ? isTruthyFlag(options.allow_rotate) : true;
          var covering = null;
          offers.forEach(function (offer) {
            var nums = getOfferPropertyNumbers(offer, code, delimiter);
            var area = getDimensionArea(nums);
            if (!Number.isFinite(area) || !canSizeFit(customSize, nums, allowRotate)) return;
            if (!covering || area < covering.area) covering = { offer: offer, area: area };
          });
          var baseAmount = getPriceAmountForDriverOffer(referenceOffer, selectedCatalogGroupId, column);
          var coveringAmount = getPriceAmountForDriverOffer(covering && covering.offer, selectedCatalogGroupId, column);
          if (Number.isFinite(baseAmount) && baseAmount > 0 && Number.isFinite(coveringAmount)) {
            adjustment.multiplier *= Math.pow(coveringAmount / baseAmount, sensitivity);
            adjustment.drivers.push(code + ":covering");
            return;
          }
        }

        var referenceArea = getDimensionArea(getOfferPropertyNumbers(referenceOffer, code, delimiter));
        if (Number.isFinite(referenceArea) && referenceArea > 0) {
          adjustment.multiplier *= Math.pow(customArea / referenceArea, sensitivity);
          adjustment.drivers.push(code + ":area");
        }
        return;
      }

      if (type === "pages") {
        var customValue = parseNumber(normalizeValueToken(customRaw), Number.NaN);
        if (!Number.isFinite(customValue) || customValue <= 0) return;
        var values = offers.map(function (offer) {
          return getOfferPropertyScalar(offer, code);
        }).filter(function (num) { return Number.isFinite(num) && num > 0; });
        if (!isWithinRange(customValue, values, allowExtrapolation)) {
          adjustment.valid = false;
          adjustment.messages.push("Значение " + code + " вне рамок опорных ТП");
          return;
        }
        var referenceValue = getOfferPropertyScalar(referenceOffer, code);
        if (Number.isFinite(referenceValue) && referenceValue > 0) {
          adjustment.multiplier *= Math.pow(customValue / referenceValue, sensitivity);
          adjustment.drivers.push(code + ":pages");
        }
      }
    });

    return adjustment;
  }

  function applyCustomPriceDrivers(priceObj, context) {
    var adjustment = buildCustomDriverAdjustment(context || {});
    if (!adjustment.valid) return null;
    if (adjustment.priceOverride) return adjustment.priceOverride;
    if (!priceObj) return null;
    var amount = parseNumber(priceObj.price, Number.NaN);
    if (!Number.isFinite(amount)) return priceObj;
    if (adjustment.multiplier === 1 && !adjustment.drivers.length) return priceObj;
    return clonePriceWithAmount(priceObj, amount * adjustment.multiplier, {
      isEstimated: true,
      drivers: adjustment.drivers
    });
  }

  function getVisibleQuantityPoints(offers, selectedByProperty, customByProperty, fieldByCode, volumeCode, numericPresets) {
    var byQty = {};
    (Array.isArray(numericPresets) ? numericPresets : []).forEach(function (preset) {
      var qty = parseNumber(preset && preset.num, Number.NaN);
      if (!Number.isFinite(qty) || byQty[String(qty)]) return;

      var draftSel = Object.assign({}, selectedByProperty);
      var draftCustom = Object.assign({}, customByProperty || {});
      draftSel[volumeCode] = String(preset.xml_id || qty);
      draftCustom[volumeCode] = false;
      var offer = pickBestReferenceOfferForCustomDrivers(offers, draftSel, draftCustom, fieldByCode);
      if (offer) byQty[String(qty)] = { offer: offer, qty: qty };
    });

    return Object.keys(byQty).map(function (key) {
      return byQty[key];
    }).sort(function (a, b) {
      return a.qty - b.qty;
    });
  }

  function renderPriceTable($block, offers, presetsByCode, selectedByProperty, volumeCode, customVolumeValue, priceGroups, selectedCatalogGroupId, driverContext) {
    var volumePresets = (presetsByCode[volumeCode] || []).slice();
    if (!volumePresets.length) {
      $block.html('<div class="frontcalc-price-empty">Нет значений тиража для таблицы.</div>');
      return;
    }

    var tooltip =
      "Примерный вес и объём тиража. Внимание! Исполнитель выполняет фасовку в соответствии с собственными соображениями оптимального хранения/логистики продукции.";
    var numericPresets = volumePresets
      .map(function (preset) {
        return {
          xml_id: String(preset.xml_id || ""),
          value: String(preset.value || preset.xml_id || ""),
          num: parseNumber(preset.xml_id, Number.NaN),
          isCustom: false,
        };
      })
      .filter(function (row) {
        return Number.isFinite(row.num);
      })
      .sort(function (a, b) {
        return a.num - b.num;
      });
    var merged = numericPresets.slice();
    if (Number.isFinite(customVolumeValue)) {
      if (!merged.some(function (row) { return row.num === customVolumeValue; })) {
        merged.push({ xml_id: String(customVolumeValue), value: String(customVolumeValue), num: customVolumeValue, isCustom: true });
      }
    }
    merged.sort(function (a, b) { return a.num - b.num; });
    var selectedXml = String(selectedByProperty[volumeCode] || (merged[0] && merged[0].xml_id) || "");

    var html = "";
    if (Array.isArray(priceGroups) && priceGroups.length) {
      html += '<div class="frontcalc-price-groups">';
      priceGroups.forEach(function (group) {
        var id = parseNumber(group && group.id, Number.NaN);
        if (!Number.isFinite(id)) return;
        html += '<button type="button" class="frontcalc-price-group' +
          (id === selectedCatalogGroupId ? ' is-active' : '') +
          '" data-catalog-group-id="' + escapeHtml(String(id)) + '">' +
          escapeHtml(group.name || ("PRICE_" + id)) +
          '</button>';
      });
      html += '</div>';
    }
    html += '<div class="frontcalc-volume-input">';
    html +=
      '<input type="text" class="frontcalc-table-input" value="' +
      escapeHtml(selectedXml) +
      '" inputmode="numeric">';
    html +=
      '<div class="frontcalc-volume-btns"><button type="button" class="frontcalc-volume-btn" data-step="-1">−</button><button type="button" class="frontcalc-volume-btn" data-step="1">+</button></div>';
    html += "</div>";
    html +=
      '<div class="frontcalc-table-head"><div>Тираж</div><div>Строгий <span class="frontcalc-tip" title="Отгрузка в соответствии с согласованным сроком"><svg width="17" height="16"><use xlink:href="/bitrix/templates/aspro-premier/images/svg/catalog/item_order_icons.svg?1774850114#attention-16-16"></use></svg></span></div><div>Гибкий <span class="frontcalc-tip" title="Срок отгрузки может быть изменен (не больше 10 рабочих дней)"><svg width="17" height="16"><use xlink:href="/bitrix/templates/aspro-premier/images/svg/catalog/item_order_icons.svg?1774850114#attention-16-16"></use></svg></span></div></div>';
    html += '<div class="frontcalc-table-body">';

    var tableCustomByProperty = driverContext && driverContext.customByProperty ? driverContext.customByProperty : {};
    var tableFieldByCode = driverContext && driverContext.fieldByCode ? driverContext.fieldByCode : {};
    var scopedQuantityPoints = getVisibleQuantityPoints(offers, selectedByProperty, tableCustomByProperty, tableFieldByCode, volumeCode, numericPresets);
    if (!scopedQuantityPoints.length) {
      scopedQuantityPoints = getScopedOffersForQuantity(offers, selectedByProperty, tableCustomByProperty, volumeCode);
    }

    merged.forEach(function (preset, index) {
      var xml = String(preset.xml_id || "");
      var qty = Math.max(1, parseNumber(xml, 1));
      var draftSel = Object.assign({}, selectedByProperty);
      var draftCustom = Object.assign({}, tableCustomByProperty || {});
      draftSel[volumeCode] = xml;
      draftCustom[volumeCode] = false;
      var offer = pickBestReferenceOfferForCustomDrivers(offers, draftSel, draftCustom, tableFieldByCode);
      var strictEstimate = buildQuantityEstimate(scopedQuantityPoints, qty, selectedCatalogGroupId, "strict", offer);
      var flexEstimate = buildQuantityEstimate(scopedQuantityPoints, qty, selectedCatalogGroupId, "flex", offer);
      var strictPrice = strictEstimate.price;
      var flex = flexEstimate.price || strictPrice;
      var rowDriverContext = Object.assign({}, driverContext || {}, {
        referenceOffer: offer || (driverContext && driverContext.anchorOffer) || null,
        targetQty: qty,
        column: "strict"
      });
      strictPrice = applyCustomPriceDrivers(strictPrice, rowDriverContext);
      flex = applyCustomPriceDrivers(flex, Object.assign({}, rowDriverContext, { column: "flex" })) || strictPrice;
      var strictNum = parseNumber(strictPrice && strictPrice.price, Number.NaN);
      var flexNum = parseNumber(flex && flex.price, strictNum);
      var weightText = formatMetric(strictEstimate.weightKg, "кг");
      var volumeText = formatMetric(strictEstimate.volumeM3, "м³");

      html +=
        '<div class="frontcalc-table-row' +
        (xml === selectedXml ? " is-selected" : "") +
        '" data-row-index="' +
        index +
        '" data-xml-id="' +
        escapeHtml(xml) +
        '">';
      html +=
        '<button type="button" class="frontcalc-cell frontcalc-cell--volume"><span class="frontcalc-cell-main" title="' + escapeHtml(String(preset.value || xml)) + '">' +
        escapeHtml(String(preset.value || xml)) +
        '</span><span class="frontcalc-cell-sub" title="' +
        escapeHtml(tooltip) +
        '">' +
        weightText +
        " · " +
        volumeText +
        "</span></button>";
      html +=
        '<button type="button" class="frontcalc-cell" data-col="strict"><span class="frontcalc-cell-main">' +
        escapeHtml(formatMoneyRow(strictPrice)) +
        '</span><span class="frontcalc-cell-sub">' +
        escapeHtml(Number.isFinite(strictNum) ? (strictNum / qty).toFixed(2) + " ₽/экз" : "—") +
        "</span></button>";
      html +=
        '<button type="button" class="frontcalc-cell" data-col="flex"><span class="frontcalc-cell-main">' +
        escapeHtml(formatMoneyRow(flex)) +
        '</span><span class="frontcalc-cell-sub">' +
        escapeHtml(Number.isFinite(flexNum) ? (flexNum / qty).toFixed(2) + " ₽/экз" : "—") +
        "</span></button>";
      html += "</div>";
    });
    html += "</div>";
    $block.html(html);

    var $body = $block.find(".frontcalc-table-body");
    var $selectedRow = $body.find('.frontcalc-table-row[data-xml-id="' + selectedXml + '"]');
    if ($selectedRow.length) {
      var rowH = $selectedRow.outerHeight(true) || 1;
      var selectedIndex = parseNumber($selectedRow.attr("data-row-index"), 0);
      var targetIndex = selectedIndex - 2;
      if (targetIndex >= 0) {
        var targetScroll = targetIndex * rowH;
        var maxScroll = Math.max(0, $body.prop("scrollHeight") - $body.innerHeight());
        if (targetScroll <= maxScroll) {
          $body.scrollTop(targetScroll);
        }
      }
    }
  }

  function renderPriceBlock($block, matchedOffer, driverContext) {
    if (!matchedOffer) {
      $block.html('<div class="frontcalc-price-empty">Для выбранных значений не найдено опорное ТП.</div>');
      return;
    }

    var primaryBuyPrice = (matchedOffer.catalog && matchedOffer.catalog.primary_buy_price) || null;
    primaryBuyPrice = applyCustomPriceDrivers(primaryBuyPrice, Object.assign({}, driverContext || {}, {
      referenceOffer: matchedOffer,
      column: "strict"
    }));
    var weightKg = parseNumber(matchedOffer.catalog && matchedOffer.catalog.weight_kg, 0).toFixed(3);
    var volumeM3 = parseNumber(matchedOffer.catalog && matchedOffer.catalog.volume_m3, 0).toFixed(3);
    var html = "<div class=\"frontcalc-price-main\">";
    html += primaryBuyPrice ? "<div class=\"frontcalc-price-value\">" + escapeHtml(primaryBuyPrice.formatted || (primaryBuyPrice.price + " " + primaryBuyPrice.currency)) + "</div>" : "<div class=\"frontcalc-price-value\">Цена не найдена</div>";
    html += "<div class=\"frontcalc-price-meta\">Вес: " + weightKg + " кг · Объём: " + volumeM3 + " м³" + (primaryBuyPrice && primaryBuyPrice.is_estimated ? " · предварительный расчёт" : "") + "</div></div>";
    $block.html(html);
  }


  function getInputItemsForField(field) {
    if (Array.isArray(field && field.group_inputs)) return field.group_inputs;
    if (Array.isArray(field && field.inputs)) return field.inputs;
    return field ? [field] : [];
  }

  function makeTemplateTargetMap(fields) {
    var map = {};
    (Array.isArray(fields) ? fields : []).forEach(function (field) {
      var propertyCode = getFieldCode(field);
      if (!propertyCode) return;
      var groupCode = String((field.group_code || "") || propertyCode.replace(/^CALC_PROP_/, "").toLowerCase()).trim();
      if (groupCode) map[groupCode] = { propertyCode: propertyCode, group: true };
      getInputItemsForField(field).forEach(function (input) {
        var inputCode = String((input && input.code) || "").trim();
        if (!inputCode) return;
        map[inputCode] = { propertyCode: propertyCode, inputCode: inputCode };
        if (groupCode) map[groupCode + "." + inputCode] = { propertyCode: propertyCode, inputCode: inputCode };
      });
    });
    return map;
  }

  function getDisplayValueForProperty(code, selectedByProperty, customByProperty, offers, anchorOffer) {
    var selected = String(selectedByProperty[code] || "");
    if (customByProperty[code]) return selected;
    var anchorProp = anchorOffer && anchorOffer.properties ? anchorOffer.properties[code] : null;
    if (anchorProp && String(anchorProp.xml_id || "") === selected) return String(anchorProp.value || selected);
    var draft = {};
    draft[code] = selected;
    var offer = pickMatchedOffer(offers || [], draft, {});
    var prop = offer && offer.properties ? offer.properties[code] : null;
    return String((prop && prop.value) || selected);
  }

  function formatTemplateTargetValue(target, fieldByCode, selectedByProperty, customByProperty, offers, anchorOffer) {
    if (!target || !target.propertyCode) return "";
    var field = fieldByCode[target.propertyCode] || {};
    var delimiter = field.group_delimiter || field.split_delimiter || "x";
    var raw = getDisplayValueForProperty(target.propertyCode, selectedByProperty, customByProperty, offers, anchorOffer);
    var values = String(raw).split(delimiter);
    var items = getInputItemsForField(field);
    var pieces = [];

    items.forEach(function (input, index) {
      if (target.inputCode && String(input && input.code || "") !== target.inputCode) return;
      var value = values[index] !== undefined ? values[index] : raw;
      var unit = String((input && input.unit) || "");
      var concatUnit = Object.prototype.hasOwnProperty.call(input || {}, "concat_unit")
        ? isTruthyFlag(input.concat_unit)
        : isTruthyFlag(field.concat_unit);
      if (unit && concatUnit) value += unit;
      pieces.push(value);
    });

    if (!pieces.length) pieces.push(raw);
    return pieces.join(delimiter);
  }

  function renderTitleTemplateNode(node, targetMap, fieldByCode, selectedByProperty, customByProperty, offers, anchorOffer) {
    if (!node || typeof node !== "object") return "";
    var mapping = node.mapping || null;
    var targetKey = String((mapping && mapping.target) || "").trim();
    if (targetKey && targetMap[targetKey]) {
      return formatTemplateTargetValue(targetMap[targetKey], fieldByCode, selectedByProperty, customByProperty, offers, anchorOffer);
    }
    var children = Array.isArray(node.children) ? node.children : [];
    if (children.length) {
      var delimiter = String(node.delimiter || "");
      return children.map(function (child) {
        return renderTitleTemplateNode(child, targetMap, fieldByCode, selectedByProperty, customByProperty, offers, anchorOffer);
      }).join(delimiter);
    }
    return String(node.text || "");
  }


  function hasAnyCustomValue(customByProperty) {
    for (var code in customByProperty) {
      if (Object.prototype.hasOwnProperty.call(customByProperty, code) && customByProperty[code]) {
        return true;
      }
    }
    return false;
  }


  function getCustomPropertyCodes(customByProperty) {
    return Object.keys(customByProperty || {}).filter(function (code) {
      return Object.prototype.hasOwnProperty.call(customByProperty, code) && customByProperty[code];
    });
  }

  function replaceAllLiteral(source, search, replacement) {
    var needle = String(search || "");
    if (!needle) return source;
    return String(source).split(needle).join(String(replacement || ""));
  }

  function formatPlainQuantityValue(value) {
    return normalizeValueToken(value);
  }

  function buildTitleFromDisplayOfferWithCustomValues(displayOffer, selectedByProperty, customByProperty, volumeCode) {
    if (!displayOffer || !displayOffer.name) return "";
    var title = String(displayOffer.name || "");
    getCustomPropertyCodes(customByProperty).forEach(function (code) {
      var prop = displayOffer && displayOffer.properties ? displayOffer.properties[code] : null;
      if (!prop) return;
      var selected = String(selectedByProperty[code] || "").trim();
      if (!selected) return;
      var replacement = code === volumeCode ? formatPlainQuantityValue(selected) : selected;
      [prop.value, prop.xml_id].forEach(function (sourceValue) {
        var source = String(sourceValue || "").trim();
        if (!source || source === replacement) return;
        title = replaceAllLiteral(title, source, replacement);
        var normalizedSource = normalizeValueToken(source);
        if (normalizedSource && normalizedSource !== source && normalizedSource !== replacement) {
          title = replaceAllLiteral(title, normalizedSource, replacement);
        }
      });
    });
    return title;
  }

  function buildOfferTitle(config, targetMap, fieldByCode, selectedByProperty, customByProperty, offers, anchorOffer) {
    var template = config && config.title_template;
    var root = template && template.root;
    if (root && typeof root === "object") {
      var title = renderTitleTemplateNode(root, targetMap, fieldByCode, selectedByProperty, customByProperty, offers, anchorOffer);
      if (title) return title;
    }
    return String((anchorOffer && anchorOffer.name) || "");
  }

  function renderCalculator($content, payload) {
    var data = payload && payload.data ? payload.data : {};
    var config = data.config || {};
    var offers = Array.isArray(data.offers) ? data.offers : [];
    var priceGroups = collectAvailablePriceGroups(data.price_groups_view, offers);
    var selectedCatalogGroupId = priceGroups.length ? parseNumber(priceGroups[0].id, Number.NaN) : Number.NaN;
    var propertyMeta = Array.isArray(data.property_meta) ? data.property_meta : [];
    var priceGroupsView = Array.isArray(data.price_groups_view) ? data.price_groups_view : [];
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
    var titleTargetMap = makeTemplateTargetMap(fields);
    var metaOrderByCode = makeCodeOrderMapFromMeta(propertyMeta);
    var fieldOrderByCode = makeCodeOrderMapFromFields(fields);
    var hiddenByProperty = buildHiddenPresetMap(config);
    var presetsByCode = buildPresetsByProperty(offers, hiddenByProperty);
    mergePresets(presetsByCode, buildPresetsFromConfigFields(fields, hiddenByProperty));
    Object.keys(propertyMetaByCode).forEach(function (code) {
      if (Array.isArray(propertyMetaByCode[code].presets) && propertyMetaByCode[code].presets.length) {
        mergePresets(presetsByCode, (function () {
          var map = {};
          map[code] = propertyMetaByCode[code].presets.filter(function (row) {
            var xmlId = String((row && row.xml_id) || "").trim();
            return !(hiddenByProperty[code] && hiddenByProperty[code][xmlId]);
          });
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
    var customByProperty = {};

    var controlsByCode = {};
    var volumeCode = "CALC_PROP_VOLUME";
    var customVolumeValue = Number.NaN;
    var explicitVolumeStep = resolveConfiguredStep(fieldByCode[volumeCode]);
    var derivedVolumeStep = deriveStepFromPresets(presetsByCode[volumeCode]);
    var volumeStep = Number.isFinite(explicitVolumeStep) && explicitVolumeStep > 0
      ? explicitVolumeStep
      : (Number.isFinite(derivedVolumeStep) && derivedVolumeStep > 0 ? derivedVolumeStep : 1);
    var volumeMin = parseNumber(fieldByCode[volumeCode] && fieldByCode[volumeCode].min, Number.NaN);
    var volumeMax = parseNumber(fieldByCode[volumeCode] && fieldByCode[volumeCode].max, Number.NaN);

    function getCurrentVolumeStepInfo() {
      var configuredStep = Number.isFinite(explicitVolumeStep) && explicitVolumeStep > 0 ? explicitVolumeStep : volumeStep;
      if (isSmartVolumeStepEnabled(fieldByCode, volumeCode)) {
        var capacity = resolveProductionSheetCapacityForSelection(offers, selectedByProperty, customByProperty, fieldByCode);
        if (Number.isFinite(capacity) && capacity > 1) {
          var productionStep = roundProductionVolumeStep(configuredStep * capacity, configuredStep);
          if (Number.isFinite(productionStep) && productionStep > 0) {
            return { step: productionStep, isProduction: true, capacity: capacity };
          }
        }
      }
      return { step: configuredStep, isProduction: false, capacity: Number.NaN };
    }

    function roundProductionVolumeBound(rawValue, step, mode) {
      var value = parseNumber(rawValue, Number.NaN);
      if (!Number.isFinite(value)) return value;
      if (!Number.isFinite(step) || step <= 0) return value;
      var ratio = value / step;
      return mode === "max" ? Math.floor(ratio) * step : Math.ceil(ratio) * step;
    }

    function getCurrentVolumeBounds(defaultMinValue, defaultMaxValue, stepInfo) {
      var info = stepInfo || getCurrentVolumeStepInfo();
      var minValue = Number.isFinite(volumeMin) ? volumeMin : defaultMinValue;
      var maxValue = Number.isFinite(volumeMax) ? volumeMax : defaultMaxValue;

      if (info.isProduction && Number.isFinite(info.capacity) && info.capacity > 1) {
        if (Number.isFinite(volumeMin)) {
          minValue = roundProductionVolumeBound(volumeMin * info.capacity, info.step, "min");
        }
        if (Number.isFinite(volumeMax)) {
          maxValue = roundProductionVolumeBound(volumeMax * info.capacity, info.step, "max");
        }
        if (Number.isFinite(minValue) && Number.isFinite(maxValue) && maxValue < minValue) {
          maxValue = minValue;
        }
      }

      return { min: minValue, max: maxValue };
    }

    function normalizeVolumeByStep(value, minValue, maxValue, stepInfo) {
      var info = stepInfo || getCurrentVolumeStepInfo();
      var base = info.isProduction ? 0 : minValue;
      return normalizeToStep(clamp(value, minValue, maxValue), base, info.step);
    }

    function moveVolumeByStep(current, direction, minValue, maxValue, stepInfo) {
      var info = stepInfo || getCurrentVolumeStepInfo();
      var step = info.step;
      if (!info.isProduction) {
        return normalizeToStep(clamp(current + direction * step, minValue, maxValue), minValue, step);
      }

      var next = direction > 0
        ? (Math.floor(current / step) + 1) * step
        : (Math.ceil(current / step) - 1) * step;
      return clamp(next, minValue, maxValue);
    }

    function pickDefaultOfferBySort(offersList, codes) {
      if (!Array.isArray(offersList) || !offersList.length) return null;
      var best = null;
      offersList.forEach(function (offer) {
        var rank = codes.map(function (code) {
          var prop = offer && offer.properties ? offer.properties[code] : null;
          return parseNumber(prop && prop.sort, 500);
        });
        if (!best) {
          best = { offer: offer, rank: rank };
          return;
        }
        for (var i = 0; i < rank.length; i++) {
          if (rank[i] < best.rank[i]) {
            best = { offer: offer, rank: rank };
            return;
          }
          if (rank[i] > best.rank[i]) return;
        }
      });
      return best ? best.offer : null;
    }

    var requestedOfferId = parseNumber(data.requested_offer_id, 0);
    var defaultOffer = null;
    if (requestedOfferId > 0) {
      defaultOffer = offers.find(function (offer) {
        return parseNumber(offer && offer.id, 0) === requestedOfferId;
      }) || null;
    }
    if (!defaultOffer) {
      defaultOffer = pickDefaultOfferBySort(offers, allCodes);
    }
    if (defaultOffer && defaultOffer.properties && defaultOffer.properties[volumeCode]) {
      selectedByProperty[volumeCode] = String(defaultOffer.properties[volumeCode].xml_id || "").trim();
      customByProperty[volumeCode] = false;
    }
    var anchorOffer = defaultOffer;
    if (defaultOffer && defaultOffer.catalog && defaultOffer.catalog.primary_buy_price) {
      var primaryGroupId = parseNumber(defaultOffer.catalog.primary_buy_price.catalog_group_id, Number.NaN);
      if (Number.isFinite(primaryGroupId) && priceGroups.some(function (group) { return parseNumber(group && group.id, Number.NaN) === primaryGroupId; })) {
        selectedCatalogGroupId = primaryGroupId;
      }
    }
    var selectorCodes = allCodes.filter(function (code) {
      return code !== volumeCode;
    });

    selectorCodes.forEach(function (code) {
      if (code === volumeCode) {
        return;
      }
      var presets = Array.isArray(presetsByCode[code]) ? presetsByCode[code] : [];
      var defaultXmlId = "";
      if (defaultOffer && defaultOffer.properties && defaultOffer.properties[code]) {
        defaultXmlId = String(defaultOffer.properties[code].xml_id || "").trim();
      }
      var existsInPresets = presets.some(function (preset) {
        return String((preset && preset.xml_id) || "") === defaultXmlId;
      });
      if (existsInPresets) {
        selectedByProperty[code] = defaultXmlId;
      } else if (presets.length) {
        selectedByProperty[code] = presets[0].xml_id;
      }
      customByProperty[code] = false;
    });

    if (!selectedByProperty[volumeCode] && Array.isArray(presetsByCode[volumeCode]) && presetsByCode[volumeCode].length) {
      selectedByProperty[volumeCode] = String(presetsByCode[volumeCode][0].xml_id || "");
      customByProperty[volumeCode] = false;
    }

    var $layout = $('<div class="frontcalc-layout"></div>');
    var $selectors = $('<div class="frontcalc-selectors"></div>');
    var $title = $('<h2 class="frontcalc-offer-title"></h2>');
    $selectors.append($title);
    var $price = $('<aside class="frontcalc-price-panel"><div class="frontcalc-price-panel__inner"></div></aside>');
    var $priceInner = $price.find(".frontcalc-price-panel__inner");

    selectorCodes.forEach(function (code) {
      var fieldConfig = fieldByCode[code] || {};
      var label = getFieldLabel(fieldConfig, propertyMetaByCode, code);
      var $section = $('<section class="frontcalc-field"></section>');
      if (label) {
        $section.append('<div class="frontcalc-field__title">' + escapeHtml(label) + "</div>");
      }

      var presets = Array.isArray(presetsByCode[code]) ? presetsByCode[code] : [];
      var $chips = createPresetButtons(presets, function (preset) {
        selectedByProperty[code] = preset.xml_id;
        customByProperty[code] = false;
        updatePrice();
      });
      if (selectedByProperty[code]) {
        $chips.find('.frontcalc-chip[data-xml-id="' + selectedByProperty[code] + '"]').addClass("is-active");
      }
      controlsByCode[code] = { chips: $chips, presets: presets };

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
        var uiGroupDivider = "×";
        if (!groupItems.length) groupItems = [fieldConfig];
        var selectedXmlForCode = String(selectedByProperty[code] || "");
        var selectedParts = selectedXmlForCode ? selectedXmlForCode.split(delimiter) : [];

        var $group = $('<div class="frontcalc-input-group"></div>');
        groupItems.forEach(function (item, idx) {
          if (idx > 0) {
            $group.append('<span class="frontcalc-input-group-divider">' + uiGroupDivider + "</span>");
          }
          var selectedPart = idx < selectedParts.length ? selectedParts[idx] : "";
          var initial = parseNumber(
            selectedPart !== "" ? selectedPart : item.default,
            parseNumber(item.default, 0)
          );
          var $inputField = createInputControl(
            item,
            initial,
            function () {
              var groupValues = [];
              $section.find(".frontcalc-input-group .frontcalc-num-input").each(function () {
                groupValues.push(String($(this).val() || "").trim());
              });

              var groupComplete = isCompleteGroupInput(groupValues);
              if (!groupComplete) {
                customByProperty[code] = false;
                updatePrice();
                return;
              }

              var compositeValue = groupValues.join(delimiter);
              var normalizedCompositeValue = groupValues
                .map(function (value) {
                  return normalizeValueToken(value);
                })
                .join(delimiter);

              var matchedPreset =
                findPresetByInputValue(presets, compositeValue) ||
                (normalizedCompositeValue !== compositeValue
                  ? findPresetByInputValue(presets, normalizedCompositeValue)
                  : null);
              if (matchedPreset) {
                selectedByProperty[code] = matchedPreset.xml_id;
                customByProperty[code] = false;
                $chips.find(".is-active").removeClass("is-active");
                $chips.find('.frontcalc-chip[data-xml-id="' + matchedPreset.xml_id + '"]').addClass("is-active");
              } else {
                selectedByProperty[code] = compositeValue;
                customByProperty[code] = true;
                $chips.find(".is-active").removeClass("is-active");
              }
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
          var presetInteractionInProgress = false;
          $chips.hide();
          $chips.on("mousedown touchstart", ".frontcalc-chip", function () {
            presetInteractionInProgress = true;
          });
          $section.on("focusin", ".frontcalc-num-input", function () {
            $chips.show();
          });
          $section.on("focusout", ".frontcalc-num-input", function () {
            setTimeout(function () {
              if (presetInteractionInProgress) return;
              if ($(document.activeElement).hasClass("frontcalc-num-input")) return;
              $chips.hide();
            }, 0);
          });

          $chips.on("click", ".frontcalc-chip", function () {
            presetInteractionInProgress = false;
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
      var availableByCode = buildAvailableValuesByCode(offers, allCodes, selectedByProperty, customByProperty);

      allCodes.forEach(function (code) {
        var ui = controlsByCode[code];
        if (!ui || !ui.chips) return;
        var available = availableByCode[code] || {};

        ui.chips.find(".frontcalc-chip").each(function () {
          var $chip = $(this);
          var xmlId = String($chip.attr("data-xml-id") || "");
          var enabled = !!available[xmlId];
          $chip.prop("disabled", !enabled);
          $chip.toggleClass("is-disabled", !enabled);
        });

        if (customByProperty[code]) return;
        var selectedXmlId = selectedByProperty[code];
        if (selectedXmlId && !available[selectedXmlId]) {
          var fallback = null;
          for (var i = 0; i < ui.presets.length; i++) {
            var preset = ui.presets[i];
            if (available[String(preset.xml_id || "")]) {
              fallback = preset;
              break;
            }
          }
          selectedByProperty[code] = fallback ? fallback.xml_id : "";
          ui.chips.find(".is-active").removeClass("is-active");
          if (fallback) {
            ui.chips.find('.frontcalc-chip[data-xml-id="' + fallback.xml_id + '"]').addClass("is-active");
          }
        }
      });

      var matched = pickMatchedOffer(offers, selectedByProperty, customByProperty);
      var displayOffer = matched || pickMatchedOfferIgnoringCustom(offers, selectedByProperty, customByProperty, null) || anchorOffer;
      if (displayOffer) {
        anchorOffer = displayOffer;
      }
      var titleText = matched && !hasAnyCustomValue(customByProperty)
        ? String(matched.name || "")
        : buildTitleFromDisplayOfferWithCustomValues(displayOffer, selectedByProperty, customByProperty, volumeCode);
      if (!titleText) {
        titleText = buildOfferTitle(config, titleTargetMap, fieldByCode, selectedByProperty, customByProperty, offers, displayOffer);
      }
      $title.text(titleText);
      var driverContext = {
        offers: getFilteredOffers(offers, selectedByProperty, customByProperty, null),
        allOffers: offers,
        volumeCode: volumeCode,
        fieldByCode: fieldByCode,
        selectedByProperty: selectedByProperty,
        customByProperty: customByProperty,
        anchorOffer: displayOffer,
        targetQty: parseNumber(selectedByProperty[volumeCode], 1),
        selectedCatalogGroupId: selectedCatalogGroupId
      };
      if (presetsByCode[volumeCode] && presetsByCode[volumeCode].length) {
        renderPriceTable($priceInner, offers, presetsByCode, selectedByProperty, volumeCode, customVolumeValue, priceGroups, selectedCatalogGroupId, driverContext);
      } else {
        renderPriceBlock($priceInner, matched || displayOffer, driverContext);
      }
    }


    $price.on("click", ".frontcalc-price-group", function () {
      var groupId = parseNumber($(this).attr("data-catalog-group-id"), Number.NaN);
      if (!Number.isFinite(groupId)) return;
      selectedCatalogGroupId = groupId;
      updatePrice();
    });

    $price.on("click", ".frontcalc-table-row .frontcalc-cell", function () {
      var $cell = $(this);
      var $row = $cell.closest('.frontcalc-table-row');
      var xmlId = String($row.attr('data-xml-id') || '');
      if (!xmlId) return;
      $price.find(".frontcalc-cell.is-picked").removeClass("is-picked");
      $cell.addClass("is-picked");
      selectedByProperty[volumeCode] = xmlId;
      var numericXmlId = parseNumber(xmlId, Number.NaN);
      var knownPreset = findPresetByInputValue(presetsByCode[volumeCode] || [], xmlId);
      customVolumeValue = !knownPreset && Number.isFinite(numericXmlId) ? numericXmlId : Number.NaN;
      customByProperty[volumeCode] = !knownPreset;
      updatePrice();
    });

    $price.on("mouseenter", ".frontcalc-table-row .frontcalc-cell", function () {
      var $cell = $(this);
      var colIndex = $cell.index();
      var $row = $cell.closest(".frontcalc-table-row");
      $price.find(".is-hover-row,.is-hover-col").removeClass("is-hover-row is-hover-col");
      $row.children(".frontcalc-cell").addClass("is-hover-row");
      $price.find(".frontcalc-table-row").each(function () {
        $(this).children(".frontcalc-cell").eq(colIndex).addClass("is-hover-col");
      });
    });
    $price.on("mouseleave", ".frontcalc-table-row .frontcalc-cell", function () {
      $price.find(".is-hover-row,.is-hover-col").removeClass("is-hover-row is-hover-col");
    });

    $price.on("click", ".frontcalc-volume-btn", function () {
      var direction = parseNumber($(this).attr('data-step'), 0);
      var list = (presetsByCode[volumeCode] || []).map(function (p) { return parseNumber(p.xml_id, Number.NaN); }).filter(Number.isFinite).sort(function(a,b){return a-b;});
      if (!list.length) return;
      var currentVolumeStep = getCurrentVolumeStepInfo();
      var currentVolumeBounds = getCurrentVolumeBounds(list[0], Number.POSITIVE_INFINITY, currentVolumeStep);
      var minV = currentVolumeBounds.min;
      var maxV = currentVolumeBounds.max;
      var current = parseNumber(selectedByProperty[volumeCode], minV);
      var next = moveVolumeByStep(current, direction, minV, maxV, currentVolumeStep);
      var nextPreset = findPresetByInputValue(presetsByCode[volumeCode] || [], String(next));
      customVolumeValue = nextPreset ? Number.NaN : next;
      selectedByProperty[volumeCode] = nextPreset ? String(nextPreset.xml_id || next) : String(next);
      customByProperty[volumeCode] = !nextPreset;
      updatePrice();
    });

    $price.on("change", ".frontcalc-table-input", function () {
      var raw = normalizeValueToken($(this).val());
      var list = presetsByCode[volumeCode] || [];
      var preset = findPresetByInputValue(list, raw);
      if (preset) {
        selectedByProperty[volumeCode] = String(preset.xml_id || '');
        customVolumeValue = Number.NaN;
        customByProperty[volumeCode] = false;
      } else {
        var presetNums = list.map(function (p) { return parseNumber(p.xml_id, Number.NaN); }).filter(Number.isFinite).sort(function(a,b){return a-b;});
        if (!presetNums.length) return;
        var currentVolumeStep = getCurrentVolumeStepInfo();
        var currentVolumeBounds = getCurrentVolumeBounds(presetNums[0], Number.POSITIVE_INFINITY, currentVolumeStep);
        var minV = currentVolumeBounds.min;
        var maxV = currentVolumeBounds.max;
        var val = parseNumber(raw, minV);
        val = normalizeVolumeByStep(val, minV, maxV, currentVolumeStep);
        customVolumeValue = val;
        selectedByProperty[volumeCode] = String(val);
        customByProperty[volumeCode] = true;
      }
      updatePrice();
    });

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
    var currentOid = "";
    try {
      var currentUrl = new URL(window.location.href);
      currentOid = currentUrl.searchParams.get("oid") || "";
    } catch (e) {}
    var requestUrl = ajaxUrl + divider + "product_id=" + encodeURIComponent(productId);
    if (currentOid) {
      requestUrl += "&offer_id=" + encodeURIComponent(currentOid);
    }
    $button.prop("disabled", true);

    loadJqmScript(function () {
      var $frame = createFrame();
      $frame.jqm({
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
