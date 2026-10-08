<?php
session_start();
require "includes/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$uid = $_SESSION["user_id"];

function valid_date($d) {
    $obj = DateTime::createFromFormat("Y-m-d", (string)$d);
    return $obj && $obj->format("Y-m-d") === $d;
}

$u = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$u->execute([$uid]);
$user = $u->fetch(PDO::FETCH_ASSOC);

$sections = [
    "priority"    => ["Top Priorities", 3],
    "goal"        => ["Today's Goals", 4],
    "dont_forget" => ["Don't Forget", 4],
    "grateful"    => ["Today I am grateful for", 3],
    "tomorrow"    => ["Tomorrow", 2],
];

$date = $_GET["date"] ?? date("Y-m-d");
if (!valid_date($date)) {
    $date = date("Y-m-d");
}

// Save
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $date = $_POST["date"] ?? "";
    if (!valid_date($date)) {
        $date = date("Y-m-d");
    }

    $save = $pdo->prepare("INSERT INTO plan_items (user_id, plan_date, section, position, content)
                           VALUES (?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE content = VALUES(content)");

    foreach ($sections as $key => $info) {
        for ($i = 1; $i <= $info[1]; $i++) {
            $text = mb_substr(trim($_POST[$key][$i] ?? ""), 0, 255);
            $save->execute([$uid, $date, $key, $i, $text]);
        }
    }

    $notes = trim($_POST["notes"] ?? "");
    $saveNotes = $pdo->prepare("INSERT INTO plan_notes (user_id, plan_date, notes)
                                VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE notes = VALUES(notes)");
    $saveNotes->execute([$uid, $date, $notes]);
        $saveSch = $pdo->prepare("INSERT INTO plan_schedule (user_id, plan_date, slot_hour, content)
                              VALUES (?, ?, ?, ?)
                              ON DUPLICATE KEY UPDATE content = VALUES(content)");
    for ($h = 5; $h <= 22; $h++) {
        $text = mb_substr(trim($_POST["schedule"][$h] ?? ""), 0, 255);
        $saveSch->execute([$uid, $date, $h, $text]);
    }

    header("Location: planner.php?date=" . $date . "&saved=1");
    exit;
}

// Load
$items = [];
$q = $pdo->prepare("SELECT section, position, content FROM plan_items WHERE user_id = ? AND plan_date = ?");
$q->execute([$uid, $date]);
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $items[$row["section"]][$row["position"]] = $row["content"];
}

$n = $pdo->prepare("SELECT notes FROM plan_notes WHERE user_id = ? AND plan_date = ?");
$n->execute([$uid, $date]);
$notes = $n->fetchColumn() ?: "";

$schedule = [];
$s = $pdo->prepare("SELECT slot_hour, content FROM plan_schedule WHERE user_id = ? AND plan_date = ?");
$s->execute([$uid, $date]);
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $schedule[(int)$row["slot_hour"]] = $row["content"];
}

function hour_label($h) {
    $suffix = $h >= 12 ? "PM" : "AM";
    $h12 = $h % 12;
    if ($h12 === 0) {
        $h12 = 12;
    }
    return $h12 . " " . $suffix;
}

$t = $pdo->prepare("SELECT id, title, status FROM tasks WHERE user_id = ? AND due_date = ? ORDER BY id");
$t->execute([$uid, $date]);
$todayTasks = $t->fetchAll(PDO::FETCH_ASSOC);

$labels = ["not_started" => "Not Started", "in_progress" => "In Progress", "completed" => "Completed"];
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Day Planner</title>
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
        <a class="active" href="planner.php">Day Planner</a>
        <a href="habits.php">Habit Tracker</a>
        <a href="add_task.php">+ Add Task</a>
        <a href="logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <h1>Day Planner</h1>

      <form class="date-bar" method="GET">
        <label for="date"><b>Date:</b></label>
        <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date); ?>"
               onchange="this.form.submit()">
        <span><?php echo date("l", strtotime($date)); ?></span>
      </form>

      <?php if (isset($_GET["saved"])): ?>
        <div class="success">Planner saved.</div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">

        <div class="planner-grid">

          <?php foreach ($sections as $key => $info): ?>
            <section class="card">
              <div class="card-head"><h3><?php echo htmlspecialchars($info[0]); ?></h3></div>
              <div class="lines">
                <?php for ($i = 1; $i <= $info[1]; $i++): ?>
                  <input type="text" maxlength="255"
                         name="<?php echo $key; ?>[<?php echo $i; ?>]"
                         placeholder="<?php echo $i; ?>."
                         value="<?php echo htmlspecialchars($items[$key][$i] ?? ""); ?>">
                <?php endfor; ?>
              </div>
            </section>
          <?php endforeach; ?>

          <section class="card">
            <div class="card-head">
              <h3>To-Do List (due this day)</h3>
              <a href="add_task.php">+ Add task</a>
            </div>
            <?php if (count($todayTasks) === 0): ?>
              <p class="empty">No tasks due on this date.</p>
            <?php endif; ?>
            <?php foreach ($todayTasks as $task): ?>
              <div class="mini-task">
                <span class="dot <?php echo htmlspecialchars($task["status"]); ?>"></span>
                <a href="edit_task.php?id=<?php echo (int)$task["id"]; ?>">
                  <?php echo htmlspecialchars($task["title"]); ?>
                </a>
                <small>(<?php echo $labels[$task["status"]] ?? "Not Started"; ?>)</small>
              </div>
            <?php endforeach; ?>
          </section>
                    <section class="card full">
            <div class="card-head"><h3>Schedule</h3></div>
            <div class="schedule-grid">
              <?php for ($h = 5; $h <= 22; $h++): ?>
                <div class="slot">
                  <span class="slot-time"><?php echo hour_label($h); ?></span>
                  <input type="text" maxlength="255"
                         name="schedule[<?php echo $h; ?>]"
                         value="<?php echo htmlspecialchars($schedule[$h] ?? ""); ?>">
                </div>
              <?php endfor; ?>
            </div>
          </section>

                    <section class="card full">
            <div class="card-head"><h3>Daily Reminders</h3></div>
            <ul class="reminders">
              <li>Small steps still count.</li>
              <li>Focus on what you can control.</li>
              <li>Practice today, results tomorrow.</li>
              <li>Be kind to yourself.</li>
            </ul>
            <?php
              $quotes = [
                  "Consistency beats perfection.",
                  "One finished task is better than ten planned ones.",
                  "Start small, keep going.",
                  "Your future self will thank you for today.",
                  "Progress, not perfection.",
                  "Do the hard thing first.",
                  "Learn a little every day."
              ];
              $quote = $quotes[((int)date("z", strtotime($date))) % count($quotes)];
            ?>
            <p class="quote">"<?php echo htmlspecialchars($quote); ?>"</p>
          </section>
          
          <section class="card full">
            <div class="card-head"><h3>Notes</h3></div>
            <textarea class="notes-area" name="notes" placeholder="Write your notes here..."><?php echo htmlspecialchars($notes); ?></textarea>
          </section>

        </div>

        <button class="btn-primary" type="submit">Save planner</button>
      </form>
    </main>

  </div>
</body>
</html>