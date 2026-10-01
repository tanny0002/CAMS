<?php
/**
 * Generate 1000+ unique-ish MCQs per module topic into a hidden quiz_type=bank.
 * Run: php -c .\.php.ini database/seed_question_banks.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/content_pack.php';

/**
 * @return list<array{0:string,1:string,2:string,3:string,4:string,5:string,6:string}>
 */
function cams_generate_topic_questions(string $slug, int $target): array
{
    $topics = cams_question_topic_kits();
    $kit = $topics[$slug] ?? $topics['_default'];
    $out = [];
    $seen = [];

    $templates = $kit['templates'];
    $subjects = $kit['subjects'];
    $good = $kit['good'];
    $bad = $kit['bad'];
    $extras = $kit['extras'];
    $explain = $kit['explain'];

    $i = 0;
    // Combinatorial generation until we hit target
    while (count($out) < $target) {
        $t = $templates[$i % count($templates)];
        $s = $subjects[$i % count($subjects)];
        $g = $good[$i % count($good)];
        // Pick 3 distinct wrongs
        $badPool = $bad;
        shuffle($badPool);
        $w1 = $badPool[0];
        $w2 = $badPool[1 % count($badPool)];
        $w3 = $badPool[2 % count($badPool)];
        if ($w2 === $w1) {
            $w2 = $badPool[min(3, count($badPool) - 1)];
        }
        if ($w3 === $w1 || $w3 === $w2) {
            $w3 = $bad[( $i + 5 ) % count($bad)];
        }

        $extra = $extras[$i % count($extras)];
        $qText = str_replace(
            ['{subject}', '{good}', '{extra}', '{n}'],
            [$s, $g, $extra, (string) (($i % 97) + 1)],
            $t
        );

        // Rotate where the correct answer sits
        $options = [$g, $w1, $w2, $w3];
        // Ensure uniqueness of option texts
        $options = array_values(array_unique($options));
        while (count($options) < 4) {
            $options[] = $bad[(count($options) * 7 + $i) % count($bad)] . ' (variant ' . (($i % 9) + 1) . ')';
            $options = array_values(array_unique($options));
        }
        $options = array_slice($options, 0, 4);
        $correctText = $g;
        // If good was deduped away somehow, force it
        if (!in_array($correctText, $options, true)) {
            $options[0] = $correctText;
        }
        // Rotate correct into letter based on i
        $rot = $i % 4;
        $rotated = [];
        for ($k = 0; $k < 4; $k++) {
            $rotated[$k] = $options[($k + $rot) % 4];
        }
        // Ensure correct is present at known index
        $correctIdx = array_search($correctText, $rotated, true);
        if ($correctIdx === false) {
            $rotated[0] = $correctText;
            $correctIdx = 0;
        }
        $letters = ['A', 'B', 'C', 'D'];
        $correct = $letters[$correctIdx];

        $hash = md5($qText . '|' . implode('|', $rotated));
        if (isset($seen[$hash])) {
            $qText .= ' (scenario ' . ($i + 1) . ')';
            $hash = md5($qText . '|' . implode('|', $rotated));
            if (isset($seen[$hash])) {
                $i++;
                continue;
            }
        }
        $seen[$hash] = true;

        $out[] = [
            $qText,
            $rotated[0],
            $rotated[1],
            $rotated[2],
            $rotated[3],
            $correct,
            $explain,
        ];
        $i++;
        if ($i > $target * 20) {
            break; // safety
        }
    }

    return $out;
}

/**
 * @return array<string, array<string, mixed>>
 */
function cams_question_topic_kits(): array
{
    $sharedBad = [
        'Ignore all security policies',
        'Share passwords in a public chat',
        'Disable antivirus permanently',
        'Click every unexpected link immediately',
        'Reuse one password on every account',
        'Send OTPs to anyone who asks',
        'Pay ransomware without reporting',
        'Post confidential files publicly',
        'Leave workstations unlocked in public areas',
        'Install software from unknown crack sites',
        'Skip reporting suspected incidents',
        'Trust display names without checking addresses',
        'Turn off MFA for convenience',
        'Use “anyone with the link” for payroll data',
        'Grant remote access from cold calls',
    ];

    return [
        'phishing' => [
            'templates' => [
                'In a phishing scenario involving {subject}, what is the safest response?',
                'Which action best reduces risk when you receive a message about {subject}?',
                'A suspicious email mentions {subject}. What should you do first?',
                'Regarding {subject}, which choice demonstrates good phishing awareness?',
                'Attackers often lure victims with {subject}. The best defence is to:',
                'If an unexpected request about {subject} asks for credentials, you should:',
                'Case {n}: a message about {subject} uses urgent language. Best next step?',
                'Which red flag is most relevant when {subject} appears in an unexpected email?',
                'For {subject}, which habit stops most phishing attempts?',
                'Your colleague forwards a “urgent {subject}” link. You should:',
            ],
            'subjects' => [
                'password resets', 'wire transfers', 'package delivery notices', 'HR payroll updates',
                'shared document access', 'voicemail attachments', 'invoice PDFs', 'IT support requests',
                'bank verification codes', 'exam portal logins', 'scholarship forms', 'cloud storage shares',
                'CEO payment requests', 'crypto wallet alerts', 'tax refund messages', 'meeting invites',
                'VPN re-authentication', 'student fee reminders', 'library account warnings', 'alumni donations',
            ],
            'good' => [
                'Verify via an official channel and report to ICT',
                'Hover/inspect links without clicking, then report',
                'Do not enter credentials from the email link',
                'Check the full sender address and domain carefully',
                'Treat unexpected credential requests as suspicious',
                'Use known bookmarks or typed URLs for sensitive sites',
                'Never share OTPs or passwords from the message',
                'Report suspected phishing even if unsure',
            ],
            'bad' => $sharedBad,
            'extras' => ['urgency', 'fear', 'authority pressure', 'lookalike domains', 'spoofed display names'],
            'explain' => 'Phishing defence: slow down, verify out-of-band, never share secrets, and report.',
        ],
        'password-security' => [
            'templates' => [
                'For account security around {subject}, the best practice is to:',
                'Which choice strengthens protection when dealing with {subject}?',
                'Scenario {n}: {subject}. What should you do?',
                'Regarding {subject}, which option reduces credential risk most?',
                'If you must handle {subject}, prefer to:',
                'A policy question about {subject}: the safest approach is:',
                'Which action is recommended for {subject}?',
                'Users facing {subject} should avoid:',
                'To protect accounts during {subject}, you should:',
                'Best MFA / password hygiene for {subject} is:',
            ],
            'subjects' => [
                'creating a new password', 'reusing old passwords', 'shared lab PCs', 'password manager use',
                'enabling MFA', 'SMS one-time codes', 'authenticator apps', 'hardware security keys',
                'breach notifications', 'credential stuffing risk', 'passphrase creation', 'password sharing requests',
                'reset emails', 'service accounts', 'admin privileges', 'guest Wi-Fi logins',
                'browser saved passwords', 'writing passwords on paper', 'periodic password changes', 'account recovery questions',
            ],
            'good' => [
                'Use a long unique passphrase and enable MFA',
                'Store unique passwords in an approved password manager',
                'Never reuse passwords across important accounts',
                'Prefer authenticator apps or hardware keys over SMS when possible',
                'Change passwords after a confirmed breach and review sessions',
                'Do not share passwords; use approved shared-account processes',
                'Avoid saving work passwords on public/shared browsers',
                'Verify unexpected reset emails through official portals',
            ],
            'bad' => $sharedBad,
            'extras' => ['length', 'uniqueness', 'MFA', 'managers', 'breach response'],
            'explain' => 'Strong unique credentials plus MFA beat short reused passwords.',
        ],
        'malware' => [
            'templates' => [
                'When dealing with {subject}, the safest malware-prevention step is:',
                'Case {n} involves {subject}. What should you do?',
                'Which response best limits malware risk related to {subject}?',
                'About {subject}, which action is recommended?',
                'If you suspect malware after {subject}, first:',
                'To reduce infection risk from {subject}, you should:',
                'Which habit helps most against threats involving {subject}?',
                'Regarding {subject}, avoid:',
                'Best awareness choice for {subject} is:',
                'Organisational guidance for {subject} typically says:',
            ],
            'subjects' => [
                'unexpected email attachments', 'USB drives from unknown sources', 'pirated software installers',
                'Office macros', 'ransomware ransom notes', 'outdated operating systems', 'browser drive-by downloads',
                'cracked games', 'suspicious “invoice” JS files', 'disabling antivirus', 'unofficial browser toolbars',
                'remote “fix” tools from cold calls', 'backing up important files', 'patching applications',
                'opening .exe from chat', 'free “optimizer” tools', 'malicious PDFs', 'infected shared folders',
                'auto-run on removable media', 'fake antivirus pop-ups',
            ],
            'good' => [
                'Keep software updated and use approved security tools',
                'Avoid untrusted downloads and verify unexpected files',
                'Report suspected infections to ICT immediately',
                'Do not pay ransomware without official guidance',
                'Prefer official vendors and app stores',
                'Treat unexpected macros/attachments as high risk',
                'Maintain tested backups of important work',
                'Disconnect/report if instructed during an incident',
            ],
            'bad' => $sharedBad,
            'extras' => ['ransomware', 'trojans', 'spyware', 'worms', 'macros'],
            'explain' => 'Prevention: patch, avoid untrusted files, backups, and rapid reporting.',
        ],
        'social-engineering' => [
            'templates' => [
                'A social-engineering attempt involving {subject} is best handled by:',
                'Case {n}: someone pressures you about {subject}. You should:',
                'Which response defeats manipulation around {subject}?',
                'For {subject}, the stop–verify–act approach means:',
                'If a caller/email insists on {subject}, safest choice is:',
                'Regarding {subject}, which habit protects you?',
                'Authority pressure about {subject} should make you:',
                'Best defence when {subject} is used as bait:',
                'Which option shows good awareness of {subject}?',
                'Tailgating / pretext related to {subject}: you should:',
            ],
            'subjects' => [
                'urgent CEO payment requests', 'fake ICT remote support', 'OTP requests by phone',
                'tailgating into secured offices', 'free USB “gifts”', 'pretexting as a vendor',
                'vishing bank verification', 'pressure to bypass policy', 'impersonation of a lecturer',
                'gift-card scams', 'fake delivery drivers', 'shoulder surfing at kiosks',
                'requests for badge access', 'emotional urgency stories', '“help me test a system” lures',
                'spoofed internal chat messages', 'requests to hide an action from ICT', 'unusual after-hours access asks',
                'baiting with contest prizes', 'requests for confidential student lists',
            ],
            'good' => [
                'Pause and verify using a known official channel',
                'Refuse OTP/password sharing and report the attempt',
                'Do not grant access or payments under pressure alone',
                'Follow policy even when someone claims authority',
                'Challenge or report physical tailgating appropriately',
                'Hang up and call back using an official number',
                'Avoid bait items like unknown USBs',
                'Escalate suspicious requests to ICT/security',
            ],
            'bad' => $sharedBad,
            'extras' => ['pretexting', 'vishing', 'baiting', 'impersonation', 'urgency'],
            'explain' => 'Social engineering targets people — verify, do not bypass controls, report.',
        ],
        'safe-browsing-wifi' => [
            'templates' => [
                'While browsing in a situation involving {subject}, you should:',
                'Case {n}: {subject}. Safest action?',
                'Which choice improves safety for {subject}?',
                'About {subject}, remember that:',
                'To reduce risk with {subject}, prefer to:',
                'A question on {subject}: best practice is:',
                'When {subject} occurs, avoid:',
                'HTTPS and {subject}: the correct understanding is to:',
                'Public networks and {subject}: you should:',
                'Browser hygiene for {subject} means:',
            ],
            'subjects' => [
                'café public Wi-Fi', 'HTTPS padlocks on unfamiliar sites', 'evil-twin hotspots',
                'banking on open Wi-Fi', 'unknown browser extensions', 'typed URLs vs email links',
                'forgetting public networks', 'VPN use on travel Wi-Fi', 'cookie prompts on odd sites',
                'lookalike shopping domains', 'shared kiosk browsers', 'automatic Wi-Fi join',
                'certificate warnings', 'downloading browser toolbars', 'using bookmarks for banks',
                'pop-up “PC infected” warnings', 'QR codes from strangers', 'hotel captive portals',
                'checking domain spelling', 'clearing sessions on shared PCs',
            ],
            'good' => [
                'Treat HTTPS as encryption, not proof of legitimacy',
                'Avoid sensitive logins on open Wi-Fi when possible; use VPN/mobile data',
                'Type known addresses or use trusted bookmarks',
                'Install extensions only from trusted sources with minimal permissions',
                'Heed certificate warnings and do not bypass casually',
                'Forget untrusted Wi-Fi networks after use',
                'Log out and clear sessions on shared browsers',
                'Be sceptical of lookalike domains and urgent browser pop-ups',
            ],
            'bad' => $sharedBad,
            'extras' => ['padlock myth', 'evil twin', 'extensions', 'captive portals', 'QR lures'],
            'explain' => 'Browse carefully: verify domains, limit public Wi-Fi exposure, control extensions.',
        ],
        'email-messaging-safety' => [
            'templates' => [
                'For safer email/chat practice around {subject}, you should:',
                'Case {n} about {subject}. Best response?',
                'Which action prevents incidents involving {subject}?',
                'Regarding {subject}, the recommended habit is:',
                'If you encounter {subject}, do this first:',
                'BEC-style risk with {subject} is reduced by:',
                'Collaboration safety for {subject} means:',
                'Before acting on {subject}, verify by:',
                'Which choice is correct for {subject}?',
                'To avoid leaks related to {subject}:',
            ],
            'subjects' => [
                'supplier bank-detail changes', 'CEO gift-card requests', 'auto-complete wrong recipients',
                'public channel secrets', 'unexpected voicemail attachments', 'display-name spoofing',
                'shortened links in mail', 'external forward of student data', 'shared drive edit rights',
                'thread hijacking after mailbox compromise', 'invoice reissue scams', 'chat password requests',
                'large payment approvals', 'CC mistakes on HR mail', 'unsolicited calendar invites',
                'file shares to personal Gmail', 'urgent “board request” wires', 'malware zipped invoices',
                'reply-all to sensitive threads', 'external users added to private channels',
            ],
            'good' => [
                'Verify payment/data changes out-of-band using known contacts',
                'Check full addresses and pause before Send on sensitive mail',
                'Use approved collaboration tools with least-privilege sharing',
                'Never share passwords or OTPs in chat because “ICT asked”',
                'Treat BEC payment urgency as high risk until verified',
                'Report suspicious business email compromise attempts',
                'Limit confidential discussion to appropriate private channels',
                'Be cautious with unexpected attachments and shortened links',
            ],
            'bad' => $sharedBad,
            'extras' => ['BEC', 'display names', 'least privilege', 'dual control', 'recipient check'],
            'explain' => 'Email/chat safety: verify identity, minimise sharing, watch BEC patterns.',
        ],
        'data-privacy' => [
            'templates' => [
                'Privacy good practice for {subject} is to:',
                'Case {n}: {subject}. What should you do?',
                'Which option protects data when handling {subject}?',
                'About {subject}, apply need-to-know by:',
                'If faced with {subject}, the safest choice is:',
                'Clean-desk / screen privacy for {subject} means:',
                'Cloud sharing and {subject}: you should:',
                'Which action reduces exposure around {subject}?',
                'Organisational data rules for {subject} say:',
                'To prevent a privacy incident involving {subject}:',
            ],
            'subjects' => [
                'student grade spreadsheets', 'payroll exports', 'medical/leave details',
                'ID photocopies', 'printer trays with confidential pages', 'unlocked screens in corridors',
                '“anyone with the link” folders', 'ex-staff cloud access', 'personal Dropbox for work files',
                'oversharing whole drives', 'shoulder surfing on buses', 'emailing data to wrong cohort lists',
                'research participant records', 'staff home addresses', 'exam question drafts',
                'CCTV export requests', 'WhatsApp forwarding of ID cards', 'public dashboards with personal data',
                'USB copies of HR folders', 'screens visible to visitors',
            ],
            'good' => [
                'Share only with authorised people at the minimum necessary access',
                'Store work data on approved systems, not personal unmanaged cloud',
                'Lock screens and collect sensitive printouts promptly',
                'Avoid public links for confidential folders',
                'Report accidental disclosures quickly',
                'Revoke access when people leave roles/projects',
                'Use need-to-know and least privilege for files',
                'Position screens and protect paper waste appropriately',
            ],
            'bad' => $sharedBad,
            'extras' => ['need-to-know', 'clean desk', 'cloud links', 'approved storage', 'breach reporting'],
            'explain' => 'Privacy: minimise access, use approved systems, protect screens/paper, report incidents.',
        ],
        'incident-reporting-hygiene' => [
            'templates' => [
                'Digital hygiene and reporting for {subject} requires you to:',
                'Case {n}: {subject}. Best immediate action?',
                'Which response is correct when {subject} happens?',
                'About {subject}, organisational guidance is to:',
                'To handle {subject} safely, you should:',
                'If you notice {subject}, do not:',
                'Good hygiene related to {subject} includes:',
                'Which detail matters most when reporting {subject}?',
                'After {subject}, the priority is:',
                'Preventive hygiene for {subject} means:',
            ],
            'subjects' => [
                'a lost work laptop', 'suspected phishing clicks', 'unusual login alerts',
                'ransomware-like messages', 'stolen phones with mail access', 'failed update prompts ignored for months',
                'accidental data emailed externally', 'malware pop-ups', 'missing encrypted USB drives',
                'suspicious admin consent prompts', 'unpatched browsers', 'shared passwords discovered',
                'physical tailgating incidents', 'backup failures', 'remote-wipe decisions',
                'device lock not enabled', 'personal risky downloads on work profiles', 'DDoS panic emails',
                ' unexplained outbound traffic reports', 'found unknown USB in a lab',
            ],
            'good' => [
                'Report promptly to ICT with what/when/systems impacted',
                'Install updates and keep device locks enabled',
                'Change credentials and follow incident instructions',
                'Preserve useful evidence and avoid covering up mistakes',
                'Use approved backups and verify restore practices where required',
                'Do not delay lost-device reporting',
                'Separate risky personal activity from work accounts per policy',
                'Treat unusual account activity as a possible compromise',
            ],
            'bad' => $sharedBad,
            'extras' => ['fast reporting', 'patching', 'device lock', 'backups', 'containment'],
            'explain' => 'Report early, patch and lock devices, follow ICT playbooks, do not hide incidents.',
        ],
        '_default' => [
            'templates' => [
                'For cybersecurity awareness about {subject}, you should:',
                'Case {n}: {subject}. Safest action?',
                'Which choice is best regarding {subject}?',
                'When handling {subject}, prefer to:',
                'Awareness question on {subject}:',
            ],
            'subjects' => ['unexpected requests', 'account security', 'data handling', 'device care', 'reporting'],
            'good' => [
                'Verify, protect credentials, and report concerns to ICT',
                'Follow policy and use approved tools',
                'Apply least privilege and need-to-know',
                'Keep systems updated and screens locked',
            ],
            'bad' => $sharedBad,
            'extras' => ['verify', 'report', 'MFA', 'updates'],
            'explain' => 'Core awareness: verify requests, protect accounts, report issues.',
        ],
    ];
}

function cams_ensure_bank_quiz(PDO $pdo, int $moduleId, string $moduleTitle): int
{
    cams_ensure_quiz_type_column($pdo);
    if (!(defined('DB_DRIVER') && DB_DRIVER === 'pgsql')) {
        $type = $pdo->query("SHOW COLUMNS FROM quizzes LIKE 'quiz_type'")->fetch();
        if ($type && !str_contains((string) $type['Type'], 'bank')) {
            $pdo->exec(
                "ALTER TABLE quizzes MODIFY COLUMN quiz_type ENUM('practice','module','exam','bank') NOT NULL DEFAULT 'module'"
            );
        }
        $drawCol = $pdo->query("SHOW COLUMNS FROM quizzes LIKE 'questions_to_draw'")->fetch();
        if (!$drawCol) {
            $pdo->exec(
                'ALTER TABLE quizzes ADD COLUMN questions_to_draw SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER max_attempts'
            );
        }
    }

    $title = $moduleTitle . ' — Question Bank';
    $find = $pdo->prepare("SELECT id FROM quizzes WHERE module_id = ? AND quiz_type = 'bank' LIMIT 1");
    $find->execute([$moduleId]);
    $id = $find->fetchColumn();
    if ($id) {
        $pdo->prepare(
            'UPDATE quizzes SET title=?, description=?, is_active=0, pass_mark=0, max_attempts=0, questions_to_draw=0 WHERE id=?'
        )->execute([
            $title,
            'Hidden bank of 1000+ shuffled questions for this topic.',
            $id,
        ]);
        return (int) $id;
    }
    $pdo->prepare(
        'INSERT INTO quizzes (module_id, title, description, quiz_type, pass_mark, max_attempts, questions_to_draw, is_active)
         VALUES (?,?,?,?,?,?,?,0)'
    )->execute([
        $moduleId,
        $title,
        'Hidden bank of 1000+ shuffled questions for this topic.',
        'bank',
        0,
        0,
        0,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * @return array{modules:int, questions:int}
 */
function cams_seed_question_banks(PDO $pdo, int $perTopic = QUESTION_BANK_SIZE): array
{
    if (!defined('QUESTION_BANK_SIZE')) {
        $perTopic = max($perTopic, 1001);
    }
    ensure_quiz_bank_schema();

    $modules = $pdo->query('SELECT id, title, slug FROM modules WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
    $ins = $pdo->prepare(
        'INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation, sort_order, is_active)
         VALUES (?,?,?,?,?,?,?,?,?,1)'
    );
    $countActive = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE quiz_id = ? AND is_active = 1');

    $moduleCount = 0;
    $questionCount = 0;

    foreach ($modules as $mod) {
        $moduleCount++;
        $bankId = cams_ensure_bank_quiz($pdo, (int) $mod['id'], (string) $mod['title']);
        $countActive->execute([$bankId]);
        $have = (int) $countActive->fetchColumn();
        if ($have >= $perTopic) {
            echo "  [skip] {$mod['slug']}: already has {$have} bank questions\n";
            $questionCount += $have;
            continue;
        }

        $need = $perTopic - $have;
        echo "  [gen] {$mod['slug']}: generating {$need} questions (have {$have})...\n";
        $batch = cams_generate_topic_questions((string) $mod['slug'], $need);
        $pdo->beginTransaction();
        $sort = $have;
        foreach ($batch as $q) {
            $sort++;
            $ins->execute([$bankId, $q[0], $q[1], $q[2], $q[3], $q[4], $q[5], $q[6], $sort]);
            $questionCount++;
        }
        $pdo->commit();

        // Set draw sizes on learner-facing quizzes for this module
        $pdo->prepare(
            "UPDATE quizzes SET questions_to_draw = CASE quiz_type
                WHEN 'practice' THEN ?
                WHEN 'exam' THEN ?
                WHEN 'module' THEN ?
                ELSE questions_to_draw END
             WHERE module_id = ? AND quiz_type IN ('practice','module','exam')"
        )->execute([
            QUIZ_DRAW_PRACTICE,
            QUIZ_DRAW_EXAM,
            QUIZ_DRAW_MODULE,
            (int) $mod['id'],
        ]);
    }

    return ['modules' => $moduleCount, 'questions' => $questionCount];
}
