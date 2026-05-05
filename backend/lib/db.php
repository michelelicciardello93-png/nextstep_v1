<?php

function db() {
  static $pdo = null;

  if ($pdo === null) {
    $pdo = new PDO(
      "mysql:host=localhost;dbname=nextstep;charset=utf8mb4",
      "root",
      "",
      [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
  }

  return $pdo;
}
