<?php
session_start();
require "includes/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST["title"]);
    $description = trim($_POST["description"]);
    $due_date = $_POST["due_date"] !== "" ? $_POST["due_date"] : null;
    $priority = $_POST["priority"];
    $status = $_POST["status"];

    $allowed_priority = ["low", "moderate", "extreme"];
    $allowed_status = ["not_started", "in_progress", "completed"];

    if ($title === "") {
        $message = "<p class='error'>Title is required.</p>";
    } elseif (!in_array($priority, $allowed_priority) || !in_array($status, $allowed_status)) {
        $message = "<p class='error'>Invalid priority or status.</p>";
    } else {
        $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, description, due_date, priority, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION["user_id"], $title, $description, $due_date, $priority, $status]);
        header("Location: dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Add Task</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <div class="box">
    <h2>Add Task</h2>
    <?php echo $message; ?>
    <form method="POST">
      <input type="text" name="title" placeholder="Task title">
      <textarea name="description" rows="3" placeholder="Description (optional)"></textarea>
      <input type="date" name="due_date">

      <select name="priority">
        <option value="low">Low priority</option>
        <option value="moderate" selected>Moderate priority</option>
        <option value="extreme">Extreme priority</option>
      </select>

      <select name="status">
        <option value="not_started" selected>Not Started</option>
        <option value="in_progress">In Progress</option>
        <option value="completed">Completed</option>
      </select>

      <button type="submit">Save task</button>
    </form>
    <p><a href="dashboard.php">Back to dashboard</a>
        <a href="planner.php">Day Planner</a> 
        <a href="habits.php">Habit Tracker</a>
    </p>
  </div>
</body>
</html>