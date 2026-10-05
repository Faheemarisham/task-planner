<?php
require "includes/db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    if ($name === "" || $email === "" || $password === "") {
        $message = "<p class='error'>All fields are required.</p>";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "<p class='error'>Enter a valid email.</p>";
    } elseif (strlen($password) < 6) {
        $message = "<p class='error'>Password must be at least 6 characters.</p>";
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->fetch()) {
            $message = "<p class='error'>This email is already registered.</p>";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");
            $insert->execute([$name, $email, $hash]);
            $message = "<p class='success'>Account created! You can log in now.</p>";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Register</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <div class="box">
    <h2>Register</h2>
    <?php echo $message; ?>
    <form method="POST">
      <input type="text" name="name" placeholder="Your name">
      <input type="email" name="email" placeholder="Email">
      <input type="password" name="password" placeholder="Password (min 6)">
      <button type="submit">Create account</button>
    </form>
  </div>
</body>
</html>