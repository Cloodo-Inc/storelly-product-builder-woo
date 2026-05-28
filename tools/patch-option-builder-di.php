<?php
/**
 * One-off patch: add DI annotations to minified directive registrations in
 * static/js/option-builder.js so AngularJS dependency injection works after
 * minification (the build pipeline did not run ng-annotate, so the original
 * factory function param names like $timeout/$window were renamed to single
 * letters and Angular's by-name DI lookup fails with $injector:unpr).
 *
 * Run from plugin root:
 *   docker exec wp_app php /var/www/html/wp-content/plugins/storelly-product-builder-woo/tools/patch-option-builder-di.php
 *
 * Safe to re-run: skips directives already wrapped in array form.
 */

$path = __DIR__ . '/../static/js/option-builder.js';
$src  = file_get_contents($path);
if ($src === false) {
    fwrite(STDERR, "Cannot read $path\n");
    exit(1);
}

// Map directive name → array of injectable service names (inferred from
// minified usage: each directive's first/second param is called as a
// function with a callback, matching $timeout's signature).
$di_map = array(
    'nboClickDebounce' => array('$timeout'),
    'nbdHelpTip'       => array('$timeout'),
    'nboAdvDropdown'   => array('$timeout'),
    'nboInputFile'     => array('$timeout', '$window'),
    'nboDisabled'      => array('$timeout'),
);

$patched_count = 0;

foreach ($di_map as $name => $deps) {
    // Skip if already patched: array form starts with `.directive("Name",["…`
    $already = '.directive("' . $name . '",["';
    if (strpos($src, $already) !== false) {
        echo "skip $name (already array form)\n";
        continue;
    }

    $needle = '.directive("' . $name . '",function(';
    $hit    = strpos($src, $needle);
    if ($hit === false) {
        echo "miss $name (signature not found)\n";
        continue;
    }

    // `function(` keyword position
    $func_start = strpos($src, 'function(', $hit);
    if ($func_start === false) {
        echo "miss $name (function keyword)\n";
        continue;
    }

    // Walk to opening `{`
    $brace_open = strpos($src, '{', $func_start);
    if ($brace_open === false) {
        echo "miss $name (opening brace)\n";
        continue;
    }

    // Brace-balance walk to find matching `}`. Skip braces inside string
    // literals and regex bodies.
    $depth = 1;
    $i     = $brace_open + 1;
    $len   = strlen($src);
    while ($i < $len && $depth > 0) {
        $ch = $src[$i];

        if ($ch === '"' || $ch === "'") {
            $quote = $ch;
            $i++;
            while ($i < $len && $src[$i] !== $quote) {
                if ($src[$i] === '\\') { $i++; }
                $i++;
            }
            $i++;
            continue;
        }

        if ($ch === '{') { $depth++; $i++; continue; }
        if ($ch === '}') { $depth--; $i++; continue; }

        $i++;
    }

    if ($depth !== 0) {
        echo "fail $name (unbalanced braces, depth $depth)\n";
        continue;
    }

    // Expect closing `)` of the directive() call immediately after the
    // function expression closing `}`. The function literal ended at $i,
    // so the next char is the `)` of `.directive("Name",function(){})`.
    if (!isset($src[$i]) || $src[$i] !== ')') {
        echo "fail $name (expected ')' at $i, got '" . substr($src, $i, 5) . "')\n";
        continue;
    }

    $func_text     = substr($src, $func_start, $i - $func_start);
    $deps_literal  = '"' . implode('","', $deps) . '"';
    $replacement   = '[' . $deps_literal . ',' . $func_text . ']';

    $src = substr($src, 0, $func_start) . $replacement . substr($src, $i);
    $patched_count++;
    echo "patched $name (deps: " . implode(',', $deps) . ")\n";
}

file_put_contents($path, $src);
echo "done. patched=$patched_count\n";
echo "size=" . filesize($path) . "\n";
