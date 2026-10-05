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

$stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ? ORDER BY due_date IS NULL, due_date ASC, id DESC");
$stmt->execute([$uid]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tasks = array_map(function ($t) {
    if (!in_array($t["status"], ["not_started", "in_progress", "completed"])) {
        $t["status"] = "not_started";
    }
    if (!in_array($t["priority"], ["low", "moderate", "extreme"])) {
        $t["priority"] = "moderate";
    }
    return $t;
}, $tasks);

$todo = [];
$completed = [];
$counts = ["not_started" => 0, "in_progress" => 0, "completed" => 0];

foreach ($tasks as $t) {
    if (!isset($counts[$t["status"]])) {
        $t["status"] = "not_started";
    }
    $counts[$t["status"]]++;
    if ($t["status"] === "completed") {
        $completed[] = $t;
    } else {
        $todo[] = $t;
    }
}

$total = count($tasks);

function pct($n, $total) {
    return $total > 0 ? round($n / $total * 100) : 0;
}

$labels = [
    "not_started" => "Not Started",
    "in_progress" => "In Progress",
    "completed"   => "Completed"
];

function render_task($t, $labels) { ?>
    <div class="task-card">
      <div class="task-head">
        <span class="dot <?php echo $t["status"]; ?>"></span>
        <h4><?php echo htmlspecialchars($t["title"]); ?></h4>
      </div>

      <?php if ($t["description"]): ?>
        <p class="desc"><?php echo htmlspecialchars($t["description"]); ?></p>
      <?php endif; ?>

      <div class="meta">
        <span>Priority: <b class="pri <?php echo $t["priority"]; ?>"><?php echo ucfirst($t["priority"]); ?></b></span>
        <span>Status: <b class="st <?php echo $t["status"]; ?>"><?php echo $labels[$t["status"]]; ?></b></span>
        <span>Due: <?php echo $t["due_date"] ? htmlspecialchars($t["due_date"]) : "Not set"; ?></span>
      </div>

      <div class="actions">
        <a class="btn" href="edit_task.php?id=<?php echo $t["id"]; ?>">Edit</a>

        <form method="POST" action="toggle_status.php">
          <input type="hidden" name="id" value="<?php echo $t["id"]; ?>">
          <button class="btn" type="submit">
            <?php echo $t["status"] === "completed" ? "Undo" : "Mark completed"; ?>
          </button>
        </form>

        <form method="POST" action="delete_task.php" onsubmit="return confirm('Delete this task?');">
          <input type="hidden" name="id" value="<?php echo $t["id"]; ?>">
          <button class="btn danger" type="submit">Delete</button>
        </form>
      </div>
    </div>
<?php } ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard</title>
  <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
  <div class="layout">

    <aside class="sidebar">
      <div class="avatar"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($user["name"], 0, 1))); ?></div>
      <div class="uname"><?php echo htmlspecialchars($user["name"]); ?></div>
      <div class="uemail"><?php echo htmlspecialchars($user["email"]); ?></div>

      <nav class="nav">
        <a class="active" href="dashboard.php">Dashboard</a>
        <a href="add_task.php">+ Add Task</a>
        <a href="logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <h1>Welcome back, <?php echo htmlspecialchars($user["name"]); ?> 👋</h1>

      <div class="grid">

        <section class="card">
          <div class="card-head">
            <h3>To-Do</h3>
            <a href="add_task.php">+ Add task</a>
          </div>

          <?php if (count($todo) === 0): ?>
            <p class="empty">Nothing to do. Click "Add task" to create one.</p>
          <?php endif; ?>

          <?php foreach ($todo as $t) { render_task($t, $labels); } ?>
        </section>

        <div>
          <section class="card">
            <div class="card-head"><h3>Task Status</h3></div>
            <div class="circles">
              <?php foreach (["completed", "in_progress", "not_started"] as $key): ?>
                <?php $p = pct($counts[$key], $total); ?>
                <div>
                  <div class="circle <?php echo $key; ?>" style="--p:<?php echo (int)$p; ?>">
                    <span><?php echo (int)$p; ?>%</span>
                  </div>
                  <small class="legend <?php echo $key; ?>"><?php echo $labels[$key]; ?></small>
                </div>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="card">
            <div class="card-head"><h3>Completed Task</h3></div>

            <?php if (count($completed) === 0): ?>
              <p class="empty">No completed tasks yet.</p>
            <?php endif; ?>

            <?php foreach ($completed as $t) { render_task($t, $labels); } ?>
          </section>
        </div>

      </div>
    </main>

  </div>
</body>
</html>