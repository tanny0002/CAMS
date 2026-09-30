<?php
/**
 * One-time enricher: expands seed lesson HTML for GfG-style article reading.
 * Run: php -c .\.php.ini database/enrich_lessons.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';

$lessons = [
    'what-is-phishing' => [
        'summary' => 'Learn how attackers use fake messages to steal information — and how phishing fits into everyday cyber risk.',
        'content' => <<<'HTML'
<h2>What is Phishing?</h2>
<p>Phishing is a social engineering attack that uses deceptive emails, SMS (smishing), voice calls (vishing), or fake websites to trick people into revealing passwords, banking details, or clicking unsafe links.</p>
<p>Attackers often impersonate banks, ICT support, delivery services, or someone in authority. The goal is usually credentials, money, or malware installation.</p>

<h3>Why phishing works</h3>
<ul>
<li>Urgency and fear reduce careful checking</li>
<li>Familiar logos and language look legitimate at a glance</li>
<li>Busy people skim messages instead of inspecting them</li>
</ul>

<h2>Warning signs</h2>
<ul>
<li>Urgent language demanding immediate action</li>
<li>Unexpected requests for passwords, OTPs, or money</li>
<li>Misspelled domains or strange sender addresses</li>
<li>Generic greetings such as “Dear Customer”</li>
<li>Links that do not match the organisation’s real website</li>
</ul>

<div class="callout"><strong>Remember:</strong> Legitimate ICT or banking staff will not pressure you to share passwords by email or SMS.</div>

<h2>What you should do</h2>
<p>Always verify unexpected requests through an official channel before responding. Report suspicious messages to ICT so others can be protected.</p>
HTML
    ],
    'spot-phishing-email' => [
        'summary' => 'Practical checks before you click — hover, inspect, and report.',
        'content' => <<<'HTML'
<h2>How to Spot a Phishing Email</h2>
<p>Before you click anything, slow down and inspect the message. Most phishing succeeds because victims act quickly.</p>

<h3>Check the sender</h3>
<ul>
<li>Expand the full email address — not only the display name</li>
<li>Look for lookalike domains (e.g. <code>rnicrosoft.com</code> instead of <code>microsoft.com</code>)</li>
<li>Be wary of free webmail accounts claiming to represent official departments</li>
</ul>

<h3>Inspect links safely</h3>
<p>Hover over links (do not click) to see the real destination on desktop. On mobile, long-press to preview when the client allows it.</p>

<h3>Attachments</h3>
<p>Unexpected attachments — especially <code>.exe</code>, <code>.js</code>, <code>.html</code>, or macro-enabled Office files — are high risk. Confirm with the sender using a known contact method.</p>

<h2>Safe response</h2>
<p>Report suspicious messages to ICT. Do not enter credentials on pages reached from email links — open the browser yourself and navigate to the official site.</p>
<table>
<thead><tr><th>Do</th><th>Don’t</th></tr></thead>
<tbody>
<tr><td>Hover / preview links</td><td>Click first “to check”</td></tr>
<tr><td>Report to ICT</td><td>Forward phishing widely to friends</td></tr>
<tr><td>Verify via official channel</td><td>Reply with passwords or OTPs</td></tr>
</tbody>
</table>
HTML
    ],
    'strong-passwords' => [
        'summary' => 'Why length and uniqueness matter more than complex short passwords.',
        'content' => <<<'HTML'
<h2>Strong Passwords</h2>
<p>A strong password is long, unique, and hard to guess. Prefer passphrases (several unrelated words) over short complex strings you cannot remember.</p>

<h3>Good practices</h3>
<ul>
<li>Do not reuse passwords across accounts</li>
<li>Never share passwords — even with colleagues</li>
<li>Use a password manager when possible</li>
<li>Enable multi-factor authentication (MFA)</li>
<li>Change passwords after a suspected breach</li>
</ul>

<div class="callout"><strong>Tip:</strong> A passphrase like four random words is usually stronger and easier to type than <code>P@ssw0rd!</code>.</div>

<h2>What attackers try</h2>
<p>Credential stuffing reuses leaked passwords from other sites. Dictionary attacks try common words and patterns. Unique long passwords reduce both risks dramatically.</p>
HTML
    ],
    'password-managers-mfa' => [
        'summary' => 'Tools that reduce password risk: managers and multi-factor authentication.',
        'content' => <<<'HTML'
<h2>Password Managers and MFA</h2>
<p>Password managers store unique passwords securely and fill them for you. Multi-factor authentication adds a second check (app code, SMS, or hardware key) so a stolen password alone is not enough.</p>

<h3>Why use a password manager?</h3>
<ul>
<li>You only memorise one strong master password (or unlock with biometrics)</li>
<li>Every site can have a unique random password</li>
<li>Many managers warn about reused or breached passwords</li>
</ul>

<h3>MFA factors</h3>
<ol>
<li><strong>Something you know</strong> — password or PIN</li>
<li><strong>Something you have</strong> — phone app, SMS, or hardware key</li>
<li><strong>Something you are</strong> — fingerprint or face unlock</li>
</ol>

<div class="callout"><strong>Prefer authenticator apps or hardware keys</strong> over SMS when available — SMS can be intercepted via SIM swap attacks.</div>

<h2>Institutional accounts</h2>
<p>Follow your organisation’s approved tools for MFA and password storage. Do not store work passwords in unsecured notes or shared documents.</p>
HTML
    ],
    'common-malware' => [
        'summary' => 'Viruses, ransomware, trojans, spyware — what they do and how they arrive.',
        'content' => <<<'HTML'
<h2>Common Types of Malware</h2>
<p>Malware is software designed to harm systems or steal data. Common types include viruses, worms, trojans, spyware, and ransomware.</p>

<h3>Quick guide</h3>
<table>
<thead><tr><th>Type</th><th>Typical behaviour</th></tr></thead>
<tbody>
<tr><td>Virus / worm</td><td>Spreads and disrupts systems</td></tr>
<tr><td>Trojan</td><td>Looks useful but hides harmful code</td></tr>
<tr><td>Spyware</td><td>Steals credentials or monitors activity</td></tr>
<tr><td>Ransomware</td><td>Encrypts files and demands payment</td></tr>
</tbody>
</table>

<h2>How malware arrives</h2>
<ul>
<li>Email attachments and malicious links</li>
<li>Pirated or unofficial software downloads</li>
<li>Infected USB drives</li>
<li>Drive-by downloads from compromised websites</li>
</ul>

<p>Reduce risk by avoiding suspicious downloads, keeping software updated, and scanning attachments before opening them.</p>
HTML
    ],
    'prevent-malware' => [
        'summary' => 'Everyday habits that protect devices from infection.',
        'content' => <<<'HTML'
<h2>Preventing Malware</h2>
<p>Most malware infections are preventable with consistent habits — not exotic technical skills.</p>

<h3>Daily habits</h3>
<ul>
<li>Keep OS and apps updated</li>
<li>Use reputable antivirus where available</li>
<li>Do not disable security tools without ICT approval</li>
<li>Be careful with USB drives from unknown sources</li>
<li>Download software only from official sources</li>
<li>Back up important files regularly</li>
</ul>

<div class="callout"><strong>If you suspect infection:</strong> disconnect from the network if instructed, do not pay ransomware demands without advice, and contact ICT immediately.</div>

<h2>Ransomware readiness</h2>
<p>Offline or versioned backups are the strongest recovery option. Knowing where backups live — and testing restore — matters as much as antivirus.</p>
HTML
    ],
    'se-tactics' => [
        'summary' => 'How attackers impersonate trusted people to bypass your judgement.',
        'content' => <<<'HTML'
<h2>Social Engineering Tactics</h2>
<p>Attackers may pretend to be a bank employee, ICT technician, lecturer, or manager. They create urgency or fear so you skip normal checks.</p>

<h3>Common plays</h3>
<ul>
<li><strong>Pretexting</strong> — inventing a story that requires sensitive data</li>
<li><strong>Impersonation</strong> — pretending to be someone you trust</li>
<li><strong>Baiting</strong> — offering something attractive (free USB, prize)</li>
<li><strong>Authority pressure</strong> — “the director needs this now”</li>
</ul>

<p>Verify unusual requests using a known contact method — not the phone number or email in the suspicious message.</p>

<div class="callout"><strong>Red flag:</strong> Anyone who discourages you from following policy or verifying with ICT is not acting in your interest.</div>
HTML
    ],
    'verify-requests' => [
        'summary' => 'Stop, verify, then act — a simple routine for unusual requests.',
        'content' => <<<'HTML'
<h2>Verify Before You Act</h2>
<p>If someone asks for passwords, money transfers, or remote access, pause. Contact the person or department through an official channel. Legitimate staff will not pressure you to bypass procedures.</p>

<h3>A simple routine</h3>
<ol>
<li><strong>Stop</strong> — do not act on urgency alone</li>
<li><strong>Verify</strong> — call or message using a known number / official directory</li>
<li><strong>Act</strong> — only after confirmation, follow approved process</li>
</ol>

<h2>Examples</h2>
<table>
<thead><tr><th>Request</th><th>Safe response</th></tr></thead>
<tbody>
<tr><td>“Share your OTP now”</td><td>Never share OTPs; report the request</td></tr>
<tr><td>“Install this remote tool”</td><td>Confirm with ICT via known channel</td></tr>
<tr><td>“Wire money for the dean”</td><td>Call the dean’s office on the official number</td></tr>
</tbody>
</table>

<p>When unsure, ask ICT. Reporting a false alarm is always better than enabling an attack.</p>
HTML
    ],
];

$pdo = db();
$upd = $pdo->prepare('UPDATE lessons SET summary = ?, content = ? WHERE slug = ?');
$n = 0;
foreach ($lessons as $slug => $row) {
    $upd->execute([$row['summary'], $row['content'], $slug]);
    $n += $upd->rowCount();
}

echo "Updated {$n} lesson row(s).\n";
