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
        i18n: {
            upload_title: 'Upload file',
            import_title: 'Import products',
            confirm_delete: 'Are you sure you want to delete this export?'
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
                toasts: []
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
            allRowsOnPageSelected() {
                return this.rows.length > 0 && this.rows.every(row => !!this.selected[row.row_id]);
            }
        },
        methods: {
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
                    sort_dir: this.sortDir
                };
            },
            toast(message) {
                const id = Date.now();
                this.toasts.push({ id, message });
                setTimeout(() => {
                    this.toasts = this.toasts.filter(t => t.id !== id);
                }, 3500);
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
                        this.toast('File uploaded');
                        this.fetchList();
                    } else {
                        this.toast('Upload failed');
                    }
                }).fail(() => {
                    this.toast('Upload failed');
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
                    }
                }).always(() => {
                    this.loading = false;
                });
            },
            selectAllFiltered() {
                this.loading = true;
                const payload = this.buildFiltersPayload('spbwc_global_import_row_ids');
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
                if (this.selected[row.row_id]) {
                    delete this.selected[row.row_id];
                } else {
                    this.selected[row.row_id] = true;
                }
            },
            changeSort(key) {
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
                this.runBatch();
                this.pollLog();
            },
            runBatch() {
                if (!this.importQueue.length) {
                    this.importRunning = false;
                    this.importStatus = '';
                    this.clearSelection();
                    this.toast('Import completed');
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
                        this.toast('Import failed');
                        this.importQueue = [];
                        this.importRunning = false;
                    }
                }).fail(xhr => {
                    this.importStatus = 'Network error during import.';
                    const message = xhr && xhr.responseJSON && xhr.responseJSON.data
                        ? xhr.responseJSON.data
                        : 'Import failed';
                    this.toast(message);
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
            categoriesText(row) {
                if (!row) return '';
                if (Array.isArray(row.categories)) {
                    return row.categories.join(', ');
                }
                return row.categories || '';
            }
        },
        mounted() {
            this.fetchList();
        },
        template: `
        <div class="spbwc-gi-grid">
            <div class="spbwc-gi-header">
                <div class="spbwc-gi-title">Global Import</div>
                <div class="spbwc-gi-toolbar">
                    <button class="spbwc-gi-btn secondary" @click="fetchList">Refresh</button>
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
                <div class="spbwc-gi-toolbar" style="margin-bottom:12px;">
                    <input class="spbwc-gi-input" v-model="search" @input="onFilterChange" placeholder="Search by name or SKU" />
                    <input class="spbwc-gi-input" v-model="category" @input="onFilterChange" placeholder="Category" />
                    <select class="spbwc-gi-select" v-model="stock" @change="onFilterChange">
                        <option value="">All stock</option>
                        <option value="instock">In stock</option>
                        <option value="outofstock">Out of stock</option>
                    </select>
                    <input class="spbwc-gi-input" v-model="priceMin" @input="onFilterChange" placeholder="Min price" />
                    <input class="spbwc-gi-input" v-model="priceMax" @input="onFilterChange" placeholder="Max price" />
                    <button class="spbwc-gi-btn" @click="startImport" :disabled="importRunning || selectedIds.length===0">Import products</button>
                </div>
                <div v-if="importRunning" style="margin-bottom:12px;">
                    <div class="spbwc-gi-progress"><span :style="{width: progressPercent+'%'}"></span></div>
                    <div style="margin-top:6px; font-weight: 600; color: var(--gi-primary);">{{ importStatus }}</div>
                    <div style="margin-top:2px; font-size: 13px; color: var(--gi-muted);">{{ completed }} / {{ totalSelected }} processed, {{ processed }} imported</div>
                </div>
                <table class="spbwc-gi-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" :checked="allRowsOnPageSelected" @change="toggleSelectAll" :disabled="loading || importRunning" /></th>
                            <th @click="changeSort('name')">Name</th>
                            <th @click="changeSort('sku')">SKU</th>
                            <th @click="changeSort('price')">Price</th>
                            <th @click="changeSort('stock_status')">Stock</th>
                            <th>Categories</th>
                            <th>Image</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.row_id">
                            <td><input type="checkbox" :checked="selected[row.row_id]" @change="toggleRow(row)" /></td>
                            <td>{{ row.name }}</td>
                            <td>{{ row.sku || '-' }}</td>
                            <td>{{ row.price }}</td>
                            <td><span class="spbwc-gi-badge">{{ row.stock_status }}</span></td>
                            <td>{{ categoriesText(row) }}</td>
                            <td><img :src="row.image" v-if="row.image" /></td>
                        </tr>
                        <tr v-if="rows.length===0"><td colspan="7">No data</td></tr>
                    </tbody>
                </table>
                <div class="spbwc-gi-pagination">
                    <button class="spbwc-gi-btn secondary" :disabled="page<=1" @click="page--; fetchList()">Prev</button>
                    <span>{{ page }} / {{ totalPages }}</span>
                    <button class="spbwc-gi-btn secondary" :disabled="page>=totalPages" @click="page++; fetchList()">Next</button>
                </div>
            </div>
            <div class="spbwc-gi-toast">
                <div class="spbwc-gi-toast-item" v-for="t in toasts" :key="t.id">{{ t.message }}</div>
            </div>
        </div>
        `
    }).mount('#spbwc-global-import-app');
})();
