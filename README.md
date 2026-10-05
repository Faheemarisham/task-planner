# Task Planner

A web app where users can register, log in, and manage their daily tasks with
priorities and statuses. Built as a personal project to practice full-stack web development.

UI inspired by "Task Manager webDesign" on Figma Community.

## Features
- User registration and login (passwords are hashed)
- Add, edit, and delete tasks
- Task priority (Low, Moderate, Extreme) and status (Not Started, In Progress, Completed)
- Dashboard with task status percentages
- Separate lists for To-Do and Completed tasks
- Each user sees only their own tasks

## Technologies
- HTML, CSS
- PHP (PDO, sessions)
- MySQL
- XAMPP (local server)

## Screenshots
![Dashboard](screenshots/dashboard.png)
![Add task](screenshots/add-task.png)
![Edit task](screenshots/edit-task.png)
![Login](screenshots/login.png)
![Register](screenshots/register.png)

## How to run
1. Install XAMPP and start **Apache** and **MySQL**.
2. Copy this project folder into `C:\xampp\htdocs\`.
3. Open `http://localhost/phpmyadmin`, click **Import**, and import `database.sql`.
4. Open `http://localhost/task-planner/register.php` in your browser.

## What I learned
- Connecting PHP to MySQL using PDO
- Secure password handling with `password_hash()`
- Preventing SQL injection with prepared statements
- Protecting pages with sessions
- Building a dashboard layout with CSS Flexbox and Grid
- Changing a database structure without losing data

## Author
YOUR NAME HERE - Faheema Risham
GitHub: https://github.com/Faheemarisham