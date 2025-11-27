<?php

namespace SPBWC;

\spl_autoload_register(function ($class) {
    // chỉ load các class thuộc namespace SPBWC\setasign\Fpdi
    $prefix = 'SPBWC\\setasign\\Fpdi\\';
    $prefixLen = strlen($prefix);

    if (strncmp($class, $prefix, $prefixLen) === 0) {
        $relativeClass = substr($class, $prefixLen);
        $filename = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
        $fullpath = __DIR__ . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($fullpath)) {
            require_once $fullpath;
        }
    }
});
