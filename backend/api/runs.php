<?php

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/response.php';

function get_node($pdo, $versionId, $nodeKey) {
  $stmt = $pdo->prepare("SELECT node_key, type, title, body, options FROM process_nodes WHERE version_id = ? AND node_key = ?");
  $stmt->execute([$versionId, $nodeKey]);
  $node = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$node) {
    return null;
  }

  $node['key'] = $node['node_key'];
  unset($node['node_key']);
  $node['options'] = $node['options'] ? json_decode($node['options'], true) : [];

  return $node;
}

function handle_runs($action) {
  switch ($action) {

    case 'start':
      $pdo = db();
      $body = json_decode(file_get_contents('php://input'), true) ?: [];
      $processId = $body['process_id'] ?? null;
      $versionId = $body['version_id'] ?? null;

      if (!$processId || !$versionId) {
        json(['error' => 'Missing process_id or version_id'], 400);
      }

      $stmt = $pdo->prepare("SELECT start_node_key FROM process_versions WHERE id = ?");
      $stmt->execute([$versionId]);
      $version = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$version) {
        json(['error' => 'Invalid version'], 400);
      }

      $startNode = $version['start_node_key'];
      $node = get_node($pdo, $versionId, $startNode);

      if (!$node) {
        json(['error' => 'Start node not found'], 400);
      }

      $stmt = $pdo->prepare("INSERT INTO runs (process_id, version_id, current_node_key) VALUES (?, ?, ?)");
      $stmt->execute([$processId, $versionId, $startNode]);

      json([
        'run_id' => (int)$pdo->lastInsertId(),
        'node' => $node,
        'completed' => $node['type'] === 'outcome'
      ]);
      break;

    case 'step':
      $pdo = db();
      $body = json_decode(file_get_contents('php://input'), true) ?: [];
      $runId = $body['run_id'] ?? null;
      $answer = $body['answer'] ?? null;

      if (!$runId || $answer === null) {
        json(['error' => 'Missing run_id or answer'], 400);
      }

      $stmt = $pdo->prepare("SELECT * FROM runs WHERE id = ?");
      $stmt->execute([$runId]);
      $run = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$run) {
        json(['error' => 'Run not found'], 404);
      }

      if ($run['status'] === 'completed') {
        json(['error' => 'Run already completed'], 400);
      }

      $currentNode = $run['current_node_key'];
      $versionId = $run['version_id'];

      $stmt = $pdo->prepare("INSERT INTO run_steps (run_id, node_key, answer_value) VALUES (?, ?, ?)");
      $stmt->execute([$runId, $currentNode, $answer]);

      $stmt = $pdo->prepare("SELECT to_node_key FROM process_edges WHERE version_id = ? AND from_node_key = ? AND option_value = ?");
      $stmt->execute([$versionId, $currentNode, $answer]);
      $edge = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$edge) {
        json(['error' => 'No next node for this answer'], 400);
      }

      $nextNodeKey = $edge['to_node_key'];
      $node = get_node($pdo, $versionId, $nextNodeKey);

      if (!$node) {
        json(['error' => 'Next node not found'], 400);
      }

      $completed = $node['type'] === 'outcome';

      if ($completed) {
        $stmt = $pdo->prepare("UPDATE runs SET current_node_key = ?, status = 'completed', completed_at = NOW() WHERE id = ?");
      } else {
        $stmt = $pdo->prepare("UPDATE runs SET current_node_key = ? WHERE id = ?");
      }
      $stmt->execute([$nextNodeKey, $runId]);

      json([
        'run_id' => (int)$runId,
        'node' => $node,
        'completed' => $completed
      ]);
      break;

    default:
      json(['error' => 'Invalid runs action'], 400);
  }
}
