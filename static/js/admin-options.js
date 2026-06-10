angular
  .module("optionApp", [])
  .controller("optionCtrl", function ($scope, $timeout) {
    /* init parameters */
    $scope.showPreview = false;
    $scope.previewWide = false;
    $scope.jsonFields = "";
    $scope.formula = {
      active: false,
      price: "",
      brIndex: null,
      fieldIndex: null,
      opIndex: null,
      saIndex: null,
      currentLinkField: "0",
    };
    /* end init parameters */
    /* quantity */
    $scope.excludeField = function (actual, expected) {
      var _field = null;
      angular.forEach($scope.options.fields, function (field) {
        if (field.id == actual) _field = field;
      });
      if (_field.general.enabled.value == "n") return false;
      return actual != expected;
    };
    $scope.includeField = function (actual, expected) {
      return actual == expected;
    };
    /* end. quantity */
    $scope.add_field = function (type, ftype) {
      var field = {};
      angular.copy(storelly_option_variable.STORELLY_OPTION_FIELD, field);
      var d = new Date();
      field["id"] = "f" + d.getTime();
      field.isExpand = true;
      if (angular.isDefined(type)) {
        field.general.title.value =
          storelly_options.storelly_options_lang[type];
        field.nbd_template = "nbd." + type;
        if (angular.isUndefined(ftype)) {
          if (
            angular.isDefined($scope.storelly_options[type]) &&
            type != "builder" &&
            $scope.storelly_options[type] == 1
          ) {
          } else {
            $scope.storelly_options[type] = 1;
          }
          field.nbd_type = type;
          angular.forEach(field.general.attributes, function (attr, a_key) {
            attr.enable_subattr = 0;
          });
        } else {
          field.nbpb_type = type;
          if (angular.isUndefined($scope.options.views))
            $scope.options.views = [
              {
                name: storelly_options.storelly_options_lang.view_name,
                base: 0,
              },
            ];
          switch (type) {
            case "nbpb_com":
              field.general.data_type.value = "m";
              field.general.data_type.hidden = true;
              field.general.component_icon = 0;
              break;
            case "nbpb_text":
              field.general.data_type.value = "i";
              field.general.input_type.value = "t";
              field.general.data_type.hidden = true;
              field.general.input_type.hidden = true;
              field.general.nbpb_text_configs = angular.isDefined(
                field.general.nbpb_text_configs
              )
                ? field.general.nbpb_text_configs
                : {
                    default_text: "",
                    allow_all_font: "y",
                    custom_fonts: [],
                    google_fonts: [],
                    allow_all_color: "y",
                    colors: [],
                    allow_change_color: "y",
                    allow_font_family: "y",
                    views: [],
                  };
              break;
            case "nbpb_image":
              field.general.data_type.value = "i";
              field.general.input_type.value = "u";
              field.general.data_type.hidden = true;
              field.general.input_type.hidden = true;
              field.general.nbpb_image_configs = angular.isDefined(
                field.general.nbpb_image_configs
              )
                ? field.general.nbpb_image_configs
                : {
                    views: [],
                  };
              break;
          }
        }
      }
      $scope.options.fields.push(field);
      $timeout(function () {
        jQuery("html,body").animate(
          {
            scrollTop: jQuery("#" + field["id"]).offset().top,
          },
          "slow"
        );
      });
      $scope.initfieldValue();
    };
    /**
     * v2 builder palette — add a field and pre-fill data_type/input_type
     * for the chosen preset. Does not touch the data model; only
     * mutates the same `field.general` keys the legacy editor uses.
     *
     * preset: 'm' (multi-choice), 'n' (number), 't' (text),
     *         'a' (textarea), 'u' (file upload)
     */
    $scope.add_field_preset = function (preset) {
      $scope.add_field();
      var f = $scope.options.fields[$scope.options.fields.length - 1];
      if (!f || !f.general) return;
      switch (preset) {
        case "m":
          f.general.data_type.value = "m";
          break;
        case "n":
          f.general.data_type.value = "i";
          f.general.input_type.value = "n";
          break;
        case "t":
          f.general.data_type.value = "i";
          f.general.input_type.value = "t";
          break;
        case "a":
          f.general.data_type.value = "i";
          f.general.input_type.value = "a";
          break;
        case "u":
          f.general.data_type.value = "i";
          f.general.input_type.value = "u";
          break;
      }
    };
    /**
     * Quantity-break helpers (option-level — not tied to a field).
     * The legacy save handler already serializes `options.quantity_*` from
     * $_POST['options'], so binding ng-model to these scope paths is enough.
     */
    /**
     * v2 preview state — non-persistent, lives only in the controller.
     * Tracks which attribute is "selected" per multi-choice field so the
     * preview pane reflects a real customer interaction (click swatch,
     * change price summary). Keyed by field.id to survive re-orders.
     */
    $scope.preview = {
      base_price: 25,           // mocked base price for the calculator
      selected: {},             // map: fieldId -> attrIndex
      qty_index: 0,             // selected quantity tier index
      qty_value: 1              // free-input quantity (stepper mode)
    };

    /**
     * Select an attribute on a multi-choice field inside the preview pane.
     */
    $scope.preview_select_attr = function (fieldId, attrIndex) {
      $scope.preview.selected[fieldId] = attrIndex;
    };

    /**
     * True if (fieldId, attrIndex) is the currently-selected option.
     * Defaults to attrIndex 0 when nothing has been picked yet, so the
     * preview always shows a sensible "chosen" state on first paint.
     */
    $scope.preview_is_selected = function (fieldId, attrIndex) {
      var sel = $scope.preview.selected[fieldId];
      if (typeof sel === 'undefined') return attrIndex === 0;
      return sel === attrIndex;
    };

    /**
     * Return the currently-selected attribute object for a field, or
     * the first attribute if none is selected yet. Used for the
     * "chosen" label inside the section header.
     */
    $scope.preview_selected_attr = function (field) {
      if (!field || !field.general || !field.general.attributes) return null;
      var attrs = field.general.attributes.options || field.general.attributes;
      if (!angular.isArray(attrs) || !attrs.length) return null;
      var idx = $scope.preview.selected[field.id];
      if (typeof idx === 'undefined') idx = 0;
      return attrs[idx] || attrs[0];
    };

    /**
     * Numeric coercion helper — turns "+$8.00" / "8" / 8 into 8.
     */
    function _toNum(v) {
      if (typeof v === 'number') return v;
      if (typeof v !== 'string') return 0;
      var n = parseFloat(v.replace(/[^\d.\-]/g, ''));
      return isNaN(n) ? 0 : n;
    }

    /**
     * Live price calculation for the preview pane.
     * Returns { base, options, qty_discount, total }.
     */
    $scope.preview_total = function () {
      var base = _toNum($scope.preview.base_price);
      var optTotal = 0;
      angular.forEach($scope.options && $scope.options.fields, function (field) {
        if (!field || !field.general) return;
        if (field.general.enabled && field.general.enabled.value === 'n') return;
        var attrs = field.general.attributes &&
            (field.general.attributes.options || field.general.attributes);
        if (!angular.isArray(attrs) || !attrs.length) return;
        var idx = $scope.preview.selected[field.id];
        if (typeof idx === 'undefined') idx = 0;
        var attr = attrs[idx];
        if (!attr) return;
        var p = 0;
        if (angular.isArray(attr.price)) {
          p = _toNum(attr.price[0]);
        } else if (attr.price) {
          p = _toNum(attr.price);
        }
        optTotal += p;
      });

      // Apply quantity-break discount if enabled.
      var qtyDiscount = 0;
      var subTotal = base + optTotal;
      if ($scope.options && $scope.options.quantity_enable === 'y' &&
          angular.isArray($scope.options.quantity_breaks) &&
          $scope.options.quantity_breaks.length) {
        var brk = $scope.options.quantity_breaks[$scope.preview.qty_index] ||
                  $scope.options.quantity_breaks[0];
        var dis = _toNum(brk.dis);
        if ($scope.options.quantity_discount_type === 'p') {
          qtyDiscount = (subTotal * dis) / 100;
        } else {
          qtyDiscount = dis;
        }
      }

      var total = Math.max(0, subTotal - qtyDiscount);
      return {
        base: base.toFixed(2),
        options: optTotal.toFixed(2),
        qty_discount: qtyDiscount.toFixed(2),
        has_discount: qtyDiscount > 0.001,
        total: total.toFixed(2)
      };
    };

    /**
     * Format an attribute's price for display in the preview swatch label.
     */
    $scope.preview_attr_price_label = function (attr) {
      if (!attr) return '';
      var p = angular.isArray(attr.price) ? attr.price[0] : attr.price;
      var n = _toNum(p);
      if (!n) return ''; // "Free"
      var sign = n > 0 ? '+' : '';
      return sign + '$' + n.toFixed(2);
    };

    /* ──────────────── Storefront-fidelity preview ────────────────
     * The quick sketch above is a fast approximation that re-implements a
     * subset of the pricing/layout. This opens the EXACT storefront render:
     * we serialize the live (unsaved) option draft and POST it into an
     * <iframe> handled by SPBWC_Template_Preview_Render, which renders it
     * through the same option-builder template + storefront-options.css +
     * option-builder.js a real product page uses. Single source of truth —
     * display mode, conditional logic and pricing are all 1:1 with the
     * frontend, so there's nothing to drift. */
    function _sfPreviewCfg() {
      return (
        (typeof storelly_option_variable !== "undefined" &&
          storelly_option_variable.preview_iframe) ||
        null
      );
    }
    $scope.currency = (function () {
      var c = _sfPreviewCfg();
      return (c && c.currency) || "$";
    })();
    $scope.storefront_preview_available = function () {
      var c = _sfPreviewCfg();
      return !!(c && c.url);
    };
    // Push the live model into the iframe. isInitial=true shows the full
    // skeleton (first open); a refresh keeps the prior render visible and just
    // dims it (mirrors the Template Library "Updating…" pattern) so following
    // edits never flash an empty frame. angular.toJson drops $$hashKey noise;
    // the descriptor-shaped model is exactly what build_runtime_options()
    // flattens on the server.
    function _sfReload(isInitial) {
      var cfg = _sfPreviewCfg();
      if (!cfg || !cfg.url) return;
      var modal = document.getElementById("spbwc-sf-preview");
      var form = document.getElementById("spbwc-sf-preview-form");
      if (!modal || !form) return;
      form.action = cfg.url;
      form.elements.draft.value = angular.toJson($scope.options);
      form.elements.base.value = _toNum($scope.preview.base_price);
      modal.classList.remove("is-error");
      modal.classList.add(isInitial ? "is-loading" : "is-updating");
      form.submit();
      // Fallback veil-clear in case the bridge never posts a height (e.g. an
      // error document rendered inside the iframe).
      if ($scope._sfVeilFallback) $timeout.cancel($scope._sfVeilFallback);
      $scope._sfVeilFallback = $timeout(function () {
        modal.classList.remove("is-loading", "is-updating");
      }, 3000);
    }
    $scope.open_storefront_preview = function () {
      var cfg = _sfPreviewCfg();
      if (!cfg || !cfg.url) return;
      var modal = document.getElementById("spbwc-sf-preview");
      if (!modal) return;
      modal.classList.add("is-open");
      modal.setAttribute("aria-hidden", "false");
      document.body.classList.add("spbwc-sf-preview-lock");
      _sfReload(true);
      // Live-follow edits while the modal stays open: re-render (debounced) on
      // any change to the option model — not just the base price — so the
      // storefront preview tracks what the merchant is building in real time.
      if (!$scope._sfWatch) {
        $scope._sfWatch = $scope.$watch(
          function () {
            return angular.toJson($scope.options);
          },
          function (nv, ov) {
            if (nv === ov) return; // skip the initial fire
            $scope.storefront_preview_refresh();
          }
        );
      }
    };
    $scope.storefront_preview_refresh = function () {
      var modal = document.getElementById("spbwc-sf-preview");
      if (!modal || !modal.classList.contains("is-open")) return;
      if ($scope._sfRefreshDebounce) $timeout.cancel($scope._sfRefreshDebounce);
      $scope._sfRefreshDebounce = $timeout(function () {
        _sfReload(false);
      }, 600);
    };
    // The base-price field rides the same debounced refresh.
    $scope.storefront_preview_base_changed = $scope.storefront_preview_refresh;
    $scope.close_storefront_preview = function () {
      var modal = document.getElementById("spbwc-sf-preview");
      if (modal) {
        modal.classList.remove("is-open", "is-loading", "is-updating", "is-error");
        modal.setAttribute("aria-hidden", "true");
      }
      document.body.classList.remove("spbwc-sf-preview-lock");
      // Stop following edits + cancel any pending refresh until reopened.
      if ($scope._sfWatch) {
        $scope._sfWatch();
        $scope._sfWatch = null;
      }
      if ($scope._sfRefreshDebounce) $timeout.cancel($scope._sfRefreshDebounce);
    };
    // One-time global listeners: the iframe (template-preview-bridge.js) posts
    // body height + running total; trust only our own origin. Escape closes.
    if (!window.__spbwcSfPreviewBound) {
      window.__spbwcSfPreviewBound = true;
      window.addEventListener("message", function (ev) {
        var cfg = _sfPreviewCfg();
        if (!cfg || !cfg.origin || ev.origin !== cfg.origin) return;
        var d = ev.data;
        if (!d || d.source !== "spbwc-tpl-preview") return;
        var modal = document.getElementById("spbwc-sf-preview");
        if (!modal) return;
        if (d.type === "height") {
          var fr = document.getElementById("spbwc-sf-preview-iframe");
          if (fr && d.value) {
            var cap = Math.round(window.innerHeight * 0.74);
            fr.style.height = Math.min(parseInt(d.value, 10) || 0, cap) + "px";
          }
          modal.classList.remove("is-loading", "is-updating");
        } else if (d.type === "total") {
          var sub = modal.querySelector("[data-sf-total]");
          if (sub) sub.textContent = d.value ? "— " + d.value : "";
        }
      });
      window.addEventListener("keydown", function (ev) {
        if (ev.key !== "Escape" && ev.keyCode !== 27) return;
        var modal = document.getElementById("spbwc-sf-preview");
        if (modal && modal.classList.contains("is-open")) {
          modal.classList.remove("is-open", "is-loading", "is-updating", "is-error");
          modal.setAttribute("aria-hidden", "true");
          document.body.classList.remove("spbwc-sf-preview-lock");
        }
      });
    }

    $scope.add_quantity_break = function () {
      if (!angular.isArray($scope.options.quantity_breaks)) {
        $scope.options.quantity_breaks = [];
      }
      var last = $scope.options.quantity_breaks[$scope.options.quantity_breaks.length - 1];
      var nextVal = last && parseInt(last.val, 10) ? parseInt(last.val, 10) * 2 : 100;
      $scope.options.quantity_breaks.push({
        val: nextVal,
        dis: '',
        default: ''
      });
    };
    $scope.remove_quantity_break = function (index) {
      if (!angular.isArray($scope.options.quantity_breaks)) return;
      $scope.options.quantity_breaks.splice(index, 1);
    };
    $scope.set_default_quantity_break = function (index) {
      if (!angular.isArray($scope.options.quantity_breaks)) return;
      angular.forEach($scope.options.quantity_breaks, function (b, i) {
        b.default = (i === index) ? 'on' : '';
      });
    };

    $scope.addView = function () {
      // In the Visual Builder empty state an option can have no nbpb field yet,
      // so options.views is still undefined (it is only seeded when a designer
      // component is added). Guard so "Add first view" works from scratch.
      if (!angular.isArray($scope.options.views)) {
        $scope.options.views = [];
      }
      $scope.options.views.push({
        name: storelly_options.storelly_options_lang.view_name,
        base: 0,
      });
      $scope.initfieldValue();
    };
    $scope.removeView = function (vIndex) {
      if ($scope.options.views.length == 1) {
        return;
      }
      $scope.options.views.splice(vIndex, 1);
      $scope.initfieldValue();
    };
    // Current product id for scoping the media picker to one product's images.
    $scope.spbwcMediaPid = function () {
      return ($scope.options && $scope.options.product_ids && parseInt($scope.options.product_ids[0], 10)) || 0;
    };
    // Media frame title: when scoped to a product, show "Images for: <product>" so
    // the merchant knows they are browsing that one product's images.
    $scope.spbwcFrameTitle = function () {
      var lang = (typeof storelly_options !== "undefined" && storelly_options.storelly_options_lang) || {};
      var pid = $scope.spbwcMediaPid();
      var labels = $scope.options && $scope.options.spbwc_product_labels;
      var name = (pid > 0 && labels && labels[pid]) ? labels[pid] : "";
      if (name && lang.media_scope_title) {
        return lang.media_scope_title.replace("%s", name);
      }
      return lang.choose_image || "";
    };
    // When an option already references an image, open the media frame with that
    // image pre-selected (highlighted + scrolled into view) so "edit image" lands
    // on the right one instead of a blank search.
    $scope.spbwcPreselect = function (frame, currentId) {
      currentId = parseInt(currentId, 10);
      if (!currentId || currentId <= 0) {
        return;
      }
      frame.on("open", function () {
        var selection = frame.state().get("selection");
        var attachment = wp.media.attachment(currentId);
        attachment.fetch();
        selection.add(attachment ? [attachment] : []);
      });
    };
    $scope.set_view_base = function (vIndex) {
      var file_frame;
      if (file_frame) {
        file_frame.open();
        return;
      }
      var spbwcPid = $scope.spbwcMediaPid();
      var lib = {
        type: ["image"],
      };
      if (spbwcPid > 0) lib.uploadedTo = spbwcPid;
      file_frame = wp.media.frames.file_frame = wp.media({
        title: $scope.spbwcFrameTitle(),
        button: {
          text: storelly_options.storelly_options_lang.choose_image,
        },
        library: lib,
        multiple: false,
      });
      $scope.spbwcPreselect(file_frame, $scope.options.views[vIndex].base);
      file_frame.on("select", function () {
        var attachment = file_frame.state().get("selection").first().toJSON();
        $scope.options.views[vIndex].base = attachment.id;
        $scope.options.views[vIndex].base_width = attachment.width;
        $scope.options.views[vIndex].base_height = attachment.height;
        var url = attachment.url;
        if (
          angular.isDefined(attachment.sizes) &&
          angular.isDefined(attachment.sizes.thumbnail)
        ) {
          url = attachment.sizes.thumbnail.url;
        }
        $scope.options.views[vIndex].base_url = url;
        if (
          $scope.$root.$$phase !== "$apply" &&
          $scope.$root.$$phase !== "$digest"
        )
          $scope.$apply();
      });
      file_frame.open();
    };
    $scope.remove_view_base = function (vIndex) {
      $scope.options.views[vIndex].base = 0;
      $scope.options.views[vIndex].base_url = "";
    };
    $scope.set_view_config_image = function (
      fieldIndex,
      attr_index,
      sattr_index,
      $index
    ) {
      var file_frame;
      if (file_frame) {
        file_frame.open();
        return;
      }
      var spbwcPid = ($scope.options && $scope.options.product_ids && parseInt($scope.options.product_ids[0], 10)) || 0;
      var lib = {
        type: ["image"],
      };
      if (spbwcPid > 0) lib.uploadedTo = spbwcPid;
      file_frame = wp.media.frames.file_frame = wp.media({
        title: $scope.spbwcFrameTitle(),
        button: {
          text: storelly_options.storelly_options_lang.choose_image,
        },
        library: lib,
        multiple: false,
      });
      $scope.spbwcPreselect(
        file_frame,
        $scope.options["fields"][fieldIndex]["general"]["pb_config"][attr_index][sattr_index].views[$index].image
      );
      file_frame.on("select", function () {
        var attachment = file_frame.state().get("selection").first().toJSON();
        $scope.options["fields"][fieldIndex]["general"]["pb_config"][
          attr_index
        ][sattr_index].views[$index].image = attachment.id;
        var url = attachment.url;
        if (
          angular.isDefined(attachment.sizes) &&
          angular.isDefined(attachment.sizes.thumbnail)
        ) {
          url = attachment.sizes.thumbnail.url;
        }
        $scope.options["fields"][fieldIndex]["general"]["pb_config"][
          attr_index
        ][sattr_index].views[$index].image_url = url;
        if (
          $scope.$root.$$phase !== "$apply" &&
          $scope.$root.$$phase !== "$digest"
        )
          $scope.$apply();
      });
      file_frame.open();
    };
    $scope.remove_view_config_image = function (
      fieldIndex,
      attr_index,
      sattr_index,
      $index
    ) {
      $scope.options["fields"][fieldIndex]["general"]["pb_config"][attr_index][
        sattr_index
      ].views[$index].image = 0;
      $scope.options["fields"][fieldIndex]["general"]["pb_config"][attr_index][
        sattr_index
      ].views[$index].image_url = "";
    };
    $scope.get_field_class = function (type) {
      var klass = "default";
      switch (type) {
        case "nbpb_com":
        case "nbpb_text":
        case "nbpb_image":
          klass = "wpo";
          break;
      }
      return klass;
    };
    $scope.get_field_type = function (type) {
      type = angular.isDefined(type) ? type : "";
      var type_number;
      switch (type) {
        case "nbpb_com":
          type_number = 2;
          break;
        case "nbpb_text":
          type_number = 3;
          break;
        case "nbpb_image":
          type_number = 4;
          break;
        default:
          type_number = 1;
          break;
      }
      return type_number;
    };

    /**
     * Human-readable label for a field's type, derived from its
     * data_type / input_type / nbpb_type combo. Used in the collapsed
     * field card meta chip (e.g. "Multi-choice", "Text input", "Upload").
     */
    $scope.get_field_label = function (field) {
      if (!field || !field.general) return "";
      if (field.nbpb_type) {
        switch (field.nbpb_type) {
          case "nbpb_com":   return "Designer Component";
          case "nbpb_text":  return "Designer Text";
          case "nbpb_image": return "Designer Image";
        }
      }
      var dt = field.general.data_type && field.general.data_type.value;
      var it = field.general.input_type && field.general.input_type.value;
      if (dt === "m") return "Multi-choice";
      if (dt === "i") {
        switch (it) {
          case "t": return "Text input";
          case "a": return "Textarea";
          case "u": return "File upload";
        }
        return "Input";
      }
      return "Field";
    };

    /**
     * Number of attribute options on a multi-choice field.
     */
    $scope.get_field_attr_count = function (field) {
      if (!field || !field.general || !field.general.attributes) return 0;
      var opts = field.general.attributes.options || field.general.attributes;
      return angular.isArray(opts) ? opts.length : 0;
    };

    /**
     * Format an attribute's first price tier into a short label
     * (e.g. "+$8.00", "Free", "−$2.00"). Returns null for the empty case.
     */
    $scope.attr_price_label = function (attr) {
      if (!attr) return null;
      var p = angular.isArray(attr.price) ? attr.price[0] : attr.price;
      if (p === null || p === undefined || p === "" || p === "0" || p === 0) {
        return null;
      }
      var n = parseFloat(("" + p).replace(/[^\d.\-]/g, ""));
      if (isNaN(n) || n === 0) return null;
      var sign = n > 0 ? "+" : "−";
      return sign + "$" + Math.abs(n).toFixed(2);
    };
    $scope.copy_field = function (index) {
      var field = {};
      angular.copy($scope.options.fields[index], field);
      var d = new Date();
      field["id"] = "f" + d.getTime();
      field["general"]["title"]["value"] =
        field["general"]["title"]["value"] + " - Copy";
      $scope.options.fields.push(field);
      $scope.initfieldValue();
    };
    $scope.delete_field = function (index) {
      var ask = window.spbwcDialog
        ? window.spbwcDialog.confirm({ message: storelly_options.storelly_options_lang.want_to_delete, tone: "danger" })
        : Promise.resolve(window.confirm(storelly_options.storelly_options_lang.want_to_delete));
      ask.then(function (ok) {
        if (!ok) { return; }
        var field = $scope.options.fields[index];
        $scope.options.fields.splice(index, 1);
        $scope.initfieldValue();
        // The confirm resolves outside Angular's digest — schedule one so
        // the field-list removal re-renders.
        $scope.$applyAsync();
      });
    };
    $scope.clear_all_fields = function (index) {
      var ask = window.spbwcDialog
        ? window.spbwcDialog.confirm({ message: storelly_options.storelly_options_lang.want_to_delete_all, tone: "danger" })
        : Promise.resolve(window.confirm(storelly_options.storelly_options_lang.want_to_delete_all));
      ask.then(function (ok) {
        if (!ok) { return; }
        $scope.options.fields = [];
        angular.forEach($scope.storelly_options, function (option, key) {
          option = 0;
        });
        $scope.initfieldValue();
        // Confirm resolves outside Angular's digest — schedule one.
        $scope.$applyAsync();
      });
    };
    $scope.sort_field = function (field_index, direction) {
      var dest_index = field_index - 1;
      if (direction == "up") {
        if (field_index == 0) return;
      } else {
        if (field_index == $scope.options.fields.length - 1) return;
        dest_index = field_index + 1;
      }
      jQuery(".nbp-loading-wrap").addClass("nbp-show");
      $timeout(function () {
        var temp_field = {};
        angular.copy($scope.options.fields[field_index], temp_field);
        angular.copy(
          $scope.options.fields[dest_index],
          $scope.options.fields[field_index]
        );
        angular.copy(temp_field, $scope.options.fields[dest_index]);
        $scope.initfieldValue();
        $timeout(function () {
          jQuery(".nbp-loading-wrap").removeClass("nbp-show");
          jQuery.each(jQuery("[nbd-tab]").find(".pcpb-field-tab"), function () {
            jQuery(this).on("click", function () {
              var target = jQuery(this).data("target");
              jQuery(this)
                .parents(".pcpb-field-wrap")
                .find(".pcpb-field-content")
                .removeClass("active");
              jQuery(this).parent("ul").find("li").removeClass("active");
              jQuery(this)
                .parents(".pcpb-field-wrap")
                .find("." + target)
                .addClass("active");
              jQuery(this).addClass("active");
            });
          });
        }, 400);
      }, 100);
    };
    $scope.toggleExpandField = function (index, $event) {
      var parent = jQuery($event.target).parents(".pcpb-field-wrap");
      function _toggleExpandField() {
        $scope.options.fields[index].isExpand =
          !$scope.options.fields[index].isExpand;
        $timeout(function () {
          jQuery("html,body").animate(
            { scrollTop: parent.offset().top - 50 },
            200
          );
        }, 0);
      }
      _toggleExpandField();
    };
    $scope.initfieldValue = function () {
      // After every collection mutation, re-arm the sortable so newly added
      // cards become draggable too. Debounced to avoid stacking digests.
      if (typeof $scope.initSortableFields === "function") {
        clearTimeout($scope._sortableT);
        $scope._sortableT = setTimeout(function () {
          $scope.initSortableFields();
        }, 120);
      }
      angular.forEach($scope.options.fields, function (field, key) {
        $scope.option_values[key] = angular.isDefined($scope.option_values[key])
          ? $scope.option_values[key]
          : "";
        if (field.general.data_type.value == "i") {
          $scope.option_values[key] = "";
        } else {
          if (field.general.attributes.options.length == 0) {
            $scope.option_values[key] = "";
          } else {
            $scope.option_values[key] = 0;
            angular.forEach(field.general.attributes.options, function (op, k) {
              if (op.selected) $scope.option_values[key] = k;
            });
          }
        }
        if (angular.isDefined(field.nbpb_type)) {
          if (angular.isUndefined($scope.options.views)) {
            $scope.options.views = [
              {
                name: storelly_options.storelly_options_lang.view_name,
                base: 0,
              },
            ];
          }

          $scope.has_product_builder_field = true;
          switch (field.nbpb_type) {
            case "nbpb_com":
              field.general.data_type.value = "m";
              field.general.data_type.hidden = true;
              field.general.data_type.hidden = true;
              field.general.component_icon = angular.isDefined(
                field.general.component_icon
              )
                ? field.general.component_icon
                : 0;
              $scope.buildPbConfigFlat(field);
              break;
            case "nbpb_text":
              field.general.data_type.value = "i";
              field.general.input_type.value = "t";
              field.general.data_type.hidden = true;
              field.general.input_type.hidden = true;
              field.general.nbpb_text_configs = angular.isDefined(
                field.general.nbpb_text_configs
              )
                ? field.general.nbpb_text_configs
                : {
                    default_text: "",
                    allow_all_font: "y",
                    custom_fonts: [],
                    google_fonts: [],
                    allow_all_color: "y",
                    colors: [],
                    allow_change_color: "y",
                    allow_font_family: "y",
                    views: [],
                  };
              field.general.nbpb_text_configs.colors = angular.isDefined(
                field.general.nbpb_text_configs.colors
              )
                ? field.general.nbpb_text_configs.colors
                : [];
              field.general.nbpb_text_configs.custom_fonts = angular.isDefined(
                field.general.nbpb_text_configs.custom_fonts
              )
                ? field.general.nbpb_text_configs.custom_fonts
                : [];
              field.general.nbpb_text_configs.google_fonts = angular.isDefined(
                field.general.nbpb_text_configs.google_fonts
              )
                ? field.general.nbpb_text_configs.google_fonts
                : [];
              field.general.nbpb_text_configs.views = angular.isDefined(
                field.general.nbpb_text_configs.views
              )
                ? field.general.nbpb_text_configs.views
                : [];
              break;
            case "nbpb_image":
              field.general.data_type.value = "i";
              field.general.input_type.value = "u";
              field.general.required.value = "n";
              field.general.data_type.hidden = true;
              field.general.input_type.hidden = true;
              field.general.required.hidden = true;
              field.general.nbpb_image_configs = angular.isDefined(
                field.general.nbpb_image_configs
              )
                ? field.general.nbpb_image_configs
                : {
                    views: [],
                  };
              field.general.nbpb_image_configs.views = angular.isDefined(
                field.general.nbpb_image_configs.views
              )
                ? field.general.nbpb_image_configs.views
                : [];
              break;
          }
        }
      });
      $timeout(function () {
        $scope.current_input_vars = jQuery("[name]").length;
        if ($scope.current_input_vars > $scope.max_input_vars) {
          jQuery("html,body").animate(
            {
              scrollTop: jQuery("#notice-max-input-vars").offset().top - 100,
            },
            "slow"
          );
          var maxInputMsg =
            storelly_options.storelly_options_lang.max_input_var +
            " " +
            $scope.max_input_vars +
            ". " +
            storelly_options.storelly_options_lang.max_input_notice;
          window.spbwcDialog
            ? window.spbwcDialog.toast({ message: maxInputMsg, tone: "warning" })
            : window.alert(maxInputMsg);
        }
      }, 2000);

      // $scope.maybeUpdateManualPm();
    };
    $scope.buildPbConfigFlat = function (field) {
      var options = field.general.attributes.options;
      field.general.pb_config_flat = [];
      if (angular.isUndefined(field.general.pb_config))
        field.general.pb_config = [];
      function build_config(attr_index, attr_rowspan, sattr_index, has_sattr) {
        if (angular.isUndefined(field.general.pb_config[attr_index]))
          field.general.pb_config[attr_index] = [];
        if (
          angular.isUndefined(field.general.pb_config[attr_index][sattr_index])
        )
          field.general.pb_config[attr_index][sattr_index] = {};
        field.general.pb_config[attr_index][sattr_index].attr_rowspan =
          attr_rowspan;
        field.general.pb_config[attr_index][sattr_index].attr_index =
          attr_index;
        field.general.pb_config[attr_index][sattr_index].sattr_index =
          sattr_index;
        field.general.pb_config[attr_index][sattr_index].has_sattr = has_sattr;
        if (
          angular.isUndefined(
            field.general.pb_config[attr_index][sattr_index].views
          )
        )
          field.general.pb_config[attr_index][sattr_index].views = [];
        angular.forEach($scope.options.views, function (view, vkey) {
          if (
            angular.isUndefined(
              field.general.pb_config[attr_index][sattr_index].views[vkey]
            )
          )
            field.general.pb_config[attr_index][sattr_index].views[vkey] = {
              image: 0,
              image_url: "",
              display: true,
            };
        });
      }
      var configIndex = 0;
      angular.forEach(options, function (op, key) {
        if (
          angular.isDefined(op.enable_subattr) &&
          (op.enable_subattr === true ||
            op.enable_subattr === "on" ||
            op.enable_subattr === 1)
        ) {
          if (
            angular.isDefined(op.sub_attributes) &&
            op.sub_attributes.length > 0
          ) {
            angular.forEach(op.sub_attributes, function (sop, skey) {
              var attr_rowspan = skey == 0 ? op.sub_attributes.length : 0;
              build_config(key, attr_rowspan, skey, true);
              configIndex++;
            });
          } else {
            build_config(key, 1, 0, false);
            configIndex++;
          }
        } else {
          build_config(key, 1, 0, false);
          configIndex++;
        }
      });
      var flatConfigIndex = 0;
      angular.forEach(field.general.pb_config, function (op_config, key) {
        var op = options[key];
        if (op) {
          if (
            angular.isDefined(op.enable_subattr) &&
            (op.enable_subattr === true ||
              op.enable_subattr === "on" ||
              op.enable_subattr === 1)
          ) {
            angular.forEach(op_config, function (sop_config, skey) {
              if (
                angular.isDefined(op.sub_attributes) &&
                angular.isDefined(op.sub_attributes[skey])
              ) {
                field.general.pb_config_flat[flatConfigIndex] = {};
                angular.copy(
                  sop_config,
                  field.general.pb_config_flat[flatConfigIndex]
                );
                flatConfigIndex++;
              }
            });
          } else {
            field.general.pb_config_flat[flatConfigIndex] = {};
            angular.copy(
              op_config[0],
              field.general.pb_config_flat[flatConfigIndex]
            );
            flatConfigIndex++;
          }
        } else {
          field.general.pb_config.splice(key, 1);
        }
      });
    };
    $scope.init = function (options) {
      $scope.storelly_options = {};
      $scope.options = storelly_option_variable.STORELLY_OPTIONS;
      $scope.current_input_vars = 1;
      $scope.max_input_vars = storelly_option_variable.max_input_vars;
      if (angular.isDefined(options)) {
        $scope.options = options;
        if (
          $scope.$root.$$phase !== "$apply" &&
          $scope.$root.$$phase !== "$digest"
        )
          $scope.$apply();
      }
      $scope.option_values = [];
      angular.forEach($scope.options.fields, function (field, key) {
        field.isExpand = false;
        if (field.general.attributes.options.length) {
          angular.forEach(
            field.general.attributes.options,
            function (attr, a_key) {
              attr.isExpand = false;
              if (attr.enable_subattr) {
                if (attr.sub_attributes.length) {
                  angular.forEach(attr.sub_attributes, function (sattr, s_key) {
                    sattr.isExpand = false;
                  });
                }
              }
            }
          );
        }
      });
      $scope.has_product_builder_field = false;
      $scope.initfieldValue();
      // Wire the sortable drag handle once Angular has painted the cards.
      $timeout(function () { $scope.initSortableFields(); }, 200);
    };
    $scope.export = function () {
      jQuery(".nbp-loading-wrap").addClass("nbp-show");
      $scope.get_media_full_size_url(function (images) {
        var new_options = $scope.merge_new_media($scope.options, images);
        var filename = "options.json",
          options = JSON.stringify(new_options, function (name, val) {
            if (name == "$$hashKey") {
              return undefined;
            } else {
              return val;
            }
          });
        jQuery(".nbp-loading-wrap").removeClass("nbp-show");
        var a = document.createElement("a");
        a.setAttribute(
          "href",
          "data:application/json;charset=utf-8," + encodeURIComponent(options)
        );
        a.setAttribute("download", filename);
        a.style.display = "none";
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
      });
    };
    $scope.get_media_full_size_url = function (callback) {
      var mediaObject = $scope.get_media_from_options($scope.options, "id");
      jQuery
        .ajax({
          url: storelly_option_variable.ajax_url,
          method: "POST",
          data: {
            action: "spbwc_get_media_full_size_url",
            nonce: storelly_option_variable.nbnonce,
            images: mediaObject,
          },
        })
        .done(function (data) {
          var res = JSON.parse(data);
          if (res.flag == 1) {
            callback(res.images);
          } else {
            jQuery(".nbp-loading-wrap").removeClass("nbp-show");
            var errTryAgainMsg = (typeof storelly_options !== "undefined" && storelly_options.storelly_options_lang && storelly_options.storelly_options_lang.error_try_again) || "Error. Please try again later.";
            window.spbwcDialog ? window.spbwcDialog.toast({ message: errTryAgainMsg, tone: "error" }) : window.alert(errTryAgainMsg);
          }
        });
    };
    $scope.merge_new_media = function (options, medias, type) {
      new_options = {};
      var new_options = angular.copy(options, new_options);
      var postfix = angular.isUndefined(type) ? "_url" : "";
      angular.forEach(medias, function (media, key) {
        var key_arr = key.split("-");
        var media_key = key_arr[key_arr.length - 1] + postfix;
        if (!/[image|icon|base]$/.test(key)) {
          media_key = key_arr[key_arr.length - 1];
          //key_arr[key_arr.length - 2] = 'bg_image' + postfix;
          key_arr[key_arr.length - 2] = key_arr[key_arr.length - 2] + postfix;
        }
        key_arr.splice(key_arr.length - 1, 1);
        function getTargetMedia(new_options, key_arr) {
          return key_arr.reduce(function (obj, __key) {
            return obj && obj[__key] !== "undefined" ? obj[__key] : undefined;
          }, new_options);
        }
        var targetMedia = getTargetMedia(new_options, key_arr);
        if (false != media) targetMedia[media_key] = media;
      });
      return new_options;
    };
    $scope.import = function () {
      var input = document.createElement("input");
      input.type = "file";
      input.accept = "text/json|application/json";
      input.style.display = "none";
      input.addEventListener("change", onChange.bind(input), false);
      document.body.appendChild(input);
      input.click();
      function onChange() {
        if (this.files.length > 0) {
          var file = this.files[0],
            reader = new FileReader();
          reader.onload = function (event) {
            if (event.target.readyState === 2) {
              jQuery(".nbp-loading-wrap").addClass("nbp-show");
              $timeout(function () {
                var result = JSON.parse(reader.result);
                $scope.update_options_media(result);
                destroy();
              }, 100);
            }
          };
          reader.readAsText(file);
        }
      }
      function destroy() {
        input.removeEventListener("change", onChange.bind(input), false);
        document.body.removeChild(input);
      }
    };
    $scope.get_media_from_options = function (options, type) {
      var mediaObject = {};
      var _key;
      if (angular.isDefined(options.views)) {
        angular.forEach(options.views, function (view, key) {
          if (view.base != "0") {
            _key = "views-" + key + "-base";
            mediaObject[_key] = type == "url" ? view.base_url : view.base;
          }
        });
      }
      angular.forEach(options.fields, function (field, fkey) {
        if (angular.isDefined(field.general.attributes.options)) {
          angular.forEach(
            field.general.attributes.options,
            function (option, okey) {
              if (option.image != "0") {
                _key =
                  "fields-" +
                  fkey +
                  "-general-attributes-options-" +
                  okey +
                  "-image";
                mediaObject[_key] =
                  type == "url" ? option.image_url : option.image;
              }
              if (
                angular.isDefined(option.bg_image) &&
                option.bg_image.length > 0
              ) {
                angular.forEach(option.bg_image, function (obi, obikey) {
                  if (obi != "0" && obi != null) {
                    _key =
                      "fields-" +
                      fkey +
                      "-general-attributes-options-" +
                      okey +
                      "-bg_image-" +
                      obikey;
                    mediaObject[_key] =
                      type == "url" ? option.bg_image_url[obikey] : obi;
                  }
                });
              }
              if (
                angular.isDefined(option.product_image) &&
                option.product_image != "0"
              ) {
                _key =
                  "fields-" +
                  fkey +
                  "-general-attributes-options-" +
                  okey +
                  "-product_image";
                mediaObject[_key] =
                  type == "url"
                    ? option.product_image_url
                    : option.product_image;
              }
              if (angular.isDefined(option.sub_attributes)) {
                angular.forEach(
                  option.sub_attributes,
                  function (sub_attr, skey) {
                    if (sub_attr.image != "0") {
                      _key =
                        "fields-" +
                        fkey +
                        "-general-attributes-options-" +
                        okey +
                        "-sub_attributes-" +
                        skey +
                        "-image";
                      mediaObject[_key] =
                        type == "url" ? sub_attr.image_url : sub_attr.image;
                    }
                  }
                );
              }
              if (
                angular.isDefined(option.overlay_image) &&
                option.overlay_image.length > 0
              ) {
                angular.forEach(option.overlay_image, function (obi, obikey) {
                  if (obi != "0" && obi != null) {
                    _key =
                      "fields-" +
                      fkey +
                      "-general-attributes-options-" +
                      okey +
                      "-overlay_image-" +
                      obikey;
                    mediaObject[_key] =
                      type == "url" ? option.overlay_image_url[obikey] : obi;
                  }
                });
              }
              if (
                angular.isDefined(option.frame_image) &&
                option.frame_image != "0"
              ) {
                _key =
                  "fields-" +
                  fkey +
                  "-general-attributes-options-" +
                  okey +
                  "-frame_image";
                mediaObject[_key] =
                  type == "url" ? option.frame_image_url : option.frame_image;
              }
            }
          );
        }
        if (angular.isDefined(field.general.component_icon)) {
          if (field.general.component_icon != "0") {
            _key = "fields-" + fkey + "-general-component_icon";
            mediaObject[_key] =
              type == "url"
                ? field.general.component_icon_url
                : field.general.component_icon;
          }
        }
        if (angular.isDefined(field.general.pb_config)) {
          angular.forEach(field.general.pb_config, function (attr, akey) {
            angular.forEach(attr, function (sattr, sakey) {
              angular.forEach(sattr.views, function (cview, vkey) {
                if (cview.image != "0") {
                  _key =
                    "fields-" +
                    fkey +
                    "-general-pb_config-" +
                    akey +
                    "-" +
                    sakey +
                    "-views-" +
                    vkey +
                    "-image";
                  mediaObject[_key] =
                    type == "url" ? cview.image_url : cview.image;
                }
              });
            });
          });
        }
      });
      return mediaObject;
    };
    $scope.update_options_media = function (options) {
      jQuery("#nbp-processing").show();
      var mediaObject = $scope.get_media_from_options(options, "url"),
        newMediaObject = {},
        keys = Object.keys(mediaObject),
        total = keys.length,
        index = 0;
      jQuery("#nbp-process-loaded").html(index);
      jQuery("#nbp-process-total").html(total);
      function update_media_false() {
        jQuery(".nbp-loading-wrap").removeClass("nbp-show");
        jQuery("#nbp-processing").hide();
        var errTryAgainMsg = (typeof storelly_options !== "undefined" && storelly_options.storelly_options_lang && storelly_options.storelly_options_lang.error_try_again) || "Error. Please try again later.";
        window.spbwcDialog ? window.spbwcDialog.toast({ message: errTryAgainMsg, tone: "error" }) : window.alert(errTryAgainMsg);
      }
      function merge_new_media() {
        var new_options = $scope.merge_new_media(options, newMediaObject, "id");
        $scope.init(new_options);
        jQuery(".nbp-loading-wrap").removeClass("nbp-show");
        jQuery("#nbp-processing").hide();
      }
      function update_remote_media(mediaObject, index) {
        if (index < total) {
          $scope.download_import_image(
            mediaObject[keys[index]],
            function (data) {
              var res = JSON.parse(data);
              if (angular.isDefined(res.flag) && res.flag == "1") {
                if (res.image.current_site == 0) {
                  newMediaObject[keys[index]] = res.image.id;
                }
                index++;
                jQuery("#nbp-process-loaded").html(index);
                update_remote_media(mediaObject, index);
              } else {
                update_media_false();
              }
            }
          );
        } else {
          merge_new_media();
        }
      }
      update_remote_media(mediaObject, index);
    };
    $scope.download_import_image = function (image, callack) {
      jQuery
        .ajax({
          url: storelly_option_variable.ajax_url,
          method: "POST",
          data: {
            action: "nbd_download_option_image",
            nonce: storelly_option_variable.nbnonce,
            image: image,
          },
        })
        .done(function (data) {
          callack(data);
        });
    };
    $scope.check_depend = function (fields, data, type) {
      if (angular.isDefined(data.hidden)) return false;
      if (angular.isUndefined(data.depend)) return true;
      var check = [],
        total_check = true;
      angular.forEach(data.depend, function (f, _key) {
        check[_key] = f.operator == "=" ? false : true;
        angular.forEach(fields, function (field, key) {
          var val_arr = f.value.split(",");
          if (val_arr.length > 1) {
            angular.forEach(val_arr, function (val, vkey) {
              if (key == f.field && field.value == val) {
                check[_key] = f.operator == "=" ? true : false;
              }
            });
          } else {
            if (key == f.field && field.value == f.value) {
              check[_key] = f.operator == "=" ? true : false;
            }
          }
        });
      });
      angular.forEach(check, function (c, k) {
        total_check = total_check && c;
      });
      return total_check;
    };
    $scope.check_option_depend = function (fieldIndex, depends) {
      if (angular.isUndefined(depends)) return true;
      var check = [],
        total_check = true;
      angular.forEach(depends, function (depend, _key) {
        check[_key] = false;
        if (depend.operator == "=") {
          if (
            $scope.options["fields"][fieldIndex]["general"][depend.field]
              .value == depend.value
          )
            check[_key] = true;
        } else {
          if (
            $scope.options["fields"][fieldIndex]["general"][depend.field]
              .value != depend.value
          )
            check[_key] = true;
        }
      });
      angular.forEach(check, function (c, k) {
        total_check = total_check && c;
      });
      return total_check;
    };
    $scope.remove_attribute = function (fieldIndex, key, $index) {
      if (
        $scope.options["fields"][fieldIndex]["general"][key].options.length == 1
      ) {
        return;
      }
      if (
        angular.isDefined(
          $scope.options["fields"][fieldIndex]["general"][key].remove_att
        )
      ) {
        if (window.spbwcDialog) { window.spbwcDialog.toast({ message: storelly_options.storelly_options_lang.can_not_remove_att, tone: "warning" }); } else { window.alert(storelly_options.storelly_options_lang.can_not_remove_att); }
        return;
      }
      $scope.options["fields"][fieldIndex]["general"][key]["options"].splice(
        $index,
        1
      );
      $scope.initfieldValue();
    };
    $scope.remove_sub_attribute = function (fieldIndex, opIndex, sopIndex) {
      $scope.options["fields"][fieldIndex]["general"]["attributes"]["options"][
        opIndex
      ]["sub_attributes"].splice(sopIndex, 1);
      $scope.initfieldValue();
    };
    $scope.add_text_configs_color = function (fieldIndex) {
      $scope.options["fields"][fieldIndex]["general"]["nbpb_text_configs"][
        "colors"
      ].push({
        name: "White",
        code: "#ffffff",
      });
    };
    $scope.remove_text_configs_color = function (fieldIndex, clIndex) {
      $scope.options["fields"][fieldIndex]["general"]["nbpb_text_configs"][
        "colors"
      ].splice(clIndex, 1);
    };
    $scope.sort_attribute = function (fieldIndex, opIndex, direction) {
      var options =
        $scope.options["fields"][fieldIndex]["general"]["attributes"][
          "options"
        ];
      var dest_index = opIndex - 1;
      if (direction == "up") {
        if (opIndex == 0) return;
      } else {
        if (opIndex == options.length - 1) return;
        dest_index = opIndex + 1;
      }
      var temp_op = {};
      angular.copy(options[opIndex], temp_op);
      angular.copy(options[dest_index], options[opIndex]);
      angular.copy(temp_op, options[dest_index]);
      $scope.initfieldValue();
    };
    $scope.sort_sub_attribute = function (
      fieldIndex,
      opIndex,
      sopIndex,
      direction
    ) {
      var sub_attributes =
        $scope.options["fields"][fieldIndex]["general"]["attributes"][
          "options"
        ][opIndex]["sub_attributes"];
      var dest_index = sopIndex - 1;
      if (direction == "up") {
        if (sopIndex == 0) return;
      } else {
        if (sopIndex == sub_attributes.length - 1) return;
        dest_index = sopIndex + 1;
      }
      var temp_sop = {};
      angular.copy(sub_attributes[sopIndex], temp_sop);
      angular.copy(sub_attributes[dest_index], sub_attributes[sopIndex]);
      angular.copy(temp_sop, sub_attributes[dest_index]);
      $scope.initfieldValue();
    };
    $scope.toggle_expand_attribute = function (fieldIndex, opIndex) {
      $scope.options["fields"][fieldIndex]["general"]["attributes"]["options"][
        opIndex
      ]["isExpand"] =
        !$scope.options["fields"][fieldIndex]["general"]["attributes"][
          "options"
        ][opIndex]["isExpand"];
    };
    $scope.toggle_expand_sub_attribute = function (
      fieldIndex,
      opIndex,
      sopIndex
    ) {
      $scope.options["fields"][fieldIndex]["general"]["attributes"]["options"][
        opIndex
      ]["sub_attributes"][sopIndex]["isExpand"] =
        !$scope.options["fields"][fieldIndex]["general"]["attributes"][
          "options"
        ][opIndex]["sub_attributes"][sopIndex]["isExpand"];
    };
    $scope.seleted_attribute = function (fieldIndex, key, $index) {
      angular.forEach(
        $scope.options["fields"][fieldIndex]["general"][key]["options"],
        function (field, _key) {
          $scope.options["fields"][fieldIndex]["general"][key]["options"][_key][
            "selected"
          ] = 0;
        }
      );
      $scope.options["fields"][fieldIndex]["general"][key]["options"][$index][
        "selected"
      ] = 1;
      $scope.initfieldValue();
    };
    $scope.seleted_sub_attribute = function (
      fieldIndex,
      key,
      opIndex,
      sopIndex
    ) {
      angular.forEach(
        $scope.options["fields"][fieldIndex]["general"][key]["options"][
          opIndex
        ]["sub_attributes"],
        function (field, _key) {
          $scope.options["fields"][fieldIndex]["general"][key]["options"][
            opIndex
          ]["sub_attributes"][_key]["selected"] = 0;
        }
      );
      $scope.options["fields"][fieldIndex]["general"][key]["options"][opIndex][
        "sub_attributes"
      ][sopIndex]["selected"] = 1;
      $scope.initfieldValue();
    };
    $scope.add_attribute = function (fieldIndex, key) {
      if (
        angular.isDefined(
          $scope.options["fields"][fieldIndex]["general"][key].add_att
        )
      ) {
        if (window.spbwcDialog) { window.spbwcDialog.toast({ message: storelly_options.storelly_options_lang.can_not_add_att, tone: "warning" }); } else { window.alert(storelly_options.storelly_options_lang.can_not_add_att); }
        return;
      }

      $scope.options["fields"][fieldIndex]["general"][key]["options"].push({
        name: storelly_options.storelly_options_lang.attribute_name,
        des: "",
        price: [],
        selected: 0,
        preview_type: "i",
        image: 0,
        image_url: "",
        color: "#ffffff",
        bg_image: [],
        bg_image_url: [],
        isExpand: true,
        depend: [
          {
            id: "",
            operator: "i",
            val: "",
            subval: "",
          },
        ],
      });
      $scope.initfieldValue();
    };
    $scope.add_sub_attribute = function (fieldIndex, opIndex) {
      if (
        angular.isUndefined(
          $scope.options["fields"][fieldIndex]["general"]["attributes"][
            "options"
          ][opIndex]["sub_attributes"]
        )
      ) {
        $scope.options["fields"][fieldIndex]["general"]["attributes"][
          "options"
        ][opIndex]["sub_attributes"] = [];
      }
      var subAttrs = $scope.options["fields"][fieldIndex]["general"]["attributes"]["options"][opIndex]["sub_attributes"];
      subAttrs.push({
        name: storelly_options.storelly_options_lang.sub_attribute_name,
        des: "",
        price: [],
        selected: 0,
        preview_type: "i",
        image: 0,
        image_url: "",
        color: "#ffffff",
        isExpand: true,
        depend: [
          {
            id: "",
            operator: "i",
            val: "",
            subval: "",
          },
        ],
      });
      $scope.initfieldValue();
      // Auto-scroll to the newly-added sub-attribute card so the user can
      // see it immediately (otherwise it can be hidden below the fold and
      // appear "covered" by the sticky Live Preview pane).
      var newIndex = subAttrs.length - 1;
      setTimeout(function () {
        var cards = document.querySelectorAll(
          '[data-field-index="' + fieldIndex + '"] .nbd-subattributes-wrap'
        );
        var target = cards[cards.length - 1];
        if (target && typeof target.scrollIntoView === 'function') {
          target.scrollIntoView({ behavior: 'smooth', block: 'center' });
          target.classList.add('is-just-added');
          setTimeout(function () {
            target.classList.remove('is-just-added');
          }, 1600);
        }
      }, 80);
    };
    $scope.toggle_enable_subattr = function (fieldIndex, opIndex) {
      var field = $scope.options["fields"][fieldIndex];
      if (
        angular.isUndefined(
          field["general"]["attributes"]["options"][opIndex][
            "sattr_display_type"
          ]
        )
      ) {
        field["general"]["attributes"]["options"][opIndex][
          "sattr_display_type"
        ] = "s";
      }
      if (angular.isDefined(field.nbpb_type) && field.nbpb_type == "nbpb_com") {
        $scope.buildPbConfigFlat(field);
      }
    };
    $scope.set_attribute_image = function (
      fieldIndex,
      $index,
      type,
      type_url,
      $bg_index
    ) {
      var file_frame;
      if (file_frame) {
        file_frame.open();
        return;
      }
      // Scope the picker to the current product's images (post_parent). The
      // merchant can still switch to "All media items" inside the frame.
      var field = $scope.options.fields[fieldIndex];
      var attr  = field && field.general && field.general.attributes
                  && field.general.attributes.options
                  && field.general.attributes.options[$index];
      var spbwcPid = $scope.spbwcMediaPid();
      var lib = {
        type: ["image"],
        orderby: "date",
        order: "DESC",
      };
      if (spbwcPid > 0) lib.uploadedTo = spbwcPid;
      file_frame = wp.media.frames.file_frame = wp.media({
        title: $scope.spbwcFrameTitle(),
        button: {
          text: storelly_options.storelly_options_lang.choose_image,
        },
        library: lib,
        multiple: false,
      });
      // Pre-select the option's current image so "edit" opens the right one.
      var spbwcCurrentImg = angular.isDefined($bg_index)
        ? (attr && attr[type] && attr[type][$bg_index])
        : (attr && attr[type]);
      $scope.spbwcPreselect(file_frame, spbwcCurrentImg);
      file_frame.on("select", function () {
        var attachment = file_frame.state().get("selection").first().toJSON();
        if (angular.isDefined($bg_index)) {
          $scope.options["fields"][fieldIndex]["general"]["attributes"][
            "options"
          ][$index][type][$bg_index] = attachment.id;
        } else {
          $scope.options["fields"][fieldIndex]["general"]["attributes"][
            "options"
          ][$index][type] = attachment.id;
        }
        var url = attachment.url;
        if (
          angular.isDefined(attachment.sizes) &&
          angular.isDefined(attachment.sizes.thumbnail)
        ) {
          url = attachment.sizes.thumbnail.url;
        }
        if (angular.isDefined($bg_index)) {
          $scope.options["fields"][fieldIndex]["general"]["attributes"][
            "options"
          ][$index][type_url][$bg_index] = url;
        } else {
          $scope.options["fields"][fieldIndex]["general"]["attributes"][
            "options"
          ][$index][type_url] = url;
        }
        if (
          $scope.$root.$$phase !== "$apply" &&
          $scope.$root.$$phase !== "$digest"
        )
          $scope.$apply();
      });
      file_frame.open();
    };
    $scope.set_component_icon = function (fieldIndex) {
      var file_frame;
      if (file_frame) {
        file_frame.open();
        return;
      }
      var spbwcPid = $scope.spbwcMediaPid();
      var lib = { type: ["image"] };
      if (spbwcPid > 0) lib.uploadedTo = spbwcPid;
      file_frame = wp.media.frames.file_frame = wp.media({
        title: $scope.spbwcFrameTitle(),
        button: {
          text: storelly_options.storelly_options_lang.choose_image,
        },
        library: lib,
        multiple: false,
      });
      $scope.spbwcPreselect(
        file_frame,
        $scope.options["fields"][fieldIndex]["general"]["component_icon"]
      );
      file_frame.on("select", function () {
        var attachment = file_frame.state().get("selection").first().toJSON();
        $scope.options["fields"][fieldIndex]["general"]["component_icon"] =
          attachment.id;
        var url = attachment.url;
        if (
          angular.isDefined(attachment.sizes) &&
          angular.isDefined(attachment.sizes.thumbnail)
        ) {
          url = attachment.sizes.thumbnail.url;
        }
        $scope.options["fields"][fieldIndex]["general"]["component_icon_url"] =
          url;
        if (
          $scope.$root.$$phase !== "$apply" &&
          $scope.$root.$$phase !== "$digest"
        )
          $scope.$apply();
      });
      file_frame.open();
    };
    $scope.remove_component_icon = function (fieldIndex) {
      $scope.options["fields"][fieldIndex]["general"]["component_icon"] = 0;
      $scope.options["fields"][fieldIndex]["general"]["component_icon_url"] =
        "";
    };
    $scope.set_sub_attribute_image = function (fieldIndex, opIndex, sopIndex) {
      var file_frame;
      if (file_frame) {
        file_frame.open();
        return;
      }
      var spbwcPid = $scope.spbwcMediaPid();
      var lib = { type: ["image"] };
      if (spbwcPid > 0) lib.uploadedTo = spbwcPid;
      file_frame = wp.media.frames.file_frame = wp.media({
        title: $scope.spbwcFrameTitle(),
        button: {
          text: storelly_options.storelly_options_lang.choose_image,
        },
        library: lib,
        multiple: false,
      });
      $scope.spbwcPreselect(
        file_frame,
        $scope.options["fields"][fieldIndex]["general"]["attributes"]["options"][opIndex]["sub_attributes"][sopIndex]["image"]
      );
      file_frame.on("select", function () {
        var attachment = file_frame.state().get("selection").first().toJSON();
        $scope.options["fields"][fieldIndex]["general"]["attributes"][
          "options"
        ][opIndex]["sub_attributes"][sopIndex]["image"] = attachment.id;
        var url = attachment.url;
        if (
          angular.isDefined(attachment.sizes) &&
          angular.isDefined(attachment.sizes.thumbnail)
        ) {
          url = attachment.sizes.thumbnail.url;
        }
        $scope.options["fields"][fieldIndex]["general"]["attributes"][
          "options"
        ][opIndex]["sub_attributes"][sopIndex]["image_url"] = url;
        if (
          $scope.$root.$$phase !== "$apply" &&
          $scope.$root.$$phase !== "$digest"
        )
          $scope.$apply();
      });
      file_frame.open();
    };
    $scope.remove_attribute_image = function (
      fieldIndex,
      $index,
      type,
      type_url
    ) {
      $scope.options["fields"][fieldIndex]["general"]["attributes"]["options"][
        $index
      ][type] = 0;
      $scope.options["fields"][fieldIndex]["general"]["attributes"]["options"][
        $index
      ][type_url] = "";
    };
    $scope.remove_sub_attribute_image = function (
      fieldIndex,
      opIndex,
      sopIndex
    ) {
      $scope.options["fields"][fieldIndex]["general"]["attributes"]["options"][
        opIndex
      ]["sub_attributes"][sopIndex]["image"] = 0;
      $scope.options["fields"][fieldIndex]["general"]["attributes"]["options"][
        opIndex
      ]["sub_attributes"][sopIndex]["image_url"] = "";
    };
    $scope.add_remove_second_color = function (fieldIndex, opIndex) {
      if (
        angular.isUndefined(
          $scope.options["fields"][fieldIndex]["general"]["attributes"][
            "options"
          ][opIndex]["color2"]
        )
      ) {
        $scope.options["fields"][fieldIndex]["general"]["attributes"][
          "options"
        ][opIndex]["color2"] = "#ffffff";
      } else {
        delete $scope.options["fields"][fieldIndex]["general"]["attributes"][
          "options"
        ][opIndex].color2;
      }
    };
    $scope.update_price_type = function (fieldIndex) {
      if (
        $scope.options["fields"][fieldIndex]["general"].data_type.value ==
          "m" &&
        $scope.options["fields"][fieldIndex]["general"].price_type.value == "c"
      ) {
        $scope.options["fields"][fieldIndex]["general"].price_type.value = "f";
      }
    };
    $scope.updateApp = function () {
      if (
        $scope.$root.$$phase !== "$apply" &&
        $scope.$root.$$phase !== "$digest"
      )
        $scope.$apply();
    };
    $scope.updateJsonFields = function (e) {
      return true;
      e.preventDefault();
      $scope.getJsonFields();
      return false;
    };

    /**
     * Single-click on the collapsed field card header expands it.
     * Skips clicks on interactive children (input, button, action area,
     * drag handle) so they keep their normal behavior.
     */
    $scope.onHeaderClick = function (fieldIndex, event) {
      var t = event.target;
      if (!t) return;
      // Skip clicks that should not toggle the card.
      if (
        t.tagName === "INPUT" ||
        t.tagName === "BUTTON" ||
        t.tagName === "A" ||
        t.tagName === "SELECT" ||
        t.tagName === "TEXTAREA" ||
        (t.closest && (
          t.closest(".field-action") ||
          t.closest(".v2-drag-handle") ||
          t.closest(".pcpb-field-name__title--editable") ||
          t.closest(".nbd-tab-nav") ||
          t.closest(".pcpb-field-content")
        ))
      ) {
        return;
      }
      if (!$scope.options.fields[fieldIndex].isExpand) {
        $scope.toggleExpandField(fieldIndex, event);
      }
    };

    /**
     * Wire up jQuery UI Sortable on the field list so the new ⋮⋮
     * drag handle works. The legacy ↑/↓ arrows keep working as a
     * keyboard-accessible fallback (no JS removal).
     *
     * Runs after the initial digest finishes so the ng-repeat'd cards
     * exist in the DOM. Re-runs whenever fields are added/removed via
     * the .destroy() + re-init cycle on the same root jQuery object.
     */
    $scope.initSortableFields = function () {
      var $container = jQuery(".pcpb-fields-builder");
      if (!$container.length || typeof $container.sortable !== "function") {
        return;
      }
      // Destroy any previous instance to avoid stacking.
      try { $container.sortable("destroy"); } catch (e) { /* not initialized yet */ }
      $container.sortable({
        handle: ".v2-drag-handle",
        items: "> .pcpb-field-wrap",
        axis: "y",
        tolerance: "pointer",
        cursor: "grabbing",
        placeholder: "v2-sortable-placeholder",
        forcePlaceholderSize: true,
        opacity: 0.7,
        revert: 150,
        start: function (e, ui) {
          ui.placeholder.height(ui.item.outerHeight());
          ui.item.addClass("is-dragging");
        },
        stop: function (e, ui) {
          ui.item.removeClass("is-dragging");
        },
        update: function (e, ui) {
          // Read the new DOM order and rebuild $scope.options.fields.
          var newOrder = [];
          $container.find("> .pcpb-field-wrap").each(function () {
            var origIdx = parseInt(jQuery(this).attr("data-field-index"), 10);
            if (!isNaN(origIdx) && $scope.options.fields[origIdx]) {
              newOrder.push($scope.options.fields[origIdx]);
            }
          });
          if (newOrder.length === $scope.options.fields.length) {
            $scope.$apply(function () {
              $scope.options.fields = newOrder;
            });
          }
        },
      });
    };
    $scope.getJsonFields = function () {
      var fields = [];
      angular.forEach($scope.options.fields, function (field, fieldIndex) {
        fields[fieldIndex] = {
          id: field.id,
          general: {
            title: field.general.title.value,
            description: field.general.description.value,
            data_type: field.general.data_type.value,
            input_type: field.general.input_type.value,
            input_option: field.general.input_option.value,
            text_option: field.general.text_option.value,
            upload_option: field.general.upload_option.value,
            enabled: field.general.enabled.value,
            published: !!field.general.published
              ? field.general.published.value
              : "y",
            required: field.general.required.value,
            price_type: field.general.price_type.value,
            price: field.general.price.value,
            // Field-level keys the editor authors via direct inputs. They live
            // in the model as {value:…} (see build_config_general_*), but this
            // serializer is whitelist-based and the PHP save lets jsonFields
            // overwrite the whole fields[] array — so anything omitted here is
            // silently dropped on every save. Carry them through.
            placeholder: field.general.placeholder
              ? field.general.placeholder.value
              : "",
            depend_qty: field.general.depend_qty
              ? field.general.depend_qty.value
              : "",
            depend_quantity: field.general.depend_quantity
              ? field.general.depend_quantity.value
              : "",
            price_breaks: field.general.price_breaks
              ? field.general.price_breaks.value
              : "",
            attributes: {},
          },
          appearance: {},
        };

        // Conditional-logic rules (V2 feature, consumed by conditional-logic.js
        // on the storefront). Stored as a plain array of {id,operator,val} on
        // field.general.conditional_depend (NOT value-wrapped). Without this the
        // merchant's rules are wiped on every save.
        if (angular.isDefined(field.general.conditional_depend)) {
          fields[fieldIndex].general.conditional_depend =
            field.general.conditional_depend;
        }

        if (field.general.attributes.options.length > 0) {
          fields[fieldIndex].general.attributes.options = [];
          angular.forEach(
            field.general.attributes.options,
            function (op, opIndex) {
              fields[fieldIndex].general.attributes.options[opIndex] = {
                preview_type: op.preview_type,
                image: op.image,
                color: op.color,
                name: op.name,
                des: op.des,
                price: op.price,
              };

              // Preserve sub-attributes on save. The editor whitelists option
              // keys above, so without this the entire sub_attributes tree
              // (color/pattern swatches + their images) is dropped on every
              // save — silently flattening multi-level options. Sub-attributes
              // are still authored in the classic editor, but the model holds
              // them regardless, so we must round-trip them here.
              if (
                angular.isDefined(op.enable_subattr) &&
                (op.enable_subattr === true ||
                  op.enable_subattr === "on" ||
                  op.enable_subattr === 1) &&
                angular.isArray(op.sub_attributes) &&
                op.sub_attributes.length > 0
              ) {
                var built_subs = [];
                angular.forEach(op.sub_attributes, function (sop, sopIndex) {
                  built_subs[sopIndex] = {
                    name: sop.name,
                    des: sop.des,
                    price: sop.price,
                    preview_type: sop.preview_type,
                    image: sop.image,
                    color: sop.color,
                  };
                });
                fields[fieldIndex].general.attributes.options[
                  opIndex
                ].enable_subattr = op.enable_subattr;
                fields[fieldIndex].general.attributes.options[
                  opIndex
                ].sattr_display_type = angular.isDefined(op.sattr_display_type)
                  ? op.sattr_display_type
                  : "s";
                fields[fieldIndex].general.attributes.options[
                  opIndex
                ].sub_attributes = built_subs;
              }

              if (field.appearance.change_image_product.value == "y") {
                fields[fieldIndex].general.attributes.options[
                  opIndex
                ].product_image = op.product_image;
              }
              if (op.selected) {
                fields[fieldIndex].general.attributes.options[
                  opIndex
                ].selected = "on";
              }
            }
          );
        }
        angular.forEach(field.appearance, function (data, key) {
          fields[fieldIndex].appearance[key] = data.value;
        });

        if (field.nbpb_type) {
          fields[fieldIndex].nbpb_type = field.nbpb_type;
          switch (field.nbpb_type) {
            case "nbpb_com":
              fields[fieldIndex].general.component_icon =
                field.general.component_icon;
              if (
                field.general.pb_config_flat.length > 0 &&
                $scope.options.views.length > 0
              ) {
                fields[fieldIndex].general.pb_config = field.general.pb_config;
              }
              break;
            case "nbpb_image":
              if ($scope.options.views.length > 0) {
                fields[fieldIndex].general.nbpb_image_configs =
                  field.general.nbpb_image_configs;
              }
              break;
            case "nbpb_text":
              fields[fieldIndex].general.nbpb_text_configs =
                field.general.nbpb_text_configs;
              break;
          }
        }
      });

      function cleanse(obj, path) {
        Object.keys(obj).forEach(function (key) {
          var value = obj[key];
          var type = typeof value;
          if (type === "object") {
            cleanse(value);
            if (!Object.keys(value).length) {
              //delete obj[key]
            }
          } else {
            if (type === "undefined" || key == "$$hashKey") {
              delete obj[key];
            } else {
              if (key == "display" || key == "selected") {
                if (value === true || value === "on") {
                  obj[key] = "on";
                } else {
                  delete obj[key];
                }
              }
            }
          }
        });
      }

      cleanse(fields);
      $scope.jsonFields = JSON.stringify(fields);
      setTimeout(function () {
        jQuery('form[name="nboForm"]').submit();
      });
    };

    /* ───────────────────────────────────────────────────────────
     * V3 in-place save (Wave 2, A2). On the standalone v3 editor
     * (#spbwc-po-v3-form, NOT the Visual Builder shell which owns its
     * own #spbwc-vb-form interceptor), turn the manual Save's full-page
     * form submit into an AJAX call to spbwc_save_option_ajax. The
     * AngularJS model already holds every field value, so dependent
     * field bodies (sub_attributes, conditional_depend, price_breaks,
     * depend_quantity, placeholder) stay correctly rendered without a
     * reload — that reload was the only reason they previously appeared
     * to "need a save to show". Serialization is unchanged (jsonFields
     * is still the source of truth), so no whitelist regression.
     * ─────────────────────────────────────────────────────────── */
    $timeout(function () {
      // Visual Builder shell present? Leave its flow untouched.
      if (document.getElementById("spbwc-vb-form")) {
        return;
      }
      var v3form = document.getElementById("spbwc-po-v3-form");
      if (!v3form || typeof window.fetch !== "function") {
        return;
      }

      function setStatus(label, dirty) {
        try {
          $scope.$root.vbSavedLabel = label;
          $scope.$root.vbDirty = !!dirty;
          if ($scope.$root.$$phase !== "$apply" && $scope.$root.$$phase !== "$digest") {
            $scope.$applyAsync();
          }
        } catch (e) { /* status line is optional */ }
      }

      v3form.addEventListener(
        "submit",
        function (e) {
          // Avoid double-handling and let the browser do a normal submit
          // only if something forced it (fallback flag).
          if (v3form._spbwcAllowNativeSubmit) {
            v3form._spbwcAllowNativeSubmit = false;
            return;
          }
          e.preventDefault();
          e.stopPropagation();
          e.stopImmediatePropagation();

          setStatus("Saving…", false);

          var fd = new window.FormData(v3form);
          // Route to the dedicated AJAX endpoint (reuses spbwc_save_option
          // server-side; same nonce + capability checks).
          fd.set("action", "spbwc_save_option_ajax");
          var ajaxUrl =
            (typeof storelly_option_variable !== "undefined" &&
              storelly_option_variable.ajax_url) ||
            window.ajaxurl;

          window
            .fetch(ajaxUrl, {
              method: "POST",
              body: fd,
              credentials: "same-origin",
            })
            .then(function (r) {
              return r.json().catch(function () {
                return null;
              });
            })
            .then(function (res) {
              if (res && res.success && res.data) {
                // New option just got its id — keep editing it in place.
                if (res.data.id) {
                  var idField = v3form.querySelector('input[name="option_id"]');
                  if (idField && String(idField.value) !== String(res.data.id)) {
                    idField.value = res.data.id;
                    // Reflect the new id in the URL without reloading so a
                    // manual refresh / further saves target the saved row.
                    if (window.history && window.history.replaceState) {
                      try {
                        var u = new window.URL(window.location.href);
                        u.searchParams.set("id", res.data.id);
                        if (u.searchParams.get("action") === "create_v3") {
                          u.searchParams.set("action", "v3");
                        }
                        window.history.replaceState(null, "", u.toString());
                      } catch (e2) { /* URL update is best-effort */ }
                    }
                  }
                }
                setStatus(
                  (res.data.msg ? res.data.msg : "Saved") +
                    " · " +
                    new Date().toLocaleTimeString(),
                  false
                );
              } else {
                var msg =
                  (res && res.data && (res.data.msg || res.data.message)) ||
                  "Save failed. Please try again.";
                setStatus(msg, true);
                if (window.spbwcDialog) { window.spbwcDialog.toast({ message: msg, tone: "error" }); } else { window.alert(msg); }
              }
            })
            .catch(function () {
              setStatus("Network error — your changes are still here.", true);
              var netMsg = "Network error while saving. Your changes are still in the editor.";
              if (window.spbwcDialog) { window.spbwcDialog.toast({ message: netMsg, tone: "error" }); } else { window.alert(netMsg); }
            });
        },
        true /* capture */
      );
    });

    $scope.init();
  })
  .directive("stringToNumber", function () {
    return {
      require: "ngModel",
      link: function (scope, element, attrs, ngModel) {
        ngModel.$parsers.push(function (value) {
          return "" + value;
        });
        ngModel.$formatters.push(function (value) {
          return parseFloat(value);
        });
      },
    };
  })
  .directive("convertToNumber", function () {
    return {
      require: "ngModel",
      link: function (scope, element, attrs, ngModel) {
        ngModel.$parsers.push(function (val) {
          return val != null ? parseInt(val, 10) : null;
        });
        ngModel.$formatters.push(function (val) {
          return val != null ? "" + val : null;
        });
      },
    };
  })
  .directive("nbdColorPicker", function () {
    return {
      restrict: "A",
      scope: {
        value: "=nbdColorPicker",
      },
      link: function (scope, element) {
        function init() {
          jQuery(element).val(scope.value);
          jQuery(element).wpColorPicker({
            change: function (evt, ui) {
              var $input = jQuery(this);
              setTimeout(function () {
                if (
                  $input.wpColorPicker("color") !== $input.data("tempcolor")
                ) {
                  $input
                    .change()
                    .data("tempcolor", $input.wpColorPicker("color"));
                  $input.val($input.wpColorPicker("color"));
                }
              }, 10);
            },
          });
        }
        scope.$watch(
          "value",
          function (newValue, oldValue) {
            if (newValue != oldValue) {
              jQuery(element).wpColorPicker("color", newValue);
            }
          },
          true
        );
        scope.$on("$destroy", function () {
          jQuery(element).parents(".wp-picker-container").remove();
        });
        init();
      },
    };
  })
  .directive("nbdSelect2", function ($timeout) {
    return {
      restrict: "A",
      link: function (scope, element) {
        $timeout(function () {
          jQuery(element).selectWoo();
        });
      },
    };
  })
  .directive("nbdTab", function ($timeout) {
    return {
      restrict: "A",
      link: function (scope, element) {
        $timeout(function () {
          jQuery.each(jQuery(element).find(".pcpb-field-tab"), function () {
            jQuery(this).on("click", function () {
              var target = jQuery(this).data("target");
              jQuery(this)
                .parents(".pcpb-field-wrap")
                .find(".pcpb-field-content")
                .removeClass("active");
              jQuery(this).parent("ul").find("li").removeClass("active");
              jQuery(this)
                .parents(".pcpb-field-wrap")
                .find("." + target)
                .addClass("active");
              jQuery(this).addClass("active");
            });
          });
        });
      },
    };
  })
  .directive("nbdTip", function ($timeout) {
    return {
      restrict: "E",
      scope: {
        dataTip: "@tip",
      },
      template:
        '<span class="woocommerce-help-tip" data-tip="{{dataTip}}" ></span>',
      link: function (scope, element, attrs) {
        var tiptip_args = {
          attribute: "data-tip",
          fadeIn: 50,
          fadeOut: 50,
          delay: 200,
        };
        $timeout(function () {
          jQuery(element).find(".woocommerce-help-tip").tipTip(tiptip_args);
        }, 0);
      },
    };
  })
  .directive("nboSubAttrSelect", function ($timeout) {
    return {
      restrict: "E",
      scope: {
        find: "=",
        oind: "=",
        cind: "=",
        sind: "=",
        con: "=",
        fields: "=",
      },
      template:
        '<select class="nbd-w-100i" name="options[fields][{{find}}][general][attributes][options][{{oind}}]{{sind_name}}[depend][{{cind}}][subval]" ng-if="available" ng-model="con.subval"><option ng-repeat="attr in attributes" value="{{$index}}">{{attr.name}}</option></select>',
      link: function (scope, element, attrs) {
        if (scope.sind) {
          scope.sind_name = "[sub_attributes][" + scope.sind + "]";
        } else {
          scope.sind_name = "";
        }
      },
    };
  })
  .filter("range", function () {
    return function (input, total) {
      total = parseInt(total);
      for (var i = 0; i < total; i++) {
        input.push(i);
      }
      return input;
    };
  })
  .directive("nbdPmDroppable", function ($timeout) {
    return {
      restrict: "A",
      scope: {
        dataDir: "@dir",
      },
      link: function (scope, element, attrs) {
        $timeout(function () {
          jQuery(element)
            .droppable({
              hoverClass: "nbd-dropzone-hover",
              accept: ".nbd-darg-pm-field",
              drop: function (evt, ui) {
                scope.$emit("mpm:drop", scope.dataDir, ui.helper.data("id"));
              },
            })
            .sortable({
              items: "> .nbd-pm-field",
              scroll: true,
              placeholder: "ui-sortable-placeholder",
              update: function () {
                var ids = jQuery(element)
                  .children(".nbd-pm-field")
                  .map(function (id, elem) {
                    return jQuery(elem).data("id");
                  })
                  .get();
                scope.$emit("mpm:sort", scope.dataDir, ids);
                $timeout(function () {
                  jQuery(element).sortable("refreshPositions");
                });
              },
            });
        });
      },
    };
  })
  .directive("nbdPmDraggable", function ($timeout) {
    return {
      restrict: "A",
      link: function (scope, element, attrs) {
        $timeout(function () {
          jQuery(element).draggable({
            helper: "clone",
            cursor: "move",
          });
        });
      },
    };
  });
jQuery(document).ready(function ($) {
  $(".nbo-dates input:not(.hasDatepicker)").datepicker({
    defaultDate: "",
    dateFormat: "yy-mm-dd",
    numberOfMonths: 1,
    showButtonPanel: true,
    showOn: "button",
    buttonImage: storelly_options.calendar_image,
    buttonImageOnly: true,
    onSelect: function (selectedDate) {
      var option = $(this).is(".date_from") ? "minDate" : "maxDate";
      var instance = $(this).data("datepicker"),
        date = $.datepicker.parseDate(
          instance.settings.dateFormat || $.datepicker._defaults.dateFormat,
          selectedDate,
          instance.settings
        );
      var dates = $(this).parents(".nbo-dates").find("input");
      dates.not(this).datepicker("option", option, date);
    },
  });
  $(".nbo-toggle-nav").on("click", function () {
    $(".nbo-toggle").removeClass("active");
    if ($(this).is(":checked")) {
      $($(this).data("toggle")).addClass("active");
    }
  });
});
