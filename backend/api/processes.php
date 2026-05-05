<?php

function handle_processes($action) {
  switch ($action) {

    case 'list':
      json([
        'data' => [],
        'message' => 'Process list endpoint ready'
      ]);
      break;

    default:
      json(['error' => 'Invalid processes action'], 400);
  }
}
