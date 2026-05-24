<?php
/**
 * Sanitize Printcart printing-option JSON dumps into Storelly template catalog.
 *
 * Reads sources from tools/source-templates/, strips per-store media references
 * + product/category bindings, detects pricing method + field count, and writes:
 *   - storage/print-templates/templates/<slug>.json
 *   - storage/print-templates/catalog.json
 *
 * Pricing values are preserved on purpose (user choice: "Reference pricing").
 * Run from container so PHP is available:
 *   docker exec wp_app php /var/www/html/wp-content/plugins/storelly-product-builder-woo/tools/sanitize-templates.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the CLI.\n");
    exit(1);
}

$plugin_root = dirname(__DIR__);
$source_dir  = $plugin_root . '/tools/source-templates';
$out_dir     = $plugin_root . '/storage/print-templates';
$out_tpl_dir = $out_dir . '/templates';
$catalog_out = $out_dir . '/catalog.json';

if (!is_dir($source_dir)) {
    fwrite(STDERR, "Source dir not found: $source_dir\n");
    exit(1);
}
if (!is_dir($out_tpl_dir)) {
    mkdir($out_tpl_dir, 0775, true);
}

/**
 * Manifest: slug => [output_slug, category, display_name, description, source_filename]
 *
 * Keep this small and explicit — when adding more templates, append a row here
 * rather than auto-deriving from filename, so catalog metadata stays curated.
 */
$manifest = [
    'business-cards' => [
        'source'      => 'printoptions_business_cards.json',
        'category'    => 'stationery',
        'name'        => 'Business Cards',
        'description' => 'Standard business cards with size, material, sides and quantity breaks.',
    ],
    'invitations' => [
        'source'      => 'printoptions_invitations.json',
        'category'    => 'stationery',
        'name'        => 'Invitations',
        'description' => 'Invitations with size, paper stock and printing sides.',
    ],
    'photo-copies' => [
        'source'      => 'printoptions_photo_copies.json',
        'category'    => 'marketing',
        'name'        => 'Photo Copies',
        'description' => 'Photo copy service with paper, color and quantity tiers.',
    ],
    'postcards-rack-cards' => [
        'source'      => 'printoptions_postcards_rack_cards.json',
        'category'    => 'marketing',
        'name'        => 'Postcards & Rack Cards',
        'description' => 'Postcards and rack cards with size matrix and finish options.',
    ],
    'flyers-letter-legal' => [
        'source'      => 'printoptions_flyers_8_5x11_8_5x14.json',
        'category'    => 'marketing',
        'name'        => 'Flyers (Letter / Legal)',
        'description' => 'Letter and legal size flyers with paper, color and quantity breaks.',
    ],
];

$categories = [
    'stationery'    => ['en_US' => 'Stationery',    'vi_VN' => 'Văn phòng phẩm'],
    'marketing'     => ['en_US' => 'Marketing',     'vi_VN' => 'Tiếp thị'],
    'large_format'  => ['en_US' => 'Large Format',  'vi_VN' => 'Khổ lớn'],
    'promotional'   => ['en_US' => 'Promotional',   'vi_VN' => 'Quà tặng'],
    'signage'       => ['en_US' => 'Signage',       'vi_VN' => 'Biển hiệu'],
    'envelopes'     => ['en_US' => 'Envelopes',     'vi_VN' => 'Phong bì'],
];

$pricing_source_note = 'Reference pricing from a US print shop (2026). Adjust prices to your market before publishing.';

$catalog = [
    'version'    => '1',
    'generated'  => gmdate('c'),
    'categories' => $categories,
    'templates'  => [],
];

$warnings = [];

foreach ($manifest as $slug => $meta) {
    $src_path = $source_dir . '/' . $meta['source'];
    if (!is_file($src_path)) {
        $warnings[] = "[$slug] source missing: {$meta['source']}";
        continue;
    }
    $raw = file_get_contents($src_path);
    if ($raw === false || $raw === '') {
        $warnings[] = "[$slug] empty source";
        continue;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $warnings[] = "[$slug] invalid JSON: " . json_last_error_msg();
        continue;
    }

    [$cleaned, $w] = sanitize_template($data, $slug);
    foreach ($w as $wn) {
        $warnings[] = "[$slug] $wn";
    }

    // Persist sanitized template.
    $tpl_file = $out_tpl_dir . '/' . $slug . '.json';
    file_put_contents(
        $tpl_file,
        json_encode($cleaned, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
    );

    $catalog['templates'][] = [
        'slug'             => $slug,
        'file'             => 'templates/' . $slug . '.json',
        'name'             => ['en_US' => $meta['name']],
        'category'         => $meta['category'],
        'thumbnail'        => 'thumbnails/' . $slug . '.webp',
        'field_count'      => count($cleaned['fields'] ?? []),
        'pricing_method'   => detect_pricing_method($cleaned),
        'template_version' => '1.0.0',
        'pricing_source'   => $pricing_source_note,
        'description'      => $meta['description'],
    ];

    echo "  ok  $slug  (" . count($cleaned['fields'] ?? []) . " fields, " . detect_pricing_method($cleaned) . ")\n";
}

file_put_contents(
    $catalog_out,
    json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);

echo "\nWrote " . count($catalog['templates']) . " templates + catalog.json\n";
if (!empty($warnings)) {
    echo "\nWarnings:\n";
    foreach ($warnings as $w) {
        echo "  ! $w\n";
    }
}

// ────────────────────────────────────────────────────────────────────────────
// Helpers
// ────────────────────────────────────────────────────────────────────────────

/**
 * Strip per-store references (media IDs, URLs, product/category bindings).
 * Pricing values are intentionally preserved.
 *
 * @return array{0: array, 1: string[]}  cleaned data, list of human warnings
 */
function sanitize_template(array $data, string $slug): array
{
    $warnings = [];

    // Top-level scope reset — admin assigns scope when applying the template.
    $data['id']           = '';
    $data['product_ids']  = [];
    $data['product_cats'] = [];
    if (!isset($data['apply_for']) || !in_array($data['apply_for'], ['p', 'c', 'a'], true)) {
        $data['apply_for'] = 'p';
        $warnings[] = "apply_for missing or invalid → defaulted to 'p'";
    }

    // Walk every field and scrub media references.
    if (!empty($data['fields']) && is_array($data['fields'])) {
        foreach ($data['fields'] as $fk => &$field) {
            if (!is_array($field)) continue;
            if (!empty($field['general']['attributes']['options']) && is_array($field['general']['attributes']['options'])) {
                foreach ($field['general']['attributes']['options'] as $ok => &$opt) {
                    scrub_attribute_media($opt);
                }
                unset($opt);
            }
        }
        unset($field);
    } else {
        $warnings[] = "no fields[] array found";
    }

    // Pad price arrays to match quantity_breaks length (defect from PDF→JSON generator).
    $warnings = array_merge($warnings, pad_price_arrays($data));

    // Validate enum + array consistency.
    $warnings = array_merge($warnings, validate_schema($data));

    return [$data, $warnings];
}

/**
 * Reset every media reference on one attribute (and any nested sub-attributes)
 * to the schema's "fresh import" sentinel values: "0" / "" / ["0"] / [""].
 */
function scrub_attribute_media(array &$opt): void
{
    if (isset($opt['image']) && $opt['image'] !== '0' && $opt['image'] !== 0) {
        $opt['image'] = '0';
    }
    if (isset($opt['image_url'])) {
        $opt['image_url'] = '';
    }
    if (isset($opt['bg_image'])) {
        $opt['bg_image'] = is_array($opt['bg_image']) ? array_fill(0, max(1, count($opt['bg_image'])), '0') : ['0'];
    }
    if (isset($opt['bg_image_url'])) {
        $opt['bg_image_url'] = is_array($opt['bg_image_url']) ? array_fill(0, max(1, count($opt['bg_image_url'])), '') : [''];
    }
    if (!empty($opt['sub_attributes']) && is_array($opt['sub_attributes'])) {
        foreach ($opt['sub_attributes'] as &$sub) {
            if (is_array($sub)) {
                scrub_attribute_media($sub);
            }
        }
        unset($sub);
    }
}

/**
 * Pad price arrays to match quantity_breaks length using the last non-empty
 * value as the fill. The PDF→JSON generator that produced these dumps emitted
 * 6-entry price arrays even when quantity_breaks had 7–25 tiers, which would
 * make the renderer compute wrong prices for high-quantity tiers.
 *
 * Skipped when quantity_type='s' (sized — pricing is per-attribute, not per-tier).
 *
 * @return string[] notes describing what was padded
 */
function pad_price_arrays(array &$data): array
{
    $notes = [];
    if (($data['quantity_type'] ?? '') === 's') {
        return $notes;
    }
    $target = is_array($data['quantity_breaks'] ?? null) ? count($data['quantity_breaks']) : 0;
    if ($target < 2) {
        return $notes;
    }
    if (empty($data['fields']) || !is_array($data['fields'])) {
        return $notes;
    }
    foreach ($data['fields'] as $fi => &$field) {
        if (!is_array($field)) continue;
        $fid = $field['id'] ?? "#$fi";

        $pb = $field['general']['price_breaks']['value'] ?? null;
        if (is_array($pb) && count($pb) < $target) {
            $field['general']['price_breaks']['value'] = pad_with_last_nonempty($pb, $target);
            $notes[] = "padded $fid price_breaks " . count($pb) . " → $target";
        }
        if (!empty($field['general']['attributes']['options']) && is_array($field['general']['attributes']['options'])) {
            foreach ($field['general']['attributes']['options'] as $oi => &$opt) {
                if (!is_array($opt)) continue;
                if (isset($opt['price']) && is_array($opt['price']) && count($opt['price']) < $target) {
                    $before = count($opt['price']);
                    $opt['price'] = pad_with_last_nonempty($opt['price'], $target);
                    $notes[] = "padded $fid attr#$oi price $before → $target";
                }
            }
            unset($opt);
        }
    }
    unset($field);
    return $notes;
}

function pad_with_last_nonempty(array $arr, int $target_len): array
{
    $fill = '';
    for ($i = count($arr) - 1; $i >= 0; $i--) {
        if ($arr[$i] !== '' && $arr[$i] !== null) {
            $fill = (string) $arr[$i];
            break;
        }
    }
    return array_pad($arr, $target_len, $fill);
}

function detect_pricing_method(array $data): string
{
    $display_type = $data['display_type'] ?? '4';
    if ($display_type === '2') {
        return 'matrix';
    }
    if (!empty($data['fields']) && is_array($data['fields'])) {
        foreach ($data['fields'] as $f) {
            if (!is_array($f)) continue;
            $mesure = $f['general']['mesure'] ?? 'n';
            if ($mesure === 'y') return 'area_based';
            $pt = $f['general']['price_type']['value'] ?? '';
            if ($pt === 'mf') return 'math_formula';
        }
        foreach ($data['fields'] as $f) {
            if (!is_array($f)) continue;
            $pt = $f['general']['price_type']['value'] ?? '';
            if ($pt === 'p' || $pt === 'p+') return 'percent';
        }
    }
    if (($data['quantity_enable'] ?? 'n') === 'y'
        && is_array($data['quantity_breaks'] ?? null)
        && count($data['quantity_breaks']) > 1
    ) {
        return 'quantity_breaks';
    }
    return 'fixed';
}

/**
 * Light schema validation — checks enums and array-length invariants from
 * printcart-json-schema.md §"Schema validation checklist". Returns warnings;
 * does NOT mutate.
 *
 * @return string[]
 */
function validate_schema(array $data): array
{
    $w = [];
    $enums = [
        'display_type'           => ['1','2','3','4','5','6'],
        'quantity_type'          => ['d','r','ra','s'],
        'quantity_discount_type' => ['p','f'],
        'apply_for'              => ['p','c','a'],
    ];
    foreach ($enums as $key => $valid) {
        if (isset($data[$key]) && !in_array((string) $data[$key], $valid, true)) {
            $w[] = "top-level $key = '" . $data[$key] . "' not in [" . implode(',', $valid) . "]";
        }
    }
    $qty_breaks_count = is_array($data['quantity_breaks'] ?? null) ? count($data['quantity_breaks']) : 0;
    if (!empty($data['fields']) && is_array($data['fields'])) {
        foreach ($data['fields'] as $fi => $field) {
            if (!is_array($field)) continue;
            $fid = $field['id'] ?? "#$fi";

            $pb = $field['general']['price_breaks']['value'] ?? null;
            if (is_array($pb) && $qty_breaks_count > 0 && count($pb) !== $qty_breaks_count
                && ($data['quantity_type'] ?? '') !== 's'
            ) {
                $w[] = "field $fid: price_breaks length " . count($pb) . " != quantity_breaks " . $qty_breaks_count;
            }
            if (!empty($field['general']['attributes']['options']) && is_array($field['general']['attributes']['options'])) {
                foreach ($field['general']['attributes']['options'] as $oi => $opt) {
                    if (!is_array($opt)) continue;
                    if (isset($opt['price']) && is_array($opt['price'])
                        && $qty_breaks_count > 0 && count($opt['price']) !== $qty_breaks_count
                        && ($data['quantity_type'] ?? '') !== 's'
                    ) {
                        $w[] = "field $fid attr#$oi: price[] length " . count($opt['price']) . " != quantity_breaks " . $qty_breaks_count;
                    }
                    if (isset($opt['preview_type']) && !in_array($opt['preview_type'], ['i','c','l'], true)) {
                        $w[] = "field $fid attr#$oi: preview_type='{$opt['preview_type']}' not in [i,c,l]";
                    }
                }
            }
        }
    }
    return $w;
}
