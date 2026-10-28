<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Overview') ?> · <?= e(school()['school_name']) ?></title>
    <script src="<?= e(asset('js/theme.js')) ?>"></script>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/theme.css')) ?>">
    <link rel="icon" href="<?= e(school_favicon()) ?>">
</head>
<body>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= e(url()) ?>"><span class="brand-icon <?= !empty(school()['logo_path'])?'has-logo':'' ?>"><?= school_logo() ?></span><span><?= e(school()['school_name']) ?><small>STUDENT MANAGEMENT</small></span></a>
    <div class="workspace"><span class="workspace-icon"><?= icon('school') ?></span><div>School workspace<small>Academic year <?= e(school()['school_year']) ?></small></div><span class="online"></span></div>
    <p class="nav-label">WORKSPACE</p>
    <nav aria-label="Main navigation"><?php foreach ([['','grid','Overview','dashboard'],['students','students','Students','students'],['teachers','students','Teachers','teachers'],['classes','book','Classes','classes'],['attendance','calendar','Attendance','attendance'],['fees','wallet','Fees & payments','fees'],['results','chart','Exam results','results']] as [$href,$ico,$label,$key]): ?><a class="<?= ($page??'')===$key?'selected':'' ?>" href="<?= e(url($href)) ?>"><?= icon($ico) ?><span><?= e($label) ?></span><?= ($page??'')===$key?'<span class="nav-dot"></span>':'' ?></a><?php endforeach ?></nav>
    <p class="nav-label">MANAGE</p><nav><a class="<?= ($page??'')==='reports'?'selected':'' ?>" href="<?= e(url('reports')) ?>"><?= icon('chart') ?>Reports & exports</a><a class="<?= ($page??'')==='settings'?'selected':'' ?>" href="<?= e(url('settings')) ?>"><?= icon('settings') ?>School settings</a></nav>
    <div class="sidebar-note"><span class="note-spark">✧</span><strong>A little more organized.</strong><p>More time for what matters.<br>Less time on paperwork.</p></div>
    <div class="profile"><span class="avatar"><?= e(initials($_SESSION['user']['name'])) ?></span><div><strong><?= e($_SESSION['user']['name']) ?></strong><small>School administrator</small></div><form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="icon-button" aria-label="Sign out" title="Sign out"><?= icon('logout') ?></button></form></div>
</aside>
<div class="app-shell"><header class="topbar"><div class="breadcrumb"><button class="icon-button mobile-menu" aria-label="Toggle navigation" data-menu>☰</button><span>Workspace</span><span class="separator">/</span><strong><?= e($title) ?></strong></div><div class="topbar-right"><?php require BASE_PATH.'/app/Views/theme-toggle.php'; ?><span class="year-badge"><span class="online"></span> <?= e(school()['school_year']) ?></span><span class="today"><?= date('D, d M Y') ?></span></div></header>
<main class="main-content"><?php if ($flash=$_SESSION['flash']??null): unset($_SESSION['flash']); ?><div class="flash <?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endif ?><?php if (!empty($_SESSION['errors'])): ?><div class="flash error" role="alert"><?= e(implode(' ', $_SESSION['errors'])) ?></div><?php endif ?><?= $content ?></main>
<footer class="footer"><span><?= e(school()['school_name']) ?> <span class="muted">· A brighter future, together.</span></span><span>School management, simplified.</span></footer></div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
