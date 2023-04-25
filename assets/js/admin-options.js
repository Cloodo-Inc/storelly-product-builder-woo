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
      angular.copy(PRINTCART_OPTION_FIELD, field);
      var d = new Date();
      field["id"] = "f" + d.getTime();
      field.isExpand = true;
      if (angular.isDefined(type)) {
        field.general.title.value =
          printcart_options.printcart_options_lang[type];
        field.nbd_template = "nbd." + type;
        if (angular.isUndefined(ftype)) {
          if (
            angular.isDefined($scope.printcart_options[type]) &&
            type != "builder" &&
            $scope.printcart_options[type] == 1
          ) {
          } else {
            $scope.printcart_options[type] = 1;
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
                name: printcart_options.printcart_options_lang.view_name,
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
    $scope.addView = function () {
      $scope.options.views.push({
        name: printcart_options.printcart_options_lang.view_name,
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
    $scope.set_view_base = function (vIndex) {
      var file_frame;
      if (file_frame) {
        file_frame.open();
        return;
      }
      file_frame = wp.media.frames.file_frame = wp.media({
        title: printcart_options.printcart_options_lang.choose_image,
        button: {
          text: printcart_options.printcart_options_lang.choose_image,
        },
        library: {
          type: ["image"],
        },
        multiple: false,
      });
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
      file_frame = wp.media.frames.file_frame = wp.media({
        title: printcart_options.printcart_options_lang.choose_image,
        button: {
          text: printcart_options.printcart_options_lang.choose_image,
        },
        library: {
          type: ["image"],
        },
        multiple: false,
      });
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
      var con = confirm(
        printcart_options.printcart_options_lang.want_to_delete
      );
      if (con) {
        var field = $scope.options.fields[index];
        $scope.options.fields.splice(index, 1);
        $scope.initfieldValue();
      }
    };
    $scope.clear_all_fields = function (index) {
      var con = confirm(
        printcart_options.printcart_options_lang.want_to_delete_all
      );
      if (con) {
        $scope.options.fields = [];
        angular.forEach($scope.printcart_options, function (option, key) {
          option = 0;
        });
        $scope.initfieldValue();
      }
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
                name: printcart_options.printcart_options_lang.view_name,
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
          alert(
            printcart_options.printcart_options_lang.max_input_var +
              " " +
              $scope.max_input_vars +
              ". " +
              printcart_options.printcart_options_lang.max_input_notice
          );
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
      $scope.printcart_options = {};
      $scope.options = PRINTCART_OPTIONS;
      $scope.current_input_vars = 1;
      $scope.max_input_vars = max_input_vars;
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
          url: ajax_url,
          method: "POST",
          data: {
            action: "nbd_get_media_full_size_url",
            nonce: nbnonce,
            images: JSON.stringify(mediaObject),
          },
        })
        .done(function (data) {
          var res = JSON.parse(data);
          if (res.flag == 1) {
            callback(res.images);
          } else {
            jQuery(".nbp-loading-wrap").removeClass("nbp-show");
            alert("Error, Try again later!");
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
        alert("Error, Try again later!");
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
          url: ajax_url,
          method: "POST",
          data: {
            action: "nbd_download_option_image",
            nonce: nbnonce,
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
        alert(printcart_options.printcart_options_lang.can_not_remove_att);
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
        alert(printcart_options.printcart_options_lang.can_not_add_att);
        return;
      }

      $scope.options["fields"][fieldIndex]["general"][key]["options"].push({
        name: printcart_options.printcart_options_lang.attribute_name,
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
      $scope.options["fields"][fieldIndex]["general"]["attributes"]["options"][
        opIndex
      ]["sub_attributes"].push({
        name: printcart_options.printcart_options_lang.sub_attribute_name,
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
      file_frame = wp.media.frames.file_frame = wp.media({
        title: printcart_options.printcart_options_lang.choose_image,
        button: {
          text: printcart_options.printcart_options_lang.choose_image,
        },
        library: {
          type: ["image"],
        },
        multiple: false,
      });
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
      file_frame = wp.media.frames.file_frame = wp.media({
        title: printcart_options.printcart_options_lang.choose_image,
        button: {
          text: printcart_options.printcart_options_lang.choose_image,
        },
        library: {
          type: ["image"],
        },
        multiple: false,
      });
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
      file_frame = wp.media.frames.file_frame = wp.media({
        title: printcart_options.printcart_options_lang.choose_image,
        button: {
          text: printcart_options.printcart_options_lang.choose_image,
        },
        library: {
          type: ["image"],
        },
        multiple: false,
      });
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
            attributes: {},
          },
          appearance: {},
        };

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
    buttonImage: printcart_options.calendar_image,
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
