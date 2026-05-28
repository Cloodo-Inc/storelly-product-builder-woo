/**
 * Buyer-side conditional logic for product option fields.
 *
 * Honors each field's `general.conditional_depend` rules (configured in the
 * admin "Conditional logic" panel) by toggling `nbd_fields[id].enable`. The
 * core buyer app already uses `enable` to gate visibility (ng-if), pricing
 * (price loop skips !enable) and required-validation — so flipping it is the
 * single, safe lever.
 *
 * Registered as a directive on the existing `nboApp` module so it shares the
 * optionCtrl scope WITHOUT editing the minified core app. Loads after
 * option-builder.js (module defined) and before jQuery(document).ready
 * bootstrap, so the directive is available at compile time.
 *
 * Rule shape: { id: <other field id>, operator: '=' | '!' | 'i' | '!i', val }
 * A field shows only when ALL its rules pass (AND).
 */
(function () {
    'use strict';
    if (typeof angular === 'undefined') { return; }
    try {
        angular.module('nboApp').directive('spbwcConditionalLogic', ['$timeout', function ($timeout) {
            return {
                restrict: 'A',
                link: function (scope) {
                    var cfg = (typeof spbwc_option_builder_variable !== 'undefined')
                        ? spbwc_option_builder_variable.options : null;
                    if (!cfg || !angular.isArray(cfg.fields)) { return; }

                    var rules = {}; // fieldId -> [ {id, operator, val} ]
                    var meta  = {}; // fieldId -> { data_type, names: {index: name} }

                    cfg.fields.forEach(function (f) {
                        if (!f || !f.id || !f.general) { return; }
                        var g = f.general;
                        var m = { data_type: g.data_type, names: {} };
                        if (g.attributes && angular.isArray(g.attributes.options)) {
                            g.attributes.options.forEach(function (attr, idx) {
                                m.names[idx] = (attr && attr.name != null) ? String(attr.name) : '';
                            });
                        }
                        meta[f.id] = m;
                        if (angular.isArray(g.conditional_depend)) {
                            var valid = g.conditional_depend.filter(function (r) { return r && r.id; });
                            if (valid.length) { rules[f.id] = valid; }
                        }
                    });

                    if (!Object.keys(rules).length) { return; }

                    // Text representation of a target field's current selection.
                    // Multi-choice (data_type 'm') stores the attribute INDEX in
                    // nbd_fields[id].value, so map it back to the attribute name
                    // (rules compare against names like "Matte"). Input fields
                    // store the typed value directly.
                    function currentValue(targetId) {
                        if (!scope.nbd_fields || !scope.nbd_fields[targetId]) { return ''; }
                        var raw = scope.nbd_fields[targetId].value;
                        var tm = meta[targetId];
                        if (tm && tm.data_type === 'm') {
                            if (raw === null || raw === undefined || raw === '') { return ''; }
                            var nm = tm.names[raw];
                            return (nm === null || nm === undefined) ? '' : String(nm);
                        }
                        return (raw === null || raw === undefined) ? '' : String(raw);
                    }

                    function rulePasses(r) {
                        var actual = currentValue(r.id);
                        var expected = (r.val === null || r.val === undefined) ? '' : String(r.val);
                        switch (r.operator) {
                            case '=':  return actual === expected;
                            case '!':  return actual !== expected;
                            case 'i':  return actual.indexOf(expected) !== -1;
                            case '!i': return actual.indexOf(expected) === -1;
                            default:   return true;
                        }
                    }

                    function evaluate() {
                        if (!scope.nbd_fields) { return; }
                        Object.keys(rules).forEach(function (fid) {
                            if (!scope.nbd_fields[fid]) { return; }
                            var show = rules[fid].every(rulePasses);
                            if (scope.nbd_fields[fid].enable !== show) {
                                scope.nbd_fields[fid].enable = show;
                            }
                        });
                    }

                    // Re-evaluate whenever any field value changes.
                    scope.$watch(function () {
                        if (!scope.nbd_fields) { return ''; }
                        var sig = '';
                        for (var k in scope.nbd_fields) {
                            if (Object.prototype.hasOwnProperty.call(scope.nbd_fields, k)) {
                                sig += k + ':' + scope.nbd_fields[k].value + '|';
                            }
                        }
                        return sig;
                    }, function () { evaluate(); });

                    $timeout(evaluate, 0); // initial pass after first render
                }
            };
        }]);
    } catch (e) { /* nboApp not registered yet — silently skip */ }
}());
