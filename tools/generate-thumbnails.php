<?php
/**
 * SVG Thumbnail Generator — Storelly Print-Template Catalog.
 *
 * Writes one 400×240 SVG per template to:
 *   storage/print-templates/thumbnails/{slug}.svg
 *
 * Run in Docker:
 *   docker exec wp_app sh -c 'php /var/www/html/wp-content/plugins/storelly-product-builder-woo/tools/generate-thumbnails.php'
 *
 * Each SVG has:
 *   • gradient background (category colour)
 *   • subtle dot-grid noise overlay
 *   • product-specific geometric icon (white, semi-transparent)
 *   • template name in white at the bottom
 */

if ( PHP_SAPI !== 'cli' ) {
    fwrite( STDERR, "Run from CLI only.\n" );
    exit( 1 );
}

$plugin_root = dirname( __DIR__ );
$thumb_dir   = $plugin_root . '/storage/print-templates/thumbnails';
if ( ! is_dir( $thumb_dir ) ) {
    mkdir( $thumb_dir, 0775, true );
}

/* ── Colour palette (matches template-library.css gradients) ── */
$gradients = [
    'stationery'   => [ '#4338ca', '#818cf8' ],
    'marketing'    => [ '#92400e', '#d97706' ],
    'large_format' => [ '#065f46', '#34d399' ],
    'promotional'  => [ '#9d174d', '#f472b6' ],
    'signage'      => [ '#991b1b', '#f87171' ],
    'envelopes'    => [ '#44403c', '#a8a29e' ],
];

/* ── Template manifest: slug → [label, category, icon_fn] ── */
$templates = [
    'business-cards'       => [ 'Business Cards',             'stationery',   'icon_business_cards' ],
    'invitations'          => [ 'Invitations',                'stationery',   'icon_invitations'    ],
    'letterhead'           => [ 'Letterhead',                 'stationery',   'icon_letterhead'     ],
    'pocket-folders'       => [ 'Pocket Folders',             'stationery',   'icon_pocket_folders' ],
    'custom-checks'        => [ 'Custom Checks',              'stationery',   'icon_checks'         ],
    'photo-copies'         => [ 'Photo Copies',               'marketing',    'icon_photo_copies'   ],
    'postcards-rack-cards' => [ 'Postcards & Rack Cards',     'marketing',    'icon_postcards'      ],
    'flyers-letter-legal'  => [ 'Flyers (Letter / Legal)',    'marketing',    'icon_flyers_large'   ],
    'brochures'            => [ 'Brochures',                  'marketing',    'icon_brochures'      ],
    'flyers-small'         => [ 'Flyers (Small Sizes)',       'marketing',    'icon_flyers_small'   ],
    'door-hangers'         => [ 'Door Hangers',               'marketing',    'icon_door_hangers'   ],
    'envelopes-commercial' => [ 'Envelopes (Commercial)',     'envelopes',    'icon_envelope_biz'   ],
    'envelopes-in-house'   => [ 'Envelopes (In-House)',       'envelopes',    'icon_envelope_open'  ],
    'banner-stands'        => [ 'Banner Stands & Backdrops',  'large_format', 'icon_banner'         ],
    'wide-format-cad'      => [ 'Wide Format / CAD Prints',   'large_format', 'icon_cad'            ],
    'trade-show-displays'  => [ 'Trade Show Displays',        'large_format', 'icon_trade_show'     ],
    'decals'               => [ 'Decals',                     'promotional',  'icon_decals'         ],
    'can-coolers'          => [ 'Can Coolers',                'promotional',  'icon_can'            ],
    'stamps'               => [ 'Stamps',                     'promotional',  'icon_stamp'          ],
    'table-throws'         => [ 'Table Throws',               'promotional',  'icon_table_throw'    ],
    'wall-art'             => [ 'Wall Art (Canvas & Acrylic)', 'promotional', 'icon_wall_art'       ],
    'coroplast-signs'      => [ 'Coroplast Signs',            'signage',      'icon_yard_sign'      ],
    'a-frames'             => [ 'A-Frames / Signicade',       'signage',      'icon_a_frame'        ],
    'event-tents'          => [ 'Event Tents',                'signage',      'icon_tent'           ],
    'flags'                => [ 'Advertising Flags',          'signage',      'icon_flag'           ],
    'signage-materials'    => [ 'Signage Materials',          'signage',      'icon_signage'        ],
];

/* ── Shared SVG scaffold ── */
function make_svg( string $label, string $cat, array $grads, string $icon_svg ): string {
    $c1 = $grads[0];
    $c2 = $grads[1];

    // Split label across two lines if > 22 chars
    $lines    = word_wrap_label( $label );
    $text_y   = count( $lines ) === 1 ? 219 : 212;
    $text_svg = '';
    foreach ( $lines as $i => $line ) {
        $y         = $text_y + $i * 17;
        $text_svg .= '<text x="200" y="' . $y . '" font-family="system-ui,-apple-system,sans-serif" '
            . 'font-size="14" font-weight="600" fill="rgba(255,255,255,0.92)" text-anchor="middle" '
            . 'letter-spacing="0.01em">' . htmlspecialchars( $line ) . '</text>' . "\n";
    }

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="240" viewBox="0 0 400 240">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{$c1}"/>
      <stop offset="100%" stop-color="{$c2}"/>
    </linearGradient>
    <filter id="noise" x="0%" y="0%" width="100%" height="100%">
      <feTurbulence type="fractalNoise" baseFrequency="0.65" numOctaves="3" stitchTiles="stitch"/>
      <feColorMatrix type="saturate" values="0"/>
      <feBlend in="SourceGraphic" mode="overlay" result="blend"/>
      <feComposite in="blend" in2="SourceGraphic" operator="in"/>
    </filter>
  </defs>
  <!-- Background -->
  <rect width="400" height="240" fill="url(#bg)"/>
  <!-- Subtle diagonal lines texture -->
  <rect width="400" height="240" fill="none" stroke="rgba(255,255,255,0.04)" stroke-width="12"
        stroke-dasharray="1,18" transform="rotate(-30 200 120)"/>
  <!-- Icon -->
  <g transform="translate(200,105)" opacity="0.88">
{$icon_svg}
  </g>
  <!-- Bottom gradient fade for text legibility -->
  <rect x="0" y="170" width="400" height="70" fill="rgba(0,0,0,0.28)"/>
  <!-- Label -->
{$text_svg}
</svg>
SVG;
}

function word_wrap_label( string $s ): array {
    if ( mb_strlen( $s ) <= 22 ) {
        return [ $s ];
    }
    $words = explode( ' ', $s );
    $line1 = '';
    $line2 = '';
    foreach ( $words as $w ) {
        $try = trim( $line1 . ' ' . $w );
        if ( mb_strlen( $try ) <= 22 ) {
            $line1 = $try;
        } else {
            $line2 = trim( $line2 . ' ' . $w );
        }
    }
    return $line2 !== '' ? [ $line1, $line2 ] : [ $line1 ];
}

/* ════════════════════════════════════════════════════════
   ICON LIBRARY  (all coords relative to centre 0,0)
   ════════════════════════════════════════════════════════ */

// ── Stationery ──────────────────────────────────────────

function icon_business_cards(): string {
    return <<<SVG
    <!-- card stack: 3 overlapping rectangles -->
    <rect x="-75" y="-38" width="110" height="68" rx="4" fill="rgba(255,255,255,0.18)"/>
    <rect x="-60" y="-48" width="110" height="68" rx="4" fill="rgba(255,255,255,0.28)"/>
    <rect x="-45" y="-58" width="110" height="68" rx="4" fill="rgba(255,255,255,0.42)"/>
    <!-- text lines on top card -->
    <rect x="-30" y="-45" width="70" height="5" rx="2" fill="rgba(255,255,255,0.75)"/>
    <rect x="-30" y="-34" width="50" height="4" rx="2" fill="rgba(255,255,255,0.50)"/>
    <rect x="-30" y="-23" width="40" height="3" rx="2" fill="rgba(255,255,255,0.40)"/>
    <rect x="-30" y="-13" width="55" height="3" rx="2" fill="rgba(255,255,255,0.40)"/>
SVG;
}

function icon_invitations(): string {
    return <<<SVG
    <!-- envelope body -->
    <rect x="-70" y="-18" width="140" height="88" rx="4" fill="rgba(255,255,255,0.30)"/>
    <!-- envelope V-flap -->
    <polygon points="-70,-18 0,42 70,-18" fill="rgba(255,255,255,0.15)" stroke="rgba(255,255,255,0.50)" stroke-width="1"/>
    <!-- card sticking out top -->
    <rect x="-42" y="-72" width="84" height="65" rx="3" fill="rgba(255,255,255,0.50)"/>
    <!-- decorative inner border on card -->
    <rect x="-34" y="-64" width="68" height="49" rx="2" fill="none" stroke="rgba(255,255,255,0.60)" stroke-width="1.5"/>
    <rect x="-20" y="-50" width="40" height="4" rx="2" fill="rgba(255,255,255,0.65)"/>
    <rect x="-15" y="-40" width="30" height="3" rx="2" fill="rgba(255,255,255,0.45)"/>
SVG;
}

function icon_letterhead(): string {
    return <<<SVG
    <!-- page -->
    <rect x="-55" y="-72" width="110" height="135" rx="3" fill="rgba(255,255,255,0.35)"/>
    <!-- header colour bar -->
    <rect x="-55" y="-72" width="110" height="22" rx="3" fill="rgba(255,255,255,0.60)"/>
    <!-- text lines -->
    <rect x="-40" y="-38" width="80" height="5" rx="2" fill="rgba(255,255,255,0.65)"/>
    <rect x="-40" y="-27" width="65" height="4" rx="2" fill="rgba(255,255,255,0.40)"/>
    <rect x="-40" y="-17" width="72" height="4" rx="2" fill="rgba(255,255,255,0.40)"/>
    <rect x="-40" y="-7"  width="55" height="4" rx="2" fill="rgba(255,255,255,0.40)"/>
    <rect x="-40" y="3"   width="68" height="4" rx="2" fill="rgba(255,255,255,0.40)"/>
    <rect x="-40" y="13"  width="45" height="4" rx="2" fill="rgba(255,255,255,0.40)"/>
    <!-- footer line -->
    <line x1="-45" y1="45" x2="45" y2="45" stroke="rgba(255,255,255,0.35)" stroke-width="1"/>
SVG;
}

function icon_pocket_folders(): string {
    return <<<SVG
    <!-- folder back -->
    <rect x="-72" y="-60" width="144" height="105" rx="4" fill="rgba(255,255,255,0.22)"/>
    <!-- tab -->
    <rect x="-72" y="-74" width="60" height="18" rx="3" fill="rgba(255,255,255,0.35)"/>
    <!-- folder front panel -->
    <rect x="-72" y="-30" width="144" height="75" rx="0 0 4 4" fill="rgba(255,255,255,0.38)"/>
    <!-- pocket fold line -->
    <line x1="-72" y1="-30" x2="72" y2="-30" stroke="rgba(255,255,255,0.50)" stroke-width="1.5"/>
    <!-- cards in pocket -->
    <rect x="-48" y="-58" width="38" height="52" rx="2" fill="rgba(255,255,255,0.50)"/>
    <rect x="-32" y="-62" width="38" height="52" rx="2" fill="rgba(255,255,255,0.38)"/>
SVG;
}

function icon_checks(): string {
    return <<<SVG
    <!-- cheque book spine -->
    <rect x="-72" y="-55" width="16" height="110" rx="3" fill="rgba(255,255,255,0.35)"/>
    <!-- top cheque -->
    <rect x="-52" y="-55" width="124" height="50" rx="3" fill="rgba(255,255,255,0.50)"/>
    <!-- MICR line -->
    <rect x="-40" y="5"   width="100" height="5"  rx="2" fill="rgba(255,255,255,0.35)"/>
    <!-- amount box -->
    <rect x="40"  y="-48" width="30"  height="16" rx="2" fill="rgba(255,255,255,0.28)" stroke="rgba(255,255,255,0.55)" stroke-width="1"/>
    <!-- payee line -->
    <rect x="-40" y="-40" width="72"  height="4"  rx="2" fill="rgba(255,255,255,0.60)"/>
    <rect x="-40" y="-28" width="55"  height="4"  rx="2" fill="rgba(255,255,255,0.40)"/>
    <!-- second cheque behind -->
    <rect x="-52" y="-2"  width="124" height="50" rx="3" fill="rgba(255,255,255,0.28)"/>
SVG;
}

// ── Marketing ───────────────────────────────────────────

function icon_photo_copies(): string {
    return <<<SVG
    <!-- paper stack shadow -->
    <rect x="-55" y="-45" width="105" height="130" rx="3" fill="rgba(255,255,255,0.15)"/>
    <rect x="-60" y="-50" width="105" height="130" rx="3" fill="rgba(255,255,255,0.22)"/>
    <!-- top sheet -->
    <rect x="-65" y="-55" width="105" height="130" rx="3" fill="rgba(255,255,255,0.42)"/>
    <!-- photo placeholder box on sheet -->
    <rect x="-48" y="-40" width="55" height="42" rx="2" fill="rgba(255,255,255,0.30)" stroke="rgba(255,255,255,0.55)" stroke-width="1"/>
    <!-- mountain/image hint in box -->
    <polygon points="-48,2 -25,-20 -3,2" fill="rgba(255,255,255,0.40)"/>
    <circle cx="28" cy="-25" r="8" fill="rgba(255,255,255,0.30)"/>
    <!-- text lines below photo -->
    <rect x="-48" y="12" width="72" height="4" rx="2" fill="rgba(255,255,255,0.60)"/>
    <rect x="-48" y="22" width="55" height="3" rx="2" fill="rgba(255,255,255,0.40)"/>
    <rect x="-48" y="31" width="60" height="3" rx="2" fill="rgba(255,255,255,0.40)"/>
SVG;
}

function icon_postcards(): string {
    return <<<SVG
    <!-- postcard body -->
    <rect x="-80" y="-48" width="160" height="105" rx="4" fill="rgba(255,255,255,0.42)"/>
    <!-- vertical divider -->
    <line x1="0" y1="-38" x2="0" y2="47" stroke="rgba(255,255,255,0.50)" stroke-width="1" stroke-dasharray="3,3"/>
    <!-- stamp box top-right -->
    <rect x="12" y="-38" width="28" height="28" rx="2" fill="rgba(255,255,255,0.28)" stroke="rgba(255,255,255,0.55)" stroke-width="1"/>
    <!-- address lines right side -->
    <rect x="12" y="2"  width="55" height="4" rx="2" fill="rgba(255,255,255,0.55)"/>
    <rect x="12" y="12" width="45" height="3" rx="2" fill="rgba(255,255,255,0.40)"/>
    <rect x="12" y="21" width="50" height="3" rx="2" fill="rgba(255,255,255,0.40)"/>
    <!-- image area left -->
    <rect x="-72" y="-38" width="60" height="70" rx="2" fill="rgba(255,255,255,0.22)"/>
    <polygon points="-72,32 -50,5 -28,32" fill="rgba(255,255,255,0.30)"/>
    <circle cx="-18" cy="-15" r="10" fill="rgba(255,255,255,0.28)"/>
SVG;
}

function icon_flyers_large(): string {
    return <<<SVG
    <!-- flyer sheet -->
    <rect x="-62" y="-72" width="124" height="143" rx="3" fill="rgba(255,255,255,0.40)"/>
    <!-- headline bar at top -->
    <rect x="-52" y="-62" width="104" height="18" rx="2" fill="rgba(255,255,255,0.55)"/>
    <!-- image block -->
    <rect x="-52" y="-36" width="104" height="58" rx="2" fill="rgba(255,255,255,0.22)"/>
    <polygon points="-52,22 -20,-10 12,22" fill="rgba(255,255,255,0.35)"/>
    <circle cx="30" cy="-15" r="14" fill="rgba(255,255,255,0.30)"/>
    <!-- text lines -->
    <rect x="-52" y="30" width="80" height="5" rx="2" fill="rgba(255,255,255,0.60)"/>
    <rect x="-52" y="42" width="65" height="4" rx="2" fill="rgba(255,255,255,0.40)"/>
    <rect x="-52" y="52" width="72" height="4" rx="2" fill="rgba(255,255,255,0.40)"/>
SVG;
}

function icon_brochures(): string {
    return <<<SVG
    <!-- tri-fold: three panels side by side -->
    <rect x="-80" y="-55" width="45" height="105" rx="2" fill="rgba(255,255,255,0.28)"/>
    <rect x="-30" y="-55" width="45" height="105" rx="2" fill="rgba(255,255,255,0.42)"/>
    <rect x="20"  y="-55" width="45" height="105" rx="2" fill="rgba(255,255,255,0.55)"/>
    <!-- fold lines -->
    <line x1="-30" y1="-55" x2="-30" y2="50" stroke="rgba(255,255,255,0.60)" stroke-width="1"/>
    <line x1="20"  y1="-55" x2="20"  y2="50" stroke="rgba(255,255,255,0.60)" stroke-width="1"/>
    <!-- content lines on front panel -->
    <rect x="28" y="-42" width="30" height="5"  rx="2" fill="rgba(255,255,255,0.75)"/>
    <rect x="28" y="-30" width="28" height="3"  rx="2" fill="rgba(255,255,255,0.50)"/>
    <rect x="28" y="-21" width="32" height="3"  rx="2" fill="rgba(255,255,255,0.50)"/>
    <!-- cover image area on middle panel -->
    <rect x="-22" y="-42" width="30" height="30" rx="2" fill="rgba(255,255,255,0.25)"/>
SVG;
}

function icon_flyers_small(): string {
    return <<<SVG
    <!-- small square flyer grid: 2×2 -->
    <rect x="-55" y="-55" width="50" height="50" rx="3" fill="rgba(255,255,255,0.42)"/>
    <rect x="5"   y="-55" width="50" height="50" rx="3" fill="rgba(255,255,255,0.35)"/>
    <rect x="-55" y="5"   width="50" height="50" rx="3" fill="rgba(255,255,255,0.35)"/>
    <rect x="5"   y="5"   width="50" height="50" rx="3" fill="rgba(255,255,255,0.28)"/>
    <!-- content hint on top-left -->
    <rect x="-48" y="-48" width="36" height="5"  rx="2" fill="rgba(255,255,255,0.75)"/>
    <rect x="-48" y="-37" width="28" height="3"  rx="2" fill="rgba(255,255,255,0.50)"/>
    <rect x="-48" y="-28" width="32" height="3"  rx="2" fill="rgba(255,255,255,0.50)"/>
SVG;
}

function icon_door_hangers(): string {
    return <<<SVG
    <!-- door frame hint (rect with keyhole) -->
    <rect x="-30" y="-75" width="60" height="115" rx="30 30 4 4" fill="rgba(255,255,255,0.15)" stroke="rgba(255,255,255,0.30)" stroke-width="2"/>
    <!-- door hanger card -->
    <rect x="-28" y="-70" width="56" height="100" rx="28 28 3 3" fill="rgba(255,255,255,0.42)"/>
    <!-- hole at top -->
    <circle cx="0" cy="-55" r="10" fill="rgba(255,255,255,0.80)"/>
    <circle cx="0" cy="-55" r="5" fill="rgba(0,0,0,0.18)"/>
    <!-- text lines on card -->
    <rect x="-18" y="-35" width="36" height="5"  rx="2" fill="rgba(255,255,255,0.70)"/>
    <rect x="-15" y="-23" width="30" height="3"  rx="2" fill="rgba(255,255,255,0.50)"/>
    <rect x="-18" y="-13" width="36" height="3"  rx="2" fill="rgba(255,255,255,0.50)"/>
    <rect x="-13" y="-3"  width="26" height="3"  rx="2" fill="rgba(255,255,255,0.50)"/>
SVG;
}

// ── Envelopes ───────────────────────────────────────────

function icon_envelope_biz(): string {
    return <<<SVG
    <!-- envelope body -->
    <rect x="-80" y="-35" width="160" height="95" rx="4" fill="rgba(255,255,255,0.38)"/>
    <!-- flap open/closed V -->
    <polyline points="-80,-35 0,30 80,-35" fill="rgba(255,255,255,0.22)" stroke="rgba(255,255,255,0.55)" stroke-width="1.5"/>
    <!-- bottom seal crease -->
    <line x1="-80" y1="60" x2="80" y2="60" stroke="rgba(255,255,255,0.30)" stroke-width="1"/>
    <!-- side creases -->
    <line x1="-80" y1="-35" x2="-80" y2="60" stroke="rgba(255,255,255,0.30)" stroke-width="1"/>
    <line x1="80"  y1="-35" x2="80"  y2="60" stroke="rgba(255,255,255,0.30)" stroke-width="1"/>
    <!-- left address block -->
    <rect x="-65" y="-15" width="55" height="4"  rx="2" fill="rgba(255,255,255,0.55)"/>
    <rect x="-65" y="-5"  width="45" height="3"  rx="2" fill="rgba(255,255,255,0.38)"/>
    <rect x="-65" y="4"   width="50" height="3"  rx="2" fill="rgba(255,255,255,0.38)"/>
    <!-- stamp box -->
    <rect x="42"  y="-22" width="28" height="22" rx="2" fill="rgba(255,255,255,0.28)" stroke="rgba(255,255,255,0.50)" stroke-width="1"/>
SVG;
}

function icon_envelope_open(): string {
    return <<<SVG
    <!-- main envelope body open from top -->
    <rect x="-80" y="-10" width="160" height="80" rx="4" fill="rgba(255,255,255,0.38)"/>
    <!-- open flap pointing up -->
    <polyline points="-80,-10 0,-65 80,-10" fill="rgba(255,255,255,0.22)" stroke="rgba(255,255,255,0.55)" stroke-width="1.5"/>
    <!-- inner envelope shade -->
    <polyline points="-80,-10 0,40 80,-10" fill="rgba(255,255,255,0.15)"/>
    <!-- letter sticking up out of envelope -->
    <rect x="-40" y="-62" width="80" height="70" rx="2" fill="rgba(255,255,255,0.55)"/>
    <rect x="-28" y="-50" width="56" height="5"  rx="2" fill="rgba(255,255,255,0.75)"/>
    <rect x="-28" y="-38" width="45" height="3"  rx="2" fill="rgba(255,255,255,0.50)"/>
    <rect x="-28" y="-29" width="50" height="3"  rx="2" fill="rgba(255,255,255,0.50)"/>
SVG;
}

// ── Large Format ────────────────────────────────────────

function icon_banner(): string {
    return <<<SVG
    <!-- banner stand base -->
    <rect x="-12" y="55" width="24" height="8" rx="3" fill="rgba(255,255,255,0.45)"/>
    <line x1="0" y1="55" x2="0" y2="-68" stroke="rgba(255,255,255,0.40)" stroke-width="4" stroke-linecap="round"/>
    <!-- banner roll top -->
    <ellipse cx="0" cy="-68" rx="42" ry="7" fill="rgba(255,255,255,0.40)"/>
    <!-- banner panel -->
    <rect x="-42" y="-65" width="84" height="120" rx="1" fill="rgba(255,255,255,0.38)"/>
    <!-- content on banner -->
    <rect x="-30" y="-55" width="60" height="28" rx="2" fill="rgba(255,255,255,0.22)"/>
    <rect x="-30" y="-18" width="55"  height="6"  rx="2" fill="rgba(255,255,255,0.65)"/>
    <rect x="-30" y="-6"  width="42"  height="4"  rx="2" fill="rgba(255,255,255,0.42)"/>
    <rect x="-30" y="4"   width="48"  height="4"  rx="2" fill="rgba(255,255,255,0.42)"/>
    <rect x="-30" y="18"  width="60"  height="8"  rx="3" fill="rgba(255,255,255,0.30)" stroke="rgba(255,255,255,0.50)" stroke-width="1"/>
SVG;
}

function icon_cad(): string {
    return <<<SVG
    <!-- blueprint/wide sheet with fold corner -->
    <rect x="-90" y="-50" width="168" height="108" rx="3" fill="rgba(255,255,255,0.35)"/>
    <!-- fold corner dog-ear -->
    <polygon points="78,-50 78,-22 106,-50" fill="rgba(255,255,255,0.20)"/>
    <line x1="78" y1="-50" x2="78" y2="-22" stroke="rgba(255,255,255,0.50)" stroke-width="1"/>
    <line x1="78" y1="-22" x2="106" y2="-50" stroke="rgba(255,255,255,0.50)" stroke-width="1"/>
    <!-- grid lines (blueprint look) -->
    <line x1="-78" y1="-22" x2="78" y2="-22" stroke="rgba(255,255,255,0.35)" stroke-width="0.75"/>
    <line x1="-78" y1="0"   x2="78" y2="0"   stroke="rgba(255,255,255,0.35)" stroke-width="0.75"/>
    <line x1="-78" y1="22"  x2="78" y2="22"  stroke="rgba(255,255,255,0.35)" stroke-width="0.75"/>
    <line x1="-45" y1="-44" x2="-45" y2="48" stroke="rgba(255,255,255,0.35)" stroke-width="0.75"/>
    <line x1="0"   y1="-44" x2="0"   y2="48" stroke="rgba(255,255,255,0.35)" stroke-width="0.75"/>
    <line x1="45"  y1="-44" x2="45"  y2="48" stroke="rgba(255,255,255,0.35)" stroke-width="0.75"/>
    <!-- title block bottom-right -->
    <rect x="28" y="28" width="50" height="20" rx="1" fill="rgba(255,255,255,0.22)" stroke="rgba(255,255,255,0.45)" stroke-width="0.75"/>
    <!-- dimension arrows -->
    <line x1="-85" y1="-44" x2="-85" y2="44" stroke="rgba(255,255,255,0.55)" stroke-width="1.5" stroke-linecap="round" marker-start="url(#arr)" marker-end="url(#arr)"/>
SVG;
}

function icon_trade_show(): string {
    return <<<SVG
    <!-- curved arch top -->
    <path d="M-75,-20 Q-75,-75 0,-75 Q75,-75 75,-20" fill="none" stroke="rgba(255,255,255,0.55)" stroke-width="3" stroke-linecap="round"/>
    <!-- left post -->
    <rect x="-80" y="-22" width="12" height="90" rx="3" fill="rgba(255,255,255,0.40)"/>
    <!-- right post -->
    <rect x="68"  y="-22" width="12" height="90" rx="3" fill="rgba(255,255,255,0.40)"/>
    <!-- booth back panel -->
    <rect x="-68" y="-22" width="136" height="85" rx="1" fill="rgba(255,255,255,0.22)"/>
    <!-- graphic on back panel -->
    <rect x="-50" y="-12" width="100" height="42" rx="2" fill="rgba(255,255,255,0.18)"/>
    <rect x="-35" y="-4"  width="70"  height="6"  rx="2" fill="rgba(255,255,255,0.60)"/>
    <rect x="-28" y="8"   width="56"  height="4"  rx="2" fill="rgba(255,255,255,0.40)"/>
    <!-- table in front -->
    <rect x="-50" y="58"  width="100" height="8"  rx="2" fill="rgba(255,255,255,0.38)"/>
    <line x1="-40" y1="66" x2="-40" y2="80" stroke="rgba(255,255,255,0.35)" stroke-width="4" stroke-linecap="round"/>
    <line x1="40"  y1="66" x2="40"  y2="80" stroke="rgba(255,255,255,0.35)" stroke-width="4" stroke-linecap="round"/>
SVG;
}

// ── Promotional ─────────────────────────────────────────

function icon_decals(): string {
    return <<<SVG
    <!-- shield/badge shape -->
    <path d="M0,-72 L60,-45 L60,10 Q60,55 0,72 Q-60,55 -60,10 L-60,-45 Z" fill="rgba(255,255,255,0.35)" stroke="rgba(255,255,255,0.55)" stroke-width="2"/>
    <!-- inner border -->
    <path d="M0,-58 L47,-35 L47,12 Q47,45 0,58 Q-47,45 -47,12 L-47,-35 Z" fill="none" stroke="rgba(255,255,255,0.45)" stroke-width="1.5"/>
    <!-- star/text center -->
    <rect x="-25" y="-12" width="50" height="6"  rx="3" fill="rgba(255,255,255,0.70)"/>
    <rect x="-18" y="2"   width="36" height="4"  rx="2" fill="rgba(255,255,255,0.45)"/>
SVG;
}

function icon_can(): string {
    return <<<SVG
    <!-- cylindrical can body -->
    <rect x="-35" y="-58" width="70" height="120" rx="3" fill="rgba(255,255,255,0.35)"/>
    <!-- top ellipse -->
    <ellipse cx="0" cy="-58" rx="35" ry="10" fill="rgba(255,255,255,0.50)"/>
    <!-- bottom ellipse -->
    <ellipse cx="0" cy="62" rx="35" ry="10" fill="rgba(255,255,255,0.30)"/>
    <!-- label band -->
    <rect x="-35" y="-25" width="70" height="55" rx="0" fill="rgba(255,255,255,0.20)"/>
    <line x1="-35" y1="-25" x2="35" y2="-25" stroke="rgba(255,255,255,0.40)" stroke-width="1"/>
    <line x1="-35" y1="30"  x2="35" y2="30"  stroke="rgba(255,255,255,0.40)" stroke-width="1"/>
    <!-- label text -->
    <rect x="-22" y="-14" width="44" height="5"  rx="2" fill="rgba(255,255,255,0.65)"/>
    <rect x="-18" y="-2"  width="36" height="3"  rx="2" fill="rgba(255,255,255,0.40)"/>
    <rect x="-20" y="6"   width="40" height="3"  rx="2" fill="rgba(255,255,255,0.40)"/>
SVG;
}

function icon_stamp(): string {
    return <<<SVG
    <!-- stamp handle -->
    <rect x="-18" y="-72" width="36" height="28" rx="6" fill="rgba(255,255,255,0.40)"/>
    <rect x="-10" y="-44" width="20" height="22" rx="2" fill="rgba(255,255,255,0.35)"/>
    <!-- stamp base with impression lines -->
    <rect x="-55" y="-25" width="110" height="48" rx="3" fill="rgba(255,255,255,0.42)"/>
    <!-- impression pattern on stamp face -->
    <rect x="-42" y="-15" width="84" height="5"  rx="2" fill="rgba(255,255,255,0.70)"/>
    <rect x="-42" y="-4"  width="65" height="3"  rx="2" fill="rgba(255,255,255,0.50)"/>
    <rect x="-42" y="4"   width="75" height="3"  rx="2" fill="rgba(255,255,255,0.50)"/>
    <!-- ink mark below -->
    <rect x="-55" y="28"  width="110" height="10" rx="2" fill="rgba(255,255,255,0.22)"/>
SVG;
}

function icon_table_throw(): string {
    return <<<SVG
    <!-- table top surface -->
    <rect x="-80" y="-42" width="160" height="10" rx="3" fill="rgba(255,255,255,0.55)"/>
    <!-- front cloth draping down -->
    <path d="M-80,-32 L-80,60 Q-80,68 -72,68 L72,68 Q80,68 80,60 L80,-32 Z" fill="rgba(255,255,255,0.38)"/>
    <!-- cloth wrinkle/fold lines -->
    <line x1="-30" y1="-32" x2="-35" y2="68" stroke="rgba(255,255,255,0.25)" stroke-width="1.5"/>
    <line x1="30"  y1="-32" x2="35"  y2="68" stroke="rgba(255,255,255,0.25)" stroke-width="1.5"/>
    <!-- logo/graphic on cloth front -->
    <rect x="-35" y="0"  width="70" height="6"  rx="3" fill="rgba(255,255,255,0.65)"/>
    <rect x="-25" y="12" width="50" height="3"  rx="2" fill="rgba(255,255,255,0.40)"/>
    <!-- side skirts -->
    <rect x="-92" y="-32" width="14" height="90" rx="0 0 3 3" fill="rgba(255,255,255,0.25)"/>
    <rect x="78"  y="-32" width="14" height="90" rx="0 0 3 3" fill="rgba(255,255,255,0.25)"/>
SVG;
}

function icon_wall_art(): string {
    return <<<SVG
    <!-- outer frame -->
    <rect x="-72" y="-62" width="144" height="124" rx="5" fill="rgba(255,255,255,0.42)"/>
    <!-- inner mat -->
    <rect x="-58" y="-48" width="116" height="96" rx="3" fill="rgba(255,255,255,0.20)"/>
    <!-- canvas area -->
    <rect x="-48" y="-38" width="96" height="76" rx="2" fill="rgba(255,255,255,0.32)"/>
    <!-- landscape scene on canvas -->
    <rect x="-48" y="4"   width="96" height="34" rx="0 0 2 2" fill="rgba(255,255,255,0.18)"/>
    <polygon points="-48,4 -15,-30 18,4" fill="rgba(255,255,255,0.35)"/>
    <polygon points="18,4 42,-20 66,4" fill="rgba(255,255,255,0.28)"/>
    <circle cx="28" cy="-22" r="12" fill="rgba(255,255,255,0.28)"/>
    <!-- hanging wire -->
    <path d="M-20,-62 Q0,-74 20,-62" fill="none" stroke="rgba(255,255,255,0.50)" stroke-width="2" stroke-linecap="round"/>
SVG;
}

// ── Signage ─────────────────────────────────────────────

function icon_yard_sign(): string {
    return <<<SVG
    <!-- H-stake left post -->
    <rect x="-42" y="-12" width="8" height="82" rx="3" fill="rgba(255,255,255,0.45)"/>
    <!-- H-stake right post -->
    <rect x="34"  y="-12" width="8" height="82" rx="3" fill="rgba(255,255,255,0.45)"/>
    <!-- H-bar connector -->
    <rect x="-34" y="25" width="68" height="8" rx="2" fill="rgba(255,255,255,0.40)"/>
    <!-- sign panel -->
    <rect x="-60" y="-72" width="120" height="65" rx="4" fill="rgba(255,255,255,0.45)"/>
    <!-- text lines -->
    <rect x="-45" y="-60" width="90" height="8"  rx="3" fill="rgba(255,255,255,0.75)"/>
    <rect x="-38" y="-44" width="76" height="5"  rx="2" fill="rgba(255,255,255,0.50)"/>
    <rect x="-38" y="-32" width="60" height="4"  rx="2" fill="rgba(255,255,255,0.50)"/>
SVG;
}

function icon_a_frame(): string {
    return <<<SVG
    <!-- left leg -->
    <line x1="-65" y1="68" x2="0" y2="-65" stroke="rgba(255,255,255,0.50)" stroke-width="6" stroke-linecap="round"/>
    <!-- right leg -->
    <line x1="65"  y1="68" x2="0" y2="-65" stroke="rgba(255,255,255,0.50)" stroke-width="6" stroke-linecap="round"/>
    <!-- cross brace -->
    <line x1="-25" y1="15" x2="25" y2="15" stroke="rgba(255,255,255,0.40)" stroke-width="3" stroke-linecap="round"/>
    <!-- left sign panel -->
    <polygon points="-58,58 -5,-55 0,-58 0,18 -55,65" fill="rgba(255,255,255,0.32)"/>
    <!-- right sign panel -->
    <polygon points="58,58 5,-55 0,-58 0,18 55,65" fill="rgba(255,255,255,0.22)"/>
    <!-- text lines on left panel -->
    <rect x="-42" y="-25" width="35" height="5"  rx="2" fill="rgba(255,255,255,0.65)" transform="rotate(-30 -25 -22)"/>
    <rect x="-40" y="-10" width="28" height="3"  rx="2" fill="rgba(255,255,255,0.42)" transform="rotate(-30 -26 -8)"/>
SVG;
}

function icon_tent(): string {
    return <<<SVG
    <!-- tent roof left slope -->
    <polygon points="0,-68 -90,10 -75,10" fill="rgba(255,255,255,0.40)"/>
    <!-- tent roof right slope -->
    <polygon points="0,-68 90,10 75,10" fill="rgba(255,255,255,0.28)"/>
    <!-- ridge cap -->
    <line x1="-90" y1="10" x2="90" y2="10" stroke="rgba(255,255,255,0.55)" stroke-width="4" stroke-linecap="round"/>
    <line x1="0"   y1="-68" x2="-90" y2="10" stroke="rgba(255,255,255,0.55)" stroke-width="3" stroke-linecap="round"/>
    <line x1="0"   y1="-68" x2="90"  y2="10" stroke="rgba(255,255,255,0.55)" stroke-width="3" stroke-linecap="round"/>
    <!-- tent side walls -->
    <rect x="-90" y="10" width="18" height="60" rx="0" fill="rgba(255,255,255,0.30)"/>
    <rect x="72"  y="10" width="18" height="60" rx="0" fill="rgba(255,255,255,0.22)"/>
    <!-- front valance/opening -->
    <rect x="-72" y="10" width="144" height="12" rx="0" fill="rgba(255,255,255,0.35)"/>
    <!-- logo on valance -->
    <rect x="-35" y="12" width="70" height="7"  rx="2" fill="rgba(255,255,255,0.65)"/>
SVG;
}

function icon_flag(): string {
    return <<<SVG
    <!-- feather flag pole -->
    <line x1="-35" y1="70" x2="-35" y2="-72" stroke="rgba(255,255,255,0.50)" stroke-width="5" stroke-linecap="round"/>
    <!-- flag fabric (feather shape) -->
    <path d="M-35,-72 Q60,-55 50,0 Q40,55 -35,70 Q-25,35 -25,0 Q-25,-35 -35,-72 Z" fill="rgba(255,255,255,0.38)"/>
    <!-- graphic/stripe on flag -->
    <path d="M-28,-55 Q45,-42 38,-8 Q-28,0 -28,-8 Z" fill="rgba(255,255,255,0.22)"/>
    <rect x="-28" y="5"  width="55" height="5"  rx="2" fill="rgba(255,255,255,0.55)" transform="rotate(3 0 5)"/>
    <rect x="-28" y="18" width="45" height="3"  rx="2" fill="rgba(255,255,255,0.38)" transform="rotate(3 0 18)"/>
    <!-- base weight -->
    <ellipse cx="-35" cy="70" rx="16" ry="5" fill="rgba(255,255,255,0.40)"/>
SVG;
}

function icon_signage(): string {
    return <<<SVG
    <!-- multiple sign panels staggered -->
    <rect x="-85" y="-55" width="90"  height="50" rx="4" fill="rgba(255,255,255,0.45)"/>
    <rect x="-60" y="-8"  width="100" height="50" rx="4" fill="rgba(255,255,255,0.35)"/>
    <rect x="-75" y="45"  width="85"  height="32" rx="4" fill="rgba(255,255,255,0.25)"/>
    <!-- text on top sign -->
    <rect x="-72" y="-43" width="62"  height="6"  rx="2" fill="rgba(255,255,255,0.75)"/>
    <rect x="-72" y="-30" width="50"  height="4"  rx="2" fill="rgba(255,255,255,0.50)"/>
    <rect x="-72" y="-20" width="56"  height="4"  rx="2" fill="rgba(255,255,255,0.50)"/>
SVG;
}

/* ── Dispatch: call the icon function and build the file ── */

$catalog_raw = file_get_contents( $plugin_root . '/storage/print-templates/catalog.json' );
$catalog     = json_decode( $catalog_raw, true ) ?: [];

$written = 0;

foreach ( $templates as $slug => [$label, $category, $icon_fn] ) {
    if ( ! function_exists( $icon_fn ) ) {
        echo "  skip  $slug  (no icon function '$icon_fn')\n";
        continue;
    }
    $grad    = $gradients[ $category ] ?? [ '#374151', '#9ca3af' ];
    $icon    = call_user_func( $icon_fn );
    $svg     = make_svg( $label, $category, $grad, $icon );

    $out = $thumb_dir . '/' . $slug . '.svg';
    file_put_contents( $out, $svg );
    $written++;
    echo "  ok  $slug\n";
}

echo "\nWrote $written thumbnails to storage/print-templates/thumbnails/\n";

/* ── Patch catalog.json thumbnail paths to .svg ── */
if ( is_array( $catalog ) && ! empty( $catalog['templates'] ) ) {
    foreach ( $catalog['templates'] as &$tpl ) {
        $tpl['thumbnail'] = 'thumbnails/' . $tpl['slug'] . '.svg';
    }
    unset( $tpl );
    $catalog['generated'] = gmdate( 'c' );
    file_put_contents(
        $plugin_root . '/storage/print-templates/catalog.json',
        json_encode( $catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
    );
    echo "Updated catalog.json thumbnail paths → .svg\n";
}
