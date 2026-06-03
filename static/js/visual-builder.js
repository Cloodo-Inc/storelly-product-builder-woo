/**
 * Visual Builder — tiny helpers that extend the classic optionApp module
 * with VB-only scope methods. Loaded ONLY on the VB edit screen so the
 * classic Pricing Options editor is not touched.
 *
 * Each helper attaches to $rootScope so child scopes from any ng-repeat /
 * ng-if can call it via plain ng-click="vbXxx(...)".
 *
 * No new AJAX, no new data shape — every method mutates the existing
 * $scope.options tree the classic editor already understands. Save still
 * goes through getJsonFields() → spbwc_save_option() unchanged.
 *
 * @package Storelly_Product_Builder
 */

( function () {
    'use strict';

    if ( typeof angular === 'undefined' || ! angular.module ) {
        return;
    }

    /* ───────────────────────────────────────────────────────────
     * vbDropZone directive — wires native dragover / dragleave /
     * drop events to an Angular expression. Used by per-view image
     * cells (single file → set per-attr per-view image) and by the
     * component card body (multi-file → bulk-create attributes).
     *
     *   <button vb-drop-zone="vbHandleViewDrop($event, ...)"
     *           vb-drop-multi="false">
     *
     * Adds/removes `.is-drop-target` class on the element so CSS
     * can highlight it during dragover.
     * ─────────────────────────────────────────────────────────── */
    angular.module( 'optionApp' )
        .directive( 'vbDropZone', function () {
            return {
                restrict: 'A',
                link: function ( scope, element, attrs ) {
                    var el = element[ 0 ];
                    if ( ! el ) return;
                    el.addEventListener( 'dragover', function ( e ) {
                        if ( ! e.dataTransfer || ! e.dataTransfer.types ||
                             e.dataTransfer.types.indexOf( 'Files' ) === -1 ) {
                            return;
                        }
                        e.preventDefault();
                        e.stopPropagation();
                        e.dataTransfer.dropEffect = 'copy';
                        element.addClass( 'is-drop-target' );
                    } );
                    el.addEventListener( 'dragleave', function ( e ) {
                        // Only clear when leaving the element entirely, not a child.
                        if ( e.target !== el ) return;
                        element.removeClass( 'is-drop-target' );
                    } );
                    el.addEventListener( 'drop', function ( e ) {
                        if ( ! e.dataTransfer || ! e.dataTransfer.files ||
                             ! e.dataTransfer.files.length ) {
                            return;
                        }
                        e.preventDefault();
                        e.stopPropagation();
                        element.removeClass( 'is-drop-target' );
                        scope.$apply( function () {
                            scope.$eval( attrs.vbDropZone, { $event: e } );
                        } );
                    } );
                }
            };
        } )
        .run( [
        '$rootScope',
        '$window',
        '$timeout',
        function ( $rootScope, $window, $timeout ) {

            /* ───────────────────────────────────────────────────────────
             * Duplicate an attribute. Deep-clone via JSON to avoid
             * sharing references (pb_config slots also clone so per-view
             * images get independent state on the new attribute).
             * ─────────────────────────────────────────────────────────── */
            $rootScope.vbDuplicateAttribute = function ( field, attrIdx ) {
                if ( ! field || ! field.general || ! field.general.attributes ||
                     ! field.general.attributes.options ||
                     attrIdx < 0 || attrIdx >= field.general.attributes.options.length ) {
                    return;
                }
                var src = field.general.attributes.options[ attrIdx ];
                var copy;
                try {
                    copy = JSON.parse( JSON.stringify( src ) );
                } catch ( e ) {
                    return;
                }
                if ( typeof copy.name === 'string' && copy.name.length > 0 ) {
                    copy.name = copy.name + ' copy';
                }
                field.general.attributes.options.splice( attrIdx + 1, 0, copy );

                // Mirror the pb_config entry so per-view images carry over.
                if ( field.nbpb_type === 'nbpb_com' &&
                     angular.isArray( field.general.pb_config ) &&
                     field.general.pb_config[ attrIdx ] ) {
                    var pbSrc;
                    try {
                        pbSrc = JSON.parse( JSON.stringify( field.general.pb_config[ attrIdx ] ) );
                    } catch ( err ) {
                        pbSrc = [];
                    }
                    field.general.pb_config.splice( attrIdx + 1, 0, pbSrc );
                }
            };

            /* ───────────────────────────────────────────────────────────
             * Apply an attribute's image from one view to every other
             * view of the same attribute. Most common case (one swatch
             * fits all views) — saves 2-3 media-picker round-trips.
             * ─────────────────────────────────────────────────────────── */
            $rootScope.vbApplyImageToAllViews = function ( field, attrIdx, sourceViewIdx ) {
                if ( ! field || ! field.general || ! field.general.pb_config ||
                     ! field.general.pb_config[ attrIdx ] ||
                     ! field.general.pb_config[ attrIdx ][ 0 ] ||
                     ! angular.isArray( field.general.pb_config[ attrIdx ][ 0 ].views ) ) {
                    return;
                }
                var srcView = field.general.pb_config[ attrIdx ][ 0 ].views[ sourceViewIdx ];
                if ( ! srcView || ! srcView.image || srcView.image === '0' || srcView.image === 0 ) {
                    return;
                }
                angular.forEach( field.general.pb_config[ attrIdx ][ 0 ].views, function ( view, vi ) {
                    if ( vi === sourceViewIdx ) {
                        return;
                    }
                    view.image = srcView.image;
                    view.image_url = srcView.image_url;
                    view.display = 'on';
                } );
            };

            /* ───────────────────────────────────────────────────────────
             * Open the Product Builder frontend (the visual configurator
             * canvas, NOT the bare product detail page) in a brand-new
             * fullscreen window so the admin can verify the layered
             * composition exactly as the buyer sees it.
             *
             * - Sizing: matches the available screen so the canvas + UI
             *   fit without scrolling, regardless of monitor.
             * - Name: empty string forces a NEW window each click (a
             *   named target would reuse the same popup). Combined with
             *   the cache-bust query, every click yields a fresh paint
             *   of the current saved state.
             * ─────────────────────────────────────────────────────────── */
            $rootScope.vbOpenPreview = function ( $event, baseUrl ) {
                if ( $event && typeof $event.preventDefault === 'function' ) {
                    $event.preventDefault();
                    if ( typeof $event.stopPropagation === 'function' ) {
                        $event.stopPropagation();
                    }
                }
                if ( ! baseUrl ) {
                    return;
                }
                var sep = baseUrl.indexOf( '?' ) > -1 ? '&' : '?';
                var url = baseUrl + sep + '_vb_preview=' + new Date().getTime();
                var w   = ( $window.screen && $window.screen.availWidth )  ? $window.screen.availWidth  : 1440;
                var h   = ( $window.screen && $window.screen.availHeight ) ? $window.screen.availHeight : 900;
                $window.open(
                    url,
                    '_blank',
                    'popup=yes,noopener=yes,noreferrer=yes,scrollbars=yes,resizable=yes' +
                    ',width=' + w + ',height=' + h + ',top=0,left=0'
                );
            };

            /* ───────────────────────────────────────────────────────────
             * Dirty tracking — watch $scope.options deeply, mark dirty
             * on any change. Exposes vbDirty + vbSavedAgo for the hero
             * indicator. Suppresses initial digest by waiting one tick.
             * ─────────────────────────────────────────────────────────── */
            $rootScope.vbDirty = false;
            $rootScope.vbLastSavedAt = null;
            $rootScope.vbSavedLabel = '';

            // Update the relative "saved Xs ago" label every 10s.
            var refreshLabel = function () {
                if ( $rootScope.vbDirty ) {
                    $rootScope.vbSavedLabel = '';
                    return;
                }
                if ( ! $rootScope.vbLastSavedAt ) {
                    $rootScope.vbSavedLabel = '';
                    return;
                }
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
            };

            /* Auto-save (#3) — debounced background save without a page
             * reload. Reuses the existing classic save handler via the
             * SAME form submission, but intercepted in the capture phase
             * so we route through fetch() instead of native navigation.
             * On success: clear vbDirty, stamp vbLastSavedAt. On failure:
             * keep dirty and surface a toast so the merchant knows.
             *
             * Debounce: 30s after the last change. Manual Save bypasses
             * the timer and clears the pending save.
             */
            var AUTO_SAVE_DELAY = 30000;
            var autoSaveTimer = null;

            function scheduleAutoSave () {
                if ( autoSaveTimer ) {
                    clearTimeout( autoSaveTimer );
                }
                autoSaveTimer = setTimeout( function () {
                    triggerAutoSave();
                }, AUTO_SAVE_DELAY );
            }

            function triggerAutoSave () {
                if ( ! $rootScope.vbDirty ) return;
                var form = document.getElementById( 'spbwc-vb-form' );
                if ( ! form ) return;
                var ctrl = getCtrlScope();
                if ( ! ctrl || typeof ctrl.getJsonFields !== 'function' ) return;

                // Catastrophic-wipe guard: never auto-save an empty field set. A
                // transient empty model (load race, accidental clear) would
                // otherwise be persisted over the saved design and wipe every
                // field + design component. Skip silently; the PHP save handler
                // enforces the same rule as a backstop.
                if ( ! ctrl.options || ! angular.isArray( ctrl.options.fields ) || ctrl.options.fields.length === 0 ) {
                    $rootScope.vbSavingState = '';
                    $rootScope.vbSavedLabel  = '';
                    return;
                }

                form._vbAutoSaveMode = true;
                $rootScope.vbSavingState = 'saving';
                $rootScope.vbSavedLabel = 'Auto-saving…';
                // Light, transient cue so the merchant sees every background save.
                $rootScope.vbShowToast( 'Auto-saving…', 'info', '💾', 4000 );

                // getJsonFields() runs cleanse + populates $scope.jsonFields
                // then setTimeouts a form.submit() — our capture-phase
                // listener (installed below) intercepts it.
                ctrl.getJsonFields();
            }

            $timeout( function () {
                // Content signature — a JSON projection of the option that EXCLUDES
                // UI-only state (isExpand, hidden) and programmatic/derived data
                // (image_url, *_url, pb_config_flat, …). Auto-save now dirties only
                // when this meaningful signature changes, so expanding a panel,
                // hovering, or background image-URL enrichment no longer schedules a
                // save — and a transient digest artifact can't trigger a wipe.
                var VB_NOISE_KEYS = {
                    '$$hashKey': 1, isExpand: 1, hidden: 1, show_subattr: 1,
                    need_show: 1, template: 1, 'class': 1, imagep: 1,
                    pb_config_flat: 1, image_url: 1, bg_image_url: 1,
                    product_image_url: 1, component_icon_url: 1, image_link: 1,
                    image_title: 1, image_alt: 1, image_srcset: 1, image_sizes: 1,
                    image_caption: 1, full_src: 1, full_src_w: 1, full_src_h: 1
                };
                function vbStripNoise( val ) {
                    if ( angular.isArray( val ) ) {
                        var arr = [];
                        for ( var i = 0; i < val.length; i++ ) { arr.push( vbStripNoise( val[ i ] ) ); }
                        return arr;
                    }
                    if ( val && typeof val === 'object' ) {
                        var out = {};
                        for ( var k in val ) {
                            if ( ! val.hasOwnProperty( k ) || VB_NOISE_KEYS[ k ] ) { continue; }
                            out[ k ] = vbStripNoise( val[ k ] );
                        }
                        return out;
                    }
                    return val;
                }
                function vbContentSig( options ) {
                    if ( ! options ) { return null; }
                    try { return JSON.stringify( vbStripNoise( options ) ); }
                    catch ( e ) { return null; }
                }

                // Wait one tick so the initial localized data load does
                // not count as a "change".
                var watcher = $rootScope.$watch(
                    function () {
                        var ctrlScope = angular.element(
                            document.querySelector( '[ng-controller="optionCtrl"]' )
                        ).scope();
                        return ctrlScope ? vbContentSig( ctrlScope.options ) : null;
                    },
                    function ( newSig, oldSig ) {
                        // Only a genuine content change (not the first run, not a
                        // UI/programmatic mutation) marks the editor dirty.
                        if ( newSig !== null && oldSig !== null && newSig !== oldSig ) {
                            $rootScope.vbDirty = true;
                            $rootScope.vbSavedLabel = '';
                            scheduleAutoSave();
                        }
                    }
                    /* string signature → default value compare (no deep watch) */
                );

                // Refresh the relative label every 10s.
                setInterval( function () {
                    $rootScope.$applyAsync( refreshLabel );
                }, 10000 );

                // Initial label.
                refreshLabel();

                // Read URL flash-notice "saved" to set initial timestamp
                // (the page just reloaded after a save).
                if ( /vb_notice=saved/.test( $window.location.search ) ) {
                    $rootScope.vbLastSavedAt = new Date().getTime();
                    refreshLabel();
                }

                // Prevent accidental data loss — confirm before leaving
                // when dirty. Only active when something genuinely changed.
                angular.element( $window ).on( 'beforeunload', function ( e ) {
                    if ( $rootScope.vbDirty ) {
                        // Modern browsers ignore the custom string; setting
                        // returnValue triggers the native confirm dialog.
                        e.returnValue = '';
                        return '';
                    }
                } );

                /* Form submit interceptor (capture phase) — splits manual
                 * Save (let it navigate) from auto-save (route via fetch). */
                var form = document.getElementById( 'spbwc-vb-form' );
                if ( form ) {
                    form.addEventListener( 'submit', function ( e ) {
                        if ( ! form._vbAutoSaveMode ) {
                            // Manual save — also cancel any pending debounce
                            // so we don't accidentally double-fire.
                            if ( autoSaveTimer ) {
                                clearTimeout( autoSaveTimer );
                                autoSaveTimer = null;
                            }
                            $rootScope.vbDirty = false;
                            return; // let the browser submit normally.
                        }

                        // Auto-save path: intercept, send via fetch().
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        form._vbAutoSaveMode = false;

                        var fd  = new $window.FormData( form );
                        var url = form.action || $window.location.href;
                        $window.fetch( url, {
                            method: 'POST',
                            body: fd,
                            credentials: 'include',
                            redirect: 'manual'
                        } ).then( function ( r ) {
                            // For redirect:manual, opaqueredirect = handler ran +
                            // tried to redirect (its normal post-save behaviour).
                            var ok = ( r.type === 'opaqueredirect' || r.ok ||
                                       r.status === 0 || r.status === 302 );
                            $rootScope.$applyAsync( function () {
                                if ( ok ) {
                                    $rootScope.vbDirty = false;
                                    $rootScope.vbLastSavedAt = new Date().getTime();
                                    $rootScope.vbSavingState = 'saved';
                                    refreshLabel();
                                } else {
                                    $rootScope.vbSavingState = 'error';
                                    $rootScope.vbSavedLabel = 'Auto-save failed';
                                }
                            } );
                            if ( ok ) {
                                $rootScope.vbShowToast( 'Auto-saved', 'success', '✓', 2000 );
                            } else {
                                $rootScope.vbShowToast(
                                    'Auto-save failed (HTTP ' + r.status + '). Your changes are still in the editor.',
                                    'warning', '⚠', 5000
                                );
                            }
                        } ).catch( function ( err ) {
                            $rootScope.$applyAsync( function () {
                                $rootScope.vbSavingState = 'error';
                                $rootScope.vbSavedLabel = 'Auto-save failed';
                            } );
                            $rootScope.vbShowToast(
                                'Auto-save network error. Your changes are still in the editor.',
                                'warning', '⚠', 5000
                            );
                        } );
                    }, true /* capture */ );
                }
            }, 200 );

            /* ───────────────────────────────────────────────────────────
             * Upload a single File to the WP media library via the
             * built-in `upload-attachment` AJAX action.
             *
             * Why XHR directly instead of wp.media.frames.file_frame:
             * the frame is a UI dialog — for drag-drop we want a silent
             * upload. Mirrors what `wp.Uploader` does internally but
             * lighter and synchronous to a single file.
             *
             * Locates the upload nonce from any of the known WP globals
             * exposed by wp_enqueue_media() so we don't depend on a
             * specific WP version's variable name.
             *
             * @param {File}     file
             * @param {Function} onSuccess  receives { id, url, sizes }
             * @param {Function} onError    receives error message
             * ─────────────────────────────────────────────────────────── */
            $rootScope.vbUploadFile = function ( file, onSuccess, onError ) {
                if ( ! file || ! ( file instanceof $window.File ) ) {
                    if ( onError ) onError( 'Invalid file' );
                    return;
                }
                if ( ! /^image\//.test( file.type ) ) {
                    if ( onError ) onError( 'Only image files can be dropped' );
                    return;
                }

                // Find WP upload nonce from any of the known WP globals.
                var nonce = '';
                try {
                    if ( $window.wp && $window.wp.media && $window.wp.media.view &&
                         $window.wp.media.view.settings &&
                         $window.wp.media.view.settings.post &&
                         $window.wp.media.view.settings.post.nonce ) {
                        nonce = $window.wp.media.view.settings.post.nonce;
                    }
                } catch ( e ) { /* fall through */ }
                if ( ! nonce && $window._wpUploaderInit &&
                     $window._wpUploaderInit.media && $window._wpUploaderInit.media.nonce ) {
                    nonce = $window._wpUploaderInit.media.nonce;
                }
                if ( ! nonce && $window._wpMediaModelsL10n && $window._wpMediaModelsL10n.nonce ) {
                    nonce = $window._wpMediaModelsL10n.nonce;
                }
                if ( ! nonce ) {
                    if ( onError ) onError( 'WP upload nonce not found — try refreshing the page.' );
                    return;
                }

                var fd = new $window.FormData();
                fd.append( 'async-upload', file, file.name );
                fd.append( 'name', file.name );
                fd.append( 'action', 'upload-attachment' );
                fd.append( '_wpnonce', nonce );

                var xhr = new $window.XMLHttpRequest();
                xhr.open( 'POST', $window.ajaxurl, true );
                xhr.onload = function () {
                    var resp;
                    try { resp = JSON.parse( xhr.responseText ); }
                    catch ( e ) {
                        if ( onError ) onError( 'Invalid server response' );
                        return;
                    }
                    if ( resp && resp.success && resp.data ) {
                        if ( onSuccess ) onSuccess( resp.data );
                    } else {
                        var msg = ( resp && resp.data && resp.data.message ) || 'Upload failed';
                        if ( onError ) onError( msg );
                    }
                };
                xhr.onerror = function () {
                    if ( onError ) onError( 'Network error during upload' );
                };
                xhr.send( fd );
            };

            /* Pick the best display URL from a WP attachment response —
             * matches the `set_attribute_image` / `set_view_config_image`
             * behaviour in classic admin-options.js so dropped images
             * look identical to picker-uploaded ones. */
            function attUrl ( att ) {
                if ( att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url ) {
                    return att.sizes.thumbnail.url;
                }
                return att.url;
            }

            function getCtrlScope () {
                return angular.element(
                    document.querySelector( '[ng-controller="optionCtrl"]' )
                ).scope();
            }

            /* ───────────────────────────────────────────────────────────
             * Drop a single file onto a per-view image cell. Uploads it
             * then writes `image` + `image_url` + `display: 'on'` onto
             * `pb_config[attrIdx][0].views[viewIdx]`.
             * ─────────────────────────────────────────────────────────── */
            $rootScope.vbHandleViewDrop = function ( $event, fieldIndex, attrIdx, viewIdx ) {
                if ( ! $event || ! $event.dataTransfer || ! $event.dataTransfer.files.length ) {
                    return;
                }
                var file = $event.dataTransfer.files[ 0 ];
                var ctrl = getCtrlScope();
                if ( ! ctrl || ! ctrl.options || ! ctrl.options.fields ||
                     ! ctrl.options.fields[ fieldIndex ] ) {
                    return;
                }
                var field = ctrl.options.fields[ fieldIndex ];
                // Ensure pb_config slot exists.
                if ( ! field.general.pb_config ) field.general.pb_config = [];
                if ( ! field.general.pb_config[ attrIdx ] ) field.general.pb_config[ attrIdx ] = [];
                if ( ! field.general.pb_config[ attrIdx ][ 0 ] ) field.general.pb_config[ attrIdx ][ 0 ] = { views: [] };
                if ( ! field.general.pb_config[ attrIdx ][ 0 ].views ) field.general.pb_config[ attrIdx ][ 0 ].views = [];
                if ( ! field.general.pb_config[ attrIdx ][ 0 ].views[ viewIdx ] ) {
                    field.general.pb_config[ attrIdx ][ 0 ].views[ viewIdx ] = {};
                }
                var cfg = field.general.pb_config[ attrIdx ][ 0 ].views[ viewIdx ];
                cfg._uploading = true;
                ctrl.$applyAsync();

                $rootScope.vbUploadFile( file, function ( att ) {
                    ctrl.$applyAsync( function () {
                        cfg.image     = att.id;
                        cfg.image_url = attUrl( att );
                        cfg.display   = 'on';
                        cfg._uploading = false;
                    } );
                    $rootScope.vbShowToast( 'Image uploaded — ' + file.name, 'success', '✓' );
                }, function ( err ) {
                    ctrl.$applyAsync( function () { cfg._uploading = false; } );
                    $rootScope.vbShowToast( 'Upload failed: ' + err, 'danger', '⚠' );
                } );
            };

            /* ───────────────────────────────────────────────────────────
             * Drop ONE OR MANY files onto a component card body. Each
             * file becomes an attribute swatch: name = filename (no ext),
             * price = "" (no surcharge by default), image = uploaded id.
             *
             * Used as a power-user shortcut to seed 10+ swatches in a
             * single drag instead of 10× "Add option" + 10× media-picker
             * round-trips.
             * ─────────────────────────────────────────────────────────── */
            $rootScope.vbHandleBulkAttributeDrop = function ( $event, fieldIndex ) {
                if ( ! $event || ! $event.dataTransfer || ! $event.dataTransfer.files.length ) {
                    return;
                }
                var files = Array.prototype.slice.call( $event.dataTransfer.files )
                    .filter( function ( f ) { return /^image\//.test( f.type ); } );
                if ( ! files.length ) {
                    $rootScope.vbShowToast( 'Drop image files only.', 'warning', '⚠' );
                    return;
                }
                var ctrl = getCtrlScope();
                if ( ! ctrl || typeof ctrl.add_attribute !== 'function' ) return;
                var field = ctrl.options.fields[ fieldIndex ];
                if ( ! field ) return;

                $rootScope.vbShowToast(
                    'Uploading ' + files.length + ' image' + ( files.length > 1 ? 's' : '' ) + '…',
                    'info', '⬆'
                );

                var uploaded = 0;
                var failed   = 0;
                files.forEach( function ( file ) {
                    // Create the slot immediately so the user sees the
                    // ng-repeat row appear (with a loading state).
                    ctrl.add_attribute( fieldIndex, 'attributes' );
                    var slotIdx = field.general.attributes.options.length - 1;
                    var op = field.general.attributes.options[ slotIdx ];
                    op.name = file.name.replace( /\.[^.]+$/, '' );
                    op._uploading = true;
                    ctrl.$applyAsync();

                    $rootScope.vbUploadFile( file, function ( att ) {
                        ctrl.$applyAsync( function () {
                            op.image     = att.id;
                            op.image_url = attUrl( att );
                            op._uploading = false;
                            uploaded++;
                            if ( uploaded + failed === files.length ) {
                                $rootScope.vbShowToast(
                                    uploaded + ' attribute' + ( uploaded > 1 ? 's' : '' ) + ' created' +
                                    ( failed ? ' (' + failed + ' failed)' : '' ),
                                    failed ? 'warning' : 'success',
                                    failed ? '⚠' : '✨'
                                );
                            }
                        } );
                    }, function ( err ) {
                        ctrl.$applyAsync( function () {
                            op._uploading = false;
                            failed++;
                            if ( uploaded + failed === files.length ) {
                                $rootScope.vbShowToast(
                                    'Bulk upload finished — ' + uploaded + ' ok, ' + failed + ' failed.',
                                    'warning', '⚠'
                                );
                            }
                        } );
                    } );
                } );
            };

            /* ───────────────────────────────────────────────────────────
             * Toast — transient inline message. Auto-clears after ~3s
             * unless dismissed sooner by a later call.
             * ─────────────────────────────────────────────────────────── */
            $rootScope.vbToast = { visible: false, message: '', type: 'info', icon: '' };
            var toastTimer = null;
            $rootScope.vbShowToast = function ( message, type, icon, duration ) {
                $rootScope.vbToast = {
                    visible: true,
                    message: message,
                    type: type || 'info',
                    icon: icon || ''
                };
                if ( toastTimer ) {
                    clearTimeout( toastTimer );
                }
                toastTimer = setTimeout( function () {
                    $rootScope.$applyAsync( function () {
                        $rootScope.vbToast.visible = false;
                    } );
                }, duration || 3000 );
            };

            /* ───────────────────────────────────────────────────────────
             * vbAddField — wraps the classic $scope.add_field() with
             * affordances missing from the bare handler:
             *   1. Toast notification ("Designer Component added")
             *   2. Auto-expand the new card
             *   3. Smooth scroll-to + brief highlight flash so the
             *      admin sees where the new card landed.
             * Called from the "+ Designer Component / Text / Image"
             * palette chips at the top of the edit screen.
             * ─────────────────────────────────────────────────────────── */
            $rootScope.vbAddField = function ( type ) {
                var ctrlScope = angular.element(
                    document.querySelector( '[ng-controller="optionCtrl"]' )
                ).scope();
                if ( ! ctrlScope || typeof ctrlScope.add_field !== 'function' ) {
                    return;
                }
                ctrlScope.add_field( type, type );

                // Wait one digest so the new entry appears in $scope.options.fields.
                $timeout( function () {
                    var fields = ctrlScope.options && ctrlScope.options.fields;
                    if ( ! fields || ! fields.length ) {
                        return;
                    }
                    // Find the LAST field whose nbpb_type matches — that's the
                    // one we just added (add_field appends).
                    var newIdx = -1;
                    for ( var i = fields.length - 1; i >= 0; i-- ) {
                        if ( fields[ i ] && fields[ i ].nbpb_type === type ) {
                            newIdx = i;
                            break;
                        }
                    }
                    if ( newIdx < 0 ) {
                        return;
                    }
                    fields[ newIdx ].isExpand = true;
                    ctrlScope.$applyAsync();

                    var label = type === 'nbpb_text'   ? 'Designer Text'
                              : type === 'nbpb_image'  ? 'Designer Image'
                              :                          'Designer Component';
                    var icon  = type === 'nbpb_text'   ? '✍️'
                              : type === 'nbpb_image'  ? '🖼️'
                              :                          '⚛';
                    $rootScope.vbShowToast(
                        label + ' added — scroll down to configure it.',
                        'success',
                        icon
                    );

                    // Scroll + flash after the digest renders the expanded card.
                    $timeout( function () {
                        var el = document.querySelector(
                            '.spbwc-vb-comp-card[data-field-index="' + newIdx + '"]'
                        );
                        if ( ! el ) {
                            return;
                        }
                        if ( typeof el.scrollIntoView === 'function' ) {
                            el.scrollIntoView( { behavior: 'smooth', block: 'center' } );
                        }
                        el.classList.add( 'is-just-added' );
                        setTimeout( function () {
                            el.classList.remove( 'is-just-added' );
                        }, 1600 );
                    }, 100 );
                }, 80 );
            };

            /* ───────────────────────────────────────────────────────────
             * 2-stage delete confirmation (no native window.confirm).
             *
             * First click  → set a transient `_confirmDelete` flag on the
             *                target object (field or attribute). CSS picks
             *                it up and morphs the button to red "click
             *                again to delete". A timeout clears the flag
             *                after 3s so an abandoned confirm reverts.
             * Second click → flag is already set → run the actual splice.
             *
             * No native confirm popup — fully styled, accessible, and
             * keyboard-friendly (Enter on the focused button counts as
             * the second click).
             * ─────────────────────────────────────────────────────────── */
            var deleteTimers = {};

            $rootScope.vbConfirmDelete = function ( target, key ) {
                if ( ! target ) {
                    return false;
                }
                var flagKey = key || '_confirmDelete';
                if ( target[ flagKey ] ) {
                    // 2nd click — clear flag, signal caller to delete.
                    target[ flagKey ] = false;
                    if ( deleteTimers[ flagKey ] ) {
                        clearTimeout( deleteTimers[ flagKey ] );
                        delete deleteTimers[ flagKey ];
                    }
                    return true;
                }
                // 1st click — arm the flag, schedule revert.
                target[ flagKey ] = true;
                if ( deleteTimers[ flagKey ] ) {
                    clearTimeout( deleteTimers[ flagKey ] );
                }
                deleteTimers[ flagKey ] = setTimeout( function () {
                    $rootScope.$applyAsync( function () {
                        target[ flagKey ] = false;
                    } );
                }, 3000 );
                return false;
            };

            /* Convenience wrappers used by ng-click in the templates so the
             * confirm flag + splice happen in one expression. */
            $rootScope.vbDeleteComponent = function ( fieldIndex ) {
                var ctrlScope = angular.element(
                    document.querySelector( '[ng-controller="optionCtrl"]' )
                ).scope();
                if ( ! ctrlScope || ! ctrlScope.options ||
                     ! ctrlScope.options.fields ||
                     ! ctrlScope.options.fields[ fieldIndex ] ) {
                    return;
                }
                var field = ctrlScope.options.fields[ fieldIndex ];
                if ( $rootScope.vbConfirmDelete( field ) ) {
                    ctrlScope.options.fields.splice( fieldIndex, 1 );
                    if ( typeof ctrlScope.initfieldValue === 'function' ) {
                        ctrlScope.initfieldValue();
                    }
                    ctrlScope.$applyAsync();
                }
            };

            $rootScope.vbDeleteAttribute = function ( field, attrIdx ) {
                if ( ! field || ! field.general || ! field.general.attributes ||
                     ! field.general.attributes.options ||
                     attrIdx < 0 || attrIdx >= field.general.attributes.options.length ) {
                    return;
                }
                var op = field.general.attributes.options[ attrIdx ];
                if ( $rootScope.vbConfirmDelete( op ) ) {
                    field.general.attributes.options.splice( attrIdx, 1 );
                    // Also drop the matching pb_config slot so per-view image
                    // entries don't dangle on the now-orphaned index.
                    if ( field.nbpb_type === 'nbpb_com' &&
                         angular.isArray( field.general.pb_config ) &&
                         attrIdx < field.general.pb_config.length ) {
                        field.general.pb_config.splice( attrIdx, 1 );
                    }
                }
            };

            /* ───────────────────────────────────────────────────────────
             * Pre-save validation (#10).
             *
             * Returns an array of issue strings. Empty = clean. Issues
             * are SOFT warnings — save still proceeds, but the merchant
             * sees a clear toast describing what looks suspicious so
             * they can fix obvious oversights (component with 0 attrs,
             * 0 views, attribute with empty name, …).
             * ─────────────────────────────────────────────────────────── */
            $rootScope.vbValidateOptions = function ( options ) {
                var issues = [];
                if ( ! options ) return issues;

                var fields = options.fields || [];
                var views  = options.views  || [];
                var hasNbpb = false;

                angular.forEach( fields, function ( f, idx ) {
                    if ( ! f || ! f.nbpb_type ) return;
                    hasNbpb = true;
                    var title = ( f.general && f.general.title && f.general.title.value ) || ( '#' + ( idx + 1 ) );

                    if ( f.nbpb_type === 'nbpb_com' ) {
                        var opts = ( f.general.attributes && f.general.attributes.options ) || [];
                        if ( opts.length === 0 ) {
                            issues.push( '"' + title + '" has no attribute options. Buyer will see an empty picker.' );
                        }
                        // Empty attribute names
                        var unnamed = 0;
                        angular.forEach( opts, function ( op ) {
                            if ( ! op || ! op.name || ! String( op.name ).trim() ) {
                                unnamed++;
                            }
                        } );
                        if ( unnamed > 0 ) {
                            issues.push( '"' + title + '": ' + unnamed +
                                ' attribute' + ( unnamed > 1 ? 's have' : ' has' ) + ' an empty name.' );
                        }
                    }
                } );

                if ( hasNbpb && views.length === 0 ) {
                    issues.push( 'No views configured yet — add at least one (Front / Back / …) so attributes have a canvas to render on.' );
                }

                return issues;
            };

            /* Save handler wrapper used by the Save Visual buttons. Runs
             * the validator, shows a warning toast if anything looks off,
             * then calls the classic getJsonFields() which submits the
             * form. Issues do NOT block the save — sometimes a half-built
             * draft is exactly what the merchant wants to persist. */
            $rootScope.vbSaveWithValidation = function () {
                var ctrl = angular.element(
                    document.querySelector( '[ng-controller="optionCtrl"]' )
                ).scope();
                if ( ! ctrl || typeof ctrl.getJsonFields !== 'function' ) {
                    return;
                }
                var issues = $rootScope.vbValidateOptions( ctrl.options );
                if ( issues.length > 0 ) {
                    var msg = ( issues.length === 1 )
                        ? issues[ 0 ]
                        : ( issues.length + ' issues to review:\n• ' + issues.slice( 0, 3 ).join( '\n• ' ) +
                            ( issues.length > 3 ? '\n• …and ' + ( issues.length - 3 ) + ' more' : '' ) );
                    $rootScope.vbShowToast( msg, 'warning', '⚠', 5000 );
                }
                ctrl.getJsonFields();
            };

            /* ───────────────────────────────────────────────────────────
             * Component sidebar nav — scroll to a component card by id
             * and expand it. Called from the sidebar items.
             * ─────────────────────────────────────────────────────────── */
            $rootScope.vbFocusComponent = function ( fieldIndex ) {
                var ctrlScope = angular.element(
                    document.querySelector( '[ng-controller="optionCtrl"]' )
                ).scope();
                if ( ! ctrlScope || ! ctrlScope.options ||
                     ! ctrlScope.options.fields ||
                     ! ctrlScope.options.fields[ fieldIndex ] ) {
                    return;
                }
                ctrlScope.options.fields[ fieldIndex ].isExpand = true;
                ctrlScope.$applyAsync();
                // Scroll after the digest renders the expanded card so the
                // body height is accounted for.
                $timeout( function () {
                    var el = document.querySelector(
                        '.spbwc-vb-comp-card[data-field-index="' + fieldIndex + '"]'
                    );
                    if ( el && typeof el.scrollIntoView === 'function' ) {
                        el.scrollIntoView( { behavior: 'smooth', block: 'start' } );
                    }
                }, 80 );
            };
        },
    ] );
} )();
