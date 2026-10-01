# Cybersecurity Awareness Management System (CAMS)
## Application Requirements Specification

**Project:** Cybersecurity Awareness Management System  
**Author context:** Tanaka Chitauro — Diploma in Cybersecurity  
**Stack:** HTML, CSS, JavaScript, PHP 8+, MySQL/MariaDB or Supabase Postgres

---

## 1. Purpose

Provide a web-based platform for cybersecurity education, online quizzes, progress tracking, security tips, and administrator reporting — so institutions can manage awareness programmes in one place instead of manual meetings and printed notices.

---

## 2. User Roles

| Role | Description |
|------|-------------|
| **User (Learner)** | Students or individual learners who study lessons, take quizzes, view progress |
| **Lecturer** | Invites students, approves course requests for assigned modules, views reports |
| **Admin** | Full management: users, invites, approvals, content, tips, reports |

---

## 3. Functional Requirements (Original)

### 3.1 Learners
- FR-01 Register an account  
- FR-02 Log in / log out  
- FR-03 View cybersecurity lessons (multi-topic curriculum)  
- FR-04 Take online quizzes / assessments  
- FR-05 View quiz results  
- FR-06 View personal learning progress  
- FR-07 Read security tips  

### 3.2 Administrators
- FR-08 Manage users  
- FR-09 Add / manage lessons  
- FR-10 Add / manage quiz questions  
- FR-11 View assessment results  
- FR-12 Generate awareness / progress reports  

---

## 4. Gap Requirements (Added)

### 4.1 Security & accounts
- GAP-01 Password reset / “Forgot password” (token-based, emailed via SMTP)  
- GAP-02 Role-based access control (User, Lecturer, Admin)  
- GAP-03 Session timeout and secure logout  
- GAP-04 Input validation; protection against XSS, CSRF, and SQL injection  
- GAP-05 Audit log of sensitive admin actions  
- GAP-22 Email OTP verification on registration  
- GAP-23 Google / GitHub social sign-in  
- GAP-24 Force password change for default admin  
- GAP-25 Complete-profile gate before app use  
- GAP-26 Rate limiting on login, OTP, and password-reset requests  

### 4.2 Learning & assessment
- GAP-06 Mark lessons as completed (explicit completion rules)  
- GAP-07 Quiz attempt limits, pass mark, and retake policy  
- GAP-08 Feedback after wrong answers  
- GAP-09 Completion certificate / badge for finished modules  
- GAP-10 Content organised by topic / module and difficulty  
- GAP-27 Large shuffled question banks per topic  

### 4.3 Access control & invitations
- GAP-28 Admin invites lecturers by email (24-hour link) with course grants  
- GAP-29 Lecturer broadcasts student invitations with course grants  
- GAP-30 Self-signup students: lecturer then admin course approval  
- GAP-31 Individual learners: admin approval and course assignment  
- GAP-32 Learners only see approved courses  

### 4.4 Admin & reporting
- GAP-11 Edit and delete (or deactivate) lessons and questions  
- GAP-12 Export reports (CSV)  
- GAP-13 Dashboard filters (user, date, topic, pass/fail)  
- GAP-14 Soft-delete / deactivate users  

### 4.5 Usability & delivery
- GAP-15 Mobile-responsive layout  
- GAP-16 Search within lessons  
- GAP-17 In-app reminders for incomplete training  
- GAP-18 Privacy / consent notice on registration  

### 4.6 Operations & evaluation
- GAP-19 Activity logging (logins, quiz submissions)  
- GAP-20 User-friendly error handling  
- GAP-21 Post-training feedback form (supports academic evaluation)  

---

## 5. Non-Functional Requirements

- NFR-01 Easy to use for non-technical learners  
- NFR-02 Secure handling of account data  
- NFR-03 Reliable under normal classroom / lab load  
- NFR-04 Acceptable response time for normal use  
- NFR-05 Maintainable PHP + SQL structure  
- NFR-06 Responsive on desktop and mobile  

---

## 6. Scope

### In scope
- Registration, OTP, OAuth, invitations, approvals  
- Lessons, quizzes, results, progress, certificates  
- Admin / lecturer management and reporting  
- Security tips and post-training feedback  
- Local PHP demo with hosted Supabase database (optional local MySQL)  

### Out of scope
- Live penetration testing  
- Network monitoring  
- Malware development  
- Advanced digital forensics  
- Public cloud hosting of the PHP front-end (demo remains local unless separately deployed)  

---

## 7. Technical Requirements

| Item | Choice |
|------|--------|
| Frontend | HTML5, CSS3, JavaScript |
| Backend | PHP 8+ |
| Database | MySQL 8 / MariaDB **or** Supabase Postgres |
| Local server | PHP built-in server / XAMPP |
| Mail | Gmail SMTP (App Password) |
| Editor | Visual Studio Code / Cursor |

---

## 8. Evaluation Criteria

- Functionality  
- Usability  
- Performance  
- Security  
- User satisfaction (post-use questionnaire / feedback form)  

---

## 9. Default admin (after migrate)

| Email | Temporary password | Notes |
|-------|--------------------|-------|
| chitaurotanaka00@gmail.com (or `BOOTSTRAP_ADMIN_EMAIL`) | Admin@CAMS1 (or `BOOTSTRAP_ADMIN_PASSWORD`) | Must change password on first login |

No shared `@cams.local` demo learner accounts are shipped.
