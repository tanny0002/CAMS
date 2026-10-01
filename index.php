<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/helpers.php';
start_app_session();

if (is_logged_in()) {
    post_login_redirect();
}

$pageTitle = 'Welcome';
$bodyClass = 'page-landing';
$landingLayout = true;
require __DIR__ . '/includes/header.php';

$img = static fn(string $file): string => e(APP_URL) . '/assets/images/' . $file;
?>

</div><!-- leave default container for full-bleed hero -->

<section class="landing-hero" aria-label="Welcome">
    <div class="landing-hero-grid">
        <div class="landing-hero-copy">
            <p class="landing-brand">CAMS</p>
            <h1>Cybersecurity awareness for African campuses</h1>
            <p class="landing-lead">
                Learn safer online habits through short lessons, quizzes, and progress tracking — built for students, staff, and trainers.
            </p>
            <div class="hero-actions">
                <a class="btn btn-primary btn-lg" href="<?= e(APP_URL) ?>/auth/register.php">Create free account</a>
                <a class="btn btn-outline btn-lg" href="<?= e(APP_URL) ?>/auth/login.php">Log in</a>
            </div>
        </div>
        <div class="landing-hero-media">
            <img
                src="<?= $img('hero-awareness.png') ?>"
                alt="African learner reviewing cybersecurity awareness content on a laptop"
                width="1600"
                height="900"
                fetchpriority="high"
            >
        </div>
    </div>
</section>

<div class="landing-strip">
    <div class="container landing-strip-inner">
        <div><strong>4</strong><span>core modules</span></div>
        <div><strong>8+</strong><span>guided lessons</span></div>
        <div><strong>3</strong><span>user roles</span></div>
        <div><strong>CSV</strong><span>report export</span></div>
    </div>
</div>

<div class="container landing-body">

    <section class="landing-section landing-why" data-reveal>
        <header class="landing-section-head">
            <h2>Why cybersecurity awareness matters</h2>
            <p>
                Most successful attacks do not start with advanced hacking tools. They start with people — a rushed
                click, a reused password, an urgent message that looks official, or a USB drive from an unknown source.
                Awareness training helps you pause, check, and choose a safer action.
            </p>
        </header>
        <div class="why-grid">
            <article>
                <div class="feature-head">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 3v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"/><path d="M9.5 12l1.8 1.8L15 10"/></svg>
                    </span>
                    <h3>Protect personal and campus accounts</h3>
                </div>
                <p>
                    Email, student portals, banking apps, and work systems are frequent targets. Knowing the warning
                    signs reduces the chance of account takeover, identity misuse, and lost academic or work data.
                </p>
            </article>
            <article>
                <div class="feature-head">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8"/><path d="M12 8v4l2.5 2.5"/><path d="M12 4v1.5M12 18.5V20M4 12H5.5M18.5 12H20"/></svg>
                    </span>
                    <h3>Build habits, not just theory</h3>
                </div>
                <p>
                    CAMS focuses on actions you can use the same day: hover before you click, verify unusual requests,
                    use unique passwords, and turn on multi-factor authentication where it is available.
                </p>
            </article>
            <article>
                <div class="feature-head">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="3"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </span>
                    <h3>Support institutional programmes</h3>
                </div>
                <p>
                    Lecturers and administrators can monitor completion, review quiz results, gather feedback, and
                    export CSV reports for awareness campaigns and academic evaluation.
                </p>
            </article>
        </div>
    </section>

    <section class="landing-section" data-reveal>
        <header class="landing-section-head">
            <h2>What you will learn</h2>
            <p>
                Four focused modules cover the threats people meet most often at school, at work, and on personal
                devices — written in clear language for everyday users, not only ICT specialists.
            </p>
        </header>
        <div class="topic-grid">
            <article class="topic-block">
                <figure class="topic-media">
                    <img src="<?= $img('topic-phishing.png') ?>" alt="African professional checking a suspicious email on a laptop" width="800" height="600" loading="lazy">
                </figure>
                <div class="topic-copy">
                    <h3>Phishing awareness</h3>
                    <p>
                        Learn how attackers use fake emails, SMS, and websites to steal passwords or money. Practise
                        spotting urgency, strange sender addresses, look-alike domains, and unsafe links before you
                        click, reply, or share personal details.
                    </p>
                </div>
            </article>
            <article class="topic-block">
                <figure class="topic-media">
                    <img src="<?= $img('topic-passwords.png') ?>" alt="African learner practising password security on a laptop" width="800" height="600" loading="lazy">
                </figure>
                <div class="topic-copy">
                    <h3>Password security</h3>
                    <p>
                        Understand why short or reused passwords fail, how longer passphrases help, and why
                        multi-factor authentication (MFA) still protects you when a password is stolen or leaked.
                    </p>
                </div>
            </article>
            <article class="topic-block">
                <figure class="topic-media">
                    <img src="<?= $img('topic-malware.png') ?>" alt="African ICT student learning malware awareness at a desk" width="800" height="600" loading="lazy">
                </figure>
                <div class="topic-copy">
                    <h3>Malware awareness</h3>
                    <p>
                        Recognise common malware types such as trojans, spyware, and ransomware. Build safer habits
                        around downloads, email attachments, USB drives, and keeping software updated.
                    </p>
                </div>
            </article>
            <article class="topic-block">
                <figure class="topic-media">
                    <img src="<?= $img('topic-social.png') ?>" alt="African colleagues verifying an unusual request together" width="800" height="600" loading="lazy">
                </figure>
                <div class="topic-copy">
                    <h3>Social engineering</h3>
                    <p>
                        See how attackers impersonate ICT support, banks, lecturers, or managers. Learn to pause,
                        verify through a known channel, and refuse pressure to skip normal security checks.
                    </p>
                </div>
            </article>
        </div>
    </section>

    <section class="landing-section" data-reveal>
        <header class="landing-section-head">
            <h2>How CAMS works</h2>
            <p>
                A clear path from your first lesson to a module certificate — with feedback and progress visible at
                every step so you always know what to do next.
            </p>
        </header>
        <ol class="path-steps">
            <li>
                <span class="path-num">1</span>
                <div>
                    <h3>Create an account and open a module</h3>
                    <p>
                        Register as a learner, then choose phishing, passwords, malware, or social engineering. Each
                        module groups related lessons so you can focus on one topic at a time.
                    </p>
                </div>
            </li>
            <li>
                <span class="path-num">2</span>
                <div>
                    <h3>Study lessons and mark them complete</h3>
                    <p>
                        Read short, practical lessons at your own pace. When you understand the key ideas, mark the
                        lesson complete so your progress updates automatically.
                    </p>
                </div>
            </li>
            <li>
                <span class="path-num">3</span>
                <div>
                    <h3>Take the quiz and earn recognition</h3>
                    <p>
                        Check your understanding with pass marks, limited attempts, and explanations after answers.
                        Finish module requirements to unlock a certificate and keep learning with security tips.
                    </p>
                </div>
            </li>
        </ol>
    </section>

    <section class="landing-section" data-reveal>
        <header class="landing-section-head">
            <h2>Built for your role</h2>
            <p>
                One platform that serves learners and the people who run awareness programmes in colleges, campuses,
                and organisations.
            </p>
        </header>
        <div class="role-rows">
            <div class="role-row">
                <div class="feature-head">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/></svg>
                    </span>
                    <h3>Learners</h3>
                </div>
                <p>
                    Register, complete training modules, take quizzes, read security tips, submit post-training
                    feedback, and view personal progress plus certificates.
                </p>
            </div>
            <div class="role-row">
                <div class="feature-head">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><path d="M8 8h8M8 12h5"/></svg>
                    </span>
                    <h3>Lecturers</h3>
                </div>
                <p>
                    Monitor awareness outcomes with progress and assessment views so you can support learners and
                    report on programme reach without managing every system setting.
                </p>
            </div>
            <div class="role-row">
                <div class="feature-head">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/><path d="M19 8v4M17 10h4"/></svg>
                    </span>
                    <h3>Administrators</h3>
                </div>
                <p>
                    Manage users, lessons, quizzes, and tips. Filter reports by date or result, deactivate accounts
                    when needed, and export CSV evidence for programme reporting.
                </p>
            </div>
        </div>
    </section>

    <section class="landing-section" data-reveal>
        <header class="landing-section-head">
            <h2>What is included</h2>
            <p>Everything you need for a structured awareness programme in one educational system.</p>
        </header>
        <div class="include-grid">
            <article>
                <div class="feature-head">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><path d="M8 7h8M8 11h6"/></svg>
                    </span>
                    <h3>Lessons & search</h3>
                </div>
                <p>Topic modules with readable lesson content and search so learners can find material quickly.</p>
            </article>
            <article>
                <div class="feature-head">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    </span>
                    <h3>Quizzes & feedback</h3>
                </div>
                <p>Multiple-choice assessments with pass marks, attempt limits, and explanations after each answer.</p>
            </article>
            <article>
                <div class="feature-head">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M8.2 13.2 7 22l5-2 5 2-1.2-8.8"/><path d="M10.5 8h3M12 6.5V9.5"/></svg>
                    </span>
                    <h3>Progress & certificates</h3>
                </div>
                <p>Completion tracking for lessons and quizzes, plus certificates when a module is finished.</p>
            </article>
            <article>
                <div class="feature-head">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4M12 18v4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M2 12h4M18 12h4M4.9 19.1l2.8-2.8M16.3 7.7l2.8-2.8"/><circle cx="12" cy="12" r="4"/></svg>
                    </span>
                    <h3>Security tips</h3>
                </div>
                <p>Short reminders learners can revisit after training to keep safe habits fresh.</p>
            </article>
            <article>
                <div class="feature-head">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6"/></svg>
                    </span>
                    <h3>Reports & CSV export</h3>
                </div>
                <p>Admin and lecturer views with filters so institutions can evidence awareness activity.</p>
            </article>
            <article>
                <div class="feature-head">
                    <span class="feature-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/><circle cx="12" cy="16" r="1.2"/></svg>
                    </span>
                    <h3>Account security basics</h3>
                </div>
                <p>Login sessions with timeout, CSRF protection, role-based access, email OTP verification, and password reset by email.</p>
            </article>
        </div>
    </section>

    <section class="landing-section" data-reveal>
        <header class="landing-section-head">
            <h2>Frequently asked questions</h2>
            <p>Quick answers for first-time users and project reviewers.</p>
        </header>
        <div class="faq-list">
            <details open>
                <summary>Who is CAMS for?</summary>
                <p>Students, staff, lecturers, and administrators who need structured cybersecurity awareness training without specialised security tools.</p>
            </details>
            <details>
                <summary>Do I need technical experience?</summary>
                <p>No. Lessons are written for everyday users. You only need a browser and an account to start.</p>
            </details>
            <details>
                <summary>Is this a live hacking or monitoring tool?</summary>
                <p>No. CAMS is an educational awareness system. It does not include penetration testing, malware development, or live network monitoring.</p>
            </details>
            <details>
                <summary>How do certificates work?</summary>
                <p>When you complete a module’s lesson and quiz requirements, CAMS can issue a module certificate you can view from your progress area.</p>
            </details>
        </div>
    </section>

    <section class="landing-cta" data-reveal>
        <h2>Ready to start training?</h2>
        <p>
            Create a free account and begin with phishing, password security, malware awareness, or social engineering.
            Already registered? Log in to continue where you left off and keep building safer online habits.
        </p>
        <div class="hero-actions">
            <a class="btn btn-primary btn-lg" href="<?= e(APP_URL) ?>/auth/register.php">Get started</a>
            <a class="btn btn-outline btn-lg" href="<?= e(APP_URL) ?>/auth/login.php">I already have an account</a>
        </div>
    </section>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
