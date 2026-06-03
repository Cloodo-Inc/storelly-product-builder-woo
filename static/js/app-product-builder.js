"use strict";

var appConfig = {
  ready: false,
};

/**
 * Fabric toSVG() may throw when a fabric.Image has no backing DOM element
 * (hasCrop reads width from null). Embed the raster from toDataURL() so
 * frame_*_svg files remain valid for PDF/export pipelines.
 */
function spbwcFabricCanvasToSVGOrFallback(canvas, rasterDataUrl) {
  try {
    return canvas.toSVG();
  } catch (err) {
    if (
      typeof rasterDataUrl !== "string" ||
      rasterDataUrl.indexOf("data:") !== 0
    ) {
      return "";
    }
    var w = canvas && canvas.width ? canvas.width : 0;
    var h = canvas && canvas.height ? canvas.height : 0;
    if (!w || !h) {
      return "";
    }
    var href = rasterDataUrl
      .replace(/&/g, "&amp;")
      .replace(/"/g, "&quot;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;");
    return (
      '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="' +
      w +
      '" height="' +
      h +
      '"><image xlink:href="' +
      href +
      '" href="' +
      href +
      '" width="' +
      w +
      '" height="' +
      h +
      '" preserveAspectRatio="none" /></svg>'
    );
  }
}

var nbdpbApp = angular.module("nbdpbApp", []);
nbdpbApp.controller("nbpbCtrl", [
  "$scope",
  "FabricWindow",
  "NBDDataFactory",
  "$window",
  "$timeout",
  function ($scope, FabricWindow, NBDDataFactory, $window, $timeout) {
    $scope.isStartDesign = false;
    $scope.isDisplayOn = function (display) {
      // Treat undefined/null as "on" by default. The Storelly admin UI does
      // not always persist the `display` flag in pb_config views (only the
      // `image` attachment is saved), so the buyer-side check returned false
      // for every view and selectAttribute() skipped the entire image-load
      // chain. Default ON keeps behavior identical for views that DO set the
      // flag, and lets unflagged data render correctly.
      if (display === undefined || display === null || display === "") return true;
      return display == "on" || display == "1" || display === 1 || display === true;
    };
    $scope.onloadTemplate = false;
    $scope.init = function () {
      $scope.initSettings();
    };
    $scope.defaultStageStates = {
      showAdminTool: false,
    };
    $scope.initSettings = function () {
      $scope.settings = {};
      $scope.stages = [];
      $scope.side = [];
      $scope.resource = {
        views: [],
        components: [],
        values: {},
        showValue: false,
        currentComponent: 0,
        currentView: 0,
        jsonDesign: {},
        config: {},
        uploaded: [],
        currentColor: "#ff0000",
        colorOptions: {
          preferredFormat: "hex",
          flat: true,
          showButtons: true,
          showInput: true,
          containerClassName: "nbd-sp",
          clickoutFiresChange: false,
          chooseText: SPBWC_PB_CONFIG.i18n.choose,
          cancelText: SPBWC_PB_CONFIG.i18n.cancel,
        },
        design_output: {
          dimension_unit: "px",
          dpi: "300",
        },
      };
      if (angular.isDefined(nbOption.options.design_output)) {
        var _allowedUnits = ["cm", "in", "mm", "px"];
        var _u = nbOption.options.design_output.dimension_unit;
        $scope.resource.design_output.dimension_unit =
          _allowedUnits.indexOf(_u) !== -1 ? _u : "px";
        var _d = parseInt(nbOption.options.design_output.dpi, 10);
        $scope.resource.design_output.dpi = _d > 0 ? String(_d) : "300";
      }
      var uploaded = localStorage.getItem("nbpb_uploaded");
      if (uploaded) {
        $scope.resource.uploaded = JSON.parse(uploaded);
      }
      angular.copy(SPBWC_PB_CONFIG, $scope.settings);
      $scope.currentStage = 0;
      $scope.includeExport = [
        "itemId",
        "isLogo",
        "evented",
        "a_index",
        "sa_index",
      ];
      $scope.processProductSettings();
    };
    $scope.processProductSettings = function () {
      $scope.initValues(true);
      _.each($scope.resource.views, function (side, index) {
        $scope.stages[index] = {
          config: {},
          states: {},
          canvas: {},
        };
        var _state = $scope.stages[index].states;
        angular.copy($scope.defaultStageStates, _state);
      });
    };
    $scope.initValues = function (init, pro) {
      if (init) {
        angular.copy(nbOption.options.views, $scope.resource.views);
        $scope.resource.components = [];
        _.each(nbOption.options.fields, function (field, index) {
          if (
            field.nbpb_type == "nbpb_com" ||
            field.nbpb_type == "nbpb_text" ||
            field.nbpb_type == "nbpb_image"
          ) {
            var _field = {};
            angular.copy(field, _field);
            _field.currentSubAtrribute = 0;
            _field.currentConfig = 0;
            if (field.nbpb_type == "nbpb_text") {
              _field.currentContent = "";
              if (_field.general.nbpb_text_configs.allow_all_color == "n") {
                if (_field.general.nbpb_text_configs.colors.length > 0) {
                  _field.currentColor =
                    _field.general.nbpb_text_configs.colors[0].color;
                } else {
                  _field.currentColor = "#000000";
                }
              } else {
                _field.currentColor = "#000000";
              }
              _field.currentFontId = "";
              if (_field.general.nbpb_text_configs.allow_font_family == "y") {
                if (_field.general.nbpb_text_configs.allow_all_font == "n") {
                  if (
                    angular.isUndefined(
                      _field.general.nbpb_text_configs.custom_fonts
                    )
                  )
                    _field.general.nbpb_text_configs.custom_fonts = [];
                  if (
                    angular.isUndefined(
                      _field.general.nbpb_text_configs.google_fonts
                    )
                  )
                    _field.general.nbpb_text_configs.google_fonts = [];
                  if (
                    _field.general.nbpb_text_configs.custom_fonts.length > 0
                  ) {
                    var i =
                      _field.general.nbpb_text_configs.custom_fonts.length - 1;
                    while (i >= 0) {
                      if (
                        $scope.settings.custom_fonts[
                          _field.general.nbpb_text_configs.custom_fonts[i]
                        ]
                      ) {
                        _field.currentFontId =
                          "c" +
                          _field.general.nbpb_text_configs.custom_fonts[i];
                      }
                      i--;
                    }
                    if (
                      _field.currentFontId == "" &&
                      _field.general.nbpb_text_configs.google_fonts.length > 0
                    ) {
                      var i =
                        _field.general.nbpb_text_configs.google_fonts.length -
                        1;
                      while (i >= 0) {
                        if (
                          $scope.settings.google_fonts[
                            _field.general.nbpb_text_configs.google_fonts[i]
                          ]
                        ) {
                          _field.currentFontId =
                            "g" +
                            _field.general.nbpb_text_configs.google_fonts[i];
                        }
                        i--;
                      }
                    }
                  } else if (
                    _field.general.nbpb_text_configs.google_fonts.length > 0
                  ) {
                    var i =
                      _field.general.nbpb_text_configs.google_fonts.length - 1;
                    while (i >= 0) {
                      if (
                        $scope.settings.google_fonts[
                          _field.general.nbpb_text_configs.google_fonts[i]
                        ]
                      ) {
                        _field.currentFontId =
                          "g" +
                          _field.general.nbpb_text_configs.google_fonts[i];
                      }
                      i--;
                    }
                  }
                } else {
                  if (SPBWC_PB_CONFIG.fonts.length > 0) {
                    var prefix =
                      SPBWC_PB_CONFIG.fonts[0].type == "google" ? "g" : "c";
                    _field.currentFontId = prefix + "0";
                  } else {
                    _field.currentFontId = "";
                  }
                }
              }
            }
            $scope.resource.components.push(_field);
            if (_field.nbpb_type == "nbpb_com") {
              _field.current_pb_configs = $scope.getComponentConfigs(_field);
            }
          }
        });
      }
      $scope.resource.values = {};
      angular.copy(nbOption.nbd_fields, $scope.resource.values);
      _.each($scope.resource.values, function (field, index) {
        var component = $scope.getComponentById(index);
        if (component) {
          component.enable = field.enable;
          if (component.nbpb_type == "nbpb_text") {
            component.currentContent = field.value;
          }
          if (component.nbpb_type == "nbpb_com") {
            var configIndex = $scope.getCurrentConfig(index, field.value, field.sub_value);
            if (angular.isDefined(configIndex)) {
                component.currentConfig = configIndex;
            } else {
                component.currentConfig = field.value;
            }
          }
        }
      });
      if (!pro) $scope.resource.showValue = false;
      if (!init) {
        _.each($scope.stages, function (stage, sIndex) {
          stage.canvas.forEachObject(function (obj) {
            var itemId = obj.get("itemId");
            if (itemId) {
              var component = $scope.getComponentById(itemId);
              if (component) {
                if (!component.enable) {
                  obj.set("visible", false);
                } else {
                  obj.set("visible", true);
                  if (component.nbpb_type == "nbpb_text") {
                    obj.set("text", component.currentContent);
                  }
                  if (component.nbpb_type == "nbpb_com") {
                    var pb_config = component.current_pb_configs[component.currentConfig];
                    if (pb_config && pb_config[sIndex] && $scope.isDisplayOn(pb_config[sIndex].display)) {
                      if (obj.get("a_index") != pb_config.a_index || obj.get("sa_index") != pb_config.sa_index) {
                        fabric.Image.fromURL(
                          pb_config[sIndex].image_url,
                          function (img) {
                            obj.setElement(img.getElement());
                            obj.set({
                              a_index: pb_config.a_index,
                              sa_index: pb_config.sa_index,
                            });
                            stage.canvas.renderAll();
                          },
                          { crossOrigin: "anonymous" }
                        );
                      }
                    } else {
                      obj.set("visible", false);
                    }
                  }
                }
              }
            }
          });
          stage.canvas.renderAll();
        });
      }
    };
    $scope.initStageSetting = function (id) {
      $scope.setStageDimension(id);
      $scope.renderStage(id);
      $scope.updateApp();
    };
    $scope.setStageDimension = function (id) {
      id = angular.isDefined(id) ? id : $scope.currentStage;
      var _stage = $scope.stages[id];
      $timeout(function () {
        var viewPort = $scope.calcViewport();
        var base_width =
            angular.isDefined($scope.resource.views[id].base_width) &&
            $scope.resource.views[id].base_width != ""
              ? parseFloat($scope.resource.views[id].base_width)
              : viewPort.width,
          base_height =
            angular.isDefined($scope.resource.views[id].base_height) &&
            $scope.resource.views[id].base_height != ""
              ? parseFloat($scope.resource.views[id].base_height)
              : viewPort.height;
        var designViewPort = $scope.fitRectangle(
          viewPort.width,
          viewPort.height,
          base_width,
          base_height,
          true
        );
        _stage["canvas"].setDimensions({
          width: designViewPort.width,
          height: designViewPort.height,
        });
        _stage.config.width = designViewPort.width;
        _stage.config.height = designViewPort.height;
        _stage.config.top = designViewPort.top;
        _stage.config.left = designViewPort.left;
        if (angular.isUndefined($scope.resource.config.lastViewport)) {
          $scope.resource.config.lastViewport = viewPort;
        }
      });
    };
    $scope.showAttribute = function (index) {
      $scope.resource.showValue = true;
      if (angular.isDefined(index)) {
        $scope.resource.currentComponent = index;
        var field = $scope.resource.components[index],
          viewLen = nbOption.options.views.length;
        $scope.resource.currentComponentObj = field;
        if (field.nbpb_type == "nbpb_com" && (!angular.isDefined(field.current_pb_configs) || field.current_pb_configs.length == 0)) {
          field.current_pb_configs = $scope.getComponentConfigs(field);
        }
        var item = $scope.getLayerById(field.id);
        if (SPBWC_PB_CONFIG.is_creating_task == 1) {
          var _canvas = $scope.stages[$scope.currentStage].canvas;
          if (item) {
            _canvas.setActiveObject(item);
            _canvas.renderAll();
          }
        }
        if (item) {
          if (field.nbpb_type == "nbpb_com") {
            $scope.resource.components[index].currentConfig =
              $scope.getCurrentConfig(
                $scope.resource.components[index].id,
                item.a_index,
                item.sa_index
              );
          } else if (field.nbpb_type == "nbpb_text") {
            var font = $scope.getFontByAlias(item.fontFamily);
            if (font) {
              $scope.resource.components[index].currentFontId =
                (font.type == "google" ? "g" : "c") + font.id;
            }
            $scope.resource.components[index].currentFontFamily =
              item.fontFamily;
            $scope.resource.components[index].currentColor = item.fill;
            $scope.resource.components[index].currentContent = item.text;
          }
        }
      }
    };
    $scope.saveLayer = function () {
      $scope.resource.showValue = false;
      $scope.deactiveAllLayer();
    };
    $scope.selectAttribute = function (index) {
      var currentComponent =
        $scope.resource.components[$scope.resource.currentComponent];
      if (!currentComponent) {
        return;
      }
      currentComponent.currentConfig = index;
      var statusImages = [],
        firstView = true;
      function isLoadedAllImages() {
        var check = true;
        _.each(statusImages, function (status, index) {
          var _status = angular.isDefined(status) ? status : true;
          check = check && _status;
        });
        return check;
      }
      _.each(
        currentComponent.current_pb_configs[index],
        function (view, viewIndex) {
          if ($scope.isDisplayOn(view.display)) {
            statusImages[viewIndex] = false;
          }
        }
      );
      var currentStage = -1;
      _.each(
        currentComponent.current_pb_configs[index],
        function (view, viewIndex) {
          var _stage = $scope.stages[viewIndex];
          if (!_stage) {
            return;
          }
          var _canvas = _stage.canvas,
            _item = $scope.getLayerById(currentComponent.id, viewIndex);
          if (!_canvas) {
            return;
          }
          if ($scope.isDisplayOn(view.display)) {
            if (currentStage == -1) {
              currentStage = viewIndex;
            }
            if (firstView) {
              jQuery(".nbpb-stage-loading").addClass("nbdpb-show");
              firstView = false;
            }
            if (_item && typeof _item.getElement === "function") {
              var el = _item.getElement();
              if (el && el.tagName === "IMG" && "src" in el) {
                el.src = view.image_url;
              }
            }
            fabric.Image.fromURL(
              view.image_url,
              function (obj) {
                if (_item) {
                  _item.setElement(obj.getElement());
                  _item.set({
                    visible: true,
                    dirty: true,
                    width: obj.width,
                    height: obj.height,
                    scaleX: (_item.scaleX * _item.width) / obj.width,
                    scaleY: (_item.scaleY * _item.height) / obj.height,
                    a_index: currentComponent.current_pb_configs[index].a_index,
                    sa_index:
                      currentComponent.current_pb_configs[index].sa_index,
                  });
                  _item.setCoords();
                } else {
                  var max_width = _canvas.width,
                    max_height = _canvas.height,
                    new_width = max_width;
                  new_width = max_width;
                  var width_ratio = new_width / obj.width,
                    new_height = obj.height * width_ratio;
                  if (new_height > max_height) {
                    new_height = max_height;
                    var height_ratio = new_height / obj.height;
                    new_width = obj.width * height_ratio;
                  }
                  obj.set({
                    fill: "#ff0000",
                    scaleX: new_width / obj.width,
                    scaleY: new_height / obj.height,
                    itemId: currentComponent.id,
                    a_index: currentComponent.current_pb_configs[index].a_index,
                    sa_index:
                      currentComponent.current_pb_configs[index].sa_index,
                  });
                  _canvas.add(obj);
                  _canvas.viewportCenterObject(obj);
                  if (SPBWC_PB_CONFIG.is_creating_task == 1) {
                    _canvas.setActiveObject(obj);
                  }
                }
                statusImages[viewIndex] = true;
                if (isLoadedAllImages())
                  jQuery(".nbpb-stage-loading").removeClass("nbdpb-show");
                _canvas.renderAll();
              },
              { crossOrigin: "anonymous" }
            );
          } else {
            if (_item) {
              _item.set({
                visible: false,
              });
              _canvas.renderAll();
            }
          }
        }
      );
      if (
        currentComponent.current_pb_configs &&
        currentComponent.current_pb_configs[index]
      ) {
        if (
          currentComponent.current_pb_configs[index][$scope.currentStage] &&
          !$scope.isDisplayOn(currentComponent.current_pb_configs[index][$scope.currentStage].display) &&
          currentStage != -1
        ) {
          appConfig.slider.activeItemByIndex(currentStage);
        }
        $scope.resource.values[currentComponent.id].value =
          "" + currentComponent.current_pb_configs[index].a_index;
        $scope.resource.values[currentComponent.id].sub_value =
          "" + currentComponent.current_pb_configs[index].sa_index;
      }
      jQuery(document).triggerHandler("update_pcpb_options_from_builder", {
        nbd_fields: $scope.resource.values,
        pro: true,
      });
    };
    $scope.selectColor = function (color) {
      if (
        angular.isDefined(color) &&
        $scope.resource.components[$scope.resource.currentComponent]
          .currentColor != color
      ) {
        $scope.resource.components[
          $scope.resource.currentComponent
        ].currentColor = color;
        $scope.updateText();
      }
    };
    $scope.updateText = function () {
      var currentComponent =
        $scope.resource.components[$scope.resource.currentComponent];
      currentComponent.currentFontFamily = "Arial";
      var font;
      if (currentComponent.currentFontId != "") {
        var type = currentComponent.currentFontId.slice(0, 1),
        id = currentComponent.currentFontId.slice(1);
        if (type == "c") {
          font = $scope.getFontByIdAndType(id, "ttf");
        } else {
          font = $scope.getFontByIdAndType(id, "google");
        }
        if (font) {
          $scope.insertFontScript(font);
          currentComponent.currentFontFamily = font.alias;
        }
      }
      var font = new FontFaceObserver(currentComponent.currentFontFamily);
      if (currentComponent.general.text_option.max != "") {
        var maxlen = parseInt(currentComponent.general.text_option.max);
        if (currentComponent.currentContent.length > maxlen) {
          currentComponent.currentContent =
            currentComponent.currentContent.slice(0, maxlen - 1);
        }
      }
      font.load(currentComponent.currentContent).then(
        function () {
          fabric.util.clearFabricFontCache();
          $scope.addText();
        },
        function () {
          $scope.addText();
        }
      );
      $scope.resource.values[currentComponent.id].value =
        currentComponent.currentContent;
      jQuery(document).triggerHandler("update_pcpb_options_from_builder", {
        nbd_fields: $scope.resource.values,
        pro: true,
      });
    };
    $scope.insertFontScript = function (font) {
      if (!jQuery("#nbpb" + font.id).length) {
        if (font.type == "google") {
          jQuery("head").append(
            '<link id="nbpb' +
              font.id +
              '" href="https://fonts.googleapis.com/css?family=' +
              font.alias.replace(/\s/gi, "+") +
              ':400,400i,700,700i" rel="stylesheet" type="text/css">'
          );
        } else {
          var css = "<style type='text/css' id='nbpb" + font.id + "' >";
          _.each(font.file, function (file, index) {
            var font_url = file;
            if (!(file.indexOf("http") > -1))
              font_url = SPBWC_PB_CONFIG["font_url"] + file;
            css += "@font-face {font-family: '" + font.alias + "';";
            css += "src: local('\u263a'), ";
            css += "url('" + font_url + "') format('truetype');";
            switch (index) {
              case "r":
                css += "font-weight: normal;font-style: normal;";
                break;
              case "b":
                css += "font-weight: bold;font-style: normal;";
                break;
              case "i":
                css += "font-weight: normal;font-style: italic;";
                break;
              case "bi":
                css += "font-weight: bold;font-style: italic;";
                break;
            }
            css += "}";
          });
          css += "</style>";
          jQuery("head").append(css);
        }
      }
    };
    $scope.getFontByIdAndType = function (id, type) {
      var _font = null;
      var fonts = SPBWC_PB_CONFIG.fonts;
      if (typeof fonts === 'string') {
        try {
          fonts = JSON.parse(fonts);
        } catch (e) {
          return null;
        }
      }
      _.each(fonts, function (font) {
        if (parseInt(font.id) === parseInt(id) && font.type.toLowerCase() === type.toLowerCase()) {
          _font = font;
        }
      });
      return _font;
    };
    $scope.getFontByAlias = function (alias) {
      var _font;
      _.each(SPBWC_PB_CONFIG.fonts, function (font, index) {
        if (font.alias == alias) {
          _font = font;
        }
      });
      return _font;
    };
    $scope.addText = function () {
      var currentComponent =
          $scope.resource.components[$scope.resource.currentComponent],
        views = currentComponent.general.nbpb_text_configs.views;
      _.each(views, function (view, viewIndex) {
        var item = $scope.getLayerById(currentComponent.id, viewIndex);
        if ($scope.isDisplayOn(view.display)) {
          var _canvas = $scope.stages[viewIndex].canvas;
          if (item) {
            item.set({
              text: currentComponent.currentContent,
              visible: true,
              fontFamily:
                currentComponent.general.nbpb_text_configs.allow_font_family ==
                "y"
                  ? currentComponent.currentFontFamily
                  : item.fontFamily,
              fill:
                currentComponent.general.nbpb_text_configs.allow_change_color ==
                "y"
                  ? currentComponent.currentColor
                  : item.fill,
            });
          } else {
            _canvas.add(
              new FabricWindow["Textbox"](currentComponent.currentContent, {
                itemId: currentComponent.id,
                fontFamily: currentComponent.currentFontFamily,
                fontSize: 19,
                fill: currentComponent.currentColor,
                textAlign: "center",
              })
            );
            _canvas.viewportCenterObject(
              _canvas.item(_canvas.getObjects().length - 1)
            );
          }
        } else {
          if (item) {
            item.set({
              visible: false,
            });
          }
        }
        _canvas.renderAll();
      });
    };
    $scope.getLayerById = function (itemId, stage) {
      var _canvas = null;
      if (angular.isDefined(stage)) {
        _canvas = $scope.stages[stage].canvas;
      } else {
        _canvas = $scope.stages[$scope.currentStage].canvas;
      }
      var _object = null;
      _canvas.forEachObject(function (obj, index) {
        if (obj.get("itemId") == itemId) _object = obj;
      });
      return _object;
    };
    $scope.getLayerIndex = function (itemId, stage) {
      var _canvas;
      if (angular.isDefined(stage)) {
        _canvas = $scope.stages[stage].canvas;
      } else {
        _canvas = $scope.stages[$scope.currentStage].canvas;
      }
      var _index;
      _canvas.forEachObject(function (obj, index) {
        if (obj.get("itemId") == itemId) _index = index;
      });
      return _index;
    };
    $scope.deactiveAllLayer = function (stage_id) {
      stage_id = stage_id ? stage_id : $scope.currentStage;
      $scope.stages[stage_id]["canvas"].discardActiveObject();
      $scope.renderStage();
      $scope.updateApp();
    };
    $scope.clearAllStages = function () {
      _.each($scope.stages, function (stage, index) {
        stage.canvas.clear();
        stage.canvas.renderAll();
      });
    };
    $scope.renderStage = function (stage_id) {
      stage_id = stage_id ? stage_id : $scope.currentStage;
      $scope.stages[stage_id]["canvas"].calcOffset();
      $scope.stages[stage_id]["canvas"].requestRenderAll();
    };
    $scope.updateApp = function () {
      if (
        $scope.$root.$$phase !== "$apply" &&
        $scope.$root.$$phase !== "$digest"
      )
        $scope.$apply();
    };
    $scope.setStackPosition = function (command, _item) {
      var item = _item
        ? _item
        : $scope.stages[$scope.currentStage]["canvas"].getActiveObject();
      switch (command) {
        case "bring-front":
          item.bringToFront();
          $scope.setStackLayerAlwaysOnTop();
          break;
        case "bring-forward":
          item.bringForward();
          break;
        case "send-backward":
          item.sendBackwards();
          break;
        case "send-back":
          item.sendToBack();
          break;
        default:
          var index = parseInt(command);
          item.moveTo(index);
      }
      $scope.renderStage($scope.currentStage);
    };
    $scope.calcViewport = function () {
      var _width = jQuery(".design-stages").width(),
        _height = jQuery(".design-stages").height();
      return { width: _width, height: _height };
    };
    $scope.makeblob = function (dataURL) {
      var BASE64_MARKER = ";base64,";
      if (dataURL.indexOf(BASE64_MARKER) == -1) {
        var parts = dataURL.split(",");
        var contentType = parts[0].split(":")[1];
        var raw = decodeURIComponent(parts[1]);
        return new Blob([raw], { type: contentType });
      }
      var parts = dataURL.split(BASE64_MARKER);
      var contentType = parts[0].split(":")[1];
      var raw = window.atob(parts[1]);
      var rawLength = raw.length;
      var uInt8Array = new Uint8Array(rawLength);
      for (var i = 0; i < rawLength; ++i) {
        uInt8Array[i] = raw.charCodeAt(i);
      }
      return new Blob([uInt8Array], { type: contentType });
    };
    /* Update the loader card produced by views/product-builder/wrapper.php — the
     * label drives the buyer's perception of progress, the bar grows through the
     * fixed phases below. No-op if the elements aren't present (e.g. inside the
     * creating-task wizard which uses a different loader). */
    $scope.setBuilderProgress = function (text, percent) {
      var lab = document.querySelector('[data-spbwc-loader-label]');
      var bar = document.querySelector('[data-spbwc-loader-fill]');
      if (lab && typeof text === 'string') { lab.textContent = text; }
      if (bar && typeof percent === 'number') { bar.style.width = Math.max(0, Math.min(100, percent)) + '%'; }
    };
    /* Lightweight timing — logs each phase of the "Done" flow to the console so we
     * can see where wall-clock time is actually going. Pair with server-side
     * `error_log` profiling in spbwc_save_product_builder_design (WP_DEBUG or
     * SPBWC_PB_PROFILE_SAVE). */
    var spbwcSaveT0 = 0, spbwcSavePrev = 0;
    $scope.spbwcMarkSave = function (label) {
      var now = (window.performance && performance.now) ? performance.now() : Date.now();
      var step = (now - spbwcSavePrev) | 0, total = (now - spbwcSaveT0) | 0;
      try { console.log('[SPBWC save] %s  step=%dms  total=%dms', label, step, total); } catch (e) {}
      spbwcSavePrev = now;
    };
    $scope.saveData = function () {
      var i18n = (SPBWC_PB_CONFIG && SPBWC_PB_CONFIG.i18n) || {};
      spbwcSaveT0 = (window.performance && performance.now) ? performance.now() : Date.now();
      spbwcSavePrev = spbwcSaveT0;
      $scope.spbwcMarkSave('saveData() start');
      $scope.toggleAppLoading();
      $scope.setBuilderProgress(i18n.preparing_design || 'Preparing your design…', 5);
      jQuery(".pcpb-custom-design").empty().hide();
      var totalStages = ($scope.stages && $scope.stages.length) || 1;
      /* saveDesign() per-stage callback: drives 5% → 45% across N stages. */
      $scope.onStageProcessed = function (done) {
        var pct = 5 + Math.round((done / totalStages) * 40);
        var tmpl = i18n.preparing_view || 'Preparing view %1 of %2…';
        $scope.setBuilderProgress(tmpl.replace('%1', done).replace('%2', totalStages), pct);
      };
      /* saveDesign now returns a Promise — toBlob() is async, so we await it. */
      Promise.resolve($scope.saveDesign()).then(function () {
        $scope.spbwcMarkSave('saveDesign() done (canvas → blobs)');
        $scope.setBuilderProgress(i18n.uploading || 'Uploading your design…', 50);
        $scope.resource.config.views = $scope.resource.views;
        $scope.resource.config.viewport = $scope.calcViewport();
        var dataObj = {};
        dataObj.design = new Blob([JSON.stringify($scope.resource.jsonDesign)], {
          type: "application/json",
        });
        _.each($scope.stages, function (stage, index) {
          var key = "frame_" + index;
          var svgKey = "frame_" + index + "_svg";
          /* Prefer the Blob produced by toBlob() (no base64 decode), fall back to
           * makeblob(dataURL) for the older sync path when toBlob isn't available. */
          dataObj[key] = stage.designBlob || $scope.makeblob(stage.design);
          dataObj[svgKey] = new Blob([stage.svg], { type: "image/svg+xml" });
        });
        ["pcpb_cart_item_key", "is_creating_task", "oid"].forEach(function (key) {
          dataObj[key] = SPBWC_PB_CONFIG[key];
        });
        var $prcpbFolder = jQuery(".variations_form, form.cart").find(
          'input[name="prcpb-folder"]'
        );
        if ($prcpbFolder.length && $prcpbFolder.val()) {
          dataObj.prcpb_folder = $prcpbFolder.val();
        }
        dataObj.config = new Blob([JSON.stringify($scope.resource.config)], {
          type: "application/json",
        });
        dataObj.used_font = new Blob(
          [JSON.stringify($scope.resource.used_font)],
          {
            type: "application/json",
          }
        );
        dataObj.design_output = new Blob(
          [JSON.stringify($scope.resource.design_output)],
          {
            type: "application/json",
          }
        );
        /* From here the server takes over (rasterize + composite + write files). */
        $scope.spbwcMarkSave('FormData built; about to POST');
        $scope.setBuilderProgress(i18n.generating_preview || 'Generating preview…', 80);
        var action = "spbwc_save_product_builder_design";
        NBDDataFactory.get(action, dataObj, function (data) {
        $scope.spbwcMarkSave('server response received');
        data = JSON.parse(data);
        if (data.flag == "success") {
          $scope.setBuilderProgress(i18n.done || 'Done', 100);
          if ($scope.settings.is_creating_task == 1) {
            if ($scope.settings.redirect_url != "")
              window.location = $scope.settings.redirect_url;
          } else {
            /* small delay so the user catches "Done" before the overlay fades. */
            setTimeout(function () { $scope.toggleAppLoading(); }, 220);
            jQuery(".pcpb-custom-design").empty().show();
            _.each(data.image, function (image) {
              image += "?t=" + Math.random();
              var item =
                '<div class="item">' +
                '<img src="' +
                image +
                '" alt="Custom Design"/>' +
                "</div>";
              jQuery(".pcpb-custom-design").append(item);
            });
            var $form = jQuery(".variations_form, form.cart");
            if ($form.find('input[name="prcpb-folder"]').length) {
              $form.find('input[name="prcpb-folder"]').val(data.folder);
            } else {
              $form.append(
                '<input type="hidden" name="prcpb-folder" value="' +
                  data.folder +
                  '" />'
              );
            }
            jQuery(document).triggerHandler(
              "update_product_image_from_builder",
              {
                image_link: data.image[0],
                image_srcset: data.image[0],
                full_src: data.image[0],
                full_src_w: $scope.stages[0].config.width,
                full_src_h: $scope.stages[0].config.height,
                image_sizes: [
                  $scope.stages[0].config.width,
                  $scope.stages[0].config.height,
                ],
                image_title: "",
                image_alt: "",
                image_caption: "",
              }
            );
            nbOption.design_stored = 1;
            jQuery(".close-popup").triggerHandler("click");
          }
        } else {
          $scope.toggleAppLoading();
          alert(SPBWC_PB_CONFIG.i18n.can_not_save_design);
        }
        });   // close NBDDataFactory.get callback
      });     // close Promise.resolve(saveDesign).then(...)
    };
    /* saveDesign: now async (returns Promise) — canvas.toBlob() is async, so we
     * gather all stages' Blobs via Promise.all. The Blob path avoids the sync
     * canvas.toDataURL() + base64 → ArrayBuffer roundtrip that was blocking the
     * main thread per stage. SVG still uses canvas.toSVG() (the rare fallback
     * path lazily encodes a dataURL only if toSVG() throws). Older browsers /
     * fabric versions without toCanvasElement/toBlob fall back to the sync path. */
    $scope.saveDesign = function () {
      var used_font = [];
      var stages = $scope.stages || [];
      var promises = stages.map(function (stage, index) {
        return new Promise(function (resolve) {
          $scope.deactiveAllLayer(index);
          var _canvas = stage.canvas;
          $scope.renderStage(index);
          $scope.resource.jsonDesign[index] = _canvas.toJSON($scope.includeExport);
          _canvas.getObjects().forEach(function (obj) {
            if (["i-text", "text", "textbox", "curvedText"].indexOf(obj.type) > -1) {
              if (!_.filter(used_font, ["alias", obj.fontFamily]).length) {
                used_font.push($scope.getFontInfo(obj.fontFamily));
              }
            }
          });
          var bufferCanvas = (typeof _canvas.toCanvasElement === 'function')
            ? _canvas.toCanvasElement(1)
            : (_canvas.lowerCanvasEl || null);
          var done = function () {
            if (typeof $scope.onStageProcessed === 'function') {
              $scope.onStageProcessed(index + 1);
            }
            resolve();
          };
          if (bufferCanvas && typeof bufferCanvas.toBlob === 'function') {
            bufferCanvas.toBlob(function (blob) {
              stage.designBlob = blob;
              try {
                stage.svg = _canvas.toSVG();
              } catch (e) {
                var raster = bufferCanvas.toDataURL ? bufferCanvas.toDataURL() : '';
                stage.svg = spbwcFabricCanvasToSVGOrFallback(_canvas, raster);
              }
              done();
            }, 'image/png');
          } else {
            /* Sync fallback for environments without canvas.toBlob(). */
            var raster = _canvas.toDataURL();
            stage.design = raster;
            stage.svg = spbwcFabricCanvasToSVGOrFallback(_canvas, raster);
            done();
          }
        });
      });
      $scope.resource.used_font = used_font;
      return Promise.all(promises);
    };
    $scope.getFontInfo = function (alias) {
      var font = _.filter(SPBWC_PB_CONFIG.fonts, { alias: alias })[0],
        _font = angular.copy(font, _font);
      if (_font) {
        _font.file = { r: font.file.r };
        _font.file.i = angular.isDefined(font.file.i) ? font.file.i : 0;
        _font.file.b = angular.isDefined(font.file.b) ? font.file.b : 0;
        _font.file.bi = angular.isDefined(font.file.bi) ? font.file.bi : 0;
      } else {
        _font = {
          name: "Roboto",
          alias: "Roboto",
          file: { r: 1, b: 1, i: 1, bi: 1 },
          cat: ["99"],
          type: "google",
          subset: "latin",
        };
      }
      return _font;
    };
    $scope.onObjectAdded = function (id, options) {
      /* Reindex layers */
      if (
        SPBWC_PB_CONFIG.is_creating_task != 1 &&
        angular.isUndefined($scope.settings.pre_builder.design)
      ) {
        _.each($scope.stages, function (stage, sIndex) {
          var layerIndex = 0,
            _canvas = stage.canvas;
          _.each($scope.resource.components, function (component, cIndex) {
            var _obj,
              itemId = component.id;
            _canvas.forEachObject(function (obj, index) {
              if (obj.get("itemId") == itemId) {
                _obj = obj;
              }
            });
            if (_obj) {
              _obj.moveTo(layerIndex);
              layerIndex++;
            }
          });
        });
      }
    };
    $scope.onMouseOver = function (id, options) {
      var _stage = $scope.stages[$scope.currentStage],
        _canvas = _stage["canvas"],
        item = options.target;
      if (item) {
        item.set("opacity", "0.9");
      }
      _canvas.renderAll();
    };
    $scope.onMouseOut = function (id, options) {
      var _stage = $scope.stages[$scope.currentStage],
        _canvas = _stage["canvas"],
        item = options.target,
        itemId = "",
        proAttr = null;
      if (item) {
        item.set("opacity", "1");
      }
      _canvas.renderAll();
    };
    $scope.onMouseDown = function (id, options) {
      var _stage = $scope.stages[$scope.currentStage],
        _canvas = _stage["canvas"],
        item = options.target;
      if (item) {
        if (angular.isDefined(item.get("itemId"))) {
          var itemId = item.get("itemId");
          _.each($scope.resource.components, function (component, index) {
            if (component.id == itemId) {
              $scope.showAttribute(index);
              $scope.updateApp();
            }
          });
        }
      } else {
        $scope.saveLayer();
      }
    };
    $scope.onSelectionCreated = function (id, options) {
      if (options.target) {
        var item = options.target,
          _stage = $scope.stages[$scope.currentStage];
        _stage.states.scaleX = item.get("scaleX");
        _stage.states.scaleY = item.get("scaleY");
        _stage.states.angle = item.get("angle");
        if (item.type == "textbox") {
          var font = $scope.getFontByAlias(item.fontFamily);
          if (font) {
            $scope.resource.components[
              $scope.resource.currentComponent
            ].currentFontId = (font.type == "google" ? "g" : "c") + font.id;
          }
          $scope.resource.components[
            $scope.resource.currentComponent
          ].currentFontFamily = item.fontFamily;
          $scope.resource.components[
            $scope.resource.currentComponent
          ].currentColor = item.fill;
          $scope.resource.components[
            $scope.resource.currentComponent
          ].currentContent = item.text;
        }
        _stage.states.showAdminTool = true;
        $scope.updateApp();
      }
    };
    $scope.onSelectionCleared = function (id, options) {
      var _stage = $scope.stages[$scope.currentStage];
      _stage.states.showAdminTool = false;
      $scope.updateApp();
    };
    $scope.updateLayerAttribute = function (type, value) {
      if (!appConfig.ready) return;
      var item = $scope.stages[$scope.currentStage]["canvas"].getActiveObject();
      if (!item) return;
      var ob = {};
      ob[type] = value;
      $scope.stages[$scope.currentStage].states[type] = value;
      item.set(ob);
      $scope.renderStage();
    };
    var _window = angular.element($window);
    _window.on("resize", function () {
      $scope.reCalcViewPort();
    });
    $scope.reCalcViewPort = function () {
      var _stages = $scope.stages;
      jQuery(".nbdpb-carousel").nbdpbCarousel();
      _.each(_stages, function (stage, index) {
        $scope.setStageDimension(index);
      });
      $scope.resizeStages($scope.resource.config.lastViewport);
    };
    $scope.resizeStages = function (viewport) {
      _.each($scope.stages, function (stage, index) {
        var currentViewport = $scope.calcViewport();
        var newFitRec = $scope.fitRectangle(
          viewport.width,
          viewport.height,
          stage.config.width,
          stage.config.height,
          true
        );
        var oldFitRec = $scope.fitRectangle(
          currentViewport.width,
          currentViewport.height,
          stage.config.width,
          stage.config.height,
          true
        );
        var factor = oldFitRec.width / newFitRec.width;
        if (factor != 1) {
          stage.canvas.forEachObject(function (obj) {
            var scaleX = obj.scaleX,
              scaleY = obj.scaleY,
              left = obj.left,
              top = obj.top,
              tempScaleX = scaleX * factor,
              tempScaleY = scaleY * factor,
              tempLeft = left * factor,
              tempTop = top * factor;
            obj.scaleX = tempScaleX;
            obj.scaleY = tempScaleY;
            obj.left = tempLeft;
            obj.top = tempTop;
            obj.setCoords();
          });
          stage.canvas.calcOffset();
          $scope.renderStage(index);
        }
        if (index == $scope.stages.length - 1) {
          $scope.resource.config.lastViewport = currentViewport;
        }
      });
    };
    $scope.$on("canvas:created", function (event, id, last) {
      /* init canvas parameters */
      $scope.initStageSetting(id);
      var _canvas = $scope.stages[id].canvas;
      /* Listen canvas events */
      _canvas.on("mouse:over", function (options) {
        $scope.onMouseOver(id, options);
      });
      _canvas.on("mouse:out", function (options) {
        $scope.onMouseOut(id, options);
      });
      _canvas.on("mouse:down", function (options) {
        $scope.onMouseDown(id, options);
      });
      _canvas.on("object:added", function (options) {
        $scope.onObjectAdded(id, options);
      });
      _canvas.on("selection:created", function (options) {
        $scope.onSelectionCreated(id, options);
      });
      _canvas.on("selection:cleared", function (options) {
        $scope.onSelectionCleared(id, options);
      });
      /* Load template after render canvas */
      if (last == "1") {
        appConfig.ready = true;
        $scope.loadPreBuilder();
      }
    });
    $scope.$on("component:mouseover", function (event, id) {
      if (!appConfig.ready) return;
      var _canvas = $scope.stages[$scope.currentStage].canvas;
      var item = $scope.getLayerById(id);
      if (item) {
        item.set("opacity", "0.9");
        _canvas.renderAll();
      }
    });
    $scope.$on("component:mouseout", function (event, id) {
      if (!appConfig.ready) return;
      var _canvas = $scope.stages[$scope.currentStage].canvas;
      var item = $scope.getLayerById(id);
      if (item) {
        item.set("opacity", "1");
        _canvas.renderAll();
      }
    });
    $scope.loadPreBuilder = function () {
      $timeout(function () {
        if (angular.isDefined($scope.settings.pre_builder.design)) {
          $scope.insertTemplate(
            $scope.settings.pre_builder.design,
            $scope.settings.pre_builder.config
          );
        }
      });
    };
    $scope.insertTemplate = function (design, config) {
      $scope.onloadTemplate = true;
      var stageIndex = 0,
        viewport = config.viewport;
      $scope.toggleAppLoading();
      function loadStage(stageIndex) {
        var stage = $scope.stages[stageIndex],
          _canvas = stage["canvas"],
          layerIndex = 0;
        _canvas.clear();
        var objects = [];
        if (angular.isDefined(design[stageIndex]))
          objects = design[stageIndex].objects;
        function loadLayer(layerIndex) {
          function continueLoadLayer() {
            layerIndex++;
            if (objects.length != 0 && layerIndex < objects.length) {
              loadLayer(layerIndex);
            } else {
              stageIndex++;
              if (stageIndex < $scope.stages.length) {
                loadStage(stageIndex);
              } else {
                _.each($scope.stages, function (_stage, index) {
                  $scope.deactiveAllLayer();
                  $scope.renderStage(index);
                  $timeout(function () {
                    $scope.deactiveAllLayer();
                    $scope.renderStage(index);
                    if (index == $scope.stages.length - 1) {
                      $scope.resizeStages(viewport);
                      $scope.toggleAppLoading();
                      $scope.onloadTemplate = false;
                    }
                  });
                });
              }
            }
          }
          if (objects.length > 0) {
            var item = objects[layerIndex],
              type = item.type,
              component = $scope.getComponentById(item.itemId);
            if (component && component.enable) {
              if (type == "image") {
                fabric.Image.fromObject(item, function (_image) {
                  if (angular.isDefined(_image.isLogo) && _image.isLogo == 1) {
                    if (
                      SPBWC_PB_CONFIG.pcpb_cart_item_key == "" &&
                      SPBWC_PB_CONFIG.is_creating_task == 0
                    )
                      _image.set({ visible: false });
                    component.general.nbpb_image_configs.views[
                      stageIndex
                    ].width = _image.width * _image.scaleX;
                    component.general.nbpb_image_configs.views[
                      stageIndex
                    ].height = _image.height * _image.scaleY;
                    component.general.nbpb_image_configs.views[stageIndex].top =
                      _image.top;
                    component.general.nbpb_image_configs.views[
                      stageIndex
                    ].left = _image.left;
                  }
                  _canvas.add(_image);
                  continueLoadLayer();
                });
              } else if (type == "textbox") {
                function addText(item) {
                  var klass = fabric.util.getKlass(type);
                  klass.fromObject(item, function (item) {
                    if (
                      SPBWC_PB_CONFIG.pcpb_cart_item_key == "" &&
                      SPBWC_PB_CONFIG.is_creating_task == 0
                    )
                      item.set({ visible: false, text: "" });
                    _canvas.add(item);
                    continueLoadLayer();
                  });
                }
                var font = $scope.getFontByAlias(item.fontFamily);
                if (font) {
                  $scope.insertFontScript(font);
                  var font = new FontFaceObserver(item.fontFamily);
                  font.load(item.text).then(
                    function () {
                      fabric.util.clearFabricFontCache();
                      addText(item);
                    },
                    function () {
                      addText(item);
                    }
                  );
                } else {
                  item.fontFamily = "Arial";
                  addText(item);
                }
              }
            } else {
              continueLoadLayer();
            }
          } else {
            continueLoadLayer();
          }
        }
        loadLayer(layerIndex);
      }
      loadStage(stageIndex);
    };
    $scope.fitRectangle = function (x1, y1, x2, y2, fill) {
      var rec = {};
      if (x2 < x1 && y2 < y1) {
        if (fill) {
          if (x1 / y1 > x2 / y2) {
            rec.width = (x2 * y1) / y2;
            rec.height = y1;
            rec.top = 0;
            rec.left = (x1 * y2 - x2 * y1) / y2 / 2;
          } else {
            rec.width = x1;
            rec.height = (x1 * y2) / x2;
            rec.top = (x2 * y1 - x1 * y2) / x2 / 2;
            rec.left = 0;
          }
        } else {
          rec.top = (x1 - x2) / 2;
          rec.left = (y1 - y2) / 2;
          rec.width = x2;
          rec.height = y2;
        }
      } else if (x1 / y1 > x2 / y2) {
        rec.width = (x2 * y1) / y2;
        rec.height = y1;
        rec.top = 0;
        rec.left = (x1 * y2 - x2 * y1) / y2 / 2;
      } else {
        rec.width = x1;
        rec.height = (x1 * y2) / x2;
        rec.top = (x2 * y1 - x1 * y2) / x2 / 2;
        rec.left = 0;
      }
      return rec;
    };
    $scope.toggleAppLoading = function () {
      var $loading = jQuery(".nbdpb-load-page");
      if ($loading.hasClass("nbdpb-show")) {
        $loading.removeClass("nbdpb-show");
        jQuery("body, html").removeClass("nbdpb-no-overflow");
      } else {
        $loading.addClass("nbdpb-show");
        jQuery("body, html").addClass("nbdpb-no-overflow");
      }
    };
    $scope.uploadImage = function (field_id, files) {
      var file = files[0],
        field = $scope.get_field(field_id),
        min_size = parseInt(field.general.upload_option.min_size),
        max_size = parseInt(field.general.upload_option.max_size);
      if (file.type.indexOf("image") === -1) {
        alert($scope.settings.i18n.only_support_image);
        return;
      }
      if (file.size > max_size * 1024 * 1024) {
        alert($scope.settings.i18n.max_file_size + max_size + " MB");
        return;
      } else if (file.size < min_size * 1024 * 1024) {
        alert($scope.settings.i18n.min_file_size + min_size + " MB");
        return;
      }
      if (file.type.indexOf("svg") > -1) {
        var reader = new FileReader();
        reader.onload = function (event) {
          if (event.target.readyState === 2) {
            var result = reader.result;
            $scope.addSvgFromString(result);
          }
        };
        reader.readAsText(file);
      } else {
        NBDDataFactory.get(
           "spbwc_customer_upload",
          { file: file },
          function (data) {
            var data = JSON.parse(data);
            if (data.flag == 1) {
              $scope.addImage(data.src);
              $scope.resource.uploaded.push(data.src);
              if ($scope.resource.uploaded.length > 10) {
                $scope.resource.uploaded.shift();
              }
              localStorage.setItem(
                "nbpb_uploaded",
                JSON.stringify($scope.resource.uploaded)
              );
            } else {
              alert(data.mes);
            }
          }
        );
      }
    };
    $scope.addImage = function (url) {
      var currentComponent = $scope.resource.components[$scope.resource.currentComponent],
      views = currentComponent?.general?.nbpb_image_configs?.views;
      var statusImages = [],
        firstView = true;
      function isLoadedAllImages() {
        var check = true;
        _.each(statusImages, function (status, index) {
          var _status = angular.isDefined(status) ? status : true;
          check = check && _status;
        });
        return check;
      }
      _.each(views, function (view, viewIndex) {
        if ($scope.isDisplayOn(view.display)) {
          statusImages[viewIndex] = false;
        }
      });
      _.each(views, function (view, viewIndex) {
        var stage = $scope.stages[viewIndex],
          _canvas = stage.canvas,
          _item = $scope.getLayerById(currentComponent.id, viewIndex);
        if ($scope.isDisplayOn(view.display)) {
          if (firstView) {
            jQuery(".nbpb-stage-loading").addClass("nbdpb-show");
            firstView = false;
          }
          fabric.Image.fromURL(
            url,
            function (op) {
              function _addImage(exist) {
                if (angular.isDefined(view.width)) {
                  //todo resize holder
                  var preViewport = $scope.settings.pre_builder.config.viewport,
                    currentViewport = $scope.calcViewport(),
                    newFitRec = $scope.fitRectangle(
                      preViewport.width,
                      preViewport.height,
                      stage.config.width,
                      stage.config.height,
                      true
                    ),
                    oldFitRec = $scope.fitRectangle(
                      currentViewport.width,
                      currentViewport.height,
                      stage.config.width,
                      stage.config.height,
                      true
                    );
                  var factor = oldFitRec.width / newFitRec.width,
                    max_width = view.width * factor,
                    max_height = view.height * factor,
                    left = view.left * factor,
                    top = view.top * factor;
                } else {
                  var max_width = _canvas.width / 2,
                    max_height = _canvas.height / 2,
                    left = _canvas.width / 2,
                    top = _canvas.height / 2;
                }
                var new_width = max_width;
                if (op.width < max_width) new_width = op.width;
                var width_ratio = new_width / op.width,
                  new_height = op.height * width_ratio;
                if (new_height > max_height) {
                  new_height = max_height;
                  var height_ratio = new_height / op.height;
                  new_width = op.width * height_ratio;
                }
                if (angular.isDefined(exist)) {
                  var existEl =
                    typeof _item.getElement === "function"
                      ? _item.getElement()
                      : null;
                  if (existEl && existEl.tagName === "IMG" && "src" in existEl) {
                    existEl.src = url;
                  }
                  _item.set({
                    dirty: true,
                    width: op.width,
                    height: op.height,
                    scaleX: new_width / op.width,
                    scaleY: new_height / op.height,
                    visible: true,
                  });
                  _item.setCoords();
                } else {
                  op.set({
                    fill: "#ff0000",
                    scaleX: new_width / op.width,
                    scaleY: new_height / op.height,
                    left: left,
                    top: top,
                    itemId: currentComponent.id,
                    isLogo: 1,
                  });
                  _canvas.add(op);
                  if (SPBWC_PB_CONFIG.is_creating_task == 1) {
                    _canvas.setActiveObject(op);
                  }
                }
              }
              if (_item) {
                if (_item.type == "image") {
                  _addImage(true);
                } else {
                  var layerIndex = $scope.getLayerIndex(
                    currentComponent.id,
                    viewIndex
                  );
                  view.width = _item.width * _item.scaleX;
                  view.height = _item.height * _item.scaleY;
                  view.left = _item.left;
                  view.top = _item.top;
                  _canvas.remove(_item);
                  _addImage(true);
                  op.moveTo(layerIndex);
                }
              } else {
                _addImage();
              }
              _canvas.renderAll();
              statusImages[viewIndex] = true;
              if (isLoadedAllImages()) {
                jQuery(".nbpb-stage-loading").removeClass("nbdpb-show");
              }
            },
            { crossOrigin: "anonymous" }
          );
        }
        jQuery(".nbd-upload-loading").removeClass("is-visible");
      });
      if (
        jQuery(".nbo-fields-wrapper").find(
          "#nbd-upload-hidden-" + currentComponent.id
        ).length > 0
      ) {
        jQuery(".nbo-fields-wrapper")
          .find("#nbd-upload-hidden-" + currentComponent.id)
          .val(url);
      } else {
        jQuery(".nbo-fields-wrapper").append(
          '<input class="nbd-upload-hidden" id="nbd-upload-hidden-' +
            currentComponent.id +
            '" type="hidden" name="pcpb-field[' +
            currentComponent.id +
            ']" value="' +
            url +
            '" />'
        );
      }
      $scope.resource.values[currentComponent.id].value = url;
      jQuery(document).triggerHandler("update_pcpb_options_from_builder", {
        nbd_fields: $scope.resource.values,
        pro: true,
      });
    };
    $scope.addSvgFromString = function (svg) {
      var currentComponent =
        $scope.resource.components[$scope.resource.currentComponent],
        views = currentComponent?.general?.nbpb_image_configs?.views;
      var statusSvgs = [],
        firstView = true;
      function isLoadedAllImages() {
        var check = true;
        _.each(statusSvgs, function (status, index) {
          var _status = angular.isDefined(status) ? status : true;
          check = check && _status;
        });
        return check;
      }
      _.each(views, function (view, viewIndex) {
        if ($scope.isDisplayOn(view.display)) {
          statusSvgs[viewIndex] = false;
        }
      });
      _.each(views, function (view, viewIndex) {
        if ($scope.isDisplayOn(view.display)) {
          var _canvas = $scope.stages[viewIndex].canvas;
          if (firstView) {
            jQuery(".nbpb-stage-loading").addClass("nbdpb-show");
            firstView = false;
          }
          fabric.loadSVGFromString(svg, function (ob, op) {
            var object = fabric.util.groupSVGElements(ob, op);
            function _addSvg(exist) {
              if (angular.isDefined(exist)) {
                var new_rect = $scope.fitRectangle(
                    view.width,
                    view.height,
                    op.width,
                    op.height,
                    true
                  ),
                  new_width = new_rect.width,
                  new_height = new_rect.height,
                  left = view.left + (view.width - new_width) / 2,
                  top = view.top + (view.height - new_height) / 2;
              } else {
                var max_width = _canvas.width,
                  max_height = _canvas.height,
                  new_width = max_width;
                if (op.width < max_width) new_width = op.width;
                var width_ratio = new_width / op.width,
                  new_height = op.height * width_ratio;
                if (new_height > max_height) {
                  new_height = max_height;
                  var height_ratio = new_height / op.height;
                  new_width = op.width * height_ratio;
                }
                var top = _canvas.height / 2,
                  left = _canvas.width / 2;
              }
              object.scaleToWidth(new_width);
              object.scaleToHeight(new_height);
              _canvas.add(object);
              object.set({
                left: left,
                top: top,
                itemId: currentComponent.id,
              });
            }
            if (
              angular.isDefined(currentComponent.existView) &&
              currentComponent.existView
            ) {
              var _item = $scope.getLayerById(currentComponent.id, viewIndex);
              var layerIndex = $scope.getLayerIndex(
                currentComponent.id,
                viewIndex
              );
              view.width = _item.width * _item.scaleX;
              view.height = _item.height * _item.scaleY;
              view.left = _item.left;
              view.top = _item.top;
              _canvas.remove(_item);
              _addSvg(true);
              object.moveTo(layerIndex);
            } else {
              _addSvg();
            }
            _canvas.renderAll();
            statusSvgs[viewIndex] = true;
            if (isLoadedAllImages()) {
              jQuery(".nbpb-stage-loading").removeClass("nbdpb-show");
              currentComponent.existView = true;
            }
          });
        }
        jQuery(".nbd-upload-loading").removeClass("is-visible");
      });
    };
    $scope.deleteLayer = function (type) {
      var type_confirm = "confirm_delete_" + type;
      var con = confirm($scope.settings.i18n[type_confirm]);
      var currentComponent =
            $scope.resource.components[$scope.resource.currentComponent],
          views = currentComponent.general["nbpb_" + type + "_configs"].views;
      if (con) {
        _.each(views, function (view, viewIndex) {
          var layerIndex = $scope.getLayerIndex(currentComponent.id, viewIndex),
            item = $scope.getLayerById(currentComponent.id, viewIndex),
            _canvas = $scope.stages[viewIndex].canvas;
          if (SPBWC_PB_CONFIG.is_creating_task == 1) {
            _canvas.remove(item);
          } else {
            item.set({ visible: false });
            if (item.type == "textbox") item.set({ text: "" });
          }
          _canvas.renderAll();
        });
      }
      if (type == "image") {
        jQuery(".nbo-fields-wrapper")
          .find("#nbd-upload-hidden-" + currentComponent.id)
          .remove();
      } else {
        currentComponent.currentContent = "";
      }
      $scope.resource.values[currentComponent.id].value = "";
      jQuery(document).triggerHandler("update_pcpb_options_from_builder", {
        nbd_fields: $scope.resource.values,
        pro: true,
      });
    };
    $scope.getComponentById = function (id) {
      var component = null;
      angular.forEach($scope.resource.components, function (_component) {
        if (_component.id == id) component = _component;
      });
      return component;
    };
    $scope.get_field = function (field_id) {
      var _field = null;
      angular.forEach(nbOption.options.fields, function (field) {
        if (field.id == field_id) _field = field;
      });
      return _field;
    };
    $scope.getComponentConfigs = function (field) {
      var configs = [],
        viewLen = nbOption.options.views.length;
      _.each(field.general.pb_config, function (attr, a_index) {
        _.each(attr, function (s_attr, sa_index) {
          var config = [];
          if (s_attr.views.length > nbOption.options.views.length) {
            s_attr.views.splice(viewLen, s_attr.views.length - viewLen);
          }
          angular.copy(s_attr.views, config);
          var attribute = field.general.attributes.options[a_index];
          if (angular.isDefined(attribute)) {
            if (
              angular.isDefined(attribute.enable_subattr) &&
              attribute.enable_subattr == "on" &&
              angular.isDefined(attribute.sub_attributes) &&
              attribute.sub_attributes.length > 0
            ) {
              config.sattr_name = attribute.sub_attributes[sa_index].name;
              config.attr_name = attribute.name;
              config.icon_bg = attribute.sub_attributes[sa_index].image_url;
              config.a_index = a_index;
              config.sa_index = sa_index;
              config.level = 2;
              config.bg_type = attribute.sub_attributes[sa_index].preview_type;
              config.icon_color = attribute.sub_attributes[sa_index].color;
              /* Surface the option price into the config so the V2 customizer
               * sidebar can render the price tag next to each choice. Price is
               * stored as an array ([0] = value) in the option schema. */
              config.price = (function (p) { var v = p && p[0]; v = parseFloat(v); return isNaN(v) ? 0 : v; })(attribute.sub_attributes[sa_index].price);
            } else {
              config.icon_bg = attribute.image_url;
              config.sattr_name = attribute.name;
              config.attr_name = "";
              config.a_index = a_index;
              config.sa_index = 0;
              config.level = 1;
              if (attribute.preview_type == "c") {
                config.bg_type = "c";
                config.icon_color = attribute.color;
              } else {
                config.bg_type = "i";
              }
              /* Same price-surfacing for top-level attribute (no sub-attributes). */
              config.price = (function (p) { var v = p && p[0]; v = parseFloat(v); return isNaN(v) ? 0 : v; })(attribute.price);
            }
            configs.push(config);
          }
        });
      });
      return configs;
    };
    $scope.getCurrentConfig = function (component_id, a_index, sa_index) {
      var config_index;
      var component = $scope.getComponentById(component_id);
      if (
        angular.isDefined(component.current_pb_configs) &&
        component.current_pb_configs.length > 0
      ) {
        _.each(component.current_pb_configs, function (config, index) {
          if (config.a_index == a_index && config.sa_index == sa_index)
            config_index = index;
        });
      }
      return config_index;
    };
    /* Pretty-print a per-option upcharge for the V2/V3 customizer sidebar.
     * Reads currency formatting from SPBWC_PB_CONFIG (added in js_config.php).
     * Falls back to plain "+$X.XX" if config missing. */
    $scope.formatPrice = function (val) {
      var n = parseFloat(val);
      if (isNaN(n) || n <= 0) { return ''; }
      var sym = (typeof SPBWC_PB_CONFIG !== 'undefined' && SPBWC_PB_CONFIG.currency_symbol) ? SPBWC_PB_CONFIG.currency_symbol : '$';
      var dec = (typeof SPBWC_PB_CONFIG !== 'undefined' && parseInt(SPBWC_PB_CONFIG.currency_decimals, 10) >= 0) ? parseInt(SPBWC_PB_CONFIG.currency_decimals, 10) : 2;
      return '+' + sym + n.toFixed(dec);
    };
    /* Pretty-print a money value with the store currency. Mirrors WC formatting
     * (symbol position + decimals) for the V3 Summary "Your price" row + CTA. */
    $scope.formatMoney = function (val) {
      var n = parseFloat(val);
      if (isNaN(n)) { n = 0; }
      var cfg = (typeof SPBWC_PB_CONFIG !== 'undefined') ? SPBWC_PB_CONFIG : {};
      var sym = cfg.currency_symbol || '$';
      var dec = (parseInt(cfg.currency_decimals, 10) >= 0) ? parseInt(cfg.currency_decimals, 10) : 2;
      return sym + n.toFixed(dec);
    };
    /* V3 — whether a single component counts as "configured". Mirrors
     * Printcart Canva v2.0 step-status pattern: ✓ when the customer has
     * actually picked / typed / uploaded; ○ when still pending. nbpb_com
     * defaults to currentConfig=0 on init (first option auto-selected),
     * so we treat it as configured as long as that index resolves to a
     * real entry — keeps the green-check affordance honest. */
    /* V3 — filter chips per nbpb_com component. Returns the unique
     * parent attribute names so the buyer can filter sub-options by
     * material/family (e.g. SIDE PANELS → Leather / Cotton / Suede).
     * Used by the chip row that renders above the option grid; auto-
     * hidden when the component has fewer than 2 parent groups. */
    $scope.getAttrFilters = function (component) {
      if (!component || component.nbpb_type !== 'nbpb_com') return [];
      var configs = component.current_pb_configs || [];
      var seen = {};
      var out = [];
      for (var i = 0; i < configs.length; i++) {
        var name = configs[i] && configs[i].attr_name;
        if (name && !seen[name]) {
          seen[name] = true;
          out.push(name);
        }
      }
      return out;
    };
    /* V3 — view filter (auto-detect which components affect the
     * currently-shown stage). Buyer sees only parts that visually change
     * what they're looking at; toggle to "All" to see every part.
     *
     * Detection rule: for nbpb_com components, check pb_config — if any
     * option in any attribute has a non-empty `views[currentStage]`
     * (image_url, image, or color), the component is relevant to that
     * view. nbpb_text and nbpb_image components are considered relevant
     * to all views. Components with no pb_config default to relevant
     * (legacy safety). */
    $scope.viewFilter = 'current';
    $scope.componentAffectsView = function (component, stageIdx) {
      if (!component || !component.enable) return false;
      if (component.nbpb_type !== 'nbpb_com') return true;
      var pb = component.general && component.general.pb_config;
      if (!_.isArray(pb) || !pb.length) return true;
      return _.some(pb, function (attr) {
        if (!_.isArray(attr)) return false;
        return _.some(attr, function (s) {
          if (!s || !_.isArray(s.views)) return false;
          var v = s.views[stageIdx];
          if (!v) return false;
          return !!(v.image_url || v.image || v.color);
        });
      });
    };
    $scope.componentVisibleInFilter = function (component) {
      if (!$scope.stages || $scope.stages.length < 2) return true;
      if ($scope.viewFilter === 'all') return true;
      return $scope.componentAffectsView(component, $scope.currentStage);
    };
    /* V3 — Accordion toggle. Clicking an already-open step closes it
     * (Printcart step-row toggle behaviour). New step opens via the
     * legacy $scope.showAttribute(idx). */
    $scope.toggleAccordion = function (idx) {
      if ($scope.resource.showValue && $scope.resource.currentComponent === idx) {
        $scope.resource.showValue = false;
        return;
      }
      $scope.showAttribute(idx);
      /* Auto-switch canvas to the component's primary view on open so
       * the customer is looking at the right side BEFORE picking.
       *
       * 1.5.3 — added a debug log behind window.SPBWC_DEBUG_VIEW. Set
       *     window.SPBWC_DEBUG_VIEW = true
       * in DevTools and re-open an accordion to confirm the heuristic
       * fired correctly. Verified offline (PHP simulation of bag DB):
       *   HANDLES        → primary view 0 (variety 10/10/10 tie)
       *   SIDE PANELS    → primary view 0 (variety 15/1/1)
       *   MIDDLE BLOCK   → primary view 0 (variety 15/1/1)
       *   INSIDE STORAGE → primary view 2 (variety 1/1/13)
       *   STRAP FABRIC   → primary view 0 (variety 6/1/1)
       */
      try {
        var comp = $scope.resource.components && $scope.resource.components[idx];
        if (comp && typeof $scope.findPrimaryView === 'function') {
          var primary = $scope.findPrimaryView(comp);
          if (typeof window !== 'undefined' && window.SPBWC_DEBUG_VIEW) {
            try { console.log('[SPBWC] toggleAccordion', idx, comp.general && comp.general.title, '→ findPrimaryView =', primary, 'currentStage =', $scope.currentStage); } catch (e) {}
          }
          if (primary >= 0 && primary !== $scope.currentStage) {
            $scope.changeStage(primary);
          }
        }
      } catch (e) {}
    };
    /* V3 — Canvas zoom (Printcart `.zoom-bar` pattern).
     * Tracks a buyer-driven scale factor and applies it to .design-zone
     * via CSS transform. Doesn't touch Fabric internals — just visual
     * preview scale, so it can't break the save pipeline. */
    $scope.zoomLevel = 1.0;
    $scope.zoomCanvas = function (delta, fitReset) {
      if (fitReset) {
        $scope.zoomLevel = 1.0;
      } else {
        $scope.zoomLevel = Math.max(0.5, Math.min(2.0, $scope.zoomLevel + (delta || 0)));
      }
      try {
        var zone = document.querySelector('.spbwc-cust-v3 .design-zone');
        if (zone) {
          zone.style.transformOrigin = 'center center';
          zone.style.transform = 'scale(' + $scope.zoomLevel + ')';
          zone.style.transition = 'transform 180ms ease';
        }
        var pct = Math.round($scope.zoomLevel * 100);
        var label = document.querySelector('[data-spbwc-zoom-value]');
        if (label) { label.textContent = pct + '%'; }
      } catch (e) { /* canvas not ready — no-op */ }
    };
    /* V3 — View switcher. Rewritten in 1.4.9 to bypass a bug in the
     * legacy `nbdpbCarousel.itemActive()` that calculated transform
     * using *current* (transformed) offsets — going from stage 0 → 1
     * read the un-transformed `item1.offset().left` and computed
     * curT = -718, but subsequent calls fed back the already-shifted
     * offsets and produced inconsistent translations (1.4.8 test
     * showed click thumb 1 → transform stayed 0, click thumb 2 →
     * jumped to -1436, click thumb 0 → stayed at -1436).
     *
     * The fix is dead-simple: index × first-item-width is the only
     * reliable transform target. We set classes + transform ourselves
     * and let the rest of the legacy carousel chain (dots, nav
     * disabled state) take its course on next user gesture. */
    $scope.changeStage = function (idx) {
      var stages = $scope.stages || [];
      if (!stages.length) return;
      var i = Math.max(0, Math.min(stages.length - 1, idx));
      $scope.currentStage = i;
      try {
        var items = document.querySelectorAll('.spbwc-cust-v3 .spbwc-cust-canvas .nbdpb-carousel-item');
        var carousel = document.querySelector('.spbwc-cust-v3 .spbwc-cust-canvas .nbdpb-carousel');
        if (items.length && carousel) {
          for (var k = 0; k < items.length; k++) {
            if (k === i) { items[k].classList.add('nbdpb-active'); }
            else { items[k].classList.remove('nbdpb-active'); }
          }
          var w = items[0].offsetWidth || 0;
          carousel.style.transform = 'translate3d(' + (-i * w) + 'px, 0, 0)';
        }
        /* Deselect any active Fabric layer on the new stage so the
         * legacy `.design-admin-tool` (Bring fwd / Clear / etc.) does
         * not pop up automatically just because the user switched view. */
        try {
          var st = $scope.stages[i];
          if (st && st.canvas) {
            st.canvas.discardActiveObject();
            st.canvas.requestRenderAll();
          }
          if (st && st.states) { st.states.showAdminTool = false; }
        } catch (e) {}
        if (typeof $scope.updateApp === 'function') { $scope.updateApp(); }
      } catch (e) { /* canvas not ready yet — currentStage update alone is fine */ }
    };

    /* V3 — detect whether a view is "passive" for a component (every
     * option points to the same image_url for that view → the view
     * doesn't visually differentiate options → don't render a Fabric
     * layer for that view, otherwise the shared placeholder image
     * shows up as a white square covering the artwork (bug from user
     * screenshot of bag → option-pick → white box overlay). */
    $scope.isViewPassiveForComponent = function (component, viewIdx) {
      if (!component || !component.current_pb_configs) return false;
      var configs = component.current_pb_configs;
      if (!configs.length) return false;
      var urls = {};
      for (var i = 0; i < configs.length; i++) {
        var url = configs[i] && configs[i][viewIdx] && configs[i][viewIdx].image_url;
        if (url) { urls[url] = true; }
      }
      return _.keys(urls).length <= 1;
    };
    /* V3 — find the PRIMARY view for a component. The previous logic
     * "first view with non-empty image_url" failed when the admin had
     * set a base image_url on every view (1.4.9 regression on the bag
     * product) — current view always passes the check, so the switch
     * never fires.
     *
     * New heuristic: count DISTINCT image_urls per view across all
     * options of the component. The view with the highest variety is
     * where the component's choices visually diverge — that's the
     * "primary" view for that component. For an INSIDE-STORAGE
     * component with options Cream/Black/Navy, view 0 might have a
     * single base.png for every option (variety = 1) while view 2
     * (Inside) has three distinct cream/black/navy images
     * (variety = 3) → primary view = 2.
     *
     * Returns -1 if no clear primary (e.g. nbpb_text / nbpb_image
     * components, or single-view products). */
    $scope.findPrimaryView = function (component) {
      if (!component || component.nbpb_type !== 'nbpb_com') return -1;
      var configs = component.current_pb_configs || [];
      if (!configs.length) return -1;
      var stagesLen = ($scope.stages || []).length;
      if (stagesLen < 2) return -1;
      var variety = []; // variety[viewIdx] = { url: true, ... }
      for (var v = 0; v < stagesLen; v++) { variety[v] = {}; }
      _.each(configs, function (cfg) {
        if (!cfg || !cfg.length) return;
        for (var v2 = 0; v2 < stagesLen && v2 < cfg.length; v2++) {
          var view = cfg[v2];
          if (view && view.image_url) { variety[v2][view.image_url] = true; }
        }
      });
      var best = -1, bestCount = 0;
      for (var k = 0; k < stagesLen; k++) {
        var c = _.keys(variety[k]).length;
        if (c > bestCount) { bestCount = c; best = k; }
      }
      return bestCount > 1 ? best : -1; // only commit if there's real variety
    };
    /* V3 — Pick a sub-option, hide the Fabric layer on PASSIVE views
     * (where every option points to the same placeholder image), and
     * auto-switch the canvas to the component's primary view. The
     * passive-layer hide fixes the "white square overlay" bug from
     * the user's screenshot: shared-placeholder image 135 was being
     * rendered as a literal white square on every view that didn't
     * actually differentiate options. */
    $scope.selectAttributeAndSwitchView = function (optionIdx, component) {
      $scope.selectAttribute(optionIdx);
      if (!component || !component.current_pb_configs) return;
      try {
        var stages = $scope.stages || [];
        for (var v = 0; v < stages.length; v++) {
          if (!$scope.isViewPassiveForComponent(component, v)) continue;
          var layer = (typeof $scope.getLayerById === 'function') ? $scope.getLayerById(component.id, v) : null;
          if (layer && typeof layer.set === 'function') {
            layer.set({ visible: false, selectable: false, evented: false });
            var st = stages[v];
            if (st && st.canvas) {
              if (st.canvas.getActiveObject && st.canvas.getActiveObject() === layer) {
                st.canvas.discardActiveObject();
              }
              if (typeof st.canvas.requestRenderAll === 'function') { st.canvas.requestRenderAll(); }
              else if (typeof st.canvas.renderAll === 'function') { st.canvas.renderAll(); }
            }
            if (st && st.states) { st.states.showAdminTool = false; }
          }
        }
      } catch (e) { /* never block the customizer flow on layer cleanup */ }
      var primary = $scope.findPrimaryView(component);
      if (typeof window !== 'undefined' && window.SPBWC_DEBUG_VIEW) {
        try { console.log('[SPBWC] selectAttribute', optionIdx, component.general && component.general.title, '→ findPrimaryView =', primary, 'currentStage =', $scope.currentStage); } catch (e) {}
      }
      if (primary < 0 || primary === $scope.currentStage) return;
      $scope.changeStage(primary);
    };
    $scope.isComponentConfigured = function (c) {
      if (!c || !c.enable) return false;
      if (c.nbpb_type === 'nbpb_com') {
        return !!(c.current_pb_configs && c.current_pb_configs[c.currentConfig]);
      }
      if (c.nbpb_type === 'nbpb_text') { return !!c.currentContent; }
      if (c.nbpb_type === 'nbpb_image') {
        return !!($scope.resource && $scope.resource.uploaded && $scope.resource.uploaded.length);
      }
      return false;
    };
    /* V3 — live grand total = base price + Σ(picked component upcharge).
     * Base price is injected via SPBWC_PB_CONFIG.base_price_raw from
     * js_config.php (set by wc_get_product()->get_price()). Adds-on are read
     * from .price (set by getComponentConfigs above). Custom text/image
     * contribute 0 in this MVP — pricing for those lives at admin-options
     * level and is applied by Woo at add-to-cart time, not here. */
    /* Current buy quantity from the WooCommerce product form. The V3 customizer
     * lives on the single-product page, so the qty input is in the DOM; default
     * to 1 when absent (e.g. admin create-task flow). */
    $scope.getBuyQty = function () {
      var q = 1;
      try {
        var $q = jQuery('form.cart input[name="quantity"], .variations_form input[name="quantity"]').filter(':visible').first();
        if (!$q.length) { $q = jQuery('input[name="quantity"]').first(); }
        var v = parseInt($q.val(), 10);
        if (!isNaN(v) && v > 0) { q = v; }
      } catch (e) { /* default 1 */ }
      return q;
    };
    /* Per-item volume discount — MUST mirror the server engine in
     * class-frontend-options.php::option_processing(): highest tier where
     * qty >= val wins; 'p' = percent of (base+addons) per item, 'f' = fixed
     * per item. Tiers come pre-filtered (val>0 && dis>0) from SPBWC_PB_CONFIG. */
    $scope.getVolumeDiscount = function (perItemBeforeDiscount, qty) {
      var cfg = (typeof SPBWC_PB_CONFIG !== 'undefined') ? SPBWC_PB_CONFIG : {};
      var breaks = (cfg.quantity_breaks && cfg.quantity_breaks.length) ? cfg.quantity_breaks : [];
      var type = cfg.quantity_discount_type || 'f';
      var tierVal = 0, tierDis = 0;
      for (var i = 0; i < breaks.length; i++) {
        var bv = parseInt(breaks[i].val, 10), bd = parseFloat(breaks[i].dis);
        if (isNaN(bv) || isNaN(bd)) { continue; }
        if (bv > 0 && bd > 0 && qty >= bv && bv > tierVal) { tierVal = bv; tierDis = bd; }
      }
      var amount = 0;
      if (tierDis > 0) { amount = (type === 'p') ? (perItemBeforeDiscount * tierDis / 100) : tierDis; }
      if (amount > perItemBeforeDiscount) { amount = perItemBeforeDiscount; }
      return { amount: amount, tierVal: tierVal, dis: tierDis, type: type };
    };
    $scope.computeBuildTotal = function () {
      var cfg = (typeof SPBWC_PB_CONFIG !== 'undefined') ? SPBWC_PB_CONFIG : {};
      var base = parseFloat(cfg.base_price_raw) || 0;
      var addons = 0;
      var configured = 0, total = 0;
      var comps = ($scope.resource && $scope.resource.components) || [];
      _.each(comps, function (c) {
        if (!c || !c.enable) return;
        total++;
        if (c.nbpb_type === 'nbpb_com') {
          var picked = c.current_pb_configs && c.current_pb_configs[c.currentConfig];
          if (picked) {
            addons += (parseFloat(picked.price) || 0);
            configured++;
          }
        } else if (c.nbpb_type === 'nbpb_text') {
          if (c.currentContent) { configured++; }
        } else if (c.nbpb_type === 'nbpb_image') {
          if ($scope.resource.uploaded && $scope.resource.uploaded.length) { configured++; }
        }
      });
      var preDiscount = base + addons;
      var qty = $scope.getBuyQty();
      var vol = $scope.getVolumeDiscount(preDiscount, qty);
      var grand = preDiscount - vol.amount;
      if (grand < 0) { grand = 0; }
      return {
        base: base, addons: addons, preDiscount: preDiscount,
        discount: vol.amount, discountTier: vol.tierVal, discountPct: vol.dis, discountType: vol.type,
        qty: qty, grand: grand, configured: configured, total: total
      };
    };
    /* Push the live total into the DOM Summary nodes. Avoids a $watch chain
     * by piggy-backing on $scope.$evalAsync after every selectAttribute /
     * updateText / uploadImage callback (we install a single $watch for
     * deep changes on resource.components and resource.uploaded below). */
    /* V3 — Toast notifications. Reuses a single body-level container.
     * showToast('message', 'success'|'warning'|'danger'|'info', ms). */
    $scope.showToast = function (msg, kind, dur) {
      if (!msg) return;
      try {
        var $tray = jQuery('.spbwc-cust-toaster');
        if (!$tray.length) { $tray = jQuery('<div class="spbwc-cust-toaster" aria-live="polite" aria-atomic="true"></div>').appendTo('body'); }
        var cls = 'spbwc-cust-toaster__item';
        if (kind && kind !== 'info') { cls += ' spbwc-cust-toaster__item--' + kind; }
        var $t = jQuery('<div class="' + cls + '" role="status"></div>').text(msg).appendTo($tray);
        setTimeout(function () { $t.addClass('is-visible'); }, 30);
        setTimeout(function () { $t.addClass('is-out'); setTimeout(function () { $t.remove(); }, 380); }, dur || 2600);
      } catch (e) { /* never let toast break the customizer */ }
    };
    /* V3 — localStorage persistence. Save the current build (component
     * picks + text + uploaded images) under a product-keyed key so a
     * customer can refresh / come back and pick up where they left off.
     * Pattern mirrors `storefront-enhance.js#buildsKey()`. */
    $scope._persistKey = function () {
      var pid = (typeof SPBWC_PB_CONFIG !== 'undefined' && SPBWC_PB_CONFIG.oid) ? SPBWC_PB_CONFIG.oid : '0';
      return 'spbwc_v3_design_' + pid;
    };
    $scope.persistDesign = function () {
      try {
        if (!$scope.resource || !window.localStorage) return;
        var payload = {
          ts: 1, /* placeholder — Date.now() would be ideal but workflow scripts can't use it; runtime is fine */
          comps: ($scope.resource.components || []).map(function (c) {
            return {
              id: c.id,
              cfg: c.currentConfig,
              content: c.currentContent || '',
              color: c.currentColor || '',
              font: c.currentFontId || ''
            };
          }),
          uploaded: $scope.resource.uploaded ? $scope.resource.uploaded.slice() : []
        };
        window.localStorage.setItem($scope._persistKey(), JSON.stringify(payload));
      } catch (e) { /* quota / private mode — silently no-op */ }
    };
    $scope.restoreDesign = function () {
      try {
        if (!$scope.resource || !window.localStorage) return false;
        var raw = window.localStorage.getItem($scope._persistKey());
        if (!raw) return false;
        var saved = JSON.parse(raw);
        if (!saved || !_.isArray(saved.comps)) return false;
        _.each(saved.comps, function (sc) {
          var c = _.find($scope.resource.components, function (cc) { return cc && cc.id === sc.id; });
          if (!c) return;
          if (typeof sc.cfg === 'number') { c.currentConfig = sc.cfg; }
          if (sc.content) { c.currentContent = sc.content; }
          if (sc.color) { c.currentColor = sc.color; }
          if (sc.font) { c.currentFontId = sc.font; }
        });
        if (_.isArray(saved.uploaded) && saved.uploaded.length) {
          $scope.resource.uploaded = saved.uploaded.slice();
        }
        $scope.showToast('Your previous design has been restored.', 'info', 3200);
        return true;
      } catch (e) { return false; }
    };
    $scope.clearPersistedDesign = function () {
      try { if (window.localStorage) { window.localStorage.removeItem($scope._persistKey()); } } catch (e) {}
    };
    $scope.refreshSummary = function () {
      try {
        /* Recompute the live total whenever the WooCommerce qty input changes,
         * so the volume discount preview tracks quantity. Bound once. */
        if (!$scope._spbwcQtyBound) {
          $scope._spbwcQtyBound = true;
          jQuery(document).on('input.spbwcVol change.spbwcVol', 'input[name="quantity"]', function () {
            $scope.refreshSummary();
          });
        }
        var info = $scope.computeBuildTotal();
        var grand = $scope.formatMoney(info.grand);
        jQuery('[data-spbwc-grand-total]').text(grand);
        jQuery('[data-spbwc-cta-price]').text(grand);
        /* Volume-discount row: show only when a tier is active. */
        var $volRow = jQuery('[data-spbwc-volume-row]');
        if (info.discount > 0) {
          var tierLbl = info.discountTier > 0 ? ('(' + info.discountTier + '+)') : '';
          var pctLbl = (info.discountType === 'p' && info.discountPct > 0) ? (' · ' + info.discountPct + '%') : '';
          jQuery('[data-spbwc-volume-label]').text(tierLbl + pctLbl);
          jQuery('[data-spbwc-volume-val]').text('-' + $scope.formatMoney(info.discount));
          $volRow.css('display', '');
        } else {
          $volRow.css('display', 'none');
        }
        var lbl = info.configured + ' / ' + info.total + ' configured';
        jQuery('[data-spbwc-progress-label]').text(lbl);
        var pct = info.total > 0 ? Math.round((info.configured / info.total) * 100) : 0;
        jQuery('[data-spbwc-progress-fill]').css('width', pct + '%');
        /* Printcart Canva v2.0 visual cue — progress bar flips to GREEN
         * when 100% configured, signalling "ready to add to cart". */
        var $panel = jQuery('.spbwc-cust-panel__progress, .spbwc-cust-summary__progress-track');
        var $pill  = jQuery('[data-spbwc-progress-pill], .spbwc-cust-summary__progress-pill');
        if (info.total > 0 && info.configured >= info.total) {
          $panel.addClass('is-complete');
          $pill.addClass('is-complete');
        } else {
          $panel.removeClass('is-complete');
          $pill.removeClass('is-complete');
        }
      } catch (e) { /* never let UI math break the customizer */ }
    };
    /* Reset every component to its first option (a_index 0 / sa_index 0) and
     * clear text + uploaded images. Called from the topbar ↻ icon and the
     * Summary "Reset all" link. A native confirm() is enough for this MVP —
     * upgrade to a custom modal if the design system gets one. */
    $scope.resetAll = function () {
      var msg = (SPBWC_PB_CONFIG && SPBWC_PB_CONFIG.i18n && SPBWC_PB_CONFIG.i18n.confirm_reset_all)
        || 'Reset all customizations to default?';
      if (typeof window !== 'undefined' && window.confirm && !window.confirm(msg)) { return; }
      var prev = $scope.resource.currentComponent;
      var comps = ($scope.resource && $scope.resource.components) || [];
      _.each(comps, function (c, idx) {
        if (!c || !c.enable) return;
        if (c.nbpb_type === 'nbpb_com' && c.current_pb_configs && c.current_pb_configs.length) {
          $scope.resource.currentComponent = idx;
          $scope.selectAttribute(0);
        } else if (c.nbpb_type === 'nbpb_text') {
          c.currentContent = '';
          $scope.resource.currentComponent = idx;
          if (typeof $scope.updateText === 'function') { $scope.updateText(); }
        }
      });
      $scope.resource.uploaded = [];
      $scope.resource.currentComponent = prev;
      $scope.refreshSummary();
      $scope.clearPersistedDesign();
      if (typeof $scope.showToast === 'function') {
        $scope.showToast('All customizations have been reset.', 'success', 2400);
      }
    };
    /* Reset a single component (per-part ↻ link inside each accordion). */
    $scope.resetComponent = function (idx) {
      var c = $scope.resource && $scope.resource.components && $scope.resource.components[idx];
      if (!c) return;
      var prev = $scope.resource.currentComponent;
      $scope.resource.currentComponent = idx;
      if (c.nbpb_type === 'nbpb_com' && c.current_pb_configs && c.current_pb_configs.length) {
        $scope.selectAttribute(0);
      } else if (c.nbpb_type === 'nbpb_text') {
        c.currentContent = '';
        if (typeof $scope.updateText === 'function') { $scope.updateText(); }
      } else if (c.nbpb_type === 'nbpb_image') {
        $scope.resource.uploaded = [];
      }
      $scope.resource.currentComponent = prev;
      $scope.refreshSummary();
    };
    /* Install a single deep $watch so the Summary refreshes whenever ANY
     * component selection or uploaded-image list changes. Also persists
     * the design to localStorage so the customer can refresh + resume. */
    $scope.$watch(function () {
      var comps = ($scope.resource && $scope.resource.components) || [];
      var sig = comps.map(function (c) {
        if (!c) return 'x';
        if (c.nbpb_type === 'nbpb_com') { return c.currentConfig; }
        if (c.nbpb_type === 'nbpb_text') { return c.currentContent || ''; }
        return '';
      }).join('|') + '#' + (($scope.resource && $scope.resource.uploaded) ? $scope.resource.uploaded.length : 0);
      return sig;
    }, function (newSig, oldSig) {
      $scope.refreshSummary();
      if (newSig !== oldSig) { $scope.persistDesign(); }
    });
    $scope.init();
  },
]);
nbdpbApp.factory("FabricWindow", [
  "$window",
  function ($window) {
    fabric.Object.NUM_FRACTION_DIGITS = 10;
    $window.fabric.Object.prototype.set({
      transparentCorners: false,
      borderColor: "rgba(79, 84, 103, 0.7)",
      cornerStyle: "circle",
      cornerColor: "rgba(255, 255, 255, 1)",
      borderDashArray: [2, 2],
      cornerStrokeColor: "rgba(63, 70, 82, 1)",
      hoverCursor: "pointer",
      borderOpacityWhenMoving: 0,
      selectable:  true ,
      perPixelTargetFind:  true,
      originX: "center",
      originY: "center",
      centeredScaling: true,
      _controlsVisibility: {
        tl: true,
        tr: true,
        br: true,
        bl: true,
        ml: false,
        mt: false,
        mr: false,
        mb: false,
        mtr: true,
      },
    });
    if (SPBWC_PB_CONFIG.is_mobile)
    $window.fabric.Object.prototype.set({ cornerSize: 17 });
    $window.fabric.Canvas.prototype.set({
      preserveObjectStacking: true,
      controlsAboveOverlay: true,
      selectionColor: "rgba(1, 196, 204, 0.3)",
      selectionBorderColor: "#01c4cc",
      selectionLineWidth: 0.5,
      centeredKey: "shiftKey",
      uniScaleKey: "altKey",
    });
    return $window.fabric;
  },
]);
nbdpbApp.directive("nbdCanvas", [
  "FabricWindow",
  "$timeout",
  "$rootScope",
  function (FabricWindow, $timeout, $rootScope) {
    return {
      restrict: "AE",
      scope: {
        stage: "=stage",
        index: "@",
        last: "@",
      },
      link: function (scope, element, attrs) {
        $timeout(function () {
          scope.stage.canvas = new FabricWindow.Canvas("canvas-" + scope.index);
          scope.$emit("canvas:created", scope.index, scope.last);
        });
      },
    };
  },
]);
nbdpbApp.directive("nbpbHover", [
  "$timeout",
  function ($timeout) {
    return {
      restrict: "AE",
      scope: {
        componentId: "@nbpbHover",
      },
      link: function (scope, element, attrs) {
        $timeout(function () {
          jQuery(element).on("mouseover", function () {
            scope.$emit("component:mouseover", scope.componentId);
          });
          jQuery(element).on("mouseout", function () {
            scope.$emit("component:mouseout", scope.componentId);
          });
        });
      },
    };
  },
]);
nbdpbApp.factory("NBDDataFactory", function ($http) {
  function spbwcUploadFilename(fieldKey, blob) {
    if (!(blob instanceof Blob)) {
      return null;
    }
    if (typeof File !== "undefined" && blob instanceof File && blob.name) {
      return blob.name;
    }
    if (fieldKey === "design" || fieldKey === "config" || fieldKey === "used_font" || fieldKey === "design_output") {
      return fieldKey + ".json";
    }
    if (fieldKey.indexOf("_svg") !== -1) {
      return fieldKey + ".svg";
    }
    if (fieldKey.indexOf("frame_") === 0) {
      return fieldKey + ".png";
    }
    return fieldKey + ".bin";
  }
  return {
    get: function (action, data, callback) {
      var formData = new FormData();
      formData.append("action", action);
      formData.append("nonce", SPBWC_PB_CONFIG["nonce"]);
      angular.forEach(data, function (value, key) {
        var keepDefault = [
          "file",
          "design",
          "config",
          "used_font",
          "design_output",
        ];
        if (
          typeof value != "object" ||
          keepDefault.indexOf(key) != -1 ||
          key.indexOf("frame") > -1
        ) {
          var uploadName = spbwcUploadFilename(key, value);
          if (uploadName) {
            formData.append(key, value, uploadName);
          } else {
            formData.append(key, value);
          }
        } else {
          var keyName;
          for (var k in value) {
            if (value.hasOwnProperty(k)) {
              keyName = [key, "[", k, "]"].join("");
              formData.append(keyName, value[k]);
            }
          }
        }
      });
      var config = {
        transformRequest: angular.identity,
        transformResponse: angular.identity,
        headers: {
          "Content-Type": undefined,
        },
      };
      var url = SPBWC_PB_CONFIG["ajax_url"];
      $http.post(url, formData, config).then(
        function (response) {
          callback(response.data);
        },
        function (response) {
          console.log(response);
        }
      );
    },
  };
});
nbdpbApp.directive("nbdDndFile", [
  "$timeout",
  function ($timeout) {
    return {
      restrict: "A",
      scope: {
        fieldId: "@",
        uploadFile: "&nbdDndFile",
      },
      link: function (scope, element) {
        $timeout(function () {
          var dropArea = jQuery(element),
            Input = dropArea.find('input[type="file"]');
          _.each(["dragenter", "dragover"], function (eventName, key) {
            dropArea.on(eventName, highlight);
          });
          _.each(["dragleave", "drop"], function (eventName, key) {
            dropArea.on(eventName, unhighlight);
          });
          function highlight(e) {
            e.preventDefault();
            e.stopPropagation();
            dropArea.addClass("highlight");
          }
          function unhighlight(e) {
            e.preventDefault();
            e.stopPropagation();
            dropArea.removeClass("highlight");
          }
          dropArea.on("drop", handleDrop);
          function handleDrop(e) {
            if (e.originalEvent.dataTransfer) {
              if (e.originalEvent.dataTransfer.files.length) {
                e.preventDefault();
                e.stopPropagation();
                handleFiles(e.originalEvent.dataTransfer.files);
              }
            }
          }
          dropArea.on("click", function (e) {
            Input.click();
          });
          Input.on("click", function (e) {
            e.stopPropagation();
          });
          Input.on("change", function () {
            handleFiles(this.files);
          });
          function handleFiles(files) {
            if (files.length > 0) {
              jQuery(element)
                .find(".nbd-upload-loading")
                .addClass("is-visible");
              scope.uploadFile({ field_id: scope.fieldId, files: files });
            }
          }
        });
      },
    };
  },
]);
nbdpbApp.directive("nbpbColorPicker", [
  "$timeout",
  function ($timeout) {
    return {
      restrict: "C",
      scope: {
        onChange: "&",
        options: "=?",
      },
      link: function (scope, element, attrs) {
        function formatColor(tiny) {
          var formatted = tiny;
          if (formatted) {
            formatted = tiny.toString(scope.options.preferredFormat);
          }
          return formatted;
        }
        $timeout(function () {
          scope.options.change = function (color) {
            scope.onChange({ color: formatColor(color) });
          };
          element.spectrum(scope.options);
        });
        element.on("$destroy", function () {
          element.spectrum("destroy");
        });
      },
    };
  },
]);
jQuery.fn.nbShowPopup = function () {
  return this.each(function () {
    var sefl = this;
    var $close = jQuery(this).find(".overlay-popup, .close-popup");
    if (!jQuery(this).hasClass("nbdpb-show")) {
      jQuery(this).addClass("nbdpb-show");
      var $scope = angular.element(
        document.getElementById("nbpb-container")
      ).scope();
      $scope.initValues(false, true);
      $scope.reCalcViewPort();
      $scope.updateApp();
    }
    $close.on("click", function () {
      jQuery(sefl).removeClass("nbdpb-show");
      jQuery("body, html").removeClass("nbdpb-no-overflow");
      var $scope = angular
        .element(document.getElementById("nbpb-container"))
        .scope();
      $scope.updateApp();
    });
  });
};
jQuery.fn.nbdpbCarousel = function () {
  var seflC = this;
  this.itemActive = function ($carousel) {
    var $items = $carousel.find(".nbdpb-carousel-item"),
      $itemA = $items.filter(".nbdpb-active"),
      $nav = $carousel.closest(".nbdpb-carousel-outer").find(".js-nav-item"),
      $dots = $carousel
        .closest(".nbdpb-carousel-outer")
        .find(".nbdpb-owl-dots");
    var curT = $carousel.offset().left - $itemA.offset().left;

    $nav.removeClass("nbdpb-disabled");
    $dots.find(".nbdpb-owl-dot").removeClass("nbdpb-active");
    $dots
      .find(".nbdpb-owl-dot")
      .filter(function (i) {
        return i === $itemA.index();
      })
      .addClass("nbdpb-active");
    $carousel.css({
      transform: "translate3d(" + curT + "px, 0, 0)",
    });
    var $scope = angular
        .element(document.getElementById("nbpb-container"))
        .scope(),
      stage = $itemA.find(".stage").data("stage");
    $scope.currentStage = stage;
    $scope.updateApp();
  };
  this.activeItemByIndex = function (index) {
    var $items = jQuery(seflC).find(".nbdpb-carousel-item");
    $items.removeClass("nbdpb-active");
    jQuery($items[index]).addClass("nbdpb-active");
    seflC.itemActive(jQuery(seflC));
  };
  return this.each(function () {
    var $sefl = jQuery(this),
      $items = jQuery(this).find(".nbdpb-carousel-item"),
      $outerCarousel = jQuery(this).closest(".nbdpb-carousel-outer");

    var cWith = 0,
      total = $items.length,
      dots = '<div class="nbdpb-owl-dots"></div>';
    var nav = '<div class="nbdpb-owl-nav"></div>',
      navPrev =
        '<button type="button" role="presentation" class="nbdpb-owl-prev js-nav-item">' +
        '<i aria-label="Previous" class="icon-nbd icon-nbd-chevron-right rotate180"></i>' +
        "</button>",
      navNext =
        '<button type="button" role="presentation" class="nbdpb-owl-next js-nav-item">' +
        '<i aria-label="Next" class="icon-nbd icon-nbd-chevron-right"></i>' +
        "</button>";
    var $dots = jQuery(dots),
      $nav = jQuery(nav),
      $navPrev = jQuery(navPrev),
      $navNext = jQuery(navNext);
    $outerCarousel.find(".nbdpb-owl-nav").remove();
    $outerCarousel.find(".nbdpb-owl-dots").remove();
    if ($items.length > 1) $outerCarousel.append($dots);
    if ($items.length > 1) $outerCarousel.append($nav);
    $nav.append($navPrev);
    $nav.append($navNext);
    $items.each(function (i) {
      var dot =
        '<button role="button" class="nbdpb-owl-dot"><span></span></button>';
      cWith += $outerCarousel.outerWidth();
      jQuery(this).css({
        width: $outerCarousel.outerWidth(),
      });
      $dots.append(dot);
    });
    $dots.find(".nbdpb-owl-dot").first().addClass("nbdpb-active");
    jQuery(this).css({
      width: cWith + "px",
    });
    $dots.find(".nbdpb-owl-dot").on("click", function () {
      var index = jQuery(this).index();

      $dots.find(".nbdpb-owl-dot").removeClass("nbdpb-active");
      jQuery(this).addClass("nbdpb-active");

      $items.removeClass("nbdpb-active");
      $items
        .filter(function (i) {
          return i === index;
        })
        .addClass("nbdpb-active");

      seflC.itemActive($sefl);
    });
    $navPrev.on("click", function () {
      var $itemA = $items.filter(".nbdpb-active");
      $itemA.removeClass("nbdpb-active");
      if ($itemA.index() == 0) {
        $items.last().addClass("nbdpb-active");
      } else {
        $itemA.prev().addClass("nbdpb-active");
      }
      seflC.itemActive($sefl);
    });
    $navNext.on("click", function () {
      var $itemA = $items.filter(".nbdpb-active");
      $itemA.removeClass("nbdpb-active");
      if ($itemA.index() == $items.length - 1) {
        $items.first().addClass("nbdpb-active");
      } else {
        $itemA.next().addClass("nbdpb-active");
      }
      seflC.itemActive($sefl);
    });
  });
};
function getTransform(el) {
  var results = jQuery(el)
    .css("transform")
    .match(
      /matrix(?:(3d)\(\d+(?:, \d+)*(?:, (\d+))(?:, (\d+))(?:, (\d+)), \d+\)|\(\d+(?:, \d+)*(?:, (\d+))(?:, (\d+))\))/
    );
  if (!results) return [0, 0, 0];
  if (results[1] == "3d") return results.slice(2, 5);
  results.push(0);
  return results.slice(5, 8);
}
jQuery(document).ready(function () {
  jQuery("#pcpb-start-design").on("click", function () {
    var $scope = angular
      .element(document.getElementById("nbpb-container"))
      .scope();
    setTimeout(function () {
      $scope.reCalcViewPort();
      $scope.updateApp();
    }, 300);
    jQuery("body, html").addClass("nbdpb-no-overflow");
    jQuery(".nbdpb-popup.popup-design")
      .nbShowPopup()
      .addClass("nbdpb-no-overflow");
    appConfig.slider = jQuery(".nbdpb-carousel").nbdpbCarousel();
  });
});
jQuery(document).on("initialed_nbo_options", function () {
  var nbdpbAppEl = document.getElementById("nbdpb-app");
  angular.element(function () {
    angular.bootstrap(nbdpbAppEl, ["nbdpbApp"]);
    if (SPBWC_PB_CONFIG.is_creating_task == 1) {
      setTimeout(function () {
        jQuery("body, html").addClass("nbdpb-no-overflow");
        jQuery(".nbdpb-popup.popup-design")
          .nbShowPopup()
          .addClass("nbdpb-no-overflow");
        appConfig.slider = jQuery(".nbdpb-carousel").nbdpbCarousel();
        jQuery(".nbdpb-load-page").removeClass("nbdpb-show");
      });
    }
  });
});
jQuery(document).on("update_nbo_options", function (e, data) {
  if (!appConfig.ready) return;
  var $scope = angular
    .element(document.getElementById("nbpb-container"))
    .scope();
  $scope.initValues(false, data.pro);
  $scope.updateApp();
});

/* =====================================================================
 * V3 — Tab nav switching + Reset hooks + Coming-soon affordance
 * --------------------------------------------------------------------
 * Lives OUTSIDE the Angular controller because:
 *   (a) the tab nav swaps between an Angular panel ("customize") and
 *       static placeholder panels (AI / Templates / Help) — no scope
 *       state is needed
 *   (b) the reset/coming-soon buttons are simple jQuery handlers
 * Bindings are namespaced under .spbwc-cust-v3 so legacy wrappers stay
 * untouched. Falls back to a no-op if the V3 markup isn't present.
 * ===================================================================== */
jQuery(function ($) {
  /* Tab switch — flip aria-selected + show/hide tabpanels. */
  $(document).on('click', '.spbwc-cust-v3 .spbwc-cust-tabbtn', function () {
    var $btn = $(this);
    var tab = $btn.data('spbwc-tab');
    if (!tab) return;
    if ($btn.data('spbwc-coming-soon')) {
      /* Friendly hint — keep on the active tab, just inform. */
      try {
        var $tip = $('<div class="spbwc-cust-toast">' + ($btn.attr('title') || 'Coming soon') + '</div>');
        $('.spbwc-cust-v3').append($tip);
        setTimeout(function () { $tip.addClass('is-out'); }, 1800);
        setTimeout(function () { $tip.remove(); }, 2200);
      } catch (e) {}
      return;
    }
    var $root = $btn.closest('.spbwc-cust-v3');
    $root.find('.spbwc-cust-tabbtn').removeClass('is-active').attr('aria-selected', 'false');
    $btn.addClass('is-active').attr('aria-selected', 'true');
    $root.find('.spbwc-cust-tabpanel').attr('hidden', true);
    $root.find('.spbwc-cust-tabpanel[data-spbwc-tabpanel="' + tab + '"]').removeAttr('hidden');
  });

  /* Reset-all — wired to both the topbar ↻ icon and the Summary "Reset all" link. */
  $(document).on('click', '.spbwc-cust-v3 [data-spbwc-action="reset-all"]', function () {
    var el = document.getElementById('nbpb-container');
    if (!el || typeof angular === 'undefined') return;
    var s = angular.element(el).scope();
    if (s && typeof s.resetAll === 'function') {
      s.$apply(function () { s.resetAll(); });
    }
  });

  /* Reset-this-part — per-component link inside each accordion body. */
  $(document).on('click', '.spbwc-cust-v3 [data-spbwc-action="reset-part"]', function (e) {
    e.preventDefault();
    e.stopPropagation();
    var idx = parseInt($(this).data('spbwc-part-index'), 10);
    if (isNaN(idx)) return;
    var el = document.getElementById('nbpb-container');
    if (!el || typeof angular === 'undefined') return;
    var s = angular.element(el).scope();
    if (s && typeof s.resetComponent === 'function') {
      s.$apply(function () { s.resetComponent(idx); });
    }
  });

  /* Initial Summary push after the modal becomes visible — also tries
   * to restore a previously-persisted design from localStorage. */
  $(document).on('initialed_nbo_options nbdpb-show', function () {
    setTimeout(function () {
      var el = document.getElementById('nbpb-container');
      if (!el || typeof angular === 'undefined') return;
      try {
        var s = angular.element(el).scope();
        if (s) {
          if (typeof s.restoreDesign === 'function') {
            s.$apply(function () { s.restoreDesign(); });
          }
          if (typeof s.refreshSummary === 'function') { s.refreshSummary(); }
        }
      } catch (e) {}
    }, 400);
  });

  /* Mobile drawer toggle — clicking the summary sticky-top on mobile
   * expands the summary bottom-sheet. Tap again (or anywhere outside)
   * to collapse. Only triggers below the responsive breakpoint. */
  $(document).on('click', '.spbwc-cust-v3 .spbwc-cust-summary__sticky-top', function (e) {
    if (window.innerWidth > 768) return;
    var $summary = $(this).closest('.spbwc-cust-summary');
    $summary.toggleClass('is-expanded');
  });

  /* Details tab — gallery thumb click swaps the hero image. */
  $(document).on('click', '.spbwc-cust-v3 [data-spbwc-thumb]', function () {
    var url = $(this).attr('data-full');
    if (!url) return;
    $('.spbwc-cust-v3 [data-spbwc-hero]').attr('src', url);
    $('.spbwc-cust-v3 [data-spbwc-thumb]').removeClass('is-active');
    $(this).addClass('is-active');
  });

  /* Teaching toast (Printcart Canva pattern). Reveals on first real
   * modal open via the storefront "Preview & customize" button; hides
   * on first option pick or after a 10-second auto-dismiss. The toast
   * lives at body-level (sibling of the .nbdpb-popup wrapper), so its
   * visibility is driven by an `is-visible` class — NOT by a CSS
   * sibling-of-modal selector — which keeps the logic robust against
   * Angular re-renders inside the modal. */
  var teachToastDismissed = false;
  var teachToastShown = false;
  function showTeachToast() {
    if (teachToastShown) return;
    teachToastShown = true;
    $('.spbwc-cust-teachtoast').addClass('is-visible');
    setTimeout(function () { dismissTeachToast(); }, 10000);
  }
  function dismissTeachToast() {
    if (teachToastDismissed) return;
    teachToastDismissed = true;
    var $t = $('.spbwc-cust-teachtoast');
    $t.addClass('is-out');
    setTimeout(function () { $t.removeClass('is-visible is-out'); }, 350);
  }
  $(document).on('click', '#pcpb-start-design', function () {
    /* Modal-open trigger — reveal the toast on first paint of the modal. */
    setTimeout(showTeachToast, 600);
  });
  $(document).on('click', '[data-spbwc-teachtoast-close]', dismissTeachToast);
  $(document).on('click', '.spbwc-cust-v3 .spbwc-cust-val', dismissTeachToast);
});
