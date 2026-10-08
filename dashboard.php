<?php
session_start();
require "includes/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$uid = $_SESSION["user_id"];
$today = date("Y-m-d");

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
$upcoming = [];
$overdue = 0;

foreach ($tasks as $t) {
    $counts[$t["status"]]++;
    if ($t["status"] === "completed") {
        $completed[] = $t;
    } else {
        $todo[] = $t;
        if ($t["due_date"]) {
            $upcoming[] = $t;
            if ($t["due_date"] < $today) {
                $overdue++;
            }
        }
    }
}

usort($upcoming, function ($a, $b) {
    return strcmp($a["due_date"], $b["due_date"]);
});
$upcoming = array_slice($upcoming, 0, 5);

$total = count($tasks);

function pct($n, $total) {
    return $total > 0 ? round($n / $total * 100) : 0;
}

$labels = [
    "not_started" => "Not Started",
    "in_progress" => "In Progress",
    "completed"   => "Completed"
];

// Today's priorities from the Day Planner
$p = $pdo->prepare("SELECT content FROM plan_items WHERE user_id = ? AND plan_date = ? AND section = 'priority' AND content <> '' ORDER BY position");
$p->execute([$uid, $today]);
$priorities = $p->fetchAll(PDO::FETCH_COLUMN);

// Habits this week
$weekStart = (new DateTime($today))->modify("monday this week")->format("Y-m-d");
$weekEnd = (new DateTime($weekStart))->modify("+6 day")->format("Y-m-d");

$hc = $pdo->prepare("SELECT COUNT(*) FROM habits WHERE user_id = ?");
$hc->execute([$uid]);
$habitCount = (int)$hc->fetchColumn();

$hl = $pdo->prepare("SELECT COUNT(*) FROM habit_logs l JOIN habits h ON h.id = l.habit_id WHERE h.user_id = ? AND l.log_date BETWEEN ? AND ?");
$hl->execute([$uid, $weekStart, $weekEnd]);
$habitDone = (int)$hl->fetchColumn();

$habitTotal = $habitCount * 7;
$habitPct = pct($habitDone, $habitTotal);

function render_task($t, $labels) { ?>
    <div class="task-card p-<?php echo htmlspecialchars($t["priority"]); ?>">
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
        <a class="btn" href="edit_task.php?id=<?php echo (int)$t["id"]; ?>">Edit</a>

        <form method="POST" action="toggle_status.php">
          <input type="hidden" name="id" value="<?php echo (int)$t["id"]; ?>">
          <button class="btn" type="submit">
            <?php echo $t["status"] === "completed" ? "Undo" : "Mark completed"; ?>
          </button>
        </form>

        <form method="POST" action="delete_task.php" onsubmit="return confirm('Delete this task?');">
          <input type="hidden" name="id" value="<?php echo (int)$t["id"]; ?>">
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
        <a href="planner.php">Day Planner</a>
        <a href="habits.php">Habit Tracker</a>
        <a href="add_task.php">+ Add Task</a>
        <a href="logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <h1>Welcome back, <?php echo htmlspecialchars($user["name"]); ?> 👋</h1>
      <p class="subtitle"><?php echo date("l, j F Y"); ?></p>

      <div class="stats">
        <div class="stat s-total">
          <span class="stat-num"><?php echo $total; ?></span>
          <span class="stat-label">Total tasks</span>
        </div>
        <div class="stat s-completed">
          <span class="stat-num"><?php echo $counts["completed"]; ?></span>
          <span class="stat-label">Completed</span>
        </div>
        <div class="stat s-progress">
          <span class="stat-num"><?php echo $counts["in_progress"]; ?></span>
          <span class="stat-label">In Progress</span>
        </div>
        <div class="stat s-notstarted">
          <span class="stat-num"><?php echo $counts["not_started"]; ?></span>
          <span class="stat-label">Not Started</span>
        </div>
      </div>

      <div class="grid">

        <div class="col">
          <section class="card">
            <div class="card-head">
              <h3>📝 To-Do</h3>
              <a href="add_task.php">+ Add task</a>
            </div>

            <?php if (count($todo) === 0): ?>
              <p class="empty">Nothing to do. Click "Add task" to create one.</p>
            <?php endif; ?>

            <?php foreach ($todo as $t) { render_task($t, $labels); } ?>
          </section>

          <section class="card">
            <div class="card-head"><h3>✅ Completed Task</h3></div>

            <?php if (count($completed) === 0): ?>
              <p class="empty">No completed tasks yet.</p>
            <?php endif; ?>

            <?php foreach ($completed as $t) { render_task($t, $labels); } ?>
          </section>
        </div>

        <div class="col">
          <section class="card">
            <div class="card-head"><h3>📊 Task Status</h3></div>
            <div class="circles">
              <?php foreach (["completed", "in_progress", "not_started"] as $key): ?>
                <?php $pc = pct($counts[$key], $total); ?>
                <div>
                  <div class="circle <?php echo $key; ?>" style="--p:<?php echo (int)$pc; ?>">
                    <span><?php echo (int)$pc; ?>%</span>
                  </div>
                  <small class="legend <?php echo $key; ?>"><?php echo $labels[$key]; ?></small>
                </div>
              <?php endforeach; ?>
            </div>
          </section>

          <section class="card">
            <div class="card-head">
              <h3>⭐ Today's Priorities</h3>
              <a href="planner.php">Open planner</a>
            </div>
            <?php if (count($priorities) === 0): ?>
              <p class="empty">No priorities set for today. Add them in the Day Planner.</p>
            <?php else: ?>
              <ul class="prio-list">
                <?php foreach ($priorities as $i => $text): ?>
                  <li><span class="prio-num"><?php echo $i + 1; ?></span><?php echo htmlspecialchars($text); ?></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </section>

          <section class="card">
            <div class="card-head">
              <h3>🔥 Habits this week</h3>
              <a href="habits.php">Open tracker</a>
            </div>
            <?php if ($habitCount === 0): ?>
              <p class="empty">No habits yet. Add some in the Habit Tracker.</p>
            <?php else: ?>
              <div class="progress"><span style="width:<?php echo (int)$habitPct; ?>%"></span></div>
              <p class="small-text"><?php echo $habitDone; ?> of <?php echo $habitTotal; ?> check-ins done (<?php echo (int)$habitPct; ?>%)</p>
            <?php endif; ?>
          </section>

          <section class="card">
            <div class="card-head">
              <h3>⏰ Upcoming deadlines</h3>
              <?php if ($overdue > 0): ?>
                <span class="pill-late"><?php echo $overdue; ?> overdue</span>
              <?php endif; ?>
            </div>
            <?php if (count($upcoming) === 0): ?>
              <p class="empty">No deadlines. Set a due date when you add a task.</p>
            <?php endif; ?>
            <?php foreach ($upcoming as $t): ?>
              <div class="due-row">
                <span><?php echo htmlspecialchars($t["title"]); ?></span>
                <small><?php echo htmlspecialchars($t["due_date"]); ?><?php echo $t["due_date"] < $today ? ' <span class="pill-late">overdue</span>' : ''; ?></small>
              </div>
            <?php endforeach; ?>
          </section>
        </div>

      </div>
    </main>

  </div>
</body>
</html>