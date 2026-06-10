"use strict";

var fontApp = angular.module("font-app", []);
fontApp.controller("fontCtrl", [
  "$scope",
  "fontObject",
  "filterFontFilter",
  function ($scope, fontObject, filterFontFilter) {
    $scope.init = function () {
      angular.forEach(storelly_manager_fonts_variable.selected_fonts, function (_font, k) {
        $scope.selectedFonts.push({
          name: _font.name,
        });
      });
      $scope.updateSelectedFont();
    };
    $scope.selectedFonts = [];
    $scope.allFonts = storelly_manager_fonts_variable.ggFonts.items;
    $scope.fSubsets = storelly_manager_fonts_variable.fSubsets;
    $scope.filterFont = {};
    $scope.filterFont.currentPage = 0;
    $scope.filterFont.pageSize = 20;
    $scope.fonts = filterFontFilter($scope.allFonts, $scope.filterFont);
    $scope.$watchCollection(
      "filterFont",
      function (newVal, oldVal) {
        $scope.fonts = filterFontFilter($scope.allFonts, $scope.filterFont);
      },
      true
    );
    $scope.$watchCollection(
      "selectedFonts",
      function (newVal, oldVal) {
        $scope.fonts = filterFontFilter($scope.allFonts, $scope.filterFont);
      },
      true
    );
    $scope.resetCurentPage = function () {
      $scope.filterFont.currentPage = 0;
    };
    $scope.updateSelectedFont = function () {
      angular.forEach($scope.allFonts, function (font, key) {
        $scope.allFonts[key].selected = false;
        angular.forEach($scope.selectedFonts, function (_font, k) {
          if (font.family == _font.name) $scope.allFonts[key].selected = true;
        });
      });
    };
    $scope.selectAll = function () {
      $scope.selectedFonts = [];
      angular.forEach($scope.allFonts, function (font, key) {
        $scope.selectedFonts.push({
          name: font.family,
        });
      });
      $scope.updateSelectedFont();
    };
    $scope.unselectAll = function () {
      $scope.selectedFonts = [];
      $scope.updateSelectedFont();
    };
    $scope.selectFont = function (font, $event) {
      if (!font.selected) {
        $scope.selectedFonts.push({
          name: font.family,
        });
      } else {
        var index = 0;
        angular.forEach($scope.selectedFonts, function (_font, k) {
          if (font.family == _font.name) index = k;
        });
        $scope.selectedFonts.splice(index, 1);
      }
      $scope.updateSelectedFont();
    };
    $scope.updateGoogleFont = function ($event) {
      jQuery
        .ajax({
          url: storelly_pb_fonts.url,
          method: "POST",
          data: {
            action: "spbwc_add_google_font",
            fonts: JSON.stringify($scope.selectedFonts),
            nonce: storelly_pb_fonts.nonce,
          },
          beforeSend: function () {
            jQuery(".showbox").show();
          },
          complete: function () {
            jQuery(".showbox").hide();
          },
        })
        .done(function (data) {
          data = JSON.parse(data);
          swal(storelly_pb_fonts.complete, data.mes, "success");
        });
    };
    $scope.init();
  },
]);
fontApp.factory("fontObject", function ($http) {
  return {
    fn: function (callback) {
      $http({
        method: "GET",
        url: font_path,
      }).then(
        function (response) {
          callback(response.data.items);
        },
        function (error) {}
      );
    },
  };
});
fontApp.filter("pageRange", function () {
  return function (input, total) {
    total = parseInt(total);
    for (var i = 0; i < total; i++) input.push(i);
    return input;
  };
});
fontApp.directive("stringToNumber", function () {
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
});
fontApp.directive("fontPagination", function () {
  return {
    restrict: "A",
    scope: {
      filterFont: "=filterFont",
      total: "=total",
    },
    template:
      '{{filterFont.totalPages}}<span ng-if="filterFont.currentPage > 0" ng-click="changePage(0)">First</span><span ng-if="filterFont.currentPage > 1" ng-click="changePage(filterFont.currentPage-1)">{{filterFont.currentPage}}</span><span ng-click="changePage(filterFont.currentPage)" class="active">{{filterFont.currentPage + 1}}</span><span ng-if="filterFont.currentPage < (totalPages - 2)" ng-click="changePage(filterFont.currentPage+1)">{{filterFont.currentPage + 2}}</span><span ng-if="filterFont.currentPage < (totalPages - 1)" ng-click="changePage(totalPages-1)">Last</span>',
    link: {},
    controller: function ($scope) {
      $scope.pages = 1;
      $scope.$watch("total", function () {
        $scope.totalPages = Math.ceil(
          $scope.total / $scope.filterFont.pageSize
        );
      });
      $scope.$watchCollection("filterFont", function () {
        $scope.totalPages = Math.ceil(
          $scope.total / $scope.filterFont.pageSize
        );
      });
      $scope.changePage = function ($index) {
        $scope.filterFont.currentPage = $index;
      };
    },
  };
});
fontApp.filter("startFrom", function () {
  return function (input, start) {
    start = +start;
    return input.slice(start);
  };
});
fontApp.filter("filterFont", function () {
  return function (fonts, filterFont) {
    var arrFont = [];
    angular.forEach(fonts, function (font, key) {
      if (!angular.isDefined(filterFont)) {
        arrFont.push(font);
      } else {
        var check = [];
        if (!!filterFont.subset) {
          check["subset"] = false;
          angular.forEach(font.subsets, function (subset, key) {
            if (subset == filterFont.subset) check["subset"] = true;
          });
        } else {
          check["subset"] = true;
        }
        if (!!filterFont.category) {
          check["category"] =
            font.category == filterFont.category ? true : false;
        } else {
          check["category"] = true;
        }
        if (!!filterFont.name) {
          check["name"] =
            font.family.toLowerCase().indexOf(filterFont.name.toLowerCase()) >=
            0
              ? true
              : false;
        } else {
          check["name"] = true;
        }
        check["select"] = true;
        if (!!filterFont.select) {
          check["select"] =
            filterFont.select == "selected"
              ? font.selected
                ? true
                : false
              : font.selected
              ? false
              : true;
        }
        if (
          check["subset"] &&
          check["category"] &&
          check["name"] &&
          check["select"]
        )
          arrFont.push(font);
      }
    });
    return arrFont;
  };
});
fontApp.directive("fontOnLoad", [
  "$interval",
  function ($interval) {
    return {
      restrict: "A",
      scope: {
        font: "=",
        preview: "=",
      },
      link: function (scope, element) {
        var font_id = scope.font.replace(/\s/gi, "").toLowerCase();
        if (!jQuery("#" + font_id).length) {
          jQuery("head").append(
            '<link id="' +
              font_id +
              '" href="https://fonts.googleapis.com/css?family=' +
              scope.font.replace(/\s/gi, "+") +
              '" rel="stylesheet" type="text/css">'
          );
        }
        var font = new FontFaceObserver(scope.font);
        font.load(scope.preview, 1e4).then(
          function () {
            element.find(".font-loading").remove();
            element.find(".font-preview").show();
            element
              .parent(".gg-font-preview-inner")
              .find("span.action ")
              .removeClass("disable");
          },
          function () {
            console.log("Font " + scope.font + " is not available");
          }
        );
        element.append('<span class="font-loading">Loading...</span>');
      },
    };
  },
]);

/* ═══════════════════════════════════════════════════════════════
   Custom Font Upload — jQuery (independent of AngularJS app)
   ═══════════════════════════════════════════════════════════════ */
(function ($) {
  "use strict";

  // Wait until the localized variable is available (it is inlined before this script)
  var vars    = (typeof storelly_manager_fonts_variable !== "undefined") ? storelly_manager_fonts_variable : {};
  var i18n    = vars.i18n    || {};
  // ajax_url injected via wp_localize_script; fall back to WordPress global
  var ajaxUrl = vars.ajax_url || (typeof ajaxurl !== "undefined" ? ajaxurl : "");
  var nonce   = vars.upload_nonce || "";

  // Selected file object
  var selectedFile = null;
  // Temp font name used for live preview
  var previewFontName = "__spbwc_preview__";

  /* ── Helpers ── */

  function slugify(str) {
    return str.replace(/[^a-z0-9]/gi, "-").replace(/-+/g, "-").toLowerCase();
  }

  function nameFromFile(filename) {
    var base = filename.replace(/\.[^/.]+$/, "");
    return base.replace(/[-_]/g, " ").replace(/\b\w/g, function (c) { return c.toUpperCase(); });
  }

  function buildCard(font) {
    var cat  = font.category || "sans-serif";
    var name = font.name     || "";
    var id   = font.id       || "";
    var url  = font.url      || "";
    var fmt  = font.format   || "woff2";

    // Inject @font-face for the new card
    var styleId = "spbwc-cf-" + slugify(id);
    if (!$("#" + styleId).length) {
      $("head").append(
        '<style id="' + styleId + '">' +
        "@font-face{font-family:'" + name.replace(/'/g, "\\'") + "';" +
        "src:url('" + url + "') format('" + fmt + "')}" +
        "</style>"
      );
    }

    return $(
      '<div class="spbwc-custom-card" data-font-id="' + id + '">' +
        '<div class="spbwc-custom-card__inner">' +
          '<p class="spbwc-custom-card__name" title="' + name + '">' + name + "</p>" +
          '<p class="spbwc-custom-card__sample" style="font-family:\'' + name + "',sans-serif\">Abc Xyz 123</p>" +
        "</div>" +
        '<div class="spbwc-custom-card__footer">' +
          '<span class="spbwc-custom-card__category">' + cat + "</span>" +
          '<button type="button" class="spbwc-custom-card__delete" data-font-id="' + id + '" aria-label="Delete font">' +
            '<span class="dashicons dashicons-trash" aria-hidden="true"></span>' +
          "</button>" +
        "</div>" +
      "</div>"
    );
  }

  function updateCount() {
    var count = $("#spbwc-custom-grid .spbwc-custom-card").length;
    $("#spbwc-custom-count").text(count);
  }

  function showError(msg) {
    if (window.spbwcDialog) {
      window.spbwcDialog.toast({ message: msg, tone: "error" });
    } else if (typeof swal === "function") {
      swal("Error", msg, "error");
    } else {
      alert(msg);
    }
  }

  function showSuccess(msg) {
    if (window.spbwcDialog) {
      window.spbwcDialog.toast({ message: msg, tone: "success" });
    } else if (typeof swal === "function") {
      swal("", msg, "success");
    } else {
      alert(msg);
    }
  }

  function resetUploadPanel() {
    selectedFile = null;
    $("#spbwc-font-file").val("");
    $("#spbwc-upload-form").attr("hidden", true);
    $("#spbwc-drop-zone").show();
    $("#spbwc-upload-progress").attr("hidden", true);
    $("#spbwc-font-name").val("");
    $("#spbwc-preview-text").css("font-family", "inherit");
    $("#spbwc-selected-filename").text("");
  }

  /* ── Live preview via FontFace API ── */

  function loadPreviewFont(file) {
    if (!file || typeof FontFace === "undefined") return;
    var reader = new FileReader();
    reader.onload = function (e) {
      try {
        var face = new FontFace(previewFontName, e.target.result);
        face.load().then(function (loadedFace) {
          document.fonts.add(loadedFace);
          $("#spbwc-preview-text").css("font-family", "'" + previewFontName + "', sans-serif");
        });
      } catch (err) { /* silent — preview is non-critical */ }
    };
    reader.readAsArrayBuffer(file);
  }

  /* ── File selection (input OR drop) ── */

  function onFileSelected(file) {
    if (!file) return;

    // Validate extension client-side
    var ext = file.name.split(".").pop().toLowerCase();
    var allowed = ["ttf", "otf", "woff", "woff2"];
    if (allowed.indexOf(ext) === -1) {
      showError(i18n.error_filetype || "Invalid file type. Allowed: TTF, OTF, WOFF, WOFF2.");
      return;
    }

    selectedFile = file;

    // Auto-fill font name
    var suggestedName = nameFromFile(file.name);
    $("#spbwc-font-name").val(suggestedName);
    $("#spbwc-selected-filename").text(file.name + " (" + (file.size / 1024).toFixed(1) + " KB)");

    // Show step 2 form
    $("#spbwc-drop-zone").hide();
    $("#spbwc-upload-form").removeAttr("hidden");

    // Live preview
    loadPreviewFont(file);
  }

  /* ── Upload to server ── */

  function doUpload() {
    if (!selectedFile) return;

    var fontName = $.trim($("#spbwc-font-name").val());
    if (!fontName) {
      $("#spbwc-font-name").focus();
      return;
    }
    var category = $("#spbwc-font-category").val();

    var formData = new FormData();
    formData.append("action",     "spbwc_upload_custom_font");
    formData.append("nonce",      nonce);
    formData.append("font_file",  selectedFile);
    formData.append("font_name",  fontName);
    formData.append("category",   category);

    // Show progress
    $("#spbwc-upload-form").attr("hidden", true);
    $("#spbwc-upload-progress").removeAttr("hidden");

    $.ajax({
      url:         ajaxUrl,
      method:      "POST",
      data:        formData,
      processData: false,
      contentType: false,
      dataType:    "json",
    })
      .done(function (response) {
        if (response && response.success && response.data && response.data.font) {
          // Inject card
          var $grid  = $("#spbwc-custom-grid");
          var $empty = $grid.find("#spbwc-custom-empty");
          $empty.remove();

          var $card = buildCard(response.data.font);
          $grid.append($card);
          updateCount();

          // Collapse panel + reset
          $("#spbwc-upload-panel").attr("hidden", true);
          $("#spbwc-toggle-upload")
            .find(".dashicons")
            .removeClass("dashicons-minus")
            .addClass("dashicons-plus-alt2");
          resetUploadPanel();

          showSuccess(i18n.upload_success || "Font uploaded successfully!");
        } else {
          var msg = (response && response.data && response.data.message)
            ? response.data.message
            : (i18n.error_generic || "An error occurred.");
          showError(msg);
          // Back to form
          $("#spbwc-upload-progress").attr("hidden", true);
          $("#spbwc-upload-form").removeAttr("hidden");
        }
      })
      .fail(function () {
        showError(i18n.error_generic || "An error occurred. Please try again.");
        $("#spbwc-upload-progress").attr("hidden", true);
        $("#spbwc-upload-form").removeAttr("hidden");
      });
  }

  /* ── Delete ── */

  function doDelete(fontId, $card) {
    var msg = i18n.delete_confirm || "Delete this font? This cannot be undone.";
    var ask = window.spbwcDialog
      ? window.spbwcDialog.confirm({ message: msg, tone: "danger", okText: i18n.delete_ok || "Delete" })
      : Promise.resolve(window.confirm(msg));
    ask.then(function (confirmed) {
      if (!confirmed) return;
      doDeleteRequest(fontId, $card);
    });
  }

  function doDeleteRequest(fontId, $card) {
    $.ajax({
      url:      ajaxUrl,
      method:   "POST",
      dataType: "json",
      data: {
        action:  "spbwc_delete_custom_font",
        nonce:   nonce,
        font_id: fontId,
      },
    })
      .done(function (response) {
        if (response && response.success) {
          $card.remove();
          updateCount();
          // Show empty state if no cards left
          if ($("#spbwc-custom-grid .spbwc-custom-card").length === 0) {
            $("#spbwc-custom-grid").append(
              '<div class="spbwc-custom-empty" id="spbwc-custom-empty">' +
              '<span class="dashicons dashicons-media-default spbwc-custom-empty__icon" aria-hidden="true"></span>' +
              '<p>No custom fonts yet. Click "Upload Font" to add your first one.</p>' +
              "</div>"
            );
          }
        } else {
          var msg = (response && response.data && response.data.message)
            ? response.data.message
            : (i18n.error_generic || "An error occurred.");
          showError(msg);
        }
      })
      .fail(function () {
        showError(i18n.error_generic || "An error occurred. Please try again.");
      });
  }

  /* ── DOM event bindings ── */

  $(function () {

    // Toggle upload panel
    $("#spbwc-toggle-upload").on("click", function () {
      var $panel = $("#spbwc-upload-panel");
      var $icon  = $(this).find(".dashicons");
      if ($panel.attr("hidden") !== undefined) {
        $panel.removeAttr("hidden");
        $icon.removeClass("dashicons-plus-alt2").addClass("dashicons-minus");
      } else {
        $panel.attr("hidden", true);
        $icon.removeClass("dashicons-minus").addClass("dashicons-plus-alt2");
        resetUploadPanel();
      }
    });

    // File input change
    $("#spbwc-font-file").on("change", function () {
      var file = this.files && this.files[0];
      onFileSelected(file);
    });

    // Drag & drop
    var $dropZone = $("#spbwc-drop-zone");

    $dropZone.on("dragover dragenter", function (e) {
      e.preventDefault();
      e.stopPropagation();
      $dropZone.addClass("is-dragover");
    });

    $dropZone.on("dragleave dragend drop", function (e) {
      e.preventDefault();
      e.stopPropagation();
      $dropZone.removeClass("is-dragover");
    });

    $dropZone.on("drop", function (e) {
      var dt = e.originalEvent.dataTransfer;
      var file = dt && dt.files && dt.files[0];
      onFileSelected(file);
    });

    // Submit upload
    $("#spbwc-submit-font").on("click", function () {
      doUpload();
    });

    // Cancel upload
    $("#spbwc-cancel-upload").on("click", function () {
      resetUploadPanel();
      $("#spbwc-drop-zone").show();
      $("#spbwc-upload-form").attr("hidden", true);
    });

    // Delete font (delegated — cards added dynamically)
    $("#spbwc-custom-grid").on("click", ".spbwc-custom-card__delete", function (e) {
      e.stopPropagation();
      var fontId = $(this).data("font-id");
      var $card  = $(this).closest(".spbwc-custom-card");
      doDelete(fontId, $card);
    });

  });

}(jQuery));
