<?php
// pages/landing.php
if (is_authenticated()) {
    $user = get_logged_in_user();
    if (in_array($user['role'], ['teacher', 'admin'])) {
        header("Location: index.php?route=admin_dashboard");
    } else {
        header("Location: index.php?route=dashboard");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= __('login_page_title') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" type="image/svg+xml" href="/static/favicon.svg">
  <link rel="stylesheet" href="/static/css/style.css">
</head>
<body>
<div class="landing-wrapper">
  <!-- Lang switcher for landing -->
  <div style="position:absolute;top:30px;right:40px;display:flex;gap:20px;z-index:100;font-weight:700;font-size:0.9rem">
    <a href="index.php?route=set_lang&lang=ru" style="color:<?= $_SESSION['lang'] == 'ru' ? 'var(--primary)' : '#fff' ?>;text-decoration:none">RU</a>
    <a href="index.php?route=set_lang&lang=kk" style="color:<?= $_SESSION['lang'] == 'kk' ? 'var(--primary)' : '#fff' ?>;text-decoration:none">KK</a>
    <a href="index.php?route=set_lang&lang=en" style="color:<?= $_SESSION['lang'] == 'en' ? 'var(--primary)' : '#fff' ?>;text-decoration:none">EN</a>
  </div>

  <!-- Левая часть — информация о курсе -->
  <div class="landing-left">
    <div class="brand-badge"><?= __('landing_badge') ?></div>
    <h1><?= __('landing_h1') ?></h1>
    <p><?= __('landing_p') ?></p>
    <div class="landing-features">
      <div class="feature-item">
        <span class="ficon">📚</span>
        <span><?= __('feature_1') ?></span>
      </div>
      <div class="feature-item">
        <span class="ficon">📝</span>
        <span><?= __('feature_2') ?></span>
      </div>
      <div class="feature-item">
        <span class="ficon">🧪</span>
        <span><?= __('feature_3') ?></span>
      </div>
      <div class="feature-item">
        <span class="ficon">📊</span>
        <span><?= __('feature_4') ?></span>
      </div>
    </div>
  </div>

  <!-- Правая часть — форма входа -->
  <div class="landing-right">
    <div class="login-box">
      <h2><?= __('welcome') ?></h2>
      <p class="subtitle"><?= __('login_subtitle') ?></p>

      <?php if (isset($_SESSION['flash'])): ?>
        <?php foreach ($_SESSION['flash'] as $f): ?>
          <div class="alert alert-<?= $f['type'] ?>">
            <?= $f['icon'] ?> <?= htmlspecialchars($f['message']) ?>
          </div>
        <?php endforeach; ?>
        <?php unset($_SESSION['flash']); ?>
      <?php endif; ?>

      <form method="POST" action="index.php?route=login">
        <div class="form-group">
          <label class="form-label"><?= __('username') ?></label>
          <input type="text" name="username" class="form-control" placeholder="<?= __('username') ?>" required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label"><?= __('password') ?></label>
          <input type="password" name="password" class="form-control" placeholder="<?= __('password') ?>" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px">
          <?= __('login_btn') ?> →
        </button>
      </form>

      <p style="text-align:center;margin-top:20px;font-size:.85rem;color:var(--muted)">
        <?= __('no_account') ?>
        <a href="index.php?route=register" style="color:var(--primary);font-weight:600"><?= __('register') ?></a>
      </p>
    </div>
  </div>
</div>
</body>
</html>
