<?php

spl_autoload_register(function ($class) {
    $prefixes = [
        'App\\Services\\' => __DIR__ . '/core/Services/',
        'App\\Models\\' => __DIR__ . '/core/Models/',
        'App\\Exceptions\\' => __DIR__ . '/core/Exceptions/',
        'Tests\\' => __DIR__ . '/tests/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});
