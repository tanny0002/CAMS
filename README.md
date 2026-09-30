# Cybersecurity Awareness Management System (CAMS)

Web app for cybersecurity awareness **courses**, **lessons**, **practice / quizzes / exams**, progress tracking, tips, certificates, and admin reporting.

Built with **HTML, CSS, JavaScript, PHP 8+, and MySQL/MariaDB** (XAMPP-friendly).

## Requirements

See [REQUIREMENTS.md](REQUIREMENTS.md) for functional and gap requirements.  
See [USER_GUIDE.md](USER_GUIDE.md) for demo walkthrough and screenshot checklist.

## Clone from GitHub

```powershell
git clone https://github.com/tanny0002/CAMS.git
cd CAMS
```

Then follow **Quick start** below (MariaDB/MySQL + PHP). Never commit real secrets — use `user/.env.example` as a template only.

## Quick start (PHP built-in server)

1. Start **MariaDB/MySQL** (user `root`, empty password by default — see `config/config.php`).
2. From the project folder:

```powershell
$env:Path = [System.Environment]::GetEnvironmentVariable('Path','Machine') + ';' + [System.Environment]::GetEnvironmentVariable('Path','User')
php -c .\.php.ini -S 127.0.0.1:8080
```

3. Open **http://127.0.0.1:8080/**

### First-time database install

If `cams_db` is empty or missing:

1. Rename `install.php.bak` → `install.php` (installer is disabled after setup).
2. Open `http://127.0.0.1:8080/install.php` once.
3. Rename back to `install.php.bak`.
4. Seed large question banks (if not already applied by install):

```powershell
php -c .\.php.ini database/expand_content.php
php -c .\.php.ini database/expand_deep_lessons.php
php -c .\.php.ini database/seed_question_banks.php
```

### XAMPP alternative

Copy the project under `htdocs`, start Apache + MySQL, then use the same install steps with your XAMPP URL.

### Demo accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@cams.local | Admin@123 |
| Lecturer | lecturer@cams.local | Lecturer@123 |
| Student | student@cams.local | Student@123 |

## Features

- Register / login / logout with consent notice  
- Forgot / reset password (local demo token)  
- Roles: user, lecturer, admin  
- Session timeout + CSRF protection  
- **Modules (courses)** with difficulty and ordered lessons  
- GeeksforGeeks-style lesson reader (sidebar, TOC, prev/next)  
- **Deep lessons with real-life case studies** (ransomware waves, BEC, mobile-money fraud, etc.)  
- Top bar: **search**, **continue learning**, and in-progress course chip  
- **Practice drills**, **module quizzes**, and **exams**  
- **1,001+ question banks per topic** — each attempt draws a **shuffled** subset (options shuffled too)  
- Progress tracking and module certificates  
- Security tips and post-training feedback  
- Admin: users, modules, lessons, quizzes/questions, tips, reports  
- Reports with filters and **CSV export**  
- Activity + audit logging  

### Quiz draws (defaults)

| Type | Questions per attempt | Typical pass mark |
|------|----------------------|-------------------|
| Practice | 20 | 60% |
| Module quiz | 25 | 70% |
| Exam | 40 | 75–80% |

Configured in `config/config.php` (`QUIZ_DRAW_*`, `QUESTION_BANK_SIZE`).

## Project layout

```
config/           App and DB config
database/         schema.sql, content pack, question bank seeder
includes/         Helpers, header, footer
auth/             Login, register, password reset
user/             Learner pages (courses, quizzes, progress, …)
admin/            Admin & lecturer tools
assets/           CSS, JS, images
storage/sessions/ PHP session files
USER_GUIDE.md     Demo / submission guide
install.php.bak   Disabled one-time installer (rename to run)
```

## Smoke test

With the app running on port 8080:

```powershell
php -c .\.php.ini database/smoke_test.php
```

## Academic note

Diploma prototype for awareness training. It does **not** include penetration testing, malware development, or live network monitoring.
