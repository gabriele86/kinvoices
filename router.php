<?php

/*
 * Router script for PHP's built-in web server:
 *
 *     php -S 127.0.0.1:8000 -t public router.php
 *
 * Existing files under public/ (CSS, JS, images) are served by the web server
 * itself, with their real MIME type; every other URL goes through the Symfony
 * front controller. Not used by Apache/nginx or by the Symfony CLI server.
 */

$path = urldecode((string) parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH));

if ('/' !== $path && is_file(__DIR__.'/public'.$path)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';

require __DIR__.'/public/index.php';
