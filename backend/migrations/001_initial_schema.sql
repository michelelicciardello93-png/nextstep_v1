CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NULL,
  role ENUM('superadmin','admin','agent') NOT NULL DEFAULT 'agent',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS workspaces (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS processes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  workspace_id INT NULL,
  name VARCHAR(255) NOT NULL,
  type ENUM('decision_flow','checklist','evaluation','form') NOT NULL DEFAULT 'decision_flow',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS process_versions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  process_id INT NOT NULL,
  version_number INT NOT NULL,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  start_node_key VARCHAR(120) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_process_version (process_id, version_number)
);

CREATE TABLE IF NOT EXISTS process_nodes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  version_id INT NOT NULL,
  node_key VARCHAR(120) NOT NULL,
  type ENUM('question','outcome') NOT NULL DEFAULT 'question',
  title VARCHAR(255) NOT NULL,
  body TEXT NULL,
  options JSON NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_version_node_key (version_id, node_key)
);

CREATE TABLE IF NOT EXISTS process_edges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  version_id INT NOT NULL,
  from_node_key VARCHAR(120) NOT NULL,
  option_value VARCHAR(255) NOT NULL,
  to_node_key VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS runs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  process_id INT NOT NULL,
  version_id INT NOT NULL,
  user_id INT NULL,
  current_node_key VARCHAR(120) NULL,
  status ENUM('active','completed') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL
);

CREATE TABLE IF NOT EXISTS run_steps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  run_id INT NOT NULL,
  node_key VARCHAR(120) NOT NULL,
  answer_value VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
