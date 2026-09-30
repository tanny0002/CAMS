-- Cybersecurity Awareness Management System (CAMS)
-- Run in phpMyAdmin or: mysql -u root < schema.sql

CREATE DATABASE IF NOT EXISTS cams_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cams_db;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS certificates;
DROP TABLE IF EXISTS quiz_answers;
DROP TABLE IF EXISTS quiz_attempts;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS quizzes;
DROP TABLE IF EXISTS lesson_progress;
DROP TABLE IF EXISTS lessons;
DROP TABLE IF EXISTS security_tips;
DROP TABLE IF EXISTS modules;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'lecturer', 'admin') NOT NULL DEFAULT 'user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    consent_accepted TINYINT(1) NOT NULL DEFAULT 0,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE modules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(160) NOT NULL,
    slug VARCHAR(160) NOT NULL UNIQUE,
    description TEXT NULL,
    difficulty ENUM('beginner', 'intermediate', 'advanced') NOT NULL DEFAULT 'beginner',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE lessons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    summary VARCHAR(500) NULL,
    content MEDIUMTEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lesson_slug (module_id, slug),
    CONSTRAINT fk_lessons_module FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE lesson_progress (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    lesson_id INT UNSIGNED NOT NULL,
    status ENUM('in_progress', 'completed') NOT NULL DEFAULT 'in_progress',
    completed_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_lesson (user_id, lesson_id),
    CONSTRAINT fk_lp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_lp_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE quizzes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    quiz_type ENUM('practice', 'module', 'exam', 'bank') NOT NULL DEFAULT 'module',
    pass_mark TINYINT UNSIGNED NOT NULL DEFAULT 70,
    max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 3,
    questions_to_draw SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_quizzes_module FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(500) NOT NULL,
    option_b VARCHAR(500) NOT NULL,
    option_c VARCHAR(500) NOT NULL,
    option_d VARCHAR(500) NOT NULL,
    correct_option ENUM('A','B','C','D') NOT NULL,
    explanation TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_questions_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE quiz_attempts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    quiz_id INT UNSIGNED NOT NULL,
    score DECIMAL(5,2) NOT NULL DEFAULT 0,
    total_questions INT UNSIGNED NOT NULL DEFAULT 0,
    correct_answers INT UNSIGNED NOT NULL DEFAULT 0,
    passed TINYINT(1) NOT NULL DEFAULT 0,
    attempt_number INT UNSIGNED NOT NULL DEFAULT 1,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME NULL,
    CONSTRAINT fk_attempts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_attempts_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE quiz_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    selected_option ENUM('A','B','C','D') NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_answers_attempt FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    CONSTRAINT fk_answers_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE certificates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    certificate_code VARCHAR(40) NOT NULL UNIQUE,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_module_cert (user_id, module_id),
    CONSTRAINT fk_cert_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_cert_module FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE security_tips (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    category VARCHAR(80) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(80) NULL,
    entity_id INT UNSIGNED NULL,
    details TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_admin FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE feedback (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comments TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Seed users (password hashes generated for: Admin@123, Lecturer@123, Student@123)
-- Hashes will be updated by seed_passwords.php if needed. Placeholders below use known bcrypt for Admin@123 style.
INSERT INTO users (full_name, email, password_hash, role, is_active, consent_accepted) VALUES
('System Admin', 'admin@cams.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, 1),
('Jane Lecturer', 'lecturer@cams.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'lecturer', 1, 1),
('Sam Student', 'student@cams.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 1, 1);

INSERT INTO modules (title, slug, description, difficulty, sort_order) VALUES
('Phishing Awareness', 'phishing', 'Recognise deceptive messages and avoid phishing traps.', 'beginner', 1),
('Password Security', 'password-security', 'Create and protect strong, unique passwords.', 'beginner', 2),
('Malware Awareness', 'malware', 'Understand common malware and how to reduce risk.', 'intermediate', 3),
('Social Engineering', 'social-engineering', 'Spot manipulation tactics that target people.', 'intermediate', 4);

INSERT INTO lessons (module_id, title, slug, summary, content, sort_order) VALUES
(1, 'What is Phishing?', 'what-is-phishing',
 'Learn how attackers use fake messages to steal information — and how phishing fits into everyday cyber risk.',
 '<h2>What is Phishing?</h2><p>Phishing is a social engineering attack that uses deceptive emails, SMS (smishing), voice calls (vishing), or fake websites to trick people into revealing passwords, banking details, or clicking unsafe links.</p><p>Attackers often impersonate banks, ICT support, delivery services, or someone in authority. The goal is usually credentials, money, or malware installation.</p><h3>Why phishing works</h3><ul><li>Urgency and fear reduce careful checking</li><li>Familiar logos and language look legitimate at a glance</li><li>Busy people skim messages instead of inspecting them</li></ul><h2>Warning signs</h2><ul><li>Urgent language demanding immediate action</li><li>Unexpected requests for passwords, OTPs, or money</li><li>Misspelled domains or strange sender addresses</li><li>Generic greetings such as \"Dear Customer\"</li><li>Links that do not match the organisation’s real website</li></ul><div class=\"callout\"><strong>Remember:</strong> Legitimate ICT or banking staff will not pressure you to share passwords by email or SMS.</div><h2>What you should do</h2><p>Always verify unexpected requests through an official channel before responding. Report suspicious messages to ICT so others can be protected.</p>', 1),
(1, 'How to Spot a Phishing Email', 'spot-phishing-email',
 'Practical checks before you click — hover, inspect, and report.',
 '<h2>How to Spot a Phishing Email</h2><p>Before you click anything, slow down and inspect the message. Most phishing succeeds because victims act quickly.</p><h3>Check the sender</h3><ul><li>Expand the full email address — not only the display name</li><li>Look for lookalike domains (e.g. <code>rnicrosoft.com</code> instead of <code>microsoft.com</code>)</li><li>Be wary of free webmail accounts claiming to represent official departments</li></ul><h3>Inspect links safely</h3><p>Hover over links (do not click) to see the real destination on desktop. On mobile, long-press to preview when the client allows it.</p><h3>Attachments</h3><p>Unexpected attachments — especially <code>.exe</code>, <code>.js</code>, <code>.html</code>, or macro-enabled Office files — are high risk. Confirm with the sender using a known contact method.</p><h2>Safe response</h2><p>Report suspicious messages to ICT. Do not enter credentials on pages reached from email links — open the browser yourself and navigate to the official site.</p><table><thead><tr><th>Do</th><th>Don’t</th></tr></thead><tbody><tr><td>Hover / preview links</td><td>Click first “to check”</td></tr><tr><td>Report to ICT</td><td>Forward phishing widely to friends</td></tr><tr><td>Verify via official channel</td><td>Reply with passwords or OTPs</td></tr></tbody></table>', 2),
(2, 'Strong Passwords', 'strong-passwords',
 'Why length and uniqueness matter more than complex short passwords.',
 '<h2>Strong Passwords</h2><p>A strong password is long, unique, and hard to guess. Prefer passphrases (several unrelated words) over short complex strings you cannot remember.</p><h3>Good practices</h3><ul><li>Do not reuse passwords across accounts</li><li>Never share passwords — even with colleagues</li><li>Use a password manager when possible</li><li>Enable multi-factor authentication (MFA)</li><li>Change passwords after a suspected breach</li></ul><div class=\"callout\"><strong>Tip:</strong> A passphrase like four random words is usually stronger and easier to type than <code>P@ssw0rd!</code>.</div><h2>What attackers try</h2><p>Credential stuffing reuses leaked passwords from other sites. Dictionary attacks try common words and patterns. Unique long passwords reduce both risks dramatically.</p>', 1),
(2, 'Password Managers & MFA', 'password-managers-mfa',
 'Tools that reduce password risk: managers and multi-factor authentication.',
 '<h2>Password Managers and MFA</h2><p>Password managers store unique passwords securely and fill them for you. Multi-factor authentication adds a second check (app code, SMS, or hardware key) so a stolen password alone is not enough.</p><h3>Why use a password manager?</h3><ul><li>You only memorise one strong master password (or unlock with biometrics)</li><li>Every site can have a unique random password</li><li>Many managers warn about reused or breached passwords</li></ul><h3>MFA factors</h3><ol><li><strong>Something you know</strong> — password or PIN</li><li><strong>Something you have</strong> — phone app, SMS, or hardware key</li><li><strong>Something you are</strong> — fingerprint or face unlock</li></ol><div class=\"callout\"><strong>Prefer authenticator apps or hardware keys</strong> over SMS when available — SMS can be intercepted via SIM swap attacks.</div><h2>Institutional accounts</h2><p>Follow your organisation’s approved tools for MFA and password storage. Do not store work passwords in unsecured notes or shared documents.</p>', 2),
(3, 'Common Types of Malware', 'common-malware',
 'Viruses, ransomware, trojans, spyware — what they do and how they arrive.',
 '<h2>Common Types of Malware</h2><p>Malware is software designed to harm systems or steal data. Common types include viruses, worms, trojans, spyware, and ransomware.</p><h3>Quick guide</h3><table><thead><tr><th>Type</th><th>Typical behaviour</th></tr></thead><tbody><tr><td>Virus / worm</td><td>Spreads and disrupts systems</td></tr><tr><td>Trojan</td><td>Looks useful but hides harmful code</td></tr><tr><td>Spyware</td><td>Steals credentials or monitors activity</td></tr><tr><td>Ransomware</td><td>Encrypts files and demands payment</td></tr></tbody></table><h2>How malware arrives</h2><ul><li>Email attachments and malicious links</li><li>Pirated or unofficial software downloads</li><li>Infected USB drives</li><li>Drive-by downloads from compromised websites</li></ul><p>Reduce risk by avoiding suspicious downloads, keeping software updated, and scanning attachments before opening them.</p>', 1),
(3, 'Preventing Malware Infections', 'prevent-malware',
 'Everyday habits that protect devices from infection.',
 '<h2>Preventing Malware</h2><p>Most malware infections are preventable with consistent habits — not exotic technical skills.</p><h3>Daily habits</h3><ul><li>Keep OS and apps updated</li><li>Use reputable antivirus where available</li><li>Do not disable security tools without ICT approval</li><li>Be careful with USB drives from unknown sources</li><li>Download software only from official sources</li><li>Back up important files regularly</li></ul><div class=\"callout\"><strong>If you suspect infection:</strong> disconnect from the network if instructed, do not pay ransomware demands without advice, and contact ICT immediately.</div><h2>Ransomware readiness</h2><p>Offline or versioned backups are the strongest recovery option. Knowing where backups live — and testing restore — matters as much as antivirus.</p>', 2),
(4, 'Social Engineering Tactics', 'se-tactics',
 'How attackers impersonate trusted people to bypass your judgement.',
 '<h2>Social Engineering Tactics</h2><p>Attackers may pretend to be a bank employee, ICT technician, lecturer, or manager. They create urgency or fear so you skip normal checks.</p><h3>Common plays</h3><ul><li><strong>Pretexting</strong> — inventing a story that requires sensitive data</li><li><strong>Impersonation</strong> — pretending to be someone you trust</li><li><strong>Baiting</strong> — offering something attractive (free USB, prize)</li><li><strong>Authority pressure</strong> — “the director needs this now”</li></ul><p>Verify unusual requests using a known contact method — not the phone number or email in the suspicious message.</p><div class=\"callout\"><strong>Red flag:</strong> Anyone who discourages you from following policy or verifying with ICT is not acting in your interest.</div>', 1),
(4, 'Verifying Unusual Requests', 'verify-requests',
 'Stop, verify, then act — a simple routine for unusual requests.',
 '<h2>Verify Before You Act</h2><p>If someone asks for passwords, money transfers, or remote access, pause. Contact the person or department through an official channel. Legitimate staff will not pressure you to bypass procedures.</p><h3>A simple routine</h3><ol><li><strong>Stop</strong> — do not act on urgency alone</li><li><strong>Verify</strong> — call or message using a known number / official directory</li><li><strong>Act</strong> — only after confirmation, follow approved process</li></ol><h2>Examples</h2><table><thead><tr><th>Request</th><th>Safe response</th></tr></thead><tbody><tr><td>“Share your OTP now”</td><td>Never share OTPs; report the request</td></tr><tr><td>“Install this remote tool”</td><td>Confirm with ICT via known channel</td></tr><tr><td>“Wire money for the dean”</td><td>Call the dean’s office on the official number</td></tr></tbody></table><p>When unsure, ask ICT. Reporting a false alarm is always better than enabling an attack.</p>', 2);

INSERT INTO quizzes (module_id, title, description, quiz_type, pass_mark, max_attempts) VALUES
(1, 'Phishing Quiz', 'Test your phishing awareness.', 'module', 70, 3),
(2, 'Password Security Quiz', 'Test password best practices.', 'module', 70, 3),
(3, 'Malware Quiz', 'Test malware awareness.', 'module', 70, 3),
(4, 'Social Engineering Quiz', 'Test social engineering awareness.', 'module', 70, 3);

INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, sort_order) VALUES
(1, 'What is phishing?', 'A type of network cable', 'A deceptive message meant to trick users', 'A firewall setting', 'A password manager', 'B', 'Phishing tricks people into revealing information or taking unsafe actions.', 1),
(1, 'Which is a common phishing warning sign?', 'Perfect grammar only', 'Urgent demand for immediate action', 'Official branding alone proves safety', 'Messages from known contacts are always safe', 'B', 'Urgency is often used to rush victims into mistakes.', 2),
(1, 'What should you do with a suspicious email link?', 'Click quickly to check it', 'Forward it to friends', 'Hover/inspect and report without clicking', 'Reply with your password', 'C', 'Never click first — verify and report.', 3),
(1, 'A good response to a password request by email is to:', 'Send the password immediately', 'Ignore forever with no report', 'Verify via official channel / report to ICT', 'Post it on social media', 'C', 'Legitimate organisations do not ask for passwords by email.', 4),
(2, 'A strong password should be:', 'Short and easy to remember', 'Shared with your team', 'Long, unique, and hard to guess', 'The same on every site', 'C', 'Length and uniqueness matter most.', 1),
(2, 'Reusing one password across many accounts is:', 'Best practice', 'Risky because one breach affects many accounts', 'Required by MFA', 'Safer than a password manager', 'B', 'Reuse multiplies impact of a single leak.', 2),
(2, 'Multi-factor authentication (MFA) means:', 'Using one short PIN only', 'Adding a second verification factor', 'Disabling antivirus', 'Sharing passwords', 'B', 'MFA adds a second check beyond the password.', 3),
(2, 'Password managers help by:', 'Reusing one password everywhere', 'Storing unique passwords securely', 'Removing the need for MFA', 'Publishing passwords publicly', 'B', 'They store and fill unique strong passwords.', 4),
(3, 'Malware is:', 'Helpful system updates', 'Harmful software that can damage or steal data', 'A type of strong password', 'An antivirus brand', 'B', 'Malware is designed to harm or steal.', 1),
(3, 'Ransomware typically:', 'Speeds up your computer', 'Encrypts files and demands payment', 'Improves backups', 'Is always harmless', 'B', 'Ransomware locks data for ransom.', 2),
(3, 'A safe habit to reduce malware risk is:', 'Open all email attachments', 'Keep software updated', 'Disable security tools', 'Download from unknown sites', 'B', 'Updates patch known vulnerabilities.', 3),
(3, 'Unexpected email attachments should be:', 'Opened immediately', 'Treated carefully and verified', 'Forwarded widely', 'Saved to USB only', 'B', 'Attachments are a common malware delivery method.', 4),
(4, 'Social engineering mainly targets:', 'Only outdated routers', 'People and their behaviour', 'Only encrypted disks', 'Printer ink levels', 'B', 'It manipulates people rather than only systems.', 1),
(4, 'An attacker pretending to be ICT support is an example of:', 'Social engineering', 'Disk defragmentation', 'Two-factor hashing', 'SQL indexing', 'A', 'Impersonation is a classic social engineering tactic.', 2),
(4, 'If someone urgently asks for remote access, you should:', 'Agree immediately', 'Verify through a known official channel', 'Give your password first', 'Ignore institutional policy', 'B', 'Verify unusual requests before acting.', 3),
(4, 'Creating fear or urgency is used to:', 'Improve password strength', 'Skip normal verification checks', 'Update antivirus automatically', 'Encrypt backups', 'B', 'Pressure reduces careful thinking.', 4);

INSERT INTO security_tips (title, body, category) VALUES
('Think before you click', 'Hover over links and verify the destination before opening them.', 'Phishing'),
('Use unique passwords', 'Never reuse the same password across important accounts.', 'Passwords'),
('Enable MFA', 'Turn on multi-factor authentication wherever it is available.', 'Passwords'),
('Keep software updated', 'Install security updates for your operating system and apps.', 'Malware'),
('Verify unusual requests', 'Call or message the person using a known contact method.', 'Social Engineering'),
('Lock your device', 'Lock your computer when you step away, even briefly.', 'General');
