<?php

require_once 'config.php';

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

// remove installation folder
$path = str_replace('client/Ficksup/api/', '', $path);

$segments = explode('/', $path);

switch ($segments[0]) {

    case 'pool-types':
    case 'pool-shapes':
    case 'pool-models':
    case 'gel-coats':
        require 'routes/pool_public.php';
        break;

    case 'admin':
        require 'routes/pool_admin.php';
        break;

    case 'proposal':
        require 'routes/proposal.php';
        break;

    case 'upload':
        require 'routes/upload.php';
        break;

    case 'auth':
        require 'routes/auth.php';
        break;

    case 'users':
        require 'routes/users.php';
        break;

    default:
        jsonResponse(false, [], 'Endpoint not found', 404);
}