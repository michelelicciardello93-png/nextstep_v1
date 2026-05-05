<?php

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/response.php';

function handle_setup($action) {
  switch ($action) {

    case 'install-demo':
      $pdo = db();

      // create process
      $pdo->exec("INSERT INTO processes (name) VALUES ('Demo Flow')");
      $processId = $pdo->lastInsertId();

      // version
      $stmt = $pdo->prepare("INSERT INTO process_versions (process_id, version_number, status, start_node_key) VALUES (?, 1, 'published', 'q1')");
      $stmt->execute([$processId]);
      $versionId = $pdo->lastInsertId();

      // nodes
      $stmt = $pdo->prepare("INSERT INTO process_nodes (version_id, node_key, type, title, options) VALUES (?, ?, ?, ?, ?)");
      $stmt->execute([$versionId, 'q1', 'question', 'Do you want pizza?', json_encode(['yes','no'])]);
      $stmt->execute([$versionId, 'end_yes', 'outcome', 'Order pizza', null]);
      $stmt->execute([$versionId, 'end_no', 'outcome', 'Do nothing', null]);

      // edges
      $stmt = $pdo->prepare("INSERT INTO process_edges (version_id, from_node_key, option_value, to_node_key) VALUES (?, ?, ?, ?)");
      $stmt->execute([$versionId, 'q1', 'yes', 'end_yes']);
      $stmt->execute([$versionId, 'q1', 'no', 'end_no']);

      json(['process_id' => (int)$processId, 'version_id' => (int)$versionId]);

      break;

    default:
      json(['error' => 'Invalid setup action'], 400);
  }
}
