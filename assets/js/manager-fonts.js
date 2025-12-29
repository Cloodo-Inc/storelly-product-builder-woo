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
