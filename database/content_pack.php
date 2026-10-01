<?php
/**
 * Expanded CAMS curriculum: modules, lessons, practice quizzes, module quizzes, exams.
 * Applied by database/expand_content.php and install.php (idempotent by slug/title).
 */
declare(strict_types=1);

function cams_ensure_quiz_type_column(PDO $pdo): void
{
    // Schema already includes quiz_type + questions_to_draw on fresh installs.
    // Keep a light check for older MySQL DBs only.
    if (defined('DB_DRIVER') && DB_DRIVER === 'pgsql') {
        return;
    }
    try {
        $col = $pdo->query("SHOW COLUMNS FROM quizzes LIKE 'quiz_type'")->fetch();
    } catch (Throwable $e) {
        return;
    }
    if (!$col) {
        $pdo->exec(
            "ALTER TABLE quizzes
             ADD COLUMN quiz_type ENUM('practice','module','exam','bank') NOT NULL DEFAULT 'module'
             AFTER description"
        );
    } elseif (!str_contains((string) $col['Type'], 'bank')) {
        $pdo->exec(
            "ALTER TABLE quizzes
             MODIFY COLUMN quiz_type ENUM('practice','module','exam','bank') NOT NULL DEFAULT 'module'"
        );
    }
    $draw = $pdo->query("SHOW COLUMNS FROM quizzes LIKE 'questions_to_draw'")->fetch();
    if (!$draw) {
        $pdo->exec(
            'ALTER TABLE quizzes ADD COLUMN questions_to_draw SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER max_attempts'
        );
    }
}

function cams_upsert_module(PDO $pdo, array $m): int
{
    $find = $pdo->prepare('SELECT id FROM modules WHERE slug = ?');
    $find->execute([$m['slug']]);
    $id = $find->fetchColumn();
    if ($id) {
        $pdo->prepare(
            'UPDATE modules SET title=?, description=?, difficulty=?, sort_order=?, is_active=1 WHERE id=?'
        )->execute([$m['title'], $m['description'], $m['difficulty'], $m['sort_order'], $id]);
        return (int) $id;
    }
    $pdo->prepare(
        'INSERT INTO modules (title, slug, description, difficulty, sort_order) VALUES (?,?,?,?,?)'
    )->execute([$m['title'], $m['slug'], $m['description'], $m['difficulty'], $m['sort_order']]);
    return (int) $pdo->lastInsertId();
}

function cams_upsert_lesson(PDO $pdo, int $moduleId, array $l): void
{
    $find = $pdo->prepare('SELECT id FROM lessons WHERE module_id = ? AND slug = ?');
    $find->execute([$moduleId, $l['slug']]);
    $id = $find->fetchColumn();
    if ($id) {
        $pdo->prepare(
            'UPDATE lessons SET title=?, summary=?, content=?, sort_order=?, is_active=1 WHERE id=?'
        )->execute([$l['title'], $l['summary'], $l['content'], $l['sort_order'], $id]);
        return;
    }
    $pdo->prepare(
        'INSERT INTO lessons (module_id, title, slug, summary, content, sort_order) VALUES (?,?,?,?,?,?)'
    )->execute([$moduleId, $l['title'], $l['slug'], $l['summary'], $l['content'], $l['sort_order']]);
}

function cams_upsert_quiz(PDO $pdo, int $moduleId, array $q): int
{
    $find = $pdo->prepare('SELECT id FROM quizzes WHERE module_id = ? AND title = ?');
    $find->execute([$moduleId, $q['title']]);
    $id = $find->fetchColumn();
    if ($id) {
        $pdo->prepare(
            'UPDATE quizzes SET description=?, quiz_type=?, pass_mark=?, max_attempts=?, is_active=1 WHERE id=?'
        )->execute([$q['description'], $q['quiz_type'], $q['pass_mark'], $q['max_attempts'], $id]);
        return (int) $id;
    }
    $pdo->prepare(
        'INSERT INTO quizzes (module_id, title, description, quiz_type, pass_mark, max_attempts) VALUES (?,?,?,?,?,?)'
    )->execute([
        $moduleId,
        $q['title'],
        $q['description'],
        $q['quiz_type'],
        $q['pass_mark'],
        $q['max_attempts'],
    ]);
    return (int) $pdo->lastInsertId();
}

function cams_sync_questions(PDO $pdo, int $quizId, array $questions): void
{
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE quiz_id = ? AND is_active = 1');
    $countStmt->execute([$quizId]);
    $existing = (int) $countStmt->fetchColumn();
    $wanted = count($questions);

    // Already populated with a full set (re-run safe).
    if ($existing >= $wanted && $wanted > 0) {
        return;
    }

    $pdo->prepare('UPDATE questions SET is_active = 0 WHERE quiz_id = ?')->execute([$quizId]);
    $ins = $pdo->prepare(
        'INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, sort_order, is_active)
         VALUES (?,?,?,?,?,?,?,?,?,1)'
    );
    foreach ($questions as $i => $qq) {
        $ins->execute([
            $quizId,
            $qq[0],
            $qq[1],
            $qq[2],
            $qq[3],
            $qq[4],
            $qq[5],
            $qq[6] ?? '',
            $i + 1,
        ]);
    }
}

function cams_upsert_tip(PDO $pdo, string $title, string $body, string $category): void
{
    $find = $pdo->prepare('SELECT id FROM security_tips WHERE title = ?');
    $find->execute([$title]);
    if ($find->fetchColumn()) {
        $pdo->prepare('UPDATE security_tips SET body=?, category=?, is_active=1 WHERE title=?')
            ->execute([$body, $category, $title]);
        return;
    }
    $pdo->prepare('INSERT INTO security_tips (title, body, category) VALUES (?,?,?)')
        ->execute([$title, $body, $category]);
}

/**
 * @return array{modules:int,lessons:int,quizzes:int,questions:int,tips:int}
 */
function cams_apply_content_pack(PDO $pdo): array
{
    cams_ensure_quiz_type_column($pdo);

    $modules = [
        [
            'slug' => 'phishing',
            'title' => 'Phishing Awareness',
            'description' => 'Recognise deceptive messages and protect credentials from phishing attacks.',
            'difficulty' => 'beginner',
            'sort_order' => 1,
            'lessons' => [
                [
                    'slug' => 'what-is-phishing',
                    'title' => 'What is Phishing?',
                    'summary' => 'How attackers use fake messages to steal information.',
                    'sort_order' => 1,
                    'content' => '<h2>What is Phishing?</h2><p>Phishing is a social engineering attack that uses deceptive emails, SMS (smishing), voice calls (vishing), or fake websites to trick people into revealing passwords, banking details, or clicking unsafe links.</p><h3>Why phishing works</h3><ul><li>Urgency and fear reduce careful checking</li><li>Familiar logos look legitimate at a glance</li><li>Busy people skim instead of inspecting</li></ul><h2>Warning signs</h2><ul><li>Urgent demands for immediate action</li><li>Requests for passwords, OTPs, or money</li><li>Misspelled domains or strange senders</li><li>Generic greetings such as “Dear Customer”</li></ul><div class="callout"><strong>Remember:</strong> Legitimate ICT or banking staff will not pressure you to share passwords by email or SMS.</div><h2>What you should do</h2><p>Verify unexpected requests through an official channel. Report suspicious messages to ICT.</p>',
                ],
                [
                    'slug' => 'spot-phishing-email',
                    'title' => 'How to Spot a Phishing Email',
                    'summary' => 'Practical checks before you click.',
                    'sort_order' => 2,
                    'content' => '<h2>How to Spot a Phishing Email</h2><p>Slow down before you click. Most phishing succeeds because victims act quickly.</p><h3>Check the sender</h3><ul><li>Expand the full email address</li><li>Watch for lookalike domains</li><li>Be wary of free webmail claiming to be official</li></ul><h3>Inspect links safely</h3><p>Hover over links (do not click) to see the real destination.</p><h2>Safe response</h2><p>Report to ICT. Navigate to official sites yourself — never via email links when credentials are requested.</p><table><thead><tr><th>Do</th><th>Don’t</th></tr></thead><tbody><tr><td>Hover / preview links</td><td>Click first “to check”</td></tr><tr><td>Report to ICT</td><td>Forward phishing widely</td></tr><tr><td>Verify officially</td><td>Reply with passwords or OTPs</td></tr></tbody></table>',
                ],
                [
                    'slug' => 'spear-phishing-whaling',
                    'title' => 'Spear Phishing & Whaling',
                    'summary' => 'Targeted attacks aimed at you — or senior leaders.',
                    'sort_order' => 3,
                    'content' => '<h2>Spear Phishing and Whaling</h2><p><strong>Spear phishing</strong> targets a specific person using personal details (role, projects, colleagues). <strong>Whaling</strong> targets executives or high-value accounts.</p><h3>Why it is dangerous</h3><ul><li>Messages look personal and credible</li><li>Attackers research social media and public pages</li><li>One compromised executive mailbox can unlock many systems</li></ul><h2>Defences</h2><ul><li>Verify unusual payment or data requests out-of-band</li><li>Limit what you post publicly about internal processes</li><li>Treat “urgent wire transfer” emails with extra scrutiny</li></ul><div class="callout"><strong>Practice:</strong> If a message uses your name and a real project title but asks you to bypass policy — still verify.</div>',
                ],
                [
                    'slug' => 'reporting-phishing',
                    'title' => 'Reporting Phishing Incidents',
                    'summary' => 'What to do after you spot (or click) a suspicious message.',
                    'sort_order' => 4,
                    'content' => '<h2>Reporting Phishing</h2><p>Fast reporting protects the whole organisation. Do not feel embarrassed — early reports stop wider damage.</p><h3>If you only spotted it</h3><ol><li>Do not click further links</li><li>Report to ICT with the full message (headers if possible)</li><li>Delete only after ICT guidance</li></ol><h3>If you clicked or entered data</h3><ol><li>Disconnect from the network if instructed</li><li>Change passwords from a known-safe device</li><li>Tell ICT immediately what you entered and when</li><li>Watch for unusual account activity</li></ol><div class="callout"><strong>Never punish yourself for reporting.</strong> Silence helps attackers.</div>',
                ],
            ],
            'quizzes' => [
                [
                    'title' => 'Phishing Practice Drill',
                    'description' => 'Low-stakes practice — unlimited-style attempts to build habits.',
                    'quiz_type' => 'practice',
                    'pass_mark' => 60,
                    'max_attempts' => 10,
                    'questions' => [
                        ['Phishing mainly tries to:', 'Speed up your Wi-Fi', 'Trick people into unsafe actions', 'Update antivirus automatically', 'Encrypt backups', 'B', 'It manipulates people into revealing data or clicking.'],
                        ['A lookalike domain is used to:', 'Improve SEO only', 'Appear as a trusted organisation', 'Increase battery life', 'Disable MFA', 'B', 'Attackers register similar spellings of real domains.'],
                        ['Smishing is phishing via:', 'Postal mail', 'SMS / text messages', 'Printer firmware', 'HDMI cables', 'B', 'Smishing = SMS phishing.'],
                        ['Best first step with a suspicious email link:', 'Click to test it', 'Hover/inspect and report', 'Reply with your password', 'Ignore forever with no report', 'B', 'Inspect without clicking; then report.'],
                        ['Whaling typically targets:', 'Only printers', 'Senior leaders / high-value accounts', 'Public guest Wi-Fi only', 'Screen savers', 'B', 'Whaling aims at executives and high-privilege users.'],
                        ['After entering a password on a fake site you should:', 'Hope nobody noticed', 'Report to ICT and change the password', 'Reuse the password elsewhere', 'Disable MFA', 'B', 'Report and reset credentials quickly.'],
                    ],
                ],
                [
                    'title' => 'Phishing Quiz',
                    'description' => 'Assess phishing awareness for this module.',
                    'quiz_type' => 'module',
                    'pass_mark' => 70,
                    'max_attempts' => 3,
                    'questions' => [
                        ['What is phishing?', 'A network cable type', 'A deceptive message meant to trick users', 'A firewall setting', 'A password manager', 'B', 'Phishing tricks people into revealing information.'],
                        ['A common warning sign is:', 'Perfect grammar only', 'Urgent demand for immediate action', 'Official branding alone proves safety', 'Known contacts are always safe', 'B', 'Urgency rushes victims into mistakes.'],
                        ['With a suspicious email link you should:', 'Click quickly', 'Forward to friends', 'Hover/inspect and report without clicking', 'Reply with your password', 'C', 'Never click first — verify and report.'],
                        ['A password request by email should be:', 'Answered immediately', 'Ignored with no report', 'Verified via official channel / reported', 'Posted on social media', 'C', 'Legitimate orgs do not ask for passwords by email.'],
                        ['Spear phishing is:', 'Random spam to millions', 'A targeted message using personal details', 'A type of strong password', 'Antivirus software', 'B', 'Spear phishing is personalised.'],
                        ['If you clicked a phishing link, first:', 'Tell nobody', 'Report to ICT and follow guidance', 'Share the link in a group chat', 'Disable updates', 'B', 'Rapid reporting limits damage.'],
                        ['OTPs should be:', 'Shared when email asks urgently', 'Never shared with anyone who asks', 'Posted in team chats', 'Written on your laptop lid', 'B', 'OTPs are one-time secrets — never share them.'],
                        ['Reporting phishing helps:', 'Only you', 'The wider organisation', 'Attackers improve', 'Nothing', 'B', 'Reports protect colleagues and systems.'],
                    ],
                ],
            ],
        ],
        [
            'slug' => 'password-security',
            'title' => 'Password Security',
            'description' => 'Build strong credentials and use MFA and password managers safely.',
            'difficulty' => 'beginner',
            'sort_order' => 2,
            'lessons' => [
                [
                    'slug' => 'strong-passwords',
                    'title' => 'Strong Passwords',
                    'summary' => 'Why length and uniqueness matter.',
                    'sort_order' => 1,
                    'content' => '<h2>Strong Passwords</h2><p>Prefer long unique passphrases over short complex strings you cannot remember.</p><ul><li>Do not reuse passwords</li><li>Never share passwords</li><li>Use a password manager</li><li>Enable MFA</li></ul><div class="callout"><strong>Tip:</strong> Four random words beat <code>P@ssw0rd!</code>.</div>',
                ],
                [
                    'slug' => 'password-managers-mfa',
                    'title' => 'Password Managers & MFA',
                    'summary' => 'Tools that reduce password risk.',
                    'sort_order' => 2,
                    'content' => '<h2>Password Managers and MFA</h2><p>Managers store unique passwords. MFA adds a second factor so a stolen password alone is not enough.</p><h3>MFA factors</h3><ol><li>Something you know</li><li>Something you have</li><li>Something you are</li></ol><div class="callout"><strong>Prefer authenticator apps or hardware keys</strong> over SMS when available.</div>',
                ],
                [
                    'slug' => 'credential-stuffing',
                    'title' => 'Credential Stuffing & Breaches',
                    'summary' => 'How leaked passwords from one site attack another.',
                    'sort_order' => 3,
                    'content' => '<h2>Credential Stuffing</h2><p>Attackers take username/password pairs leaked from one breach and try them on other sites. Reuse makes this devastating.</p><h3>Protect yourself</h3><ul><li>Unique password per important account</li><li>Change passwords after breach notifications</li><li>Enable MFA everywhere it is offered</li><li>Monitor for unusual logins</li></ul>',
                ],
                [
                    'slug' => 'sharing-and-reset-hygiene',
                    'title' => 'Sharing & Reset Hygiene',
                    'summary' => 'Safe habits for resets, shared devices, and handovers.',
                    'sort_order' => 4,
                    'content' => '<h2>Sharing and Reset Hygiene</h2><p>Never send passwords by chat or email. Use organisation-approved vaults for shared service accounts.</p><h3>On shared or public PCs</h3><ul><li>Do not save passwords in the browser</li><li>Log out fully when finished</li><li>Clear the session if required by policy</li></ul><h3>Password resets</h3><p>Use official reset flows only. Treat unexpected “reset your password” messages as phishing until verified.</p>',
                ],
            ],
            'quizzes' => [
                [
                    'title' => 'Password Practice Drill',
                    'description' => 'Practice password and MFA decisions.',
                    'quiz_type' => 'practice',
                    'pass_mark' => 60,
                    'max_attempts' => 10,
                    'questions' => [
                        ['Reusing one password across sites is:', 'Best practice', 'Risky after any single breach', 'Required by MFA', 'Safer than a manager', 'B', 'Reuse multiplies impact of leaks.'],
                        ['MFA means:', 'One short PIN only', 'Adding a second verification factor', 'Disabling antivirus', 'Sharing passwords', 'B', 'Multi-factor adds another check.'],
                        ['Password managers help by:', 'Reusing one password', 'Storing unique passwords securely', 'Removing MFA need', 'Publishing passwords', 'B', 'They store and fill unique secrets.'],
                        ['Credential stuffing uses:', 'Stolen password pairs on other sites', 'Only USB malware', 'Printer ink levels', 'Screen brightness', 'A', 'Leaked credentials are tried elsewhere.'],
                        ['On a shared PC you should:', 'Save all passwords in the browser', 'Avoid saving passwords; log out', 'Write passwords on sticky notes', 'Disable the lock screen', 'B', 'Shared devices need stricter hygiene.'],
                        ['Unexpected password-reset emails should be:', 'Clicked immediately', 'Verified via official channel', 'Ignored forever', 'Forwarded publicly', 'B', 'Confirm through known portals.'],
                    ],
                ],
                [
                    'title' => 'Password Security Quiz',
                    'description' => 'Module assessment on passwords and MFA.',
                    'quiz_type' => 'module',
                    'pass_mark' => 70,
                    'max_attempts' => 3,
                    'questions' => [
                        ['A strong password should be:', 'Short and easy', 'Shared with your team', 'Long, unique, and hard to guess', 'The same on every site', 'C', 'Length and uniqueness matter most.'],
                        ['Reusing passwords is:', 'Best practice', 'Risky because one breach hits many accounts', 'Required by MFA', 'Safer than a manager', 'B', 'Reuse multiplies impact.'],
                        ['MFA means:', 'One short PIN only', 'Adding a second verification factor', 'Disabling antivirus', 'Sharing passwords', 'B', 'Second factor beyond the password.'],
                        ['Password managers help by:', 'Reusing one password', 'Storing unique passwords securely', 'Removing MFA', 'Publishing passwords', 'B', 'Secure storage of unique passwords.'],
                        ['After a breach notification you should:', 'Ignore it', 'Change affected passwords and enable MFA', 'Reuse the leaked password', 'Disable alerts', 'B', 'Reset and harden accounts.'],
                        ['Sharing your password with a colleague is:', 'Fine if they are trusted', 'Against good practice / policy', 'Required for MFA', 'Safer than a vault', 'B', 'Use approved shared accounts instead.'],
                        ['SMS MFA is:', 'Always the strongest option', 'Better than nothing but weaker than app/hardware keys', 'Useless', 'The same as a password', 'B', 'SIM swap risk makes SMS weaker.'],
                        ['Passphrases are useful because they are:', 'Short', 'Long and memorable', 'Always public', 'Illegal', 'B', 'Length with memorability.'],
                    ],
                ],
            ],
        ],
        [
            'slug' => 'malware',
            'title' => 'Malware Awareness',
            'description' => 'Understand malware types and everyday prevention habits.',
            'difficulty' => 'intermediate',
            'sort_order' => 3,
            'lessons' => [
                [
                    'slug' => 'common-malware',
                    'title' => 'Common Types of Malware',
                    'summary' => 'Viruses, ransomware, trojans, spyware.',
                    'sort_order' => 1,
                    'content' => '<h2>Common Types of Malware</h2><p>Malware is software designed to harm systems or steal data.</p><table><thead><tr><th>Type</th><th>Behaviour</th></tr></thead><tbody><tr><td>Virus / worm</td><td>Spreads and disrupts</td></tr><tr><td>Trojan</td><td>Looks useful, hides harm</td></tr><tr><td>Spyware</td><td>Steals data / monitors</td></tr><tr><td>Ransomware</td><td>Encrypts files for ransom</td></tr></tbody></table>',
                ],
                [
                    'slug' => 'prevent-malware',
                    'title' => 'Preventing Malware Infections',
                    'summary' => 'Everyday habits that protect devices.',
                    'sort_order' => 2,
                    'content' => '<h2>Preventing Malware</h2><ul><li>Keep OS and apps updated</li><li>Use reputable antivirus</li><li>Do not disable security tools without ICT approval</li><li>Be careful with unknown USBs</li><li>Download from official sources</li><li>Back up important files</li></ul><div class="callout"><strong>Suspect infection?</strong> Contact ICT — do not pay ransomware without advice.</div>',
                ],
                [
                    'slug' => 'ransomware-response',
                    'title' => 'Ransomware Awareness & Response',
                    'summary' => 'What ransomware does and what to do if hit.',
                    'sort_order' => 3,
                    'content' => '<h2>Ransomware</h2><p>Ransomware encrypts files and demands payment. Paying does not guarantee recovery and may fund crime.</p><h3>If screens show a ransom note</h3><ol><li>Disconnect the device from the network if instructed</li><li>Do not delete files or pay immediately</li><li>Contact ICT / incident response</li><li>Preserve evidence (screenshots, messages)</li></ol><h2>Best defence</h2><p>Tested backups, least privilege, patching, and phishing resistance.</p>',
                ],
                [
                    'slug' => 'safe-downloads',
                    'title' => 'Safe Downloads & Attachments',
                    'summary' => 'Reduce risk from files and installers.',
                    'sort_order' => 4,
                    'content' => '<h2>Safe Downloads and Attachments</h2><ul><li>Prefer official vendor sites and app stores</li><li>Treat unexpected attachments as hostile until verified</li><li>Be extra careful with macros in Office documents</li><li>Scan files when tools are available</li></ul><div class="callout"><strong>Crack / pirated software</strong> is a common malware delivery method — avoid it entirely on work devices.</div>',
                ],
            ],
            'quizzes' => [
                [
                    'title' => 'Malware Practice Drill',
                    'description' => 'Practice recognising malware risks.',
                    'quiz_type' => 'practice',
                    'pass_mark' => 60,
                    'max_attempts' => 10,
                    'questions' => [
                        ['Malware is:', 'Helpful updates', 'Harmful software that can damage or steal data', 'A strong password', 'An antivirus brand', 'B', 'Designed to harm or steal.'],
                        ['Ransomware typically:', 'Speeds up PCs', 'Encrypts files and demands payment', 'Improves backups', 'Is always harmless', 'B', 'Locks data for ransom.'],
                        ['A safe habit is:', 'Open all attachments', 'Keep software updated', 'Disable security tools', 'Download from unknown sites', 'B', 'Updates patch vulnerabilities.'],
                        ['Unexpected attachments should be:', 'Opened immediately', 'Treated carefully and verified', 'Forwarded widely', 'Saved to USB only', 'B', 'Common malware delivery.'],
                        ['Pirated software often:', 'Is safer', 'Delivers malware', 'Removes the need for backups', 'Replaces MFA', 'B', 'Crack sites are high risk.'],
                        ['If you see a ransom note:', 'Pay instantly and stay quiet', 'Contact ICT and follow response steps', 'Wipe evidence immediately', 'Post passwords online', 'B', 'Report; do not freelance recovery.'],
                    ],
                ],
                [
                    'title' => 'Malware Quiz',
                    'description' => 'Module assessment on malware awareness.',
                    'quiz_type' => 'module',
                    'pass_mark' => 70,
                    'max_attempts' => 3,
                    'questions' => [
                        ['Malware is:', 'Helpful system updates', 'Harmful software that can damage or steal data', 'A type of strong password', 'An antivirus brand', 'B', 'Designed to harm or steal.'],
                        ['Ransomware typically:', 'Speeds up your computer', 'Encrypts files and demands payment', 'Improves backups', 'Is always harmless', 'B', 'Locks data for ransom.'],
                        ['A safe habit to reduce malware risk is:', 'Open all email attachments', 'Keep software updated', 'Disable security tools', 'Download from unknown sites', 'B', 'Updates patch known flaws.'],
                        ['Unexpected email attachments should be:', 'Opened immediately', 'Treated carefully and verified', 'Forwarded widely', 'Saved to USB only', 'B', 'Common delivery method.'],
                        ['A trojan is malware that:', 'Only prints documents', 'Disguises itself as useful software', 'Improves Wi-Fi', 'Is always detected instantly', 'B', 'Looks legitimate while hiding harm.'],
                        ['Backups help against ransomware by:', 'Paying the attacker', 'Enabling recovery without paying', 'Removing antivirus need', 'Sharing passwords', 'B', 'Restore from clean backups.'],
                        ['Macros in unexpected Office files are:', 'Always safe', 'A common infection path', 'Required for MFA', 'Harmless PDF viewers', 'B', 'Macro malware is frequent.'],
                        ['Disabling antivirus without approval is:', 'Recommended', 'Risky and usually against policy', 'Required for updates', 'A password tip', 'B', 'Keep protections enabled.'],
                    ],
                ],
            ],
        ],
        [
            'slug' => 'social-engineering',
            'title' => 'Social Engineering',
            'description' => 'Spot manipulation tactics that target people.',
            'difficulty' => 'intermediate',
            'sort_order' => 4,
            'lessons' => [
                [
                    'slug' => 'se-tactics',
                    'title' => 'Social Engineering Tactics',
                    'summary' => 'How attackers impersonate trusted people.',
                    'sort_order' => 1,
                    'content' => '<h2>Social Engineering Tactics</h2><p>Attackers may pretend to be ICT, a bank, a lecturer, or a manager.</p><ul><li>Pretexting</li><li>Impersonation</li><li>Baiting</li><li>Authority pressure</li></ul><div class="callout"><strong>Red flag:</strong> Anyone who discourages verification is not acting in your interest.</div>',
                ],
                [
                    'slug' => 'verify-requests',
                    'title' => 'Verifying Unusual Requests',
                    'summary' => 'Stop, verify, then act.',
                    'sort_order' => 2,
                    'content' => '<h2>Verify Before You Act</h2><ol><li><strong>Stop</strong></li><li><strong>Verify</strong> via known channel</li><li><strong>Act</strong> only after confirmation</li></ol><table><thead><tr><th>Request</th><th>Safe response</th></tr></thead><tbody><tr><td>Share OTP</td><td>Never share; report</td></tr><tr><td>Install remote tool</td><td>Confirm with ICT</td></tr><tr><td>Urgent wire transfer</td><td>Call official number</td></tr></tbody></table>',
                ],
                [
                    'slug' => 'tailgating-physical',
                    'title' => 'Physical & Tailgating Awareness',
                    'summary' => 'Social engineering is not only online.',
                    'sort_order' => 3,
                    'content' => '<h2>Physical Social Engineering</h2><p><strong>Tailgating</strong> is following authorised people into restricted areas without credentials.</p><h3>Habits</h3><ul><li>Do not hold doors for unknown people into secured areas</li><li>Challenge politely or report to security</li><li>Never leave unlocked workstations unattended</li><li>Shred sensitive printouts</li></ul>',
                ],
                [
                    'slug' => 'vishing-pretext-calls',
                    'title' => 'Vishing & Pretext Calls',
                    'summary' => 'Phone-based social engineering.',
                    'sort_order' => 4,
                    'content' => '<h2>Vishing (Voice Phishing)</h2><p>Attackers call pretending to be banks, ICT, or vendors. They create urgency and request codes, remote access, or payments.</p><h3>Defence</h3><ul><li>Hang up and call back using the official number from a trusted source</li><li>Never read OTPs aloud to callers</li><li>Do not grant remote access from cold calls</li></ul>',
                ],
            ],
            'quizzes' => [
                [
                    'title' => 'Social Engineering Practice',
                    'description' => 'Practice spotting manipulation.',
                    'quiz_type' => 'practice',
                    'pass_mark' => 60,
                    'max_attempts' => 10,
                    'questions' => [
                        ['Social engineering mainly targets:', 'Only routers', 'People and behaviour', 'Only encrypted disks', 'Printer ink', 'B', 'It manipulates people.'],
                        ['Pretending to be ICT support is:', 'Social engineering', 'Disk defrag', 'Two-factor hashing', 'SQL indexing', 'A', 'Classic impersonation.'],
                        ['Urgent remote-access requests should be:', 'Accepted immediately', 'Verified via official channel', 'Answered with your password', 'Ignored forever', 'B', 'Verify first.'],
                        ['Fear/urgency is used to:', 'Improve passwords', 'Skip verification checks', 'Update antivirus', 'Encrypt backups', 'B', 'Pressure reduces careful thinking.'],
                        ['Tailgating means:', 'Following into secure areas without access', 'A strong passphrase', 'A Wi-Fi standard', 'An MFA app', 'A', 'Physical access abuse.'],
                        ['On a cold call asking for an OTP:', 'Read it out', 'Refuse and verify independently', 'Text it to the caller', 'Post it online', 'B', 'Never share OTPs.'],
                    ],
                ],
                [
                    'title' => 'Social Engineering Quiz',
                    'description' => 'Module assessment on social engineering.',
                    'quiz_type' => 'module',
                    'pass_mark' => 70,
                    'max_attempts' => 3,
                    'questions' => [
                        ['Social engineering mainly targets:', 'Only outdated routers', 'People and their behaviour', 'Only encrypted disks', 'Printer ink levels', 'B', 'Manipulates people rather than only systems.'],
                        ['An attacker pretending to be ICT support is:', 'Social engineering', 'Disk defragmentation', 'Two-factor hashing', 'SQL indexing', 'A', 'Impersonation tactic.'],
                        ['If someone urgently asks for remote access, you should:', 'Agree immediately', 'Verify through a known official channel', 'Give your password first', 'Ignore institutional policy', 'B', 'Verify unusual requests.'],
                        ['Creating fear or urgency is used to:', 'Improve password strength', 'Skip normal verification checks', 'Update antivirus automatically', 'Encrypt backups', 'B', 'Pressure reduces careful thinking.'],
                        ['Vishing is social engineering via:', 'Voice / phone calls', 'Only USB sticks', 'Only billboards', 'Keyboard firmware only', 'A', 'Voice phishing.'],
                        ['Best response to a suspicious “CEO payment” email:', 'Pay instantly', 'Verify using a known contact method', 'Reply with bank details', 'Disable MFA', 'B', 'Out-of-band verification.'],
                        ['Leaving a logged-in PC unlocked in a public area is:', 'Fine', 'A risk for physical social engineering', 'Required by MFA', 'A backup method', 'B', 'Anyone can misuse the session.'],
                        ['Baiting often uses:', 'Attractive free items (e.g. USB “gifts”)', 'Only strong encryption', 'Official patch notes', 'Password managers', 'A', 'Curiosity/greed as lure.'],
                    ],
                ],
            ],
        ],
        [
            'slug' => 'safe-browsing-wifi',
            'title' => 'Safe Browsing & Wi-Fi',
            'description' => 'Browse safely, spot fake sites, and use public Wi-Fi with care.',
            'difficulty' => 'beginner',
            'sort_order' => 5,
            'lessons' => [
                [
                    'slug' => 'https-and-fake-sites',
                    'title' => 'HTTPS, Certificates & Fake Sites',
                    'summary' => 'Padlocks help — but they do not prove a site is the real organisation.',
                    'sort_order' => 1,
                    'content' => '<h2>HTTPS and Fake Sites</h2><p>HTTPS encrypts traffic between you and a site. A padlock means the connection is encrypted — not that the site is trustworthy or official.</p><h3>Check</h3><ul><li>Spelling of the domain</li><li>Unexpected subdomains</li><li>Whether you typed the address yourself</li></ul><div class="callout"><strong>Phishing sites can have HTTPS too.</strong> Encryption ≠ legitimacy.</div>',
                ],
                [
                    'slug' => 'public-wifi-risks',
                    'title' => 'Public Wi-Fi Risks',
                    'summary' => 'Cafés, airports, and open networks need extra caution.',
                    'sort_order' => 2,
                    'content' => '<h2>Public Wi-Fi</h2><p>Open networks can be monitored or spoofed (evil twin hotspots).</p><h3>Safer habits</h3><ul><li>Avoid logging into sensitive accounts on open Wi-Fi when possible</li><li>Use organisational VPN when required</li><li>Prefer mobile data for banking</li><li>Forget untrusted networks after use</li></ul>',
                ],
                [
                    'slug' => 'browser-hygiene',
                    'title' => 'Browser Hygiene & Extensions',
                    'summary' => 'Keep browsers patched; be careful what you install.',
                    'sort_order' => 3,
                    'content' => '<h2>Browser Hygiene</h2><ul><li>Keep the browser updated</li><li>Install extensions only from trusted sources</li><li>Review extension permissions</li><li>Clear sessions on shared computers</li></ul><p>Malicious extensions can read pages, steal cookies, or inject ads.</p>',
                ],
            ],
            'quizzes' => [
                [
                    'title' => 'Browsing & Wi-Fi Practice',
                    'description' => 'Practice safer browsing choices.',
                    'quiz_type' => 'practice',
                    'pass_mark' => 60,
                    'max_attempts' => 10,
                    'questions' => [
                        ['A padlock (HTTPS) proves:', 'The site is always the real bank', 'Traffic is encrypted to that site', 'Your PC has no malware', 'Wi-Fi is private', 'B', 'Encryption ≠ identity trust.'],
                        ['Evil twin Wi-Fi is:', 'A strong password', 'A fake hotspot mimicking a real network', 'An MFA app', 'A backup disk', 'B', 'Attackers lure devices to rogue APs.'],
                        ['On open café Wi-Fi, safest for banking is often:', 'No caution needed', 'Mobile data / trusted VPN / delay until safer network', 'Saving passwords in a shared browser', 'Disabling updates', 'B', 'Reduce exposure on open networks.'],
                        ['Browser extensions can be risky because they may:', 'Only change themes', 'Read page data or steal sessions', 'Improve MFA automatically', 'Replace antivirus', 'B', 'Permissions can be abused.'],
                        ['Best way to reach your bank site:', 'Click email link', 'Type the official address or use a known bookmark', 'Search random ads', 'Use a stranger’s saved login', 'B', 'Avoid emailed destinations.'],
                        ['After using a public PC browser you should:', 'Leave accounts open', 'Log out and clear the session', 'Save all passwords', 'Install random toolbars', 'B', 'End the session cleanly.'],
                    ],
                ],
                [
                    'title' => 'Safe Browsing Module Quiz',
                    'description' => 'Module quiz on browsing and Wi-Fi safety.',
                    'quiz_type' => 'module',
                    'pass_mark' => 70,
                    'max_attempts' => 3,
                    'questions' => [
                        ['HTTPS primarily provides:', 'Guaranteed legitimacy of any company', 'Encryption in transit', 'Unlimited downloads', 'Free antivirus', 'B', 'Encrypts the connection.'],
                        ['Phishing sites can:', 'Never use HTTPS', 'Still use HTTPS', 'Only work offline', 'Disable all browsers', 'B', 'HTTPS is common on fake sites too.'],
                        ['Public Wi-Fi risk includes:', 'Only slow speed', 'Eavesdropping and fake hotspots', 'Stronger MFA', 'Automatic backups', 'B', 'Traffic and portals can be hostile.'],
                        ['Organisational VPN helps by:', 'Making all sites safe to click', 'Encrypting traffic on untrusted networks', 'Removing password need', 'Blocking all email', 'B', 'Protects data in transit on risky networks.'],
                        ['Untrusted browser extensions should be:', 'Installed freely', 'Avoided or vetted carefully', 'Required for phishing', 'Used instead of updates', 'B', 'Limit extension trust.'],
                        ['Typing a URL yourself is safer than:', 'Using a known bookmark', 'Clicking an unexpected email link', 'Using the official app', 'Calling the bank', 'B', 'Email links may be malicious.'],
                        ['“Forget network” after public Wi-Fi helps:', 'Nothing', 'Reduce auto-reconnect to untrusted APs', 'Disable HTTPS', 'Share passwords', 'B', 'Stops silent reconnects.'],
                        ['Cookie theft via malicious extension may lead to:', 'Account takeover', 'Faster Wi-Fi only', 'Stronger passwords automatically', 'Free MFA hardware', 'A', 'Session cookies can hijack accounts.'],
                    ],
                ],
            ],
        ],
        [
            'slug' => 'email-messaging-safety',
            'title' => 'Email & Messaging Safety',
            'description' => 'Handle email, chat, and collaboration tools without leaking data.',
            'difficulty' => 'beginner',
            'sort_order' => 6,
            'lessons' => [
                [
                    'slug' => 'email-red-flags',
                    'title' => 'Email Red Flags Checklist',
                    'summary' => 'A repeatable checklist before you act on email.',
                    'sort_order' => 1,
                    'content' => '<h2>Email Red Flags</h2><ul><li>Mismatch between display name and address</li><li>Urgent money or credential requests</li><li>Unexpected invoices or “voice messages”</li><li>Links shortened to hide destinations</li><li>Pressure to skip normal approval</li></ul><p>When in doubt — verify out-of-band and report.</p>',
                ],
                [
                    'slug' => 'safe-collaboration',
                    'title' => 'Safe Collaboration & File Sharing',
                    'summary' => 'Share only what is needed, with the right people.',
                    'sort_order' => 2,
                    'content' => '<h2>Collaboration Safety</h2><ul><li>Use approved file-sharing platforms</li><li>Set least-privilege access (view vs edit)</li><li>Avoid putting secrets in public channels</li><li>Double-check recipient lists before send</li></ul><div class="callout"><strong>Auto-complete mistakes</strong> cause many data leaks — pause before Send.</div>',
                ],
                [
                    'slug' => 'business-email-compromise',
                    'title' => 'Business Email Compromise (BEC)',
                    'summary' => 'Fraud that hijacks or spoofs business email for payments.',
                    'sort_order' => 3,
                    'content' => '<h2>Business Email Compromise</h2><p>Attackers spoof or take over mailboxes to request payments, gift cards, or data. Messages often look like they come from a boss or supplier.</p><h3>Controls</h3><ul><li>Verify payment changes by phone using known numbers</li><li>Follow dual-control for large transfers</li><li>Watch for slight domain changes in supplier email</li></ul>',
                ],
            ],
            'quizzes' => [
                [
                    'title' => 'Email Safety Practice',
                    'description' => 'Practice spotting risky email and chat behaviour.',
                    'quiz_type' => 'practice',
                    'pass_mark' => 60,
                    'max_attempts' => 10,
                    'questions' => [
                        ['Display name alone proves the sender is:', 'Always real', 'Not proof — check the full address', 'A password', 'An MFA token', 'B', 'Display names are easy to fake.'],
                        ['BEC often aims to:', 'Improve spam filters', 'Trick staff into paying or sending data', 'Speed Wi-Fi', 'Update antivirus', 'B', 'Fraud via business email.'],
                        ['Before changing supplier bank details:', 'Update immediately from the email', 'Verify via known official channel', 'Post details in a public chat', 'Disable MFA', 'B', 'Out-of-band verification.'],
                        ['Auto-complete recipient errors can cause:', 'Stronger encryption', 'Accidental data leaks', 'Faster MFA', 'Free backups', 'B', 'Wrong person receives sensitive mail.'],
                        ['Secrets in public channels are:', 'Fine if the team is large', 'A data exposure risk', 'Required for phishing tests', 'A form of MFA', 'B', 'Limit sensitive discussion scope.'],
                        ['Unexpected “voicemail” email attachments are often:', 'Always safe', 'A malware lure', 'Official OS updates', 'Password managers', 'B', 'Common phishing/malware bait.'],
                    ],
                ],
                [
                    'title' => 'Email & Messaging Quiz',
                    'description' => 'Module quiz on email and messaging safety.',
                    'quiz_type' => 'module',
                    'pass_mark' => 70,
                    'max_attempts' => 3,
                    'questions' => [
                        ['Best check on a suspicious sender:', 'Trust the logo', 'Inspect the full email address / domain', 'Click all links', 'Reply with OTP', 'B', 'Verify identity signals.'],
                        ['Least privilege when sharing files means:', 'Everyone gets edit access', 'Only needed people get needed access', 'No passwords ever', 'Public links always', 'B', 'Minimise access.'],
                        ['A CEO gift-card request by email should be:', 'Fulfilled instantly', 'Verified independently', 'Ignored with no report', 'Posted online', 'B', 'Classic BEC pattern.'],
                        ['Approved collaboration tools are preferred because:', 'They are always free', 'They meet organisational security controls', 'They remove phishing forever', 'They disable HTTPS', 'B', 'Policy and controls matter.'],
                        ['Shortened links in unexpected mail are risky because:', 'They always improve safety', 'They can hide the real destination', 'They enforce MFA', 'They encrypt disks', 'B', 'Obscure destinations.'],
                        ['Dual control for payments helps stop:', 'Only malware', 'Single-person BEC fraud', 'All phishing', 'Wi-Fi evil twins', 'B', 'Requires a second check.'],
                        ['Sensitive data in CC to the wrong list is:', 'Harmless', 'A potential incident to report', 'Required training', 'An MFA factor', 'B', 'Report and contain.'],
                        ['Chat messages from “ICT” asking for your password are:', 'Normal', 'Suspicious — ICT should not ask this way', 'Required weekly', 'Part of HTTPS', 'B', 'Never share passwords in chat.'],
                    ],
                ],
            ],
        ],
        [
            'slug' => 'data-privacy',
            'title' => 'Data Privacy & Sharing',
            'description' => 'Protect personal and organisational data in everyday work.',
            'difficulty' => 'intermediate',
            'sort_order' => 7,
            'lessons' => [
                [
                    'slug' => 'personal-vs-organisational-data',
                    'title' => 'Personal vs Organisational Data',
                    'summary' => 'Know what you handle and why it matters.',
                    'sort_order' => 1,
                    'content' => '<h2>Types of Data</h2><p>You may handle personal data (names, IDs, health, finance) and organisational data (grades, HR files, research, credentials).</p><h3>Principles</h3><ul><li>Collect/use only what you need</li><li>Share only with authorised people</li><li>Store on approved systems</li><li>Report suspected breaches quickly</li></ul>',
                ],
                [
                    'slug' => 'clean-desk-screen',
                    'title' => 'Clean Desk & Screen Privacy',
                    'summary' => 'Simple physical habits that prevent exposure.',
                    'sort_order' => 2,
                    'content' => '<h2>Clean Desk and Screen Privacy</h2><ul><li>Lock your screen when you step away (Win+L / platform equivalent)</li><li>Do not leave sensitive printouts on printers</li><li>Position screens away from public view when possible</li><li>Shred confidential paper waste</li></ul>',
                ],
                [
                    'slug' => 'cloud-sharing-mistakes',
                    'title' => 'Cloud Sharing Mistakes',
                    'summary' => 'Public links and oversharing cause breaches.',
                    'sort_order' => 3,
                    'content' => '<h2>Cloud Sharing Mistakes</h2><ul><li>“Anyone with the link” for confidential files</li><li>Leaving ex-staff access active</li><li>Syncing work data to personal unmanaged accounts</li><li>Sharing whole folders when one file is enough</li></ul><div class="callout"><strong>Review sharing permissions</strong> periodically on important folders.</div>',
                ],
            ],
            'quizzes' => [
                [
                    'title' => 'Privacy Practice Drill',
                    'description' => 'Practice privacy decisions.',
                    'quiz_type' => 'practice',
                    'pass_mark' => 60,
                    'max_attempts' => 10,
                    'questions' => [
                        ['Locking your screen when away:', 'Is optional theatre', 'Reduces unauthorised access risk', 'Disables backups', 'Replaces MFA', 'B', 'Basic physical security.'],
                        ['“Anyone with the link” for payroll files is:', 'Fine', 'Usually too broad / high risk', 'Required by HTTPS', 'A form of encryption', 'B', 'Oversharing.'],
                        ['Personal unmanaged cloud for work data is:', 'Always best', 'Often against policy / risky', 'Stronger than MFA', 'A phishing defence', 'B', 'Use approved systems.'],
                        ['Suspected data exposure should be:', 'Hidden', 'Reported promptly', 'Posted publicly first', 'Ignored', 'B', 'Early reporting limits harm.'],
                        ['Need-to-know access means:', 'Everyone sees everything', 'Only people who need data get it', 'No passwords', 'Public Wi-Fi only', 'B', 'Least privilege for data.'],
                        ['Leaving student records on a shared printer is:', 'Safe', 'A privacy risk', 'Required for audits', 'An MFA step', 'B', 'Collect prints promptly; shred when needed.'],
                    ],
                ],
                [
                    'title' => 'Data Privacy Quiz',
                    'description' => 'Module quiz on privacy and sharing.',
                    'quiz_type' => 'module',
                    'pass_mark' => 70,
                    'max_attempts' => 3,
                    'questions' => [
                        ['Organisational data should be stored:', 'Anywhere convenient', 'On approved systems', 'Only on USB forever', 'In public posts', 'B', 'Follow approved storage.'],
                        ['Sharing a whole confidential folder when one file is needed:', 'Is least privilege', 'Is oversharing', 'Is MFA', 'Is encryption', 'B', 'Share the minimum.'],
                        ['Clean desk practices help prevent:', 'Only malware', 'Physical data exposure', 'All phishing', 'DNS failures', 'B', 'Shoulder surfing and abandoned documents.'],
                        ['Ex-staff retaining cloud access is:', 'Ideal', 'An access-control failure risk', 'Required by law always', 'A password tip', 'B', 'Revoke leavers promptly.'],
                        ['Health or ID numbers are typically:', 'Non-sensitive', 'Sensitive personal data', 'Public by default', 'Wi-Fi passwords', 'B', 'Handle with extra care.'],
                        ['Before emailing personal data externally:', 'Send freely', 'Confirm authorisation and correct recipients', 'CC public lists', 'Disable encryption always', 'B', 'Authorisation + recipient check.'],
                        ['Screen privacy filters / positioning help against:', 'Shoulder surfing', 'Ransomware encryption math', 'DNS spoofing only', 'Battery drain only', 'A', 'Visual exposure.'],
                        ['A privacy incident example is:', 'Strong passphrase use', 'Sending a grade sheet to the wrong class list', 'Installing updates', 'Using MFA', 'B', 'Unauthorised disclosure.'],
                    ],
                ],
            ],
        ],
        [
            'slug' => 'incident-reporting-hygiene',
            'title' => 'Incident Reporting & Digital Hygiene',
            'description' => 'Report issues fast and keep everyday digital habits healthy.',
            'difficulty' => 'beginner',
            'sort_order' => 8,
            'lessons' => [
                [
                    'slug' => 'when-and-how-to-report',
                    'title' => 'When and How to Report',
                    'summary' => 'What counts as an incident and whom to tell.',
                    'sort_order' => 1,
                    'content' => '<h2>When to Report</h2><ul><li>Suspected phishing or malware</li><li>Lost/stolen devices with work data</li><li>Unusual account activity</li><li>Accidental data sharing</li><li>Physical security issues in ICT areas</li></ul><h3>How</h3><p>Contact ICT / the designated incident channel quickly. Include what happened, when, which systems, and whether credentials were entered.</p>',
                ],
                [
                    'slug' => 'device-update-hygiene',
                    'title' => 'Updates, Backups & Device Care',
                    'summary' => 'Patching and backups are everyday security.',
                    'sort_order' => 2,
                    'content' => '<h2>Digital Hygiene</h2><ul><li>Install security updates promptly</li><li>Keep approved backups of important work</li><li>Use device lock + strong account protection</li><li>Separate personal risky browsing from work accounts when policy requires</li></ul>',
                ],
                [
                    'slug' => 'lost-device-response',
                    'title' => 'Lost or Stolen Device Response',
                    'summary' => 'Minutes matter when a device goes missing.',
                    'sort_order' => 3,
                    'content' => '<h2>Lost or Stolen Devices</h2><ol><li>Report to ICT / security immediately</li><li>Change passwords for accounts used on the device</li><li>Note last known location and time</li><li>Cooperate with remote wipe if offered</li></ol><div class="callout"><strong>Do not delay reporting</strong> hoping the device “turns up.”</div>',
                ],
            ],
            'quizzes' => [
                [
                    'title' => 'Incident Response Practice',
                    'description' => 'Practice reporting and hygiene decisions.',
                    'quiz_type' => 'practice',
                    'pass_mark' => 60,
                    'max_attempts' => 10,
                    'questions' => [
                        ['After a suspected phishing click you should:', 'Stay silent', 'Report to ICT promptly', 'Post details publicly', 'Disable all updates', 'B', 'Fast reporting helps containment.'],
                        ['A lost work laptop should be:', 'Ignored for a week', 'Reported immediately', 'Sold online', 'Used without PINs forever', 'B', 'Time-critical incident.'],
                        ['Security updates mainly:', 'Only change wallpaper', 'Patch known vulnerabilities', 'Remove the need for passwords', 'Disable HTTPS', 'B', 'Close known holes.'],
                        ['Unusual login alerts should be:', 'Deleted unread', 'Investigated / reported if not you', 'Shared with strangers', 'Ignored forever', 'B', 'Possible compromise.'],
                        ['Good digital hygiene includes:', 'Never updating', 'Backups + patching + screen lock', 'Sharing OTP in chat', 'Public payroll links', 'B', 'Everyday protective habits.'],
                        ['Remote wipe after theft helps:', 'Attackers', 'Reduce data exposure on the missing device', 'Replace phishing training', 'Weaken MFA', 'B', 'Removes reachable data.'],
                    ],
                ],
                [
                    'title' => 'Incident & Hygiene Quiz',
                    'description' => 'Module quiz on reporting and hygiene.',
                    'quiz_type' => 'module',
                    'pass_mark' => 70,
                    'max_attempts' => 3,
                    'questions' => [
                        ['An incident worth reporting includes:', 'Only confirmed nation-state attacks', 'Suspected phishing, malware, lost devices, data leaks', 'Only printer jams', 'Only Wi-Fi slowdowns', 'B', 'Report suspected as well as confirmed.'],
                        ['When reporting, include:', 'Nothing specific', 'What/when/systems/whether secrets were entered', 'Only your favourite colour', 'Other people’s passwords', 'B', 'Actionable detail helps responders.'],
                        ['Delaying a lost-device report:', 'Is best practice', 'Increases risk', 'Improves encryption', 'Enables MFA', 'B', 'Attackers may act quickly.'],
                        ['Device lock helps when:', 'Devices are misplaced or unattended', 'Only online', 'Only on servers', 'Never', 'A', 'Blocks casual access.'],
                        ['Approved backups support:', 'Recovery after ransomware or loss', 'Phishing only', 'Evil twin Wi-Fi', 'Display-name spoofing', 'A', 'Restore capability.'],
                        ['Unusual account activity might mean:', 'Everything is fine always', 'Possible compromise', 'Stronger Wi-Fi', 'A successful backup', 'B', 'Investigate and report.'],
                        ['Personal risky downloads on a work profile can:', 'Only help productivity', 'Introduce malware into work context', 'Replace antivirus', 'Enforce least privilege', 'B', 'Keep risky activity separate per policy.'],
                        ['The goal of fast incident reporting is:', 'Blame individuals', 'Contain and reduce harm', 'Disable all learning', 'Remove MFA', 'B', 'Protect people and systems.'],
                    ],
                ],
            ],
        ],
    ];

    // Final exams spanning multiple themes (attached to modules but comprehensive)
    $finalExams = [
        [
            'module_slug' => 'phishing',
            'title' => 'CAMS Midterm Exam — Awareness Foundations',
            'description' => 'Broader exam covering phishing, passwords, and social engineering basics. Fewer attempts — treat as a formal test.',
            'quiz_type' => 'exam',
            'pass_mark' => 75,
            'max_attempts' => 2,
            'questions' => [
                ['Phishing succeeds mainly because users:', 'Have perfect tools', 'Are rushed or deceived into unsafe actions', 'Never use email', 'Disable HTTPS', 'B', 'Human factors.'],
                ['MFA protects accounts by:', 'Removing passwords', 'Requiring an extra factor beyond the password', 'Sharing OTPs publicly', 'Disabling updates', 'B', 'Second factor.'],
                ['A strong unique passphrase is better than:', 'A short reused password', 'A hardware security key alone with no account password policy', 'Reporting incidents', 'Locking your screen', 'A', 'Uniqueness + length.'],
                ['Impersonating a manager by email is:', 'Social engineering / BEC-style risk', 'A backup strategy', 'An HTTPS feature', 'Antivirus', 'A', 'People-targeted fraud.'],
                ['Best response to unexpected payment urgency:', 'Pay immediately', 'Verify out-of-band', 'Send OTPs', 'Disable MFA', 'B', 'Verify first.'],
                ['Credential stuffing relies on:', 'Password reuse across sites', 'Only USB drives', 'Printer toner', 'Screen brightness', 'A', 'Leaked pairs tried elsewhere.'],
                ['Reporting suspected phishing:', 'Helps only attackers', 'Protects the organisation', 'Is discouraged', 'Replaces all technical controls', 'B', 'Collective defence.'],
                ['HTTPS padlock means:', 'Site is always legitimate', 'Connection is encrypted', 'No phishing possible', 'Wi-Fi is trusted', 'B', 'Encryption only.'],
                ['Tailgating is mainly a:', 'Physical security / social engineering issue', 'DNS setting', 'Password hash', 'VPN protocol', 'A', 'Unauthorised physical follow-in.'],
                ['After entering secrets on a fake site:', 'Do nothing', 'Report and change credentials', 'Reuse the password', 'Turn off logging', 'B', 'Contain compromise.'],
                ['Least privilege means:', 'Maximum access for convenience', 'Minimum access needed for the task', 'No passwords', 'Public links always', 'B', 'Limit access.'],
                ['Ransomware typically demands:', 'Nothing', 'Payment after encrypting files', 'Only stronger Wi-Fi', 'More browser extensions', 'B', 'Extortion via encryption.'],
            ],
        ],
        [
            'module_slug' => 'incident-reporting-hygiene',
            'title' => 'CAMS Final Exam — Cyber Awareness',
            'description' => 'Capstone exam across browsing, privacy, email safety, malware, and reporting. Formal pass mark.',
            'quiz_type' => 'exam',
            'pass_mark' => 80,
            'max_attempts' => 2,
            'questions' => [
                ['Public Wi-Fi elevates risk of:', 'Only slow pages', 'Eavesdropping and rogue hotspots', 'Automatic MFA strength', 'Perfect privacy', 'B', 'Untrusted networks.'],
                ['Business Email Compromise often requests:', 'Wallpaper changes', 'Fraudulent payments or data', 'Antivirus uninstall for fun', 'Longer vacations only', 'B', 'Financial/data fraud.'],
                ['“Anyone with the link” on confidential HR files is:', 'Ideal', 'Oversharing risk', 'Required encryption', 'An MFA factor', 'B', 'Too broad access.'],
                ['Malicious browser extensions may:', 'Only change colours', 'Steal sessions or data', 'Guarantee safety', 'Replace incident response', 'B', 'Powerful permissions.'],
                ['Clean desk reduces:', 'Shoulder surfing and abandoned sensitive paper', 'Only ransomware math', 'DNS spoofing alone', 'Battery use only', 'A', 'Physical exposure.'],
                ['Lost device first action:', 'Wait a month', 'Report immediately and follow ICT guidance', 'Post passwords online', 'Disable all locks forever', 'B', 'Rapid reporting.'],
                ['Safe download habit:', 'Prefer official sources; verify unexpected files', 'Install every email attachment', 'Use crack sites', 'Disable scanning always', 'A', 'Reduce malware intake.'],
                ['Vishing is:', 'Phone-based social engineering', 'A Wi-Fi encryption suite', 'A backup format', 'A password manager', 'A', 'Voice phishing.'],
                ['Dual control on payments mitigates:', 'Single-approver BEC fraud', 'All malware forever', 'Need for updates', 'Screen locks', 'A', 'Second person check.'],
                ['Incident reports should include:', 'Vague guesses only', 'Clear what/when/impact details', 'Other users’ passwords', 'Nothing actionable', 'B', 'Help responders act.'],
                ['Patching systems reduces:', 'Known vulnerability exposure', 'Need for any awareness', 'All social engineering', 'Printer paper use only', 'A', 'Close known holes.'],
                ['Spear phishing differs from bulk phishing by:', 'Being personalised to a target', 'Never using email', 'Only attacking printers', 'Disabling HTTPS', 'A', 'Targeted content.'],
                ['Privacy need-to-know means:', 'Broadcast data widely', 'Limit data to authorised need', 'Ignore policies', 'Share OTPs', 'B', 'Minimise disclosure.'],
                ['If ransomware appears:', 'Pay silently always', 'Contact ICT; preserve evidence; follow playbook', 'Wipe evidence immediately without guidance', 'Post the note publicly with secrets', 'B', 'Organised response.'],
                ['Auto-complete email leaks are prevented by:', 'Rushing Send', 'Pausing to check recipients', 'Disabling all security', 'Using only SMS MFA forever', 'B', 'Human check before send.'],
            ],
        ],
    ];

    $tips = [
        ['Think before you click', 'Hover over links and verify the destination before opening them.', 'Phishing'],
        ['Use unique passwords', 'Never reuse the same password across important accounts.', 'Passwords'],
        ['Enable MFA', 'Turn on multi-factor authentication wherever it is available.', 'Passwords'],
        ['Keep software updated', 'Install security updates for your operating system and apps.', 'Malware'],
        ['Verify unusual requests', 'Call or message the person using a known contact method.', 'Social Engineering'],
        ['Lock your device', 'Lock your computer when you step away, even briefly.', 'General'],
        ['HTTPS ≠ trust', 'A padlock means encryption — not that a site is the real organisation.', 'Browsing'],
        ['Beware public Wi-Fi', 'Avoid sensitive logins on open networks; use VPN or mobile data when needed.', 'Browsing'],
        ['Check recipients twice', 'Auto-complete mistakes cause accidental data leaks.', 'Email'],
        ['Report fast', 'Early incident reports protect colleagues — do not stay silent.', 'Incidents'],
        ['Least privilege sharing', 'Share the minimum file and access level required.', 'Privacy'],
        ['Official downloads only', 'Prefer vendor sites and approved stores; avoid pirated software.', 'Malware'],
        ['Never share OTPs', 'One-time codes are secrets — ICT or banks will not ask you to read them out.', 'Phishing'],
        ['Clean desk', 'Collect printouts promptly and shred confidential paper waste.', 'Privacy'],
        ['Review cloud links', 'Avoid “anyone with the link” for confidential folders.', 'Privacy'],
        ['Forget untrusted Wi-Fi', 'Remove public networks after use so your device does not auto-reconnect.', 'Browsing'],
    ];

    $counts = ['modules' => 0, 'lessons' => 0, 'quizzes' => 0, 'questions' => 0, 'tips' => 0];
    $moduleIds = [];

    foreach ($modules as $mod) {
        $mid = cams_upsert_module($pdo, $mod);
        $moduleIds[$mod['slug']] = $mid;
        $counts['modules']++;
        foreach ($mod['lessons'] as $lesson) {
            cams_upsert_lesson($pdo, $mid, $lesson);
            $counts['lessons']++;
        }
        foreach ($mod['quizzes'] as $quiz) {
            $qid = cams_upsert_quiz($pdo, $mid, $quiz);
            cams_sync_questions($pdo, $qid, $quiz['questions']);
            $counts['quizzes']++;
            $counts['questions'] += count($quiz['questions']);
        }
    }

    foreach ($finalExams as $exam) {
        $mid = $moduleIds[$exam['module_slug']] ?? null;
        if (!$mid) {
            continue;
        }
        $qid = cams_upsert_quiz($pdo, $mid, $exam);
        cams_sync_questions($pdo, $qid, $exam['questions']);
        $counts['quizzes']++;
        $counts['questions'] += count($exam['questions']);
    }

    foreach ($tips as $t) {
        cams_upsert_tip($pdo, $t[0], $t[1], $t[2]);
        $counts['tips']++;
    }

    // Mark legacy module quizzes as module type if still default
    $pdo->exec("UPDATE quizzes SET quiz_type = 'module' WHERE quiz_type = 'module' OR quiz_type IS NULL OR quiz_type = ''");

    return $counts;
}
