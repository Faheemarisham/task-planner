<?php
session_start();
require "includes/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = (int)$_POST["id"];
    $stmt = $pdo->prepare("UPDATE tasks SET status = IF(status = 'completed', 'not_started', 'completed') WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION["user_id"]]);
}

header("Location: dashboard.php");
exit;