<?php
// autoload_patched.php — custom wrapper

$loader = require __DIR__ . '/autoload.php';

// Wrap the Composer class loader to check interface/class exists
if ($loader instanceof \Composer\Autoload\ClassLoader) {
    spl_autoload_register(function ($class) use ($loader) {
        if (interface_exists($class, false) || class_exists($class, false)) {
            return; // already loaded, skip
        }
        $loader->loadClass($class);
    }, true, true);
}

return $loader;
