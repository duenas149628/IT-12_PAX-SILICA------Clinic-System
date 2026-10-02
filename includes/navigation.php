<?php
$currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$userRole = (string) ($_SESSION['role'] ?? '');
$isStaff = $userRole === 'staff';
$patientPrefix = str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/staff/') ? '../patient/' : '';
$pendingAppointmentCount = 0;
if ($isStaff && isset($pdo) && $pdo instanceof PDO) {
    $pendingAppointmentCount = (int) $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn();
}
$navigationItems = $isStaff
    ? [
        ['Dashboard', 'dashboard.php', 'fa-gauge-high', ['dashboard.php']],
        ['Patients', 'patients.php', 'fa-users', ['patients.php', 'patient_create.php', 'patient_view.php', 'patient_edit.php']],
        ['Appointments', 'appointments.php', 'fa-calendar-check', ['appointments.php', 'appointment_view.php', 'appointment_requests.php', 'appointment_create.php']],
        ['Visit Records', 'visit_records.php', 'fa-file-medical', ['visit_records.php', 'visit_create.php', 'visit_edit.php', 'visit_view.php']],
    ]
    : [
        ['Dashboard', $patientPrefix . 'dashboard.php', 'fa-gauge-high', ['dashboard.php']],
        ['Profile', $patientPrefix . 'profile.php', 'fa-user', ['profile.php']],
        ['Appointments', $patientPrefix . 'appointments.php', 'fa-calendar-check', ['appointments.php', 'request.php']],
        ['Records', $patientPrefix . 'visit_history.php', 'fa-file-medical', ['visit_history.php']],
    ];

$brandTarget = $isStaff ? 'dashboard.php' : $patientPrefix . 'dashboard.php';
$moreItems = [
    ['Reports', 'reports.php', 'fa-chart-column', ['reports.php']],
    ['Staff accounts', 'accounts.php', 'fa-user-shield', ['accounts.php']],
    ['Activity log', 'activity_log.php', 'fa-list-check', ['activity_log.php']],
];
$moreIsActive = $isStaff && in_array($currentPage, ['reports.php', 'accounts.php', 'activity_log.php'], true);
$changePasswordTarget = '../auth/password_change.php';
?>
<style>
    @import url("https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css");
    header.header:not(.app-navigation), header.topbar, .page > .topbar { display: none !important; }
    .app-navigation {
        position: sticky; top: 0; z-index: 1000; min-height: 75px; display: flex; align-items: center;
        justify-content: space-between; gap: 18px; padding: 12px 30px; background: rgba(255,255,255,.97);
        box-shadow: 0 3px 15px rgba(0,0,0,.15);
    }
    .app-navigation .app-brand { display: inline-flex; align-items: center; flex: 0 0 auto; text-decoration: none; }
    .app-navigation .app-brand-logo { display:block; width:150px; height:76px; object-fit:contain; }
    .app-navigation .app-nav-links { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 5px; }
    .app-navigation .app-nav-links a,
    .app-navigation .app-nav-links summary { display: inline-flex; align-items: center; gap: 7px; padding: 10px 12px; border: 0; border-radius: 8px; color: #555; font: 14px Arial, Helvetica, sans-serif; text-decoration: none; white-space: nowrap; transition: background .2s, color .2s; cursor: pointer; list-style: none; }
    .app-navigation .app-nav-links summary::-webkit-details-marker { display: none; }
    .app-navigation .app-nav-links a:hover,
    .app-navigation .app-nav-links a[aria-current="page"],
    .app-navigation .app-nav-links summary:hover,
    .app-navigation .app-nav-links details[open] > summary,
    .app-navigation .app-nav-links summary.is-active { background: #e6f7fa; color: #167b8c; }
    .app-navigation .app-nav-links a[aria-current="page"], .app-navigation .app-nav-links summary.is-active { font-weight: 700; }
    .app-navigation .app-nav-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 21px; height: 21px; padding: 0 6px; border-radius: 999px; background: #f5c451; color: #593d00; font: 700 11px Arial, Helvetica, sans-serif; line-height: 1; }
    .app-navigation .app-nav-dropdown { position: relative; }
    .app-navigation .app-dropdown-menu { position: absolute; top: calc(100% + 7px); right: 0; z-index: 1001; min-width: 190px; padding: 6px; border: 1px solid #e2ecee; border-radius: 10px; background: #fff; box-shadow: 0 10px 25px rgba(21,62,70,.14); }
    .app-navigation .app-dropdown-menu a { display: flex; width: 100%; }
    .app-navigation .app-nav-links a.app-nav-logout { margin-left: 3px; background: #167b8c; color: #fff; }
    .app-navigation .app-nav-links a.app-nav-logout:hover { background: #126c79; }
    @media (max-width: 1050px) {
        .app-navigation { align-items: flex-start; flex-direction: column; gap: 8px; padding: 12px 20px; }
        .app-navigation .app-nav-links { justify-content: flex-start; }
    }
    @media (max-width: 600px) {
        .app-navigation { padding: 12px 14px; }
        .app-navigation .app-brand-logo { width:122px; height:62px; }
        .app-navigation .app-nav-links { width: 100%; gap: 3px; }
        .app-navigation .app-nav-links a, .app-navigation .app-nav-links summary { padding: 9px; font-size: 13px; }
        .app-navigation .app-dropdown-menu { left: 0; right: auto; }
    }
</style>
<header class="app-navigation">
    <a class="app-brand" href="<?= h($brandTarget) ?>">
        <img class="app-brand-logo" src="../assets/tooth-lounge-logo.png" alt="The Tooth Lounge Co.">
    </a>
    <nav class="app-nav-links" aria-label="<?= $isStaff ? 'Staff' : 'Patient' ?> navigation">
        <?php foreach ($navigationItems as [$label, $href, $icon, $activePages]): ?>
            <a href="<?= h($href) ?>" <?= in_array($currentPage, $activePages, true) ? 'aria-current="page"' : '' ?>>
                <i class="fa-solid <?= h($icon) ?>" aria-hidden="true"></i><?= h($label) ?>
                <?php if ($isStaff && $label === 'Appointments' && $pendingAppointmentCount > 0): ?>
                    <span class="app-nav-badge" title="Pending appointment requests requiring attention" aria-label="<?= h($pendingAppointmentCount) ?> pending appointment requests requiring staff attention"><?= h($pendingAppointmentCount) ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
        <?php if ($isStaff): ?>
            <details class="app-nav-dropdown">
                <summary class="<?= $moreIsActive ? 'is-active' : '' ?>"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i>More <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                <div class="app-dropdown-menu">
                    <?php foreach ($moreItems as [$label, $href, $icon, $activePages]): ?>
                        <a href="<?= h($href) ?>" <?= in_array($currentPage, $activePages, true) ? 'aria-current="page"' : '' ?>>
                            <i class="fa-solid <?= h($icon) ?>" aria-hidden="true"></i><?= h($label) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>
        <details class="app-nav-dropdown">
            <summary><i class="fa-solid fa-user-circle" aria-hidden="true"></i>Account <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
            <div class="app-dropdown-menu">
                <a href="<?= h($changePasswordTarget) ?>"><i class="fa-solid fa-key" aria-hidden="true"></i>Change password</a>
                <a class="app-nav-logout" href="../auth/logout.php"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>Logout</a>
            </div>
        </details>
    </nav>
</header>
