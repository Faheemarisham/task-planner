CREATE DATABASE IF NOT EXISTS task_planner CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE task_planner;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT,
  priority ENUM('low','moderate','extreme') NOT NULL DEFAULT 'moderate',
  due_date DATE,
  status ENUM('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE plan_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  plan_date DATE NOT NULL,
  section ENUM('priority','goal','dont_forget','grateful','tomorrow') NOT NULL,
  position TINYINT NOT NULL,
  content VARCHAR(255) NOT NULL DEFAULT '',
  UNIQUE KEY uniq_item (user_id, plan_date, section, position),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE plan_notes (
  user_id INT NOT NULL,
  plan_date DATE NOT NULL,
  notes TEXT,
  PRIMARY KEY (user_id, plan_date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE plan_schedule (
  user_id INT NOT NULL,
  plan_date DATE NOT NULL,
  slot_hour TINYINT NOT NULL,
  content VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (user_id, plan_date, slot_hour),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE habits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(60) NOT NULL,
  UNIQUE KEY uniq_habit (user_id, name),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE habit_logs (
  habit_id INT NOT NULL,
  log_date DATE NOT NULL,
  PRIMARY KEY (habit_id, log_date),
  FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE
);