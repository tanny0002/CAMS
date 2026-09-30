<?php
/**
 * Deep curriculum expansion: more lessons per topic + real-life cases.
 * Original CAMS content inspired by common cyber-awareness curricula (not copied from any site).
 * Run: php -c .\.php.ini database/expand_deep_lessons.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/content_pack.php';

/**
 * @return list<array<string,mixed>>
 */
function cams_deep_curriculum(): array
{
    return [
        [
            'slug' => 'computer-security-basics',
            'title' => 'Computer Security Foundations',
            'description' => 'CIA triad, types of security, and everyday protection habits for learners.',
            'difficulty' => 'beginner',
            'sort_order' => 0,
            'lessons' => [
                [
                    'slug' => 'what-is-computer-security',
                    'title' => 'What is Computer Security?',
                    'summary' => 'Protecting systems, networks, and data from misuse and attack.',
                    'sort_order' => 1,
                    'content' => <<<'HTML'
<h2>What is Computer Security?</h2>
<p>Computer security means protecting computers, networks, applications, and data from unauthorised access, misuse, disruption, or damage. For everyday users, it is less about “hacking tools” and more about habits that keep accounts, files, and devices safe.</p>
<h3>Why it matters on campus</h3>
<ul>
<li>Student and staff accounts hold personal and academic records</li>
<li>Shared labs and public Wi-Fi increase exposure</li>
<li>One compromised account can affect email, cloud drives, and payments</li>
</ul>
<div class="case-study">
<h3>Real-life case: shared lab session left open</h3>
<p>A student logged into webmail on a library PC and walked away without logging out. The next user read private messages and changed the recovery email. <strong>Lesson:</strong> always lock or sign out on shared devices.</p>
</div>
<div class="lesson-key-points"><strong>Key points:</strong> security protects people and data; shared devices need extra care; small habits prevent large incidents.</div>
HTML
                ],
                [
                    'slug' => 'cia-triad',
                    'title' => 'The CIA Triad Explained',
                    'summary' => 'Confidentiality, Integrity, Availability — the three classic security goals.',
                    'sort_order' => 2,
                    'content' => <<<'HTML'
<h2>The CIA Triad</h2>
<p>Security teams often summarise goals as the <strong>CIA triad</strong>:</p>
<table>
<thead><tr><th>Goal</th><th>Meaning</th><th>Everyday example</th></tr></thead>
<tbody>
<tr><td><strong>Confidentiality</strong></td><td>Only authorised people can see data</td><td>Not sharing passwords; privacy settings</td></tr>
<tr><td><strong>Integrity</strong></td><td>Data is accurate and not secretly altered</td><td>Protecting grade sheets from tampering</td></tr>
<tr><td><strong>Availability</strong></td><td>Systems work when needed</td><td>Backups and updates so ransomware does not lock you out</td></tr>
</tbody>
</table>
<div class="case-study">
<h3>Real-life case: ransomware hits availability</h3>
<p>Hospitals and universities have been forced offline by ransomware that encrypts files. Even if confidentiality of some data survives, <strong>availability</strong> collapses — classes, clinics, and payments stop. Backups and patching protect availability.</p>
</div>
HTML
                ],
                [
                    'slug' => 'types-of-security',
                    'title' => 'Types of Security (Awareness View)',
                    'summary' => 'Cyber, information, application, and network security — what each protects.',
                    'sort_order' => 3,
                    'content' => <<<'HTML'
<h2>Types of Security</h2>
<ul>
<li><strong>Cyber security</strong> — protecting systems and people from internet-based threats</li>
<li><strong>Information security</strong> — protecting data wherever it lives (files, databases, printouts)</li>
<li><strong>Application security</strong> — keeping software and web apps safe from abuse</li>
<li><strong>Network security</strong> — protecting connections between devices</li>
</ul>
<p>As a learner you mainly influence cyber and information security through strong accounts, careful sharing, and reporting.</p>
<div class="case-study">
<h3>Real-life case: public cloud link</h3>
<p>An HR officer shared payroll with “anyone with the link.” Search engines and forwarded links exposed salaries. That is an <strong>information security</strong> failure caused by oversharing — not a complex hack.</p>
</div>
HTML
                ],
                [
                    'slug' => 'common-attack-overview',
                    'title' => 'Common Attacks Learners Should Recognise',
                    'summary' => 'Phishing, malware, DoS (awareness), spoofing, eavesdropping — recognition, not exploitation.',
                    'sort_order' => 4,
                    'content' => <<<'HTML'
<h2>Common Attacks (Recognition Only)</h2>
<p>You do not need to perform attacks to stay safe. You do need to recognise names and effects:</p>
<ul>
<li><strong>Phishing / spoofing</strong> — fake identity to steal trust</li>
<li><strong>Malware</strong> — harmful software</li>
<li><strong>Eavesdropping</strong> — listening on weak networks</li>
<li><strong>Denial of Service (DoS)</strong> — flooding a service so real users cannot access it</li>
<li><strong>Social engineering</strong> — manipulating people</li>
</ul>
<div class="case-study">
<h3>Real-life case: campus portal slowdown scare</h3>
<p>During registration week, a rumour claimed “hackers took down the portal.” Often the cause is overload or maintenance — but learners should still use official status channels and avoid “fix tool” downloads emailed during outages (common malware lure).</p>
</div>
<div class="callout"><strong>Awareness focus:</strong> recognise, report, and harden habits — never try attacks on live systems.</div>
HTML
                ],
                [
                    'slug' => 'everyday-protection-steps',
                    'title' => 'Everyday Protection Steps',
                    'summary' => 'Updates, secure networks, antivirus, locks, and least privilege in plain language.',
                    'sort_order' => 5,
                    'content' => <<<'HTML'
<h2>Everyday Protection Steps</h2>
<ol>
<li>Keep the operating system and apps updated</li>
<li>Prefer secure networks; be careful on public Wi-Fi</li>
<li>Use reputable security tools where provided</li>
<li>Enable device lock and account MFA</li>
<li>Back up important academic work</li>
<li>Install software only from trusted sources</li>
</ol>
<div class="case-study">
<h3>Real-life case: skipped updates</h3>
<p>WannaCry-style ransomware waves exploited unpatched systems. Organisations that delayed updates suffered more. Personal devices with auto-update enabled avoid many “known hole” attacks.</p>
</div>
HTML
                ],
            ],
        ],
        [
            'slug' => 'phishing',
            'title' => 'Phishing Awareness',
            'description' => 'Recognise deceptive messages and protect credentials from phishing attacks.',
            'difficulty' => 'beginner',
            'sort_order' => 1,
            'lessons' => [
                [
                    'slug' => 'phishing-lifecycle',
                    'title' => 'How a Phishing Attack Unfolds',
                    'summary' => 'From lure to credential theft — the typical stages.',
                    'sort_order' => 5,
                    'content' => <<<'HTML'
<h2>How a Phishing Attack Unfolds</h2>
<ol>
<li><strong>Lure</strong> — urgent email/SMS/call</li>
<li><strong>Landing</strong> — fake login or attachment</li>
<li><strong>Capture</strong> — password, OTP, or malware install</li>
<li><strong>Abuse</strong> — account takeover, fraud, lateral phishing to contacts</li>
</ol>
<div class="case-study">
<h3>Real-life case: university email compromise</h3>
<p>Attackers phished one staff mailbox, then sent “updated scholarship forms” to students from the real address. Because the sender looked internal, click rates were high. <strong>Lesson:</strong> even familiar senders can be compromised — verify unusual attachments and links.</p>
</div>
HTML
                ],
                [
                    'slug' => 'brand-impersonation',
                    'title' => 'Brand Impersonation & Fake Portals',
                    'summary' => 'Banks, delivery brands, Microsoft/Google lookalikes.',
                    'sort_order' => 6,
                    'content' => <<<'HTML'
<h2>Brand Impersonation</h2>
<p>Attackers copy logos and colours of banks, courier companies, and cloud vendors. HTTPS can still be present on fake sites.</p>
<ul>
<li>Type the official address yourself</li>
<li>Check spelling: <code>rnicrosoft.com</code> vs <code>microsoft.com</code></li>
<li>Do not trust logos alone</li>
</ul>
<div class="case-study">
<h3>Real-life case: “parcel held” SMS</h3>
<p>Thousands of people worldwide received SMS claiming a delivery fee was unpaid. The link led to a card-harvesting page. <strong>Lesson:</strong> open the courier app or official site — never pay via SMS links.</p>
</div>
HTML
                ],
                [
                    'slug' => 'qr-code-phishing',
                    'title' => 'QR Code Phishing (Quishing)',
                    'summary' => 'Malicious QR codes on posters, emails, and flyers.',
                    'sort_order' => 7,
                    'content' => <<<'HTML'
<h2>QR Code Phishing</h2>
<p>QR codes hide the destination URL. Attackers place stickers over real codes on parking meters, campus posters, or restaurant tables.</p>
<h3>Safer habits</h3>
<ul>
<li>Preview the URL when your phone allows it</li>
<li>Be suspicious of unexpected “scan to login” emails</li>
<li>Prefer typed official URLs for banking and student portals</li>
</ul>
<div class="case-study">
<h3>Real-life case: parking QR swap</h3>
<p>Criminals have covered municipal parking QR codes with stickers directing payments to attacker wallets. Drivers thought they paid the city. <strong>Lesson:</strong> verify payment destinations; use official apps when available.</p>
</div>
HTML
                ],
                [
                    'slug' => 'deepfake-voice-awareness',
                    'title' => 'Deepfake & Voice Scam Awareness',
                    'summary' => 'AI voice clones used to request urgent money.',
                    'sort_order' => 8,
                    'content' => <<<'HTML'
<h2>Deepfake and Voice Scams</h2>
<p>Attackers can clone voices from short social media clips and call relatives or finance staff claiming an emergency.</p>
<div class="case-study">
<h3>Real-life case: “CEO voice” payment request</h3>
<p>Finance teams have reported calls sounding like executives demanding urgent transfers. Some succeeded for large sums. <strong>Defence:</strong> dual approval, call-back on a known number, and a secret family/team challenge question.</p>
</div>
HTML
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
                    'slug' => 'password-attacks-awareness',
                    'title' => 'How Passwords Get Attacked (Awareness)',
                    'summary' => 'Guessing, stuffing, phishing, and keyloggers — what users can stop.',
                    'sort_order' => 5,
                    'content' => <<<'HTML'
<h2>How Passwords Get Attacked</h2>
<ul>
<li><strong>Guessing / spraying</strong> — common passwords tried widely</li>
<li><strong>Credential stuffing</strong> — leaked pairs tried on other sites</li>
<li><strong>Phishing</strong> — you type the password into a fake page</li>
<li><strong>Malware / keyloggers</strong> — steal what you type</li>
</ul>
<div class="case-study">
<h3>Real-life case: one breach, many takeovers</h3>
<p>After major website breaches, attackers automate logins on email and banking sites using the same passwords. Victims who reused passwords lost multiple accounts. <strong>Fix:</strong> unique passwords + MFA.</p>
</div>
HTML
                ],
                [
                    'slug' => 'passkeys-and-modern-auth',
                    'title' => 'Passkeys & Modern Sign-in (Intro)',
                    'summary' => 'Why phishing-resistant sign-in methods are growing.',
                    'sort_order' => 6,
                    'content' => <<<'HTML'
<h2>Passkeys and Modern Sign-in</h2>
<p>Passkeys use device cryptography instead of typed passwords for supported sites. They resist many phishing pages because a fake site cannot complete the real cryptographic check.</p>
<p>Where your institution offers MFA apps or passkeys, prefer them over SMS when possible.</p>
<div class="case-study">
<h3>Real-life case: SIM swap vs app MFA</h3>
<p>Attackers convinced mobile carriers to move a number (SIM swap), intercepted SMS codes, and emptied accounts. Victims with authenticator apps or hardware keys were far harder to compromise.</p>
</div>
HTML
                ],
                [
                    'slug' => 'account-recovery-hygiene',
                    'title' => 'Account Recovery Hygiene',
                    'summary' => 'Protect recovery email, phone, and security questions.',
                    'sort_order' => 7,
                    'content' => <<<'HTML'
<h2>Account Recovery Hygiene</h2>
<p>Attackers often hijack the recovery channel first. Protect recovery email and phone with MFA. Avoid security questions with public answers (mother’s maiden name on social media).</p>
<div class="case-study">
<h3>Real-life case: celebrity account takeovers</h3>
<p>High-profile takeovers often started with weak recovery questions or reused passwords on secondary email. Once recovery was owned, password reset completed the theft.</p>
</div>
HTML
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
                    'slug' => 'wannacry-style-lessons',
                    'title' => 'Case Study: Global Ransomware Waves',
                    'summary' => 'What WannaCry-style events taught the world about patching.',
                    'sort_order' => 5,
                    'content' => <<<'HTML'
<h2>Case Study: Global Ransomware Waves</h2>
<p>In 2017, WannaCry spread rapidly across unpatched Windows systems, disrupting hospitals, companies, and public services in many countries.</p>
<div class="case-study">
<h3>What went wrong</h3>
<ul>
<li>Critical patches were not applied widely enough</li>
<li>Worm-like spreading increased speed</li>
<li>Availability of care and business services collapsed</li>
</ul>
<p><strong>Learner takeaway:</strong> enable updates; do not ignore IT patch windows; keep offline/versioned backups of important work.</p>
</div>
HTML
                ],
                [
                    'slug' => 'banking-trojans-awareness',
                    'title' => 'Banking Trojans & Fake Apps',
                    'summary' => 'Malware that steals financial credentials via fake apps and overlays.',
                    'sort_order' => 6,
                    'content' => <<<'HTML'
<h2>Banking Trojans and Fake Apps</h2>
<p>Fake banking or “security” apps on unofficial stores can overlay real apps and steal credentials.</p>
<ul>
<li>Install only from official app stores / IT-approved sources</li>
<li>Check publisher names carefully</li>
<li>Never grant accessibility permissions to unknown apps</li>
</ul>
<div class="case-study">
<h3>Real-life case: fake COVID / relief apps</h3>
<p>During crises, fake “relief payment” and “vaccine booking” apps spiked. Users who sideloaded APKs from chat groups lost banking credentials. Official government domains and stores were the safe path.</p>
</div>
HTML
                ],
                [
                    'slug' => 'infostealers-cookies',
                    'title' => 'Info-stealers & Stolen Sessions',
                    'summary' => 'Malware that steals browser cookies and saved passwords.',
                    'sort_order' => 7,
                    'content' => <<<'HTML'
<h2>Info-stealers and Stolen Sessions</h2>
<p>Modern stealers grab browser cookies so attackers can hijack logged-in sessions without your password.</p>
<div class="lesson-key-points"><strong>Reduce risk:</strong> avoid crack sites, keep browsers updated, use MFA, review active sessions, and do not save work passwords in untrusted browsers.</div>
<div class="case-study">
<h3>Real-life case: cracked software bundle</h3>
<p>A “free” design suite from a pirate site installed an info-stealer. Within hours, social and email sessions were hijacked. Paying for legitimate software is cheaper than account recovery.</p>
</div>
HTML
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
                    'slug' => 'bec-case-studies',
                    'title' => 'Case Studies: Business Email Compromise',
                    'summary' => 'How fake invoices and CEO fraud drain organisations.',
                    'sort_order' => 5,
                    'content' => <<<'HTML'
<h2>Business Email Compromise Cases</h2>
<p>BEC remains one of the costliest cybercrime categories globally because it abuses trust and payment processes — often without malware.</p>
<div class="case-study">
<h3>Real-life pattern: supplier payment switch</h3>
<p>Finance receives an email “from” a known supplier with new bank details. The display name matches; the domain is off by one letter. Funds are wired to criminals. <strong>Control:</strong> verify bank changes by phone using a number already on file — not the number in the email.</p>
</div>
<div class="case-study">
<h3>Campus pattern: “dean needs airtime / fees”</h3>
<p>Students receive messages claiming a senior staff member urgently needs mobile money. Real names are harvested from social media. Always verify on an official channel.</p>
</div>
HTML
                ],
                [
                    'slug' => 'romance-and-job-scams',
                    'title' => 'Romance, Job & Scholarship Scams',
                    'summary' => 'Long-con social engineering targeting money and data.',
                    'sort_order' => 6,
                    'content' => <<<'HTML'
<h2>Romance, Job, and Scholarship Scams</h2>
<p>Attackers build trust over weeks, then request fees, gift cards, or “processing payments,” or steal identity documents for fraud.</p>
<ul>
<li>Never pay to receive a salary or scholarship</li>
<li>Video-call to verify remote “recruiters”</li>
<li>Do not send ID scans to unverified contacts</li>
</ul>
<div class="case-study">
<h3>Real-life case: fake internship offer</h3>
<p>Graduates were asked to pay “visa placement fees” to a polished website. No company existed. Reverse-image checks and official career-office verification would have stopped the loss.</p>
</div>
HTML
                ],
                [
                    'slug' => 'tech-support-scams',
                    'title' => 'Tech Support Scams',
                    'summary' => 'Pop-ups and cold calls that demand remote access.',
                    'sort_order' => 7,
                    'content' => <<<'HTML'
<h2>Tech Support Scams</h2>
<p>Full-screen browser warnings claim “your PC is infected — call this number.” Scammers then request remote access and payment.</p>
<div class="case-study">
<h3>Real-life case: remote access theft</h3>
<p>Victims who allowed remote tools watched scammers open bank sites and drain accounts. <strong>Rule:</strong> close the browser/tab, do not call numbers from pop-ups, and contact ICT or your vendor via official support pages.</p>
</div>
HTML
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
                    'slug' => 'dns-and-typosquatting',
                    'title' => 'Typosquatting & Fake Domains',
                    'summary' => 'How one wrong letter sends you to an attacker site.',
                    'sort_order' => 4,
                    'content' => <<<'HTML'
<h2>Typosquatting and Fake Domains</h2>
<p>Attackers register lookalike domains hoping you mistype or click too fast.</p>
<div class="case-study">
<h3>Real-life case: one-letter bank domain</h3>
<p>Users typing a bank URL with a common misspelling landed on a cloned site that harvested cards. Bookmarks and official apps remove typing risk.</p>
</div>
HTML
                ],
                [
                    'slug' => 'https-myths',
                    'title' => 'HTTPS Myths Debunked',
                    'summary' => 'Encryption is not the same as trustworthiness.',
                    'sort_order' => 5,
                    'content' => <<<'HTML'
<h2>HTTPS Myths</h2>
<p>Many phishing sites now use free HTTPS certificates. A padlock means the connection is encrypted to <em>that</em> site — not that the site is your real bank.</p>
<div class="case-study">
<h3>Real-life case: “secure” phishing shop</h3>
<p>Investigators regularly find phishing kits served over HTTPS. Victims felt safe because of the lock icon. Checking the domain name matters more than the icon.</p>
</div>
HTML
                ],
                [
                    'slug' => 'travel-wifi-playbook',
                    'title' => 'Travel & Hotel Wi-Fi Playbook',
                    'summary' => 'Practical steps for airports, buses, and hotels.',
                    'sort_order' => 6,
                    'content' => <<<'HTML'
<h2>Travel Wi-Fi Playbook</h2>
<ol>
<li>Confirm the exact network name with staff</li>
<li>Prefer personal hotspot for banking</li>
<li>Use institutional VPN when required</li>
<li>Forget the network afterward</li>
<li>Avoid software updates on untrusted portals when possible</li>
</ol>
<div class="case-study">
<h3>Real-life case: evil twin at a conference</h3>
<p>Researchers (and criminals) have run hotspots named like the venue Wi-Fi. Attendees joined and exposed sessions. Asking staff for the SSID spelling is a simple defence.</p>
</div>
HTML
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
                    'slug' => 'thread-hijacking',
                    'title' => 'Thread Hijacking After Mailbox Theft',
                    'summary' => 'When attackers reply inside real conversations.',
                    'sort_order' => 4,
                    'content' => <<<'HTML'
<h2>Thread Hijacking</h2>
<p>After stealing a mailbox, attackers reply inside existing threads (“Updated invoice attached”) so the message looks expected.</p>
<div class="case-study">
<h3>Real-life case: silent invoice swap</h3>
<p>A company paid a fraudulent invoice inserted into a long vendor thread. Tone and history matched. Out-of-band confirmation of bank details would have blocked the loss.</p>
</div>
HTML
                ],
                [
                    'slug' => 'secure-attachments-habits',
                    'title' => 'Attachments, Macros & Safe Sharing',
                    'summary' => 'Office macros, HTML attachments, and safer alternatives.',
                    'sort_order' => 5,
                    'content' => <<<'HTML'
<h2>Attachments and Macros</h2>
<p>Macro-enabled Office files and HTML attachments remain common malware loaders. Prefer cloud links from approved platforms and confirm unexpected files by chat/call on a known channel.</p>
<div class="case-study">
<h3>Real-life case: “resume.docm”</h3>
<p>Recruiters opening unsolicited macro résumés have infected HR networks. Policy: open untrusted résumés in safe viewers or sandboxes; never enable macros by default.</p>
</div>
HTML
                ],
                [
                    'slug' => 'messaging-app-risks',
                    'title' => 'WhatsApp & Chat App Risks',
                    'summary' => 'Forwarded virus myths, account takeovers, and OTP theft.',
                    'sort_order' => 6,
                    'content' => <<<'HTML'
<h2>Messaging App Risks</h2>
<ul>
<li>OTP codes sent to chat = account theft</li>
<li>Fake “from ICT” voices notes asking for remote access</li>
<li>Forwarded APK files in groups</li>
</ul>
<div class="case-study">
<h3>Real-life case: WhatsApp takeover</h3>
<p>Victims shared a 6-digit code “to activate a video call feature.” Attackers registered the account on another phone and messaged contacts for money. <strong>Never share chat OTPs.</strong></p>
</div>
HTML
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
                    'slug' => 'identity-theft-basics',
                    'title' => 'Identity Theft Basics',
                    'summary' => 'How stolen IDs and documents become fraud.',
                    'sort_order' => 4,
                    'content' => <<<'HTML'
<h2>Identity Theft Basics</h2>
<p>Stolen national IDs, student cards, and selfies-with-ID are sold or used to open accounts and take loans.</p>
<div class="case-study">
<h3>Real-life case: “KYC selfie” scam</h3>
<p>Fraudsters posing as employers asked applicants for a selfie holding their ID “for verification,” then used the images for mobile-money KYC fraud. Legitimate employers do not usually demand this via random chat.</p>
</div>
HTML
                ],
                [
                    'slug' => 'gdpr-style-principles',
                    'title' => 'Privacy Principles for Learners',
                    'summary' => 'Minimise, purpose limitation, and secure handling — in plain language.',
                    'sort_order' => 5,
                    'content' => <<<'HTML'
<h2>Privacy Principles (Plain Language)</h2>
<ul>
<li>Collect only what you need</li>
<li>Use data only for the stated purpose</li>
<li>Store on approved systems</li>
<li>Delete or return data when finished (per policy)</li>
<li>Report breaches quickly</li>
</ul>
<div class="case-study">
<h3>Real-life case: emailed class list</h3>
<p>A lecturer accidentally CC’d an external mailing list with student numbers and grades. Rapid reporting limited further forwarding and supported notification duties.</p>
</div>
HTML
                ],
                [
                    'slug' => 'social-media-oversharing',
                    'title' => 'Social Media Oversharing',
                    'summary' => 'Travel plans, badges, and exam details as attacker fuel.',
                    'sort_order' => 6,
                    'content' => <<<'HTML'
<h2>Social Media Oversharing</h2>
<p>Public posts about being away, desk badges, or “exam tomorrow on portal X” help attackers time phishing and physical theft.</p>
<div class="case-study">
<h3>Real-life case: vacation burglary pattern</h3>
<p>Thieves monitored social posts announcing travel dates. Digitally, the same idea applies: announcing “out of office with full access delegated” can invite BEC against your team.</p>
</div>
HTML
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
                    'slug' => 'incident-timeline-thinking',
                    'title' => 'Thinking in Incident Timelines',
                    'summary' => 'What happened, when, what was accessed — why detail matters.',
                    'sort_order' => 4,
                    'content' => <<<'HTML'
<h2>Thinking in Incident Timelines</h2>
<p>Responders need a clear timeline: when you clicked, what you typed, which device, and what you noticed next.</p>
<div class="case-study">
<h3>Real-life case: delayed report</h3>
<p>An employee waited three days after entering a password on a fake VPN page. Attackers created inbox rules to hide alerts. Earlier reporting would have cut mailbox abuse sooner.</p>
</div>
HTML
                ],
                [
                    'slug' => 'cyber-ethics-awareness',
                    'title' => 'Cyber Ethics & Responsible Use',
                    'summary' => 'Legal and ethical boundaries for campus computing.',
                    'sort_order' => 5,
                    'content' => <<<'HTML'
<h2>Cyber Ethics and Responsible Use</h2>
<p>Scanning, password guessing, or accessing others’ accounts “for practice” on live systems without written authorisation is unethical and often illegal.</p>
<div class="case-study">
<h3>Real-life case: “curious” student access</h3>
<p>Students have faced disciplinary action for accessing open lab shares containing others’ coursework. Curiosity is not consent. Use approved labs and CTF platforms for learning offensive techniques.</p>
</div>
HTML
                ],
                [
                    'slug' => 'backup-3-2-1-awareness',
                    'title' => 'Backup Thinking (3-2-1 Awareness)',
                    'summary' => 'Simple backup ideas that survive ransomware and loss.',
                    'sort_order' => 6,
                    'content' => <<<'HTML'
<h2>Backup Thinking (3-2-1 Awareness)</h2>
<p>A common guideline: keep <strong>3</strong> copies, on <strong>2</strong> different media, with <strong>1</strong> offsite/offline. For students: cloud + local copy of thesis drafts is a practical start.</p>
<div class="case-study">
<h3>Real-life case: laptop theft before submission</h3>
<p>Final-year projects vanished with stolen laptops when the only copy lived on the device. Students with cloud version history recovered overnight.</p>
</div>
HTML
                ],
            ],
        ],
        [
            'slug' => 'identity-fraud-awareness',
            'title' => 'Identity Theft & Online Fraud',
            'description' => 'Recognise identity theft, financial fraud patterns, and how to respond.',
            'difficulty' => 'intermediate',
            'sort_order' => 9,
            'lessons' => [
                [
                    'slug' => 'how-identity-theft-works',
                    'title' => 'How Identity Theft Works',
                    'summary' => 'Data gathering, account opening, and fraud monetisation.',
                    'sort_order' => 1,
                    'content' => <<<'HTML'
<h2>How Identity Theft Works</h2>
<ol>
<li>Collect personal data (phish, breach, oversharing)</li>
<li>Open accounts or take over existing ones</li>
<li>Borrow money, launder funds, or sell the identity pack</li>
</ol>
<div class="case-study">
<h3>Real-life case: synthetic identity</h3>
<p>Fraudsters combine real national IDs with fake details to pass weak checks. Protecting your ID documents and monitoring unexplained credit activity reduces impact.</p>
</div>
HTML
                ],
                [
                    'slug' => 'mobile-money-fraud',
                    'title' => 'Mobile Money & Payment Fraud',
                    'summary' => 'PIN sharing, fake agents, and reversal scams.',
                    'sort_order' => 2,
                    'content' => <<<'HTML'
<h2>Mobile Money and Payment Fraud</h2>
<ul>
<li>Never share mobile-money PINs or OTPs</li>
<li>Confirm agent till numbers from official lists</li>
<li>Be wary of “send money then I reverse” tricks</li>
</ul>
<div class="case-study">
<h3>Real-life case: fake reversal</h3>
<p>A buyer claimed they sent money by mistake and asked the seller to reverse before the fake pending SMS “cleared.” The seller lost goods and cash. Wait for real confirmed credit in your official app.</p>
</div>
HTML
                ],
                [
                    'slug' => 'what-to-do-after-fraud',
                    'title' => 'What to Do After Suspected Fraud',
                    'summary' => 'Freeze, report, reset, and document.',
                    'sort_order' => 3,
                    'content' => <<<'HTML'
<h2>What to Do After Suspected Fraud</h2>
<ol>
<li>Contact your bank / mobile-money provider immediately</li>
<li>Change passwords and enable MFA from a safe device</li>
<li>Report to ICT if a work/school account was involved</li>
<li>Keep screenshots and reference numbers</li>
<li>Watch for follow-on phishing claiming to “help recover funds”</li>
</ol>
<div class="case-study">
<h3>Real-life case: recovery scammers</h3>
<p>After theft, victims were contacted by fake “cybercrime recovery agents” demanding more fees. Official channels never ask you to pay criminals to recover money via gift cards.</p>
</div>
HTML
                ],
                [
                    'slug' => 'card-skimming-awareness',
                    'title' => 'Card Skimming & ATM Awareness',
                    'summary' => 'Physical and digital card theft basics.',
                    'sort_order' => 4,
                    'content' => <<<'HTML'
<h2>Card Skimming and ATM Awareness</h2>
<p>Skimmers capture card data at ATMs or POS terminals. Shield your PIN, avoid odd devices attached to card slots, and prefer indoor bank ATMs when possible.</p>
<div class="case-study">
<h3>Real-life case: overlay skimmer</h3>
<p>Police reports worldwide describe fake keypad overlays and camera clips on ATMs. Users who noticed loose parts and used another machine avoided cloned cards.</p>
</div>
HTML
                ],
            ],
        ],
    ];
}

$pdo = db();
$pack = cams_deep_curriculum();
$lessonCount = 0;
$moduleCount = 0;

foreach ($pack as $mod) {
    $mid = cams_upsert_module($pdo, $mod);
    $moduleCount++;
    foreach ($mod['lessons'] as $lesson) {
        cams_upsert_lesson($pdo, $mid, $lesson);
        $lessonCount++;
    }
}

// Ensure new modules get practice/module quizzes if missing
require_once __DIR__ . '/question_bank_generator.php';
foreach (['computer-security-basics', 'identity-fraud-awareness'] as $slug) {
    $m = $pdo->prepare('SELECT id, title FROM modules WHERE slug = ?');
    $m->execute([$slug]);
    $row = $m->fetch();
    if (!$row) {
        continue;
    }
    $mid = (int) $row['id'];
    foreach (
        [
            ['Practice Drill', 'practice', 60, 10],
            ['Module Quiz', 'module', 70, 3],
        ] as [$suffix, $type, $pass, $max]
    ) {
        $title = $row['title'] . ' — ' . $suffix;
        $find = $pdo->prepare('SELECT id FROM quizzes WHERE module_id = ? AND title = ?');
        $find->execute([$mid, $title]);
        if ($find->fetchColumn()) {
            continue;
        }
        $pdo->prepare(
            'INSERT INTO quizzes (module_id, title, description, quiz_type, pass_mark, max_attempts, questions_to_draw, is_active)
             VALUES (?,?,?,?,?,?,?,1)'
        )->execute([
            $mid,
            $title,
            $type === 'practice' ? 'Practice questions for this topic.' : 'Module assessment.',
            $type,
            $pass,
            $max,
            $type === 'practice' ? 20 : 25,
        ]);
    }
}

echo "Deep lessons applied.\nModules touched: {$moduleCount}\nLessons upserted: {$lessonCount}\n";

$stats = $pdo->query(
    'SELECT m.title, COUNT(l.id) AS c FROM modules m
     LEFT JOIN lessons l ON l.module_id = m.id AND l.is_active = 1
     WHERE m.is_active = 1 GROUP BY m.id ORDER BY m.sort_order'
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($stats as $s) {
    echo "  {$s['title']}: {$s['c']} lessons\n";
}
