# CAMS — User Guide (Demo)

Short guide for demonstrating the Cybersecurity Awareness Management System.

## Start the app (local)

```powershell
cd D:\Projects\Repo\Cybersecurity-Awareness-Management-System
$env:Path = [System.Environment]::GetEnvironmentVariable('Path','Machine') + ';' + [System.Environment]::GetEnvironmentVariable('Path','User')
# MariaDB must be running on 127.0.0.1:3306
php -c .\.php.ini -S 127.0.0.1:8080
```

Open: **http://127.0.0.1:8080/**

## Demo accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@cams.local | Admin@123 |
| Lecturer | lecturer@cams.local | Lecturer@123 |
| Student | student@cams.local | Student@123 |

## Learner walkthrough

1. **Register or log in** as the student account (consent checkbox on register).
2. **Dashboard** — see progress and incomplete-training reminders.
3. **Courses** — open a module syllabus; use **Start / Continue**.
4. **Lesson reader** — left sidebar of lessons, on-page outline, mark complete, previous/next.
5. **Quizzes & exams**
   - **Practice** — more attempts, lower pass mark; shuffled draw from a 1,000+ question bank.
   - **Module quiz** — needed toward module certificates.
   - **Exams** — fewer attempts, higher pass mark.
6. **Progress** — lesson and quiz status by module.
7. **Tips** — short security reminders.
8. **Feedback** — post-training evaluation form.
9. **Certificate** — appears after all lessons in a module are completed and a module/exam quiz is passed.

## Lecturer walkthrough

1. Log in as lecturer.
2. Open **Reports** — filter by user, module, pass/fail, date; export **CSV**.

## Admin walkthrough

1. Log in as admin.
2. **Users** — activate/deactivate, change roles.
3. **Modules** — create/edit courses (difficulty, order).
4. **Lessons** — HTML article content (`h2`/`h3` become the lesson outline).
5. **Quizzes** — adjust pass mark / attempts; add questions.
6. **Tips** — publish security tips.
7. **Reports** — same filters and CSV as lecturer.

## Suggested screenshots for submission

1. Landing page  
2. Login (split layout)  
3. Student dashboard with reminder  
4. Courses catalog  
5. Lesson reader (sidebar + TOC)  
6. Practice quiz (shuffled draw note)  
7. Quiz result with feedback  
8. Progress page  
9. Certificate view  
10. Admin modules / lessons  
11. Reports + CSV mention  

## Re-install (only if you wipe the database)

The installer is disabled after setup (`install.php.bak`). To reinstall:

1. Ensure MariaDB is running and `config/config.php` DB settings are correct.
2. Rename `install.php.bak` → `install.php`.
3. Open `/install.php` once (this **drops and recreates** `cams_db`).
4. Rename `install.php` back to `install.php.bak`.
5. Optionally run:  
   `php -c .\.php.ini database/expand_content.php`  
   `php -c .\.php.ini database/seed_question_banks.php`
