<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
ensure_access_schema();
require_login();

refresh_session_user();
$u = current_user();
$id = (int) $u['id'];

$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    logout_user();
    redirect('auth/login.php');
}

if ((int) ($row['profile_complete'] ?? 0) === 1 && empty($row['must_change_password'])) {
    if (($row['approval_status'] ?? '') === 'approved' || $row['role'] === 'admin') {
        redirect(role_home($row['role']));
    }
    redirect('auth/pending-approval.php');
}

$inviteToken = (string) ($_SESSION['invite_token'] ?? $_GET['invite'] ?? '');
$invite = $inviteToken !== '' ? find_valid_invitation($inviteToken) : null;
$modules = db()->query('SELECT id, title FROM modules WHERE is_active = 1 ORDER BY sort_order, title')->fetchAll();

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['full_name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $organisation = trim((string) ($_POST['organisation'] ?? ''));
    $department = trim((string) ($_POST['department'] ?? ''));
    $accountType = (string) ($_POST['account_type'] ?? 'individual');
    $courseIds = array_map('intval', $_POST['courses'] ?? []);
    $inviteToken = (string) ($_POST['invite_token'] ?? $inviteToken);
    $invite = $inviteToken !== '' ? find_valid_invitation($inviteToken) : null;

    // Invited lecturers/students locked to invite role
    if ($invite) {
        $accountType = $invite['role'] === 'lecturer' ? 'lecturer' : 'student';
        if (strtolower((string) $invite['email']) !== strtolower((string) $row['email'])) {
            $error = 'This invitation was sent to a different email address.';
        }
    } elseif ($row['role'] === 'admin') {
        $accountType = 'admin';
    } elseif (!in_array($accountType, ['student', 'individual'], true)) {
        $error = 'Choose whether you are a student or an individual learner.';
    }

    if ($error === null && !profile_is_complete_payload($name, $phone, $organisation, $accountType === 'admin' ? 'admin' : $accountType)) {
        $error = 'Please fill in your full name, phone, organisation, and account type.';
    }

    if ($error === null && !$invite && $accountType === 'student' && $courseIds === []) {
        $error = 'Select at least one course. A lecturer and admin must approve your access.';
    }
    if ($error === null && !$invite && $accountType === 'individual' && $courseIds === []) {
        $error = 'Select the courses you want. An admin must approve your access.';
    }

    if ($error === null) {
        $role = $row['role'];
        $approval = $row['approval_status'] ?? 'pending';

        if ($invite) {
            $role = $invite['role'] === 'lecturer' ? 'lecturer' : 'user';
            $approval = 'approved';
            $courseIds = invitation_module_ids((int) $invite['id']);
        } elseif ($role === 'admin') {
            $approval = 'approved';
        } else {
            $role = 'user';
            $approval = 'pending';
        }

        db()->prepare(
            'UPDATE users SET full_name = ?, phone = ?, organisation = ?, department = ?, account_type = ?,
             role = ?, profile_complete = 1, approval_status = ?, consent_accepted = 1 WHERE id = ?'
        )->execute([$name, $phone, $organisation, $department, $accountType, $role, $approval, $id]);

        if ($invite) {
            grant_course_access($id, $courseIds, 'approved', 'invite', (int) ($invite['invited_by'] ?? 0) ?: null);
            mark_invitation_used((int) $invite['id'], $id);
            unset($_SESSION['invite_token']);
        } elseif ($accountType === 'student') {
            grant_course_access($id, $courseIds, 'pending_lecturer', 'self', null);
        } elseif ($accountType === 'individual') {
            grant_course_access($id, $courseIds, 'pending_admin', 'self', null);
        }

        log_activity($id, 'profile_complete', 'Profile completed as ' . $accountType);
        refresh_session_user();
        flash('success', 'Profile saved.');
        if ($approval !== 'approved') {
            redirect('auth/pending-approval.php');
        }
        redirect(role_home($role));
    }
}

$pageTitle = 'Complete profile';
$bodyClass = 'page-auth';
$authLayout = true;
require __DIR__ . '/../includes/header.php';
?>
<section class="auth-split" aria-label="Complete profile">
    <aside class="auth-split-visual">
        <img src="<?= e(APP_URL) ?>/assets/images/auth-login-panel.png" alt="" width="900" height="1600">
        <div class="auth-split-overlay"><div class="auth-split-copy">
            <p class="auth-split-brand">CAMS</p>
            <p class="auth-split-quote">Tell us who you are so we can grant the right courses.</p>
        </div></div>
    </aside>
    <div class="auth-split-panel"><div class="auth-split-card auth-split-card-wide">
        <p class="auth-kicker">Almost there</p>
        <h1>Complete your profile</h1>
        <p class="auth-sub">Required before you can use CAMS<?= $invite ? ' (invitation detected)' : '' ?>.</p>
        <?php foreach (get_flashes() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

        <form method="post" class="auth-form">
            <?= csrf_field() ?>
            <input type="hidden" name="invite_token" value="<?= e($inviteToken) ?>">
            <div class="form-group">
                <label for="full_name">Full name</label>
                <input type="text" id="full_name" name="full_name" required maxlength="120"
                       value="<?= e($_POST['full_name'] ?? $row['full_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" required maxlength="40"
                       value="<?= e($_POST['phone'] ?? $row['phone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="organisation">Organisation / school</label>
                <input type="text" id="organisation" name="organisation" required maxlength="160"
                       value="<?= e($_POST['organisation'] ?? $row['organisation'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="department">Department (optional)</label>
                <input type="text" id="department" name="department" maxlength="120"
                       value="<?= e($_POST['department'] ?? $row['department'] ?? '') ?>">
            </div>

            <?php if ($row['role'] !== 'admin' && !$invite): ?>
                <div class="form-group">
                    <label for="account_type">I am registering as</label>
                    <select id="account_type" name="account_type" required>
                        <option value="student" <?= (($_POST['account_type'] ?? '') === 'student') ? 'selected' : '' ?>>Student</option>
                        <option value="individual" <?= (($_POST['account_type'] ?? 'individual') === 'individual') ? 'selected' : '' ?>>Individual learner</option>
                    </select>
                </div>
                <fieldset class="form-group">
                    <legend>Courses requested</legend>
                    <p class="muted" style="margin:0 0 0.5rem;font-size:0.88rem;">
                        Students need lecturer then admin approval. Individuals need admin approval.
                    </p>
                    <?php foreach ($modules as $m): ?>
                        <label class="checkbox-row" style="display:flex;gap:0.5rem;align-items:center;margin:0.25rem 0;">
                            <input type="checkbox" name="courses[]" value="<?= (int) $m['id'] ?>"
                                <?= in_array((int) $m['id'], array_map('intval', $_POST['courses'] ?? []), true) ? 'checked' : '' ?>>
                            <span><?= e($m['title']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php elseif ($invite): ?>
                <div class="alert alert-info">
                    Invitation role: <strong><?= e($invite['role']) ?></strong>.
                    Courses from your invitation will be granted automatically after you save.
                </div>
            <?php endif; ?>

            <button class="btn btn-primary btn-block" type="submit">Save and continue</button>
        </form>
    </div></div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
