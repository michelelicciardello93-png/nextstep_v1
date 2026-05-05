<?php

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/response.php';

function handle_runs($action) {
  switch ($action) {

    case 'start':
      $pdo = db();

      $body = json_decode(file_get_contents('php://input'), true);
      $processId = $body['process_id'] ?? null;
      $versionId = $body['version_id'] ?? null;

      if (!$processId || !$versionId) {
        json(['error' => 'Missing process_id or version_id'], 400);
      }

      // get start node
      $stmt = $pdo->prepare("SELECT start_node_key FROM process_versions WHERE id = ?");
      $stmt->execute([$versionId]);
      $version = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$version) {
        json(['error' => 'Invalid version'], 400);
      }

      $startNode = $version['start_node_key'];

      $stmt = $pdo->prepare("INSERT INTO runs (process_id, version_id, current_node_key) VALUES (?, ?, ?)");
      $stmt->execute([$processId, $versionId, $startNode]);

      $runId = $pdo->lastInsertId();

      json([
        'run_id' => $runId,
        'current_node_key' => $startNode
      ]);

      break;

    case 'step':
      $pdo = db();

      $body = json_decode(file_get_contents('php://input'), true);
      $runId = $body['run_id'] ?? null;
      $answer = $body['answer'] ?? null;

      if (!$runId || $answer === null) {
        json(['error' => 'Missing run_id or answer'], 400);
      }

      // get run
      $stmt = $pdo->prepare("SELECT * FROM runs WHERE id = ?");
      $stmt->execute([$runId]);
      $run = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$run) {
        json(['error' => 'Run not found'], 404);
      }

      $currentNode = $run['current_node_key'];
      $versionId = $run['version_id'];

      // store step
      $stmt = $pdo->prepare("INSERT INTO run_steps (run_id, node_key, answer_value) VALUES (?, ?, ?)");
      $stmt->execute([$runId, $currentNode, $answer]);

      // find next node
      $stmt = $pdo->prepare("SELECT to_node_key FROM process_edges WHERE version_id = ? AND from_node_key = ? AND option_value = ?");
      $stmt->execute([$versionId, $currentNode, $answer]);
      $edge = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$edge) {
        json(['error' => 'No next node'], 400);
      }

      $nextNode = $edge['to_node_key'];

      // update run
      $stmt = $pdo->prepare("UPDATE runs SET current_node_key = ? WHERE id = ?");
      $stmt->execute([$nextNode, $runId]);

      json([
        'next_node_key' => $nextNode
      ]);

      break;

    default:
      json(['error' => 'Invalid runs action'], 400);
  }
}
