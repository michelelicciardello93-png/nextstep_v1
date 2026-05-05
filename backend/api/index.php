<?php

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/response.php';

$path = $_GET['path'] ?? '';
$parts = explode('/', trim($path, '/'));

$resource = $parts[0] ?? '';
$action = $parts[1] ?? '';

switch ($resource) {

  case 'health':
    json(['ok' => true]);
    break;

  case 'processes':
    require_once __DIR__ . '/processes.php';
    handle_processes($action);
    break;

  default:
    json(['error' => 'Not found'], 404);
}
