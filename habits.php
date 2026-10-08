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

function week_start($date) {
    $d = new DateTime($date);
    $d->modify("monday this week");
    return $d->format("Y-m-d");
}

$u = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
$u->execute([$uid]);
$user = $u->fetch(PDO::FETCH_ASSOC);

$input = $_GET["week"] ?? date("Y-m-d");
if (!valid_date($input)) {
    $input = date("Y-m-d");
}
$start = week_start($input);

// Actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    $postWeek = $_POST["week"] ?? "";
    if (!valid_date($postWeek)) {
        $postWeek = date("Y-m-d");
    }
    $start = week_start($postWeek);

    if ($action === "add") {
        $name = mb_substr(trim($_POST["name"] ?? ""), 0, 60);
        if ($name !== "") {
            $ins = $pdo->prepare("INSERT IGNORE INTO habits (user_id, name) VALUES (?, ?)");
            $ins->execute([$uid, $name]);
        }
    } elseif ($action === "delete") {
        $del = $pdo->prepare("DELETE FROM habits WHERE id = ? AND user_id = ?");
        $del->execute([(int)($_POST["id"] ?? 0), $uid]);
    } elseif ($action === "defaults") {
        $names = ["Wake up early", "Exercise", "Drink water", "Read", "Study", "No screen time", "Sleep on time"];
        $ins = $pdo->prepare("INSERT IGNORE INTO habits (user_id, name) VALUES (?, ?)");
        foreach ($names as $name) {
            $ins->execute([$uid, $name]);
        }
    } elseif ($action === "save") {
        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $d = new DateTime($start);
            $d->modify("+$i day");
            $weekDays[] = $d->format("Y-m-d");
        }

        $h = $pdo->prepare("SELECT id FROM habits WHERE user_id = ?");
        $h->execute([$uid]);
        $ids = $h->fetchAll(PDO::FETCH_COLUMN);

        $pdo->beginTransaction();
        $clear = $pdo->prepare("DELETE FROM habit_logs WHERE habit_id = ? AND log_date BETWEEN ? AND ?");
        $add = $pdo->prepare("INSERT INTO habit_logs (habit_id, log_date) VALUES (?, ?)");
        foreach ($ids as $hid) {
            $clear->execute([$hid, $weekDays[0], $weekDays[6]]);
            foreach ($weekDays as $day) {
                if (isset($_POST["done"][$hid][$day])) {
                    $add->execute([$hid, $day]);
                }
            }
        }
        $pdo->commit();
    }

    header("Location: habits.php?week=" . $start . ($action === "save" ? "&saved=1" : ""));
    exit;
}

// Load
$days = [];
for ($i = 0; $i < 7; $i++) {
    $d = new DateTime($start);
    $d->modify("+$i day");
    $days[] = $d->format("Y-m-d");
}

$prev = (new DateTime($start))->modify("-7 day")->format("Y-m-d");
$next = (new DateTime($start))->modify("+7 day")->format("Y-m-d");
$today = date("Y-m-d");
$letters = ["M", "T", "W", "T", "F", "S", "S"];

$h = $pdo->prepare("SELECT id, name FROM habits WHERE user_id = ? ORDER BY id");
$h->execute([$uid]);
$habits = $h->fetchAll(PDO::FETCH_ASSOC);

$logs = [];
$l = $pdo->prepare("SELECT l.habit_id, l.log_date FROM habit_logs l
                    JOIN habits h ON h.id = l.habit_id
                    WHERE h.user_id = ? AND l.log_date BETWEEN ? AND ?");
$l->execute([$uid, $days[0], $days[6]]);
foreach ($l->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $logs[$row["habit_id"]][$row["log_date"]] = true;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Habit Tracker</title>
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
        <a href="planner.php">Day Planner</a>
        <a class="active" href="habits.php">Habit Tracker</a>
        <a href="add_task.php">+ Add Task</a>
        <a href="logout.php">Logout</a>
      </nav>
    </aside>

    <main class="main">
      <h1>Habit Tracker</h1>

      <div class="week-nav">
        <a href="habits.php?week=<?php echo $prev; ?>">&larr; Previous week</a>
        <b><?php echo date("M j", strtotime($days[0])); ?> - <?php echo date("M j, Y", strtotime($days[6])); ?></b>
        <a href="habits.php?week=<?php echo $next; ?>">Next week &rarr;</a>
        <a href="habits.php">This week</a>
      </div>

      <?php if (isset($_GET["saved"])): ?>
        <div class="success">Habits saved.</div>
      <?php endif; ?>

      <section class="card">
        <?php if (count($habits) === 0): ?>
          <p class="empty">No habits yet. Add your own below, or start with the default list.</p>
          <form method="POST">
            <input type="hidden" name="action" value="defaults">
            <input type="hidden" name="week" value="<?php echo $start; ?>">
            <button class="btn-primary" type="submit">Add default habits</button>
          </form>
        <?php else: ?>
          <form method="POST">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="week" value="<?php echo $start; ?>">

            <div class="table-wrap">
              <table class="habit-table">
                <thead>
                  <tr>
                    <th>Habit</th>
                    <?php foreach ($days as $i => $day): ?>
                      <th class="<?php echo $day === $today ? 'today' : ''; ?>">
                        <?php echo $letters[$i]; ?>
                        <small><?php echo date("j", strtotime($day)); ?></small>
                      </th>
                    <?php endforeach; ?>
                    <th>Total</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($habits as $habit): ?>
                    <?php $count = isset($logs[$habit["id"]]) ? count($logs[$habit["id"]]) : 0; ?>
                    <tr>
                      <td><?php echo htmlspecialchars($habit["name"]); ?></td>
                      <?php foreach ($days as $day): ?>
                        <td class="<?php echo $day === $today ? 'today' : ''; ?>">
                          <input type="checkbox"
                                 name="done[<?php echo (int)$habit["id"]; ?>][<?php echo $day; ?>]"
                                 <?php echo !empty($logs[$habit["id"]][$day]) ? "checked" : ""; ?>>
                        </td>
                      <?php endforeach; ?>
                      <td><?php echo $count; ?>/7</td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <button class="btn-primary" type="submit">Save habits</button>
          </form>
        <?php endif; ?>
      </section>

      <section class="card form-card">
        <div class="card-head"><h3>Manage habits</h3></div>

        <form method="POST" class="inline-form">
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="week" value="<?php echo $start; ?>">
          <input type="text" name="name" maxlength="60" placeholder="New habit (example: Walk 30 minutes)">
          <button class="btn-primary" type="submit" style="margin-top:0;">Add</button>
        </form>

        <?php foreach ($habits as $habit): ?>
          <div class="habit-item">
            <span><?php echo htmlspecialchars($habit["name"]); ?></span>
            <form method="POST" onsubmit="return confirm('Delete this habit and its history?');">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="week" value="<?php echo $start; ?>">
              <input type="hidden" name="id" value="<?php echo (int)$habit["id"]; ?>">
              <button class="btn danger" type="submit">Delete</button>
            </form>
          </div>
        <?php endforeach; ?>
      </section>
    </main>

  </div>
</body>
</html>