<?php

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) {
    die();
}

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__)
);

$paths = [];
foreach ($iter as $info) {
    if (!$info->isFile()) {
        continue;
    }

    if ($info->getRealPath() === __FILE__) {
        continue;
    }

    $paths[] = $info->getRealPath();
}
unset($iter);

sort($paths);

foreach ($paths as $path) {
    require_once $path;
}

unset($paths);
