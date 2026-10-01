# Cybersecurity Awareness Management System (CAMS)

Web app for cybersecurity awareness **courses**, **lessons**, **practice / quizzes / exams**, progress tracking, tips, certificates, and admin reporting.

Built with **HTML, CSS, JavaScript, PHP 8+, and MySQL/MariaDB** (XAMPP-friendly).

## Requirements

See [REQUIREMENTS.md](REQUIREMENTS.md) for functional and gap requirements.  
See [USER_GUIDE.md](USER_GUIDE.md) for walkthrough and screenshot checklist.

## Clone from GitHub

```powershell
git clone https://github.com/tanny0002/CAMS.git
cd CAMS
```

Then follow **Quick start** below (MariaDB/MySQL + PHP). Never commit real secrets — use `.env.example` as a template only. Copy to `.env` and put the Gmail app password in `user/.env` (or `MAIL_PASSWORD` in `.env`).

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

### Live auth (email OTP + OAuth)

1. Ensure MariaDB/Postgres is running, then migrate auth tables:

```powershell
php -c .\.php.ini database/migrate_auth.php
php -c .\.php.ini database/migrate_access.php
```

2. Mail uses Gmail SMTP. Set `MAIL_USERNAME` / `MAIL_FROM` and `MAIL_PASSWORD` (Gmail App Password) in `.env` or `user/.env`.
3. **Default admin** (forced password change on first login):

| Email | Temporary password |
|-------|--------------------|
| `chitaurotanaka00@gmail.com` (or `BOOTSTRAP_ADMIN_EMAIL`) | `Admin@CAMS1` (or `BOOTSTRAP_ADMIN_PASSWORD`) |

4. Optional: set Google/GitHub OAuth client IDs (see USER_GUIDE). Redirect URI: `{APP_URL}/auth/oauth-callback.php`.

There are **no shared demo learner accounts**. Lecturers/students join via invitation or approved self-signup.

## Features

- Register with **email OTP** verification (HTML templates)  
- **Google** and **GitHub** sign-in / sign-up  
- Forgot / reset password via **real email**  
- Roles: user, lecturer, admin (first user or bootstrap email → admin)  
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
