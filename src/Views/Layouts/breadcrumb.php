<?php

//file: Views/Layouts/breadcrumb.php
declare(strict_types=1);

// Ensure this view is not directly accessible via the web
if (!defined('BASE_PATH')) {
    header("HTTP/1.0 403 Forbidden");
    exit;
}

use App\Utils\Breadcrumb;

// Get the current route
$uri = $_SERVER['REQUEST_URI'];
$route = parse_url($uri, PHP_URL_PATH);
$route = ltrim($route, '/');

// Get parameters (e.g., ID). Breadcrumb URL placeholders are scalars like {id};
// only collect scalar view vars. Passing objects/arrays (e.g. $settingsService,
// $users) made replaceUrlParams cast them to string and throw
// "Object ... could not be converted to string" / array-to-string warnings.
$params = [];
foreach ($data ?? [] as $key => $value) {
    if (is_string($key) && !is_numeric($key) && (is_scalar($value) || $value === null)) {
        $params[$key] = $value;
    }
}

// Render the breadcrumbs
echo Breadcrumb::render($route, $params);
