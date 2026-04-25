(function(root){
    const helpers = {
        chunk(arr, size) {
            const out = [];
            for (let i = 0; i < arr.length; i += size) {
                out.push(arr.slice(i, i + size));
            }
            return out;
        },
        normalizeRow(row) {
            return {
                name: row.name || '',
                sku: row.sku || '',
                price: Number(row.price || 0),
                stock_status: row.stock_status || 'instock'
            };
        }
    };
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = helpers;
    } else {
        root.SPBWCGlobalImportHelpers = helpers;
    }
})(this);
