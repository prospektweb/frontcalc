appAspro.popup = {};

appAspro.popup.onLoadjqm = (name, hash, _this) => {
  if (funcDefined("onlyCatalogMenuClose")) {
    onlyCatalogMenuClose();
  }

  BX.Aspro?.xPopover?.hide();
  BX.Aspro?.Map?.closeFullscreenMap();

  if (hash.c.noOverlay === undefined || (hash.c.noOverlay !== undefined && !hash.c.noOverlay)) {
    $("body").addClass("jqm-initied");
  }

  $("body").addClass("swipeignore");

  if (typeof $(hash.t).data("ls") !== " undefined" && $(hash.t).data("ls")) {
    var ls = $(hash.t).data("ls"),
      ls_timeout = 0,
      v = "";

    if ($(hash.t).data("ls_timeout")) ls_timeout = $(hash.t).data("ls_timeout");

    ls_timeout = ls_timeout ? Date.now() + ls_timeout * 1000 : "";

    if (typeof localStorage !== "undefined") {
      var val = localStorage.getItem(ls);
      try {
        v = JSON.parse(val);
      } catch (e) {
        v = val;
      }
      if (v != null) {
        localStorage.removeItem(ls);
      }
      v = {};
      v["VALUE"] = "Y";
      v["TIMESTAMP"] = ls_timeout; // default: seconds for 1 day

      localStorage.setItem(ls, JSON.stringify(v));
    } else {
      var val = $.cookie(ls);
      if (!val) $.cookie(ls, "Y", { expires: ls_timeout }); // default: seconds for 1 day
    }

    var dopClasses = hash.w.find(".marketing-popup").data("classes");
    if (dopClasses) {
      hash.w.addClass(dopClasses);
    }
  }

  //update show password
  //show password eye
  if (hash.w.hasClass("auth_frame")) {
    hash.w.find(".form-group:not(.eye-password-ignore) [type=password]").each(function (item) {
      let inputBlock = $(this).closest(".input");
      if (inputBlock.length) {
        inputBlock.addClass("eye-password");
      } else {
        let labelBlock = passBlock.find(".label_block");
        let passBlock = $(this).closest(".form-group");
        if (labelBlock.length) {
          labelBlock.addClass("eye-password");
        } else {
          passBlock.addClass("eye-password");
        }
      }
    });
  }

  $.each($(hash.t).get(0).attributes, function (index, attr) {
    if (/^data\-autoload\-(.+)$/.test(attr.nodeName)) {
      var key = attr.nodeName.match(/^data\-autoload\-(.+)$/)[1];
      var el = $('input[name="' + key.toUpperCase() + '"]');
      if (!el.length) {
        //is form block
        el = $('input[data-sid="' + key.toUpperCase() + '"]');
      }

      var value = $(hash.t).data("autoload-" + key);
      value = value.toString().replace(/%99/g, "\\"); // replace symbol \

      el.val(BX.util.htmlspecialcharsback(value)).attr("readonly", "readonly");
      el.closest(".form-group").addClass("input-filed");
      el.attr("title", el.val());
    }
  });

  //show gift block
  if (hash.w.hasClass("send_gift_frame")) {
    let imgHtml, priceHtml, propsHtml;
    imgHtml = priceHtml = propsHtml = "";

    if ($('.detail-gallery-big link[itemprop="image"]').length) {
      imgHtml =
        '<img class="image-rounded-x" src="' +
        $('.detail-gallery-big link[itemprop="image"]').attr("href") +
        '" alt="" title="" />';
    }

    if ($(".catalog-detail__buy-block .price__new").length) {
      priceHtml =
        '<div class="item-block-info__price color_222 fw-500">' +
        $(".catalog-detail__buy-block .price__new-val").text() +
        "</div>";
    }

    if ($(".catalog-block__offers .sku-props__item").length) {
      propsHtml = '<div class="item-block-info__props font_13">';
      $(".catalog-block__offers .sku-props__item").each(function () {
        propsHtml += '<div class="item-block-info__props-item">' + $(this).find(".sku-props__title").text() + "</div>";
      });
      propsHtml += "</div>";
    }

    $(
      '<div class="popup__item-block">' +
        '<div class="popup__item-block-info bordered outer-rounded-x grid-list gap gap--16">' +
        '<div class="item-block-info__image grid-list__item">' +
        imgHtml +
        "</div>" +
        '<div class="item-block-info__text grid-list__item">' +
        '<div class="name color_222 font_14">' +
        $("h1").text() +
        "</div>" +
        priceHtml +
        propsHtml +
        "</div>" +
        "</div>" +
        "</div>"
    ).prependTo(hash.w.find(".form-body"));
  }

  if (hash.c.noOverlay === undefined || (hash.c.noOverlay !== undefined && !hash.c.noOverlay)) {
    hash.w.closest("#popup_iframe_wrapper").css({ "z-index": 3000, display: "flex" });
  }

  var eventdata = { action: "loadForm" };
  BX.onCustomEvent("onCompleteAction", [eventdata, $(hash.t)[0]]);

  if ($(hash.t).data("autohide")) {
    $(hash.w).data("autohide", $(hash.t).data("autohide"));
  }

  if ($(hash.t).data("autoshow")) {
    eval($(hash.t).data("autoshow"));
  }

  if (name == "order_product") {
    if ($(hash.t).data("product")) {
      $('input[name="PRODUCT"]').closest(".form-group").addClass("input-filed");
      $('input[name="PRODUCT"]')
        .val($(hash.t).data("product"))
        .attr("readonly", "readonly")
        .attr("title", $('input[name="PRODUCT"]').val());
    }
  }
  if (name == "question") {
    if ($(hash.t).data("product")) {
      $('input[name="NEED_PRODUCT"]').closest(".form-group").addClass("input-filed");
      $('input[name="NEED_PRODUCT"]')
        .val($(hash.t).data("product"))
        .attr("readonly", "readonly")
        .attr("title", $('input[name="NEED_PRODUCT"]').val());
    }
  }

  if (name == "fast_view" && $(".smart-filter-filter").length) {
    var navButtons =
      '<div class="navigation-wrapper-fast-view">' +
      '<div class="fast-view-nav prev bg-theme-hover" data-fast-nav="prev">' +
      '<i class="svg left">' +
      '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="6.969" viewBox="0 0 12 6.969"><path id="Rounded_Rectangle_702_copy_24" data-name="Rounded Rectangle 702 copy 24" class="cls-1" d="M361.691,401.707a1,1,0,0,1-1.414,0L356,397.416l-4.306,4.291a1,1,0,0,1-1.414,0,0.991,0.991,0,0,1,0-1.406l5.016-5a1.006,1.006,0,0,1,1.415,0l4.984,5A0.989,0.989,0,0,1,361.691,401.707Z" transform="translate(-350 -395.031)"/></svg>' +
      "</i>" +
      "</div>" +
      '<div class="fast-view-nav next bg-theme-hover" data-fast-nav="next">' +
      '<i class="svg right">' +
      '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="6.969" viewBox="0 0 12 6.969"><path id="Rounded_Rectangle_702_copy_24" data-name="Rounded Rectangle 702 copy 24" class="cls-1" d="M361.691,401.707a1,1,0,0,1-1.414,0L356,397.416l-4.306,4.291a1,1,0,0,1-1.414,0,0.991,0.991,0,0,1,0-1.406l5.016-5a1.006,1.006,0,0,1,1.415,0l4.984,5A0.989,0.989,0,0,1,361.691,401.707Z" transform="translate(-350 -395.031)"/></svg>' +
      "</i>" +
      "</div>" +
      "</div>";

    // hash.w.addClass("no_custom_scroll");
    hash.w.closest("#popup_iframe_wrapper").append(navButtons);
  }

  var needScrollbar = true;

  if (
    arAsproOptions["THEME"]["REGIONALITY_SEARCH_ROW"] == "Y" &&
    (hash.w.hasClass("city_chooser_frame") || hash.w.hasClass("city_chooser_small_frame"))
  ) {
    hash.w.addClass("small_popup_regions");
    hash.w.addClass("jqmWindow--overflow-visible");
    needScrollbar = false;
  }

  hash.w.addClass("show").css({ opacity: 1 });
  if (needScrollbar) hash.w.find(">div").addClass("scrollbar");
};

appAspro.popup.onHidejqm = (name, hash) => {
  if ($(hash.w).data("autohide")) {
    eval($(hash.w).data("autohide"));
  }

  hash.c.trigger.removeClass("clicked");
  hash.c.trigger.parent().removeClass("loadings");

  // hash.w.css('opacity', 0).hide();
  hash.w.animate({ opacity: 0 }, 200, function () {
    // hash.w.removeClass("scroll-init srollbar-custom").mCustomScrollbar("destroy");
    hash.w.hide();
    hash.w.empty();
    hash.o.remove();
    hash.w.removeClass("show");
    hash.w.removeClass("success");

    if (!hash.w.closest("#popup_iframe_wrapper").find(".jqmOverlay").length) {
      $("body").css({ overflow: "", height: "" });
      hash.w.closest("#popup_iframe_wrapper").css({ "z-index": "", display: "" });
    }

    if (window.matchMedia("(max-width: 991px)").matches) {
      $("body").removeClass("all_viewed");
    }
    if ((!$(".overlay").length && !$(".jqmOverlay").length) || $(".jqmOverlay.waiting").length) {
      $("body").removeClass("jqm-initied");
    }

    $("body").removeClass("swipeignore");
    $("body").removeClass("overflow-block");

    if (name == "fast_view") {
      $(".fast_view_popup").remove();

      var navButtons = hash.w.closest("#popup_iframe_wrapper").find(".navigation-wrapper-fast-view");
      navButtons.remove();
    }

    if (name == "stores") {
      window.GLOBAL_arMapObjects = {};
    }
  });

  window.b24form = false;
};

$.fn.jqmEx = function (onLoad, onHide) {
  $(this).each(function () {
    let $this = $(this);
    let name = $this.data("name");
    name = typeof name === "undefined" || !name.length ? "noname" : name;

    let paramsStr = "";
    let trigger = $this;

    // trigger attrs and params
    $.each($this.get(0).attributes, function (index, attr) {
      var attrName = attr.nodeName;
      var attrValue = $this.attr(attrName);
      if (/^data\-param\-(.+)$/.test(attrName)) {
        var key = attrName.match(/^data\-param\-(.+)$/)[1];
        paramsStr += key + "=" + attrValue.replace(/ /g, "+") + "&";
      }
    });


    // one click buy with sale
    if (name == "ocb" && arAsproOptions.MODULES.sale) {
        const $buyAction = $this.closest(".js-popup-block").find('.buy_block .js-item-action[data-action="basket"]');
      if ($buyAction.length) {
        let action = JItemAction.factory($buyAction[0]);
        if (action.valid) {
          let dataItem = action.data;
          if (dataItem) {
            paramsStr += "ELEMENT_ID=" + dataItem.ID + "&";
            paramsStr += "IBLOCK_ID=" + dataItem.IBLOCK_ID + "&";
            paramsStr += "ELEMENT_QUANTITY=" + action.quantity + "&";
          }

          let props = action.node.getAttribute("data-props") || "";
          try {
            props = props.length ? props.split(";") : [];
          } catch (e) {
            props = [];
          }

          paramsStr += "OFFER_PROPS=" + JSON.stringify(props) + "&";
        }
      }
    }

    // popup url
    let script = arAsproOptions["SITE_DIR"] + "ajax/form.php";
    if (name == "auth") {
      script += "?" + paramsStr + "auth=Y";
    } else {
      script += "?" + paramsStr;
    }

    // ext frame class
    let extClass = "";
    if ($this.closest("#fast_view_item").length) {
      extClass = "fast_view_popup";
    }
    if($this.data('name') === 'ocb'){
      extClass+= ' compact'
    }


    // use overlay?
    var noOverlay = $this.data("noOverlay") == "Y";

    // call counter
    if (typeof $.fn.jqmEx.counter === "undefined") {
      $.fn.jqmEx.counter = 0;
    } else {
      ++$.fn.jqmEx.counter;
    }

    // unique frame to each trigger
    if (noOverlay) {
      var frame = $(
        '<div class="' +
          name +
          "_frame " +
          extClass +
          ' jqmWindow jqmWindow--mobile-fill popup" data-popup="' +
          $.fn.jqmEx.counter +
          '"></div>'
      ).appendTo("body");
    } else {
      var frame = $(
        '<div class="' +
          name +
          "_frame " +
          extClass +
          ' jqmWindow jqmWindow--mobile-fill popup" data-popup="' +
          $.fn.jqmEx.counter +
          '"></div>'
      ).appendTo("#popup_iframe_wrapper");
    }
    appAspro.loadScript([arAsproOptions["SITE_TEMPLATE_PATH"] + "/js/jqModal.js"], () => {
      frame.jqm({
        ajax: script,
        trigger: trigger,
        noOverlay: noOverlay,
        onLoad: function (hash) {
          appAspro.popup.onLoadjqm(name, hash, $this);

          if (typeof onLoad === "function") {
            onLoad(name, hash, $this);
          }
        },
        onHide: function (hash) {
          appAspro.popup.onHidejqm(name, hash, $this);

          if (typeof onHide === "function") {
            onHide(name, hash, $this);
          }
        },
      });
      $this.trigger("click");
    });
  });
};

$(document).on("click", '*[data-event="jqm"]', function (e) {
  e.preventDefault();
  e.stopPropagation();
  if (!$(this).hasClass("clicked")) {
    $(this).addClass("clicked");
    $(this).jqmEx();
    //   $(this).trigger("click");
  }
});
