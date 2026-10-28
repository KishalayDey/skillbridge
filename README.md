# KaajKoro - Part 2 (PHP, MySQL, Session, Cookie, JSON)

Web Technologies Final Project.
This is the same KaajKoro project as Part 1, but now everything is saved in a **MySQL database**
and the pages are made with **PHP**.

## How to run

1. Copy the folder `KaajKoro_PHP` inside `C:\xampp\htdocs\`
2. Start **Apache** and **MySQL** from the XAMPP control panel.
3. Open **phpMyAdmin** : http://localhost/phpmyadmin
4. Click **Import**, choose the file `database.sql` from this folder and press **Go**.
   (It creates the database `kaajkoro_db` with all the tables and the sample data.)
5. Open the project : http://localhost/KaajKoro_PHP/index.php

If your MySQL user or password is different, change it in **config.php**.

## Login accounts

| Email | Password | Who |
|---|---|---|
| rawan@kaajkoro.com | 123456 | Student (also posts jobs) |
| farhan@kaajkoro.com | 123456 | Client with jobs and hired students |
| nusrat@kaajkoro.com | 123456 | Designer with pending proposals |
| admin@kaajkoro.com | admin123 | Admin |

All the students in the sample data use the password `123456`.
The passwords are **not** saved as normal text, they are saved with `password_hash()`.

## The four things the course asked for

| Requirement | Where it is used |
|---|---|
| **MySQL database** | `config.php` makes the connection, every page reads and writes with prepared statements |
| **Session** | `login.php` creates `$_SESSION`, `check_login.php` and `check_admin.php` protect every page, `logout.php` destroys it |
| **Cookie** | `login.php` saves `remember_email` for 30 days with `setcookie()`, and fills the email box the next time |
| **JSON + AJAX** | `check_email.php` (checks the email while registering) and `get_messages.php` (refreshes the chat every 5 seconds) |

## Files

**Common files (included by every page)**

| File | What it does |
|---|---|
| `config.php` | The database connection |
| `functions.php` | The common functions : getUser, getJob, getAverageRating, showStars, showMoney, addNotification ... |
| `header.php` | The top of every page. The menu changes with the session (visitor / student / admin) |
| `footer.php` | The bottom of every page |
| `check_login.php` | Sends a visitor to login.php, and gives `$me` with my information |
| `check_admin.php` | Only the admin can pass |
| `database.sql` | The database : 15 tables and the sample data |
| `style.css` | The design (the same file as Part 1) |

**Pages (25)**

`index.php` `register.php` `login.php` `forgot.php` `logout.php`
`dashboard.php` `profile.php` `notifications.php` `report.php`
`jobs.php` `job-details.php` `post-job.php` `my-jobs.php` `proposals.php` `reviews.php`
`exchange.php` `post-exchange.php` `exchange-requests.php`
`students.php` `student-profile.php` `messages.php` `meetings.php`
`admin-dashboard.php` `admin-users.php` `admin-jobs.php` `admin-reports.php`

**AJAX files (they send JSON, not HTML)**

`check_email.php` · `get_messages.php`

**JavaScript files (client side validation only)**

`register.js` · `login.js` · `messages.js`

## The database

15 tables, made from our ER diagram :

`users` `admins` `skills` `user_skills` `jobs` `job_skills` `proposals`
`posts` `requests` `messages` `meetings` `reviews` `reports` `notifications` `activities`

Two of them are **junction tables** for the many-to-many relations :

- `user_skills (user_id, skill_id)` - one student has many skills, one skill belongs to many students
- `job_skills (job_id, skill_id)` - one job needs many skills

Most tables have a **FOREIGN KEY with ON DELETE CASCADE**. That is why deleting one user also
deletes his jobs, posts, proposals and messages with one single query.

## What changed from Part 1

| Part 1 (HTML, CSS, JS) | Part 2 (PHP, MySQL) |
|---|---|
| `dashboard.html` | `dashboard.php` |
| arrays in `data.js` | tables in `kaajkoro_db` |
| `for` loop + `innerHTML` | `while ($row = $result->fetch_assoc())` |
| `sessionStorage.setItem("loginId", id)` | `$_SESSION["user_id"] = $id;` |
| `checkLogin()` in main.js | `require_once "check_login.php";` |
| `users.push({...})` | `INSERT INTO users ... ` |
| `newId(users, "user_id")` | `AUTO_INCREMENT` |
| `getUrlValue("id")` | `$_GET["id"]` |
| the header written in every page | `include "header.php";` |
| password saved as normal text | `password_hash()` and `password_verify()` |
| validation only in JavaScript | validation in JavaScript **and** in PHP |

## Security (the teacher may ask)

- **Prepared statements** (`prepare`, `bind_param`) are used for every query that takes something
  from the user, so SQL injection is not possible.
- **`htmlspecialchars()`** is used before printing anything that a user wrote, so nobody can put
  HTML or JavaScript inside a job title.
- **`password_hash()`** saves the password in a form that cannot be read back, and
  **`password_verify()`** checks it at login.
- The status and sort words that go directly into the SQL (`open`, `working`, `done`) are first
  checked with `in_array()`, and every id is converted with `intval()`.
- Every action checks the owner. For example `DELETE FROM proposals WHERE proposal_id = ? AND user_id = ?`
  so one student can never delete the proposal of another student.
