/**
 * Pricing Option Editor v3 — minimal scope helpers.
 *
 * Reuses everything else from admin-options.js (optionCtrl, getJsonFields,
 * field/attribute/view handlers). v3 only needs:
 *   • A dirty / saved-Xs-ago indicator that matches the VB pattern, so
 *     both editors feel like one product.
 *   • A beforeunload guard.
 *
 * If visual-builder.js is also loaded on this hook (e.g. an admin opens
 * v3 then jumps to VB), its existing $rootScope flags
 * (`vbDirty`, `vbSavedLabel`) are already populated — we just no-op.
 *
 * @package Storelly_Product_Builder
 */

( function () {
    'use strict';

    if ( typeof angular === 'undefined' || ! angular.module ) {
        return;
    }

    angular.module( 'optionApp' ).run( [
        '$rootScope', '$window', '$timeout',
        function ( $rootScope, $window, $timeout ) {

            // If VB helpers already initialised these globals, skip.
            if ( typeof $rootScope.vbDirty !== 'undefined' ) {
                return;
            }

            $rootScope.vbDirty       = false;
            $rootScope.vbLastSavedAt = null;
            $rootScope.vbSavedLabel  = '';

            function refreshLabel () {
                if ( $rootScope.vbDirty ) { $rootScope.vbSavedLabel = ''; return; }
                if ( ! $rootScope.vbLastSavedAt ) { $rootScope.vbSavedLabel = ''; return; }
                var diff = Math.floor( ( new Date().getTime() - $rootScope.vbLastSavedAt ) / 1000 );
                if ( diff < 5 ) {
                    $rootScope.vbSavedLabel = 'Saved just now';
                } else if ( diff < 60 ) {
                    $rootScope.vbSavedLabel = 'Saved ' + diff + 's ago';
                } else if ( diff < 3600 ) {
                    $rootScope.vbSavedLabel = 'Saved ' + Math.floor( diff / 60 ) + 'm ago';
                } else {
                    $rootScope.vbSavedLabel = 'Saved ' + Math.floor( diff / 3600 ) + 'h ago';
                }
            }

            $timeout( function () {
                // Deep watch on $scope.options so any change flips dirty.
                $rootScope.$watch(
                    function () {
                        var ctrlScope = angular.element(
                            document.querySelector( '[ng-controller="optionCtrl"]' )
                        ).scope();
                        return ctrlScope ? ctrlScope.options : null;
                    },
                    function ( newVal, oldVal ) {
                        if ( newVal && oldVal && newVal !== oldVal ) {
                            $rootScope.vbDirty = true;
                            $rootScope.vbSavedLabel = '';
                        }
                    },
                    true
                );

                setInterval( function () {
                    $rootScope.$applyAsync( refreshLabel );
                }, 10000 );
                refreshLabel();

                // Read the `message=created` URL flash from classic flow
                // OR a future v3-specific notice to stamp initial savedAt.
                if ( /message=created|saved/.test( $window.location.search ) ) {
                    $rootScope.vbLastSavedAt = new Date().getTime();
                    refreshLabel();
                }

                // Confirm before leaving with unsaved changes.
                angular.element( $window ).on( 'beforeunload', function ( e ) {
                    if ( $rootScope.vbDirty ) {
                        e.returnValue = '';
                        return '';
                    }
                } );

                // Clear dirty when the form actually submits (manual save).
                angular.element( document ).on( 'submit', 'form[name="nboForm"]', function () {
                    $rootScope.vbDirty = false;
                } );
            }, 200 );
        }
    ] );
} )();
