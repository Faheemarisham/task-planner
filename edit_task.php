<?php
session_start();
require "includes/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$uid = $_SESSION["user_id"];

$u = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$u->execute([$uid]);
$user = $u->fetch(PDO::FETCH_ASSOC);

$id = (int)($_GET["id"] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $uid]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    header("Location: dashboard.php");
    exit;
}

if (!in_array($task["status"], ["not_started", "in_progress", "completed"])) {
    $task["status"] = "not_started";
}
if (!in_array($task["priority"], ["low", "moderate", "extreme"])) {
    $task["priority"] = "moderate";
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
        $update = $pdo->prepare("UPDATE tasks SET title = ?, description = ?, due_date = ?, priority = ?, status = ? WHERE id = ? AND user_id = ?");
        $update->execute([$title, $description, $due_date, $priority, $status, $id, $uid]);
        header("Location: dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Task</title>
  <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
  <div class="layout">

    <aside class="sidebar">
      <div class="avatar"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($user["name"], 0, 1))); ?></div>
      <div class="uname"><?php echo htmlspecialchars($user["name"]); ?></div>
      <div class="uemail"><?php echo htmlspecialchars($user["email"]); ?></div>

      <nav class="nav">
        <a href="dashboard.php">Dashboard</a>
        <a href="add_task.php">+ Add Task</a>
        <a href="logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <h1>Edit Task</h1>

      <section class="card form-card">
        <?php echo $message; ?>

        <form method="POST">
          <label for="title">Title</label>
          <input type="text" id="title" name="title"
                 value="<?php echo htmlspecialchars($_POST["title"] ?? $task["title"]); ?>">

          <label for="description">Description</label>
          <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($_POST["description"] ?? ($task["description"] ?? "")); ?></textarea>

          <label for="due_date">Due date</label>
          <input type="date" id="due_date" name="due_date"
                 value="<?php echo htmlspecialchars($_POST["due_date"] ?? ($task["due_date"] ?? "")); ?>">

          <div class="row">
            <div>
              <label for="priority">Priority</label>
              <select id="priority" name="priority">
                <?php foreach (["low" => "Low", "moderate" => "Moderate", "extreme" => "Extreme"] as $value => $label): ?>
                  <option value="<?php echo $value; ?>" <?php echo ($_POST["priority"] ?? $task["priority"]) === $value ? "selected" : ""; ?>>
                    <?php echo $label; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="status">Status</label>
              <select id="status" name="status">
                <?php foreach (["not_started" => "Not Started", "in_progress" => "In Progress", "completed" => "Completed"] as $value => $label): ?>
                  <option value="<?php echo $value; ?>" <?php echo ($_POST["status"] ?? $task["status"]) === $value ? "selected" : ""; ?>>
                    <?php echo $label; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <button class="btn-primary" type="submit">Update task</button>
          <a class="back-link" href="dashboard.php">Cancel</a>
        </form>
      </section>
    </main>

  </div>
</body>
</html>