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

  function pickFlexiblePrice(pricesView, strictPrice) {
    if (!Array.isArray(pricesView) || !pricesView.length) return strictPrice;
    return pricesView[2] || pricesView[1] || strictPrice;
  }

  function renderPriceTable($block, offers, presetsByCode, selectedByProperty, volumeCode) {
    var volumePresets = (presetsByCode[volumeCode] || []).slice();
    if (!volumePresets.length) {
      $block.html('<div class="frontcalc-price-empty">Нет значений тиража для таблицы.</div>');
      return;
    }

    var tooltip =
      "Примерный вес и объём тиража. Внимание! Исполнитель выполняет фасовку в соответствии с собственными соображениями оптимального хранения/логистики продукции.";
    var selectedXml = String(selectedByProperty[volumeCode] || volumePresets[0].xml_id || "");

    var html = '<div class="frontcalc-volume-input">';
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

    volumePresets.forEach(function (preset, index) {
      var xml = String(preset.xml_id || "");
      var draftSel = Object.assign({}, selectedByProperty);
      draftSel[volumeCode] = xml;
      var offer = pickMatchedOffer(offers, draftSel, {});
      var strictPrice = offer && offer.catalog ? offer.catalog.primary_buy_price || null : null;
      var strictNum = parseNumber(strictPrice && strictPrice.price, 0);
      var qty = Math.max(1, parseNumber(xml, 1));
      var flex = pickFlexiblePrice(offer && offer.catalog ? offer.catalog.prices_view : [], strictPrice);
      var flexNum = parseNumber(flex && flex.price, strictNum);
      var weightKg = parseNumber(offer && offer.catalog && offer.catalog.weight_kg, 0).toFixed(3);
      var volumeM3 = parseNumber(offer && offer.catalog && offer.catalog.volume_m3, 0).toFixed(3);

      html +=
        '<div class="frontcalc-table-row' +
        (xml === selectedXml ? " is-selected" : "") +
        '" data-row-index="' +
        index +
        '" data-xml-id="' +
        escapeHtml(xml) +
        '">';
      html +=
        '<button type="button" class="frontcalc-cell frontcalc-cell--volume"><span class="frontcalc-cell-main">' +
        escapeHtml(String(preset.value || xml)) +
        '</span><span class="frontcalc-cell-sub" title="' +
        escapeHtml(tooltip) +
        '">' +
        weightKg +
        " кг · " +
        volumeM3 +
        " м³</span></button>";
      html +=
        '<button type="button" class="frontcalc-cell" data-col="strict"><span class="frontcalc-cell-main">' +
        escapeHtml(formatMoneyRow(strictPrice)) +
        '</span><span class="frontcalc-cell-sub">' +
        escapeHtml((strictNum / qty).toFixed(2) + " ₽/экз") +
        "</span></button>";
      html +=
        '<button type="button" class="frontcalc-cell" data-col="flex"><span class="frontcalc-cell-main">' +
        escapeHtml(formatMoneyRow(flex)) +
        '</span><span class="frontcalc-cell-sub">' +
        escapeHtml((flexNum / qty).toFixed(2) + " ₽/экз") +
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

  function renderPriceBlock($block, matchedOffer) {
    if (!matchedOffer) {
      $block.html('<div class="frontcalc-price-empty">Для произвольных значений цена пока не рассчитывается.</div>');
      return;
    }

    var primaryBuyPrice = (matchedOffer.catalog && matchedOffer.catalog.primary_buy_price) || null;
    var weightKg = parseNumber(matchedOffer.catalog && matchedOffer.catalog.weight_kg, 0).toFixed(3);
    var volumeM3 = parseNumber(matchedOffer.catalog && matchedOffer.catalog.volume_m3, 0).toFixed(3);
    var html = "<div class=\"frontcalc-price-main\">";
    html += primaryBuyPrice ? "<div class=\"frontcalc-price-value\">" + escapeHtml(primaryBuyPrice.formatted || (primaryBuyPrice.price + " " + primaryBuyPrice.currency)) + "</div>" : "<div class=\"frontcalc-price-value\">Цена не найдена</div>";
    html += "<div class=\"frontcalc-price-meta\">Вес: " + weightKg + " кг · Объём: " + volumeM3 + " м³</div></div>";
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
    allCodes.forEach(function (code) {
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
      if (presetsByCode[volumeCode] && presetsByCode[volumeCode].length) {
        renderPriceTable($priceInner, offers, presetsByCode, selectedByProperty, volumeCode);
      } else {
        renderPriceBlock($priceInner, matched);
      }
    }


    $price.on("click", ".frontcalc-table-row .frontcalc-cell", function () {
      var $cell = $(this);
      var $row = $cell.closest('.frontcalc-table-row');
      var xmlId = String($row.attr('data-xml-id') || '');
      if (!xmlId) return;
      $price.find(".frontcalc-cell.is-picked").removeClass("is-picked");
      $cell.addClass("is-picked");
      selectedByProperty[volumeCode] = xmlId;
      customByProperty[volumeCode] = false;
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
      var step = parseNumber($(this).attr('data-step'), 0);
      var list = presetsByCode[volumeCode] || [];
      var current = String(selectedByProperty[volumeCode] || '');
      var idx = list.findIndex(function (p) { return String(p.xml_id) === current; });
      if (idx < 0) idx = 0;
      idx = clamp(idx + step, 0, Math.max(0, list.length - 1));
      if (list[idx]) selectedByProperty[volumeCode] = String(list[idx].xml_id || '');
      updatePrice();
    });

    $price.on("change", ".frontcalc-table-input", function () {
      var raw = normalizeValueToken($(this).val());
      var list = presetsByCode[volumeCode] || [];
      var preset = findPresetByInputValue(list, raw);
      if (preset) {
        selectedByProperty[volumeCode] = String(preset.xml_id || '');
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
