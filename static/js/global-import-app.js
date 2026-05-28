(() => {
    const { createApp } = Vue;
    const GI_CONFIG = window.SPBWC_GLOBAL_IMPORT || {
        ajax_url: '',
        admin_url: '',
        page_slug: 'storelly-product-builder-for-woocommerce-options/global-import',
        nonce: '',
        rest_url: '',
        rest_nonce: '',
        assets_url: '',
        background: false,
        i18n: {
            upload_title: 'Upload file',
            import_title: 'Import products',
            confirm_delete: 'Are you sure you want to delete this export?',
            loading_products: 'Loading products…'
        }
    };
    createApp({
        data() {
            return {
                config: GI_CONFIG,
                importId: '',
                runId: '',
                rows: [],
                total: 0,
                page: 1,
                perPage: 12,
                batchSize: 5,
                search: '',
                category: '',
                stock: '',
                priceMin: '',
                priceMax: '',
                sortBy: 'name',
                sortDir: 'asc',
                selected: {},
                loading: false,
                uploading: false,
                dragActive: false,
                importRunning: false,
                importStatus: '',
                importQueue: [],
                totalSelected: 0,
                completed: 0,
                processed: 0,
                logs: [],
                logOffset: 0,
                toasts: [],
                summary: { total: 0, imported_ok: 0, issues: 0, not_imported: 0 },
                importState: '',
                expandedRows: {},
                reimporting: {},
                health: null,
                healthLoading: false
            };
        },
        computed: {
            totalPages() {
                return Math.ceil(this.total / this.perPage) || 1;
            },
            progressPercent() {
                if (!this.totalSelected) return 0;
                return Math.min(100, Math.round((this.completed / this.totalSelected) * 100));
            },
            selectedIds() {
                return Object.keys(this.selected).filter(rowId => !!this.selected[rowId]);
            },
            selectableRows() {
                return this.rows.filter(row => !(row.import && row.import.imported));
            },
            allRowsOnPageSelected() {
                return this.selectableRows.length > 0 && this.selectableRows.every(row => !!this.selected[row.row_id]);
            }
        },
        methods: {
            parseGallery(val) {
                if (!val) return [];
                if (Array.isArray(val)) return val.filter(Boolean);
                const s = String(val || '').trim();
                if (!s) return [];
                try {
                    const arr = JSON.parse(s);
                    if (Array.isArray(arr)) return arr.filter(Boolean);
                } catch (e) {}
                return s.split(',').map(v => v.trim()).filter(Boolean);
            },
            hasManyImages(row) {
                if (!row || !row.raw) return false;
                if (row.raw.hasManyImages === true) return true;
                const gallery = this.parseGallery(row.raw.gallery);
                return gallery.length >= 5;
            },
            buildFiltersPayload(actionName) {
                return {
                    action: actionName,
                    nonce: this.config.nonce,
                    import_id: this.importId,
                    search: this.search,
                    category: this.category,
                    stock: this.stock,
                    price_min: this.priceMin,
                    price_max: this.priceMax,
                    sort_by: this.sortBy,
                    sort_dir: this.sortDir,
                    import_state: this.importState
                };
            },
            setImportState(state) {
                this.importState = state;
                this.page = 1;
                this.clearSelection();
                this.fetchList();
            },
            statusInfo(row) {
                const im = (row && row.import) || {};
                if (!im.imported) {
                    return { key: 'none', label: 'Not imported', cls: 'is-none' };
                }
                if (im.status === 'ok') {
                    return { key: 'ok', label: 'Imported', cls: 'is-ok' };
                }
                return { key: 'issues', label: 'Imported with issues', cls: 'is-issues' };
            },
            toggleExpand(row) {
                const id = row.row_id;
                if (this.expandedRows[id]) {
                    delete this.expandedRows[id];
                } else {
                    this.expandedRows[id] = true;
                }
            },
            toast(message, type) {
                const id = Date.now() + Math.random();
                this.toasts.push({ id, message, type: type || '' });
                setTimeout(() => {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }, 3800);
            },
            clearSelection() {
                this.selected = {};
            },
            onFilterChange() {
                this.page = 1;
                this.clearSelection();
                this.fetchList();
            },
            openFilePicker() {
                this.$refs.fileInput.click();
            },
            handleFileChange(e) {
                const file = e.target.files[0];
                if (file) this.uploadFile(file);
            },
            handleDrop(e) {
                e.preventDefault();
                this.dragActive = false;
                const file = e.dataTransfer.files[0];
                if (file) this.uploadFile(file);
            },
            handleDragOver(e) {
                e.preventDefault();
                this.dragActive = true;
            },
            handleDragLeave() {
                this.dragActive = false;
            },
            uploadFile(file) {
                this.uploading = true;
                const data = new FormData();
                data.append('action', 'spbwc_global_import_upload');
                data.append('nonce', this.config.nonce);
                data.append('file', file);
                jQuery.ajax({
                    url: this.config.ajax_url,
                    method: 'POST',
                    data,
                    contentType: false,
                    processData: false
                }).done(res => {
                    if (res.success) {
                        this.importId = res.data.import_id;
                        this.total = res.data.total;
                        this.rows = res.data.preview || [];
                        this.page = 1;
                        this.clearSelection();
                        this.toast('File uploaded', 'success');
                        this.fetchList();
                    } else {
                        this.toast('Upload failed', 'error');
                    }
                }).fail(() => {
                    this.toast('Upload failed', 'error');
                }).always(() => {
                    this.uploading = false;
                });
            },
            fetchList() {
                this.loading = true;
                const payload = this.buildFiltersPayload('spbwc_global_import_list');
                payload.page = this.page;
                payload.per_page = this.perPage;
                jQuery.post(this.config.ajax_url, payload).done(res => {
                    if (res.success) {
                        this.rows = res.data.items;
                        this.total = res.data.total;
                        if (res.data.summary) {
                            this.summary = res.data.summary;
                        }
                    }
                }).always(() => {
                    this.loading = false;
                });
            },
            selectAllFiltered() {
                this.loading = true;
                const payload = this.buildFiltersPayload('spbwc_global_import_row_ids');
                payload.import_state = 'none';
                jQuery.post(this.config.ajax_url, payload).done(res => {
                    if (res.success && Array.isArray(res.data.row_ids)) {
                        const selectedMap = {};
                        res.data.row_ids.forEach(rowId => {
                            selectedMap[String(rowId)] = true;
                        });
                        this.selected = selectedMap;
                        if (res.data.row_ids.length) {
                            this.toast('Selected all filtered products');
                        } else {
                            this.toast('No products to select');
                        }
                    } else {
                        this.toast('Select all failed');
                    }
                }).fail(() => {
                    this.toast('Select all failed');
                }).always(() => {
                    this.loading = false;
                });
            },
            toggleSelectAll(e) {
                if (e.target.checked) {
                    this.selectAllFiltered();
                } else {
                    this.clearSelection();
                }
            },
            toggleRow(row) {
                if (row.import && row.import.imported) {
                    return;
                }
                if (this.selected[row.row_id]) {
                    delete this.selected[row.row_id];
                } else {
                    this.selected[row.row_id] = true;
                }
            },
            changeSort(key) {
                if (this.loading) {
                    return;
                }
                if (this.sortBy === key) {
                    this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortBy = key;
                    this.sortDir = 'asc';
                }
                this.clearSelection();
                this.page = 1;
                this.fetchList();
            },
            startImport() {
                this.importQueue = Array.from(new Set(this.selectedIds.map(rowId => String(rowId))));
                if (!this.importQueue.length) {
                    this.toast('No items selected');
                    return;
                }
                this.runId = 'run-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
                this.importRunning = true;
                this.importStatus = 'Initializing import...';
                this.totalSelected = this.importQueue.length;
                this.completed = 0;
                this.processed = 0;
                this.logs = [];
                this.logOffset = 0;
                if (this.config.background) {
                    this.enqueueImport();
                } else {
                    this.runBatch();
                    this.pollLog();
                }
            },
            enqueueImport() {
                this.importStatus = 'Queuing background import…';
                jQuery.ajax({
                    url: this.config.ajax_url,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'spbwc_global_import_enqueue',
                        nonce: this.config.nonce,
                        import_id: this.importId,
                        run_id: this.runId,
                        row_ids: this.importQueue.slice(),
                        batch: this.batchSize
                    }
                }).done(res => {
                    if (res.success) {
                        this.importStatus = 'Importing in background…';
                        this.toast('Import queued — it will keep running even if you close this page.', 'success');
                        this.pollProgress();
                        this.pollLog();
                    } else if (res.data && res.data.code === 'no_background') {
                        // Action Scheduler unavailable — fall back to in-page batches.
                        this.runBatch();
                        this.pollLog();
                    } else {
                        this.importStatus = '';
                        this.importRunning = false;
                        this.toast((res.data && res.data.message) ? res.data.message : 'Failed to queue import', 'error');
                    }
                }).fail(() => {
                    this.importStatus = '';
                    this.importRunning = false;
                    this.toast('Failed to queue import', 'error');
                });
            },
            pollProgress() {
                if (!this.importRunning) return;
                jQuery.post(this.config.ajax_url, {
                    action: 'spbwc_global_import_progress',
                    nonce: this.config.nonce,
                    run_id: this.runId,
                    import_id: this.importId
                }).done(res => {
                    if (res.success && res.data.job) {
                        const j = res.data.job;
                        this.totalSelected = j.total;
                        this.completed = j.processed;
                        this.processed = j.succeeded;
                        if (j.status === 'done' || j.status === 'error') {
                            this.importRunning = false;
                            this.importStatus = '';
                            this.clearSelection();
                            this.toast('Import finished — ' + j.succeeded + ' imported, ' + j.failed + ' failed', j.failed ? '' : 'success');
                            this.fetchList();
                            return;
                        }
                        this.importStatus = j.current_name
                            ? ('Importing: ' + j.current_name + ' (' + j.processed + '/' + j.total + ')')
                            : ('Importing in background… (' + j.processed + '/' + j.total + ')');
                    }
                }).always(() => {
                    if (this.importRunning) setTimeout(() => this.pollProgress(), 1500);
                });
            },
            resumeBackgroundJob() {
                if (!this.config.background) return;
                jQuery.post(this.config.ajax_url, {
                    action: 'spbwc_global_import_progress',
                    nonce: this.config.nonce,
                    import_id: this.importId
                }).done(res => {
                    if (res.success && res.data.job && (res.data.job.status === 'running' || res.data.job.status === 'queued')) {
                        const j = res.data.job;
                        this.runId = j.run_id;
                        this.importRunning = true;
                        this.totalSelected = j.total;
                        this.completed = j.processed;
                        this.processed = j.succeeded;
                        this.importStatus = 'Resuming background import…';
                        this.logs = [];
                        this.logOffset = 0;
                        this.toast('A background import is still running — showing live progress.');
                        this.pollProgress();
                        this.pollLog();
                    }
                });
            },
            runBatch() {
                if (!this.importQueue.length) {
                    this.importRunning = false;
                    this.importStatus = '';
                    this.clearSelection();
                    this.toast('Import completed', 'success');
                    this.fetchList();
                    return;
                }
                const batch = this.importQueue.splice(0, this.batchSize);
                this.importStatus = `Importing products... (Current batch: ${batch.length} items)`;
                jQuery.ajax({
                    url: this.config.ajax_url,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'spbwc_global_import_run',
                        nonce: this.config.nonce,
                        import_id: this.importId,
                        run_id: this.runId,
                        row_ids: batch,
                        batch: this.batchSize
                    }
                }).done(res => {
                    if (res.success) {
                        const processedRows = Array.isArray(res.data && res.data.processed) ? res.data.processed : [];
                        const importErrors = Array.isArray(res.data && res.data.errors) ? res.data.errors : [];
                        const attempted = Number(res.data && res.data.attempted ? res.data.attempted : batch.length);
                        this.completed += attempted;
                        this.processed += processedRows.length;
                        processedRows.forEach(item => {
                            if (item && item.row_id) {
                                delete this.selected[String(item.row_id)];
                            }
                        });
                        // if (importErrors.length) {
                        //     this.toast('Some items failed');
                        // }
                    } else {
                        this.importStatus = 'Batch failed.';
                        this.toast('Import failed', 'error');
                        this.importQueue = [];
                        this.importRunning = false;
                    }
                }).fail(xhr => {
                    this.importStatus = 'Network error during import.';
                    const message = xhr && xhr.responseJSON && xhr.responseJSON.data
                        ? xhr.responseJSON.data
                        : 'Import failed';
                    this.toast(message, 'error');
                    this.importQueue = [];
                    this.importRunning = false;
                }).always(() => {
                    if (this.importRunning) {
                        setTimeout(() => this.runBatch(), 50);
                    }
                });
            },
            pollLog() {
                if (!this.importRunning) return;
                jQuery.post(this.config.ajax_url, {
                    action: 'spbwc_global_import_log',
                    nonce: this.config.nonce,
                    import_id: this.importId,
                    run_id: this.runId,
                    offset: this.logOffset
                }).done(res => {
                    if (res.success) {
                        this.logs = this.logs.concat(res.data.lines);
                        this.logOffset = res.data.offset;
                        if (res.data.lines.length > 0) {
                            // Update status with latest log line if it's informative
                            const lastLine = res.data.lines[res.data.lines.length - 1];
                            if (lastLine.includes('Downloading') || lastLine.includes('IMPORTING') || lastLine.includes('Successfully')) {
                                this.importStatus = lastLine.replace(/^\[\d+:\d+:\d+\]\s*/, '');
                            }
                            
                            this.$nextTick(() => {
                                const logEl = this.$el.querySelector('.spbwc-gi-log');
                                if (logEl) logEl.scrollTop = logEl.scrollHeight;
                            });
                        }
                    }

                }).always(() => {
                    if (this.importRunning) {
                        setTimeout(() => this.pollLog(), 1200);
                    }
                });
            },
            fetchHealth() {
                this.healthLoading = true;
                jQuery.post(this.config.ajax_url, {
                    action: 'spbwc_global_import_health',
                    nonce: this.config.nonce
                }).done(res => {
                    if (res.success) {
                        this.health = res.data;
                        if (res.data.recommended_batch) {
                            this.batchSize = res.data.recommended_batch;
                        }
                    }
                }).always(() => {
                    this.healthLoading = false;
                });
            },
            scrollLogToBottom() {
                this.$nextTick(() => {
                    const logEl = this.$el.querySelector('.spbwc-gi-log');
                    if (logEl) logEl.scrollTop = logEl.scrollHeight;
                });
            },
            reimportRow(row) {
                const pid = row && row.import && row.import.product_id;
                if (!pid || this.reimporting[pid]) return;
                this.reimporting[pid] = true;
                const runId = 'reimport-' + pid + '-' + Date.now().toString(36);
                this.logs = [];
                let offset = 0;
                let polling = true;
                const fetchLog = (after) => {
                    jQuery.post(this.config.ajax_url, {
                        action: 'spbwc_global_import_log',
                        nonce: this.config.nonce,
                        run_id: runId,
                        offset: offset
                    }).done(res => {
                        if (res.success && Array.isArray(res.data.lines) && res.data.lines.length) {
                            this.logs = this.logs.concat(res.data.lines);
                            offset = res.data.offset;
                            this.scrollLogToBottom();
                        }
                    }).always(() => {
                        if (typeof after === 'function') after();
                        else if (polling) setTimeout(() => fetchLog(), 1000);
                    });
                };
                fetchLog();
                jQuery.ajax({
                    url: this.config.ajax_url,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'spbwc_global_import_reimport',
                        nonce: this.config.nonce,
                        product_id: pid,
                        run_id: runId
                    }
                }).done(res => {
                    if (res.success) {
                        const warnCount = Array.isArray(res.data.warnings) ? res.data.warnings.length : 0;
                        this.toast(warnCount
                            ? `Re-imported "${row.name}" with ${warnCount} warning(s)`
                            : `Re-imported "${row.name}" successfully`, warnCount ? '' : 'success');
                        this.fetchList();
                    } else {
                        this.toast((res.data && typeof res.data === 'string') ? res.data : 'Re-import failed', 'error');
                    }
                }).fail(xhr => {
                    const message = xhr && xhr.responseJSON && xhr.responseJSON.data
                        ? xhr.responseJSON.data
                        : 'Re-import failed';
                    this.toast(message, 'error');
                }).always(() => {
                    polling = false;
                    fetchLog(() => {});
                    delete this.reimporting[pid];
                });
            }
        },
        mounted() {
            this.fetchList();
            this.fetchHealth();
            this.resumeBackgroundJob();
            this._beforeUnload = (e) => {
                const clientImport = this.importRunning && !this.config.background;
                if (clientImport || Object.keys(this.reimporting).length > 0) {
                    e.preventDefault();
                    e.returnValue = '';
                    return '';
                }
            };
            window.addEventListener('beforeunload', this._beforeUnload);
        },
        beforeUnmount() {
            if (this._beforeUnload) {
                window.removeEventListener('beforeunload', this._beforeUnload);
            }
        },
        template: `
        <div class="spbwc-gi-grid">
            <div class="spbwc-gi-header">
                <div class="spbwc-gi-title">Global Import</div>
                <div class="spbwc-gi-toolbar">
                    <span v-if="health" class="spbwc-gi-badge spbwc-gi-serverpill" :class="health.healthy ? 'good' : 'warn'" :title="health.healthy ? 'Server ready for imports' : 'Server has capability warnings — see banner below'">
                        {{ health.healthy ? '● Server OK' : '● Server: check warnings' }}
                    </span>
                    <button class="spbwc-gi-btn secondary" @click="fetchList" :disabled="loading">{{ loading ? config.i18n.loading_products : 'Refresh' }}</button>
                </div>
            </div>
            <div class="spbwc-gi-card spbwc-gi-banner" v-if="health && !health.healthy">
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                    <span class="dashicons dashicons-warning" style="color:var(--nbd-color-warning);" aria-hidden="true"></span>
                    <h3 style="margin:0;">Server capability warning</h3>
                </div>
                <p v-if="!health.connectivity.ok" class="spbwc-gi-banner-msg">{{ health.connectivity.message }}</p>
                <ul v-if="health.warnings.length" class="spbwc-gi-banner-msg" style="margin-left:18px;">
                    <li v-for="(w, i) in health.warnings" :key="i">{{ w }}</li>
                </ul>
                <p style="margin:4px 0; font-size:var(--text-md); color:var(--gi-muted);">
                    Heavy products may import partially (truncated images or pricing JSON). Recommended batch size for this server: <strong>{{ health.recommended_batch }}</strong>.
                </p>
                <div style="margin-top:8px; display:flex; flex-wrap:wrap; gap:6px;">
                    <span v-for="(c, i) in health.checks" :key="i" class="spbwc-gi-badge" :class="c.ok ? 'good' : 'bad'">{{ c.label }}: {{ c.value }}</span>
                </div>
            </div>
            <div class="spbwc-gi-card" v-if="logs.length > 0">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h3 style="margin:0;">Realtime log</h3>
                    <button class="spbwc-gi-btn secondary" style="padding:4px 8px; font-size:11px;" @click="logs = []">Clear</button>
                </div>
                <div class="spbwc-gi-log">
                    <div v-for="(line, idx) in logs" :key="idx" :style="{color: line.includes('ERROR') ? '#f87171' : (line.includes('BATCH_COMPLETE') ? '#4ade80' : 'inherit')}">
                        {{ line }}
                    </div>
                </div>
            </div>

            <div class="spbwc-gi-card">
                <h3>{{ config.i18n.import_title }}</h3>
                <div class="spbwc-gi-chips">
                    <button class="spbwc-gi-chip" :class="{active: importState===''}" @click="setImportState('')">All · <b>{{ summary.total }}</b></button>
                    <button class="spbwc-gi-chip" :class="{active: importState==='none'}" @click="setImportState('none')">⚪ Not imported · <b>{{ summary.not_imported }}</b></button>
                    <button class="spbwc-gi-chip" :class="{active: importState==='imported'}" @click="setImportState('imported')">✅ Imported · <b>{{ summary.imported_ok }}</b></button>
                    <button class="spbwc-gi-chip" :class="{active: importState==='issues'}" @click="setImportState('issues')">⚠ With issues · <b>{{ summary.issues }}</b></button>
                </div>
                <div class="spbwc-gi-toolbar" style="margin-bottom:12px;">
                    <input class="spbwc-gi-input" v-model="search" @input="onFilterChange" placeholder="Search by name or SKU" :disabled="loading" />
                    <input class="spbwc-gi-input" v-model="category" @input="onFilterChange" placeholder="Category" :disabled="loading" />
                    <select class="spbwc-gi-select" v-model="stock" @change="onFilterChange" :disabled="loading">
                        <option value="">All stock</option>
                        <option value="instock">In stock</option>
                        <option value="outofstock">Out of stock</option>
                    </select>
                    <input class="spbwc-gi-input" v-model="priceMin" @input="onFilterChange" placeholder="Min price" :disabled="loading" />
                    <input class="spbwc-gi-input" v-model="priceMax" @input="onFilterChange" placeholder="Max price" :disabled="loading" />
                    <button class="spbwc-gi-btn" @click="startImport" :disabled="importRunning || selectedIds.length===0 || loading">Import{{ selectedIds.length ? ' (' + selectedIds.length + ')' : '' }}</button>
                </div>
                <div v-if="importRunning" style="margin-bottom:12px;">
                    <div class="spbwc-gi-progress"><span :style="{width: progressPercent+'%'}"></span></div>
                    <div style="margin-top:6px; font-weight: 600; color: var(--gi-primary);">{{ importStatus }}</div>
                    <div style="margin-top:2px; font-size: 13px; color: var(--gi-muted);">{{ completed }} / {{ totalSelected }} processed, {{ processed }} imported</div>
                </div>
                <div class="spbwc-gi-table-wrap">
                    <div v-if="loading" class="spbwc-gi-loading-overlay" role="status" aria-live="polite">
                        <span class="spbwc-gi-spinner" aria-hidden="true"></span>
                        <span>{{ config.i18n.loading_products }}</span>
                    </div>
                    <table class="spbwc-gi-table" :class="{ 'spbwc-gi-table--loading': loading }">
                    <thead>
                        <tr>
                            <th><input type="checkbox" :checked="allRowsOnPageSelected" @change="toggleSelectAll" :disabled="loading || importRunning" /></th>
                            <th>Image</th>
                            <th @click="changeSort('name')">Name</th>
                            <th>Status</th>
                            <th @click="changeSort('price')">Price</th>
                            <th @click="changeSort('stock_status')">Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="row in rows" :key="row.row_id">
                        <tr>
                            <td><input type="checkbox" :checked="selected[row.row_id]" @change="toggleRow(row)" :disabled="loading || (row.import && row.import.imported)" /></td>
                            <td><img :src="row.image" v-if="row.image" /></td>
                            <td>
                                <div class="spbwc-gi-name">
                                    <span>{{ row.name }}</span>
                                    <span v-if="!(row.import && row.import.imported) && hasManyImages(row)" class="spbwc-gi-warning" title="This product has many images, so the import time may be longer.">
                                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path fill="currentColor" d="M1 21h22L12 2 1 21zm12-3h-2v2h2v-2zm0-6h-2v5h2v-5z"/>
                                        </svg>
                                        <span>Many images</span>
                                    </span>
                                </div>
                                <a v-if="row.import && row.import.imported && row.import.edit_url" :href="row.import.edit_url" target="_blank" rel="noopener" class="spbwc-gi-editlink">
                                    <span class="dashicons dashicons-edit" aria-hidden="true"></span> Edit product
                                </a>
                            </td>
                            <td>
                                <span class="spbwc-gi-pill" :class="statusInfo(row).cls">{{ statusInfo(row).label }}</span>
                                <div v-if="row.import && row.import.imported && row.import.date" class="spbwc-gi-meta">{{ row.import.date }}</div>
                                <button v-if="row.import && row.import.warnings && row.import.warnings.length" class="spbwc-gi-issue-toggle" @click="toggleExpand(row)">
                                    {{ expandedRows[row.row_id] ? 'Hide details ▴' : row.import.warnings.length + ' issue(s) ▾' }}
                                </button>
                            </td>
                            <td>{{ row.price }}</td>
                            <td><span class="spbwc-gi-badge">{{ row.stock_status }}</span></td>
                            <td style="white-space:nowrap;">
                                <button v-if="row.import && row.import.imported" class="spbwc-gi-btn" style="padding:5px 12px;" @click="reimportRow(row)" :disabled="!!reimporting[row.import.product_id]" title="Re-fetch source files and overwrite this product in place (keeps the product ID, so existing orders stay valid).">
                                    {{ reimporting[row.import.product_id] ? 'Re-importing…' : 'Re-import' }}
                                </button>
                                <span v-else style="color:var(--gi-muted); font-size:var(--text-base);">Select to import</span>
                            </td>
                        </tr>
                        <tr v-if="expandedRows[row.row_id] && row.import && row.import.warnings.length" class="spbwc-gi-detail-row">
                            <td colspan="7">
                                <ul>
                                    <li v-for="(w, i) in row.import.warnings" :key="i">{{ w }}</li>
                                </ul>
                            </td>
                        </tr>
                        </template>
                        <tr v-if="rows.length===0 && !loading"><td colspan="7">No products in this view</td></tr>
                    </tbody>
                </table>
                </div>
                <div class="spbwc-gi-pagination">
                    <button class="spbwc-gi-btn secondary" :disabled="page<=1 || loading" @click="page--; fetchList()">Prev</button>
                    <span>{{ page }} / {{ totalPages }}</span>
                    <button class="spbwc-gi-btn secondary" :disabled="page>=totalPages || loading" @click="page++; fetchList()">Next</button>
                </div>
            </div>
            <div class="spbwc-gi-toast">
                <div class="spbwc-gi-toast-item" :class="t.type" v-for="t in toasts" :key="t.id">
                    <span v-if="t.type==='success'" class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                    <span v-else-if="t.type==='error'" class="dashicons dashicons-warning" aria-hidden="true"></span>
                    <span>{{ t.message }}</span>
                </div>
            </div>
        </div>
        `
    }).mount('#spbwc-global-import-app');
})();
