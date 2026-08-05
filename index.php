<?php

declare(strict_types=1);

// Entry point for installations where the document root IS the application
// root, which is the only layout some shared hosts allow.
//
// public/assets is a real directory the web server serves directly, so asset
// URLs point there rather than requiring files to be copied or symlinked.
// BASE_PATH is still defined as public/ by the front controller below, so every
// include resolves exactly as it does in the recommended layout.
define('AUREO_ASSET_PREFIX', '/public/assets');

require __DIR__ . '/public/index.php';
