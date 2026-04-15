<?php
// pages/register.php
if (is_authenticated()) {
    $user = get_logged_in_user();
    redirect_to_route(default_route_for_role($user['role'] ?? null));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= __('register') ?> - <?= SITE_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" type="image/svg+xml" href="/static/favicon.svg">
  <link rel="stylesheet" href="/static/css/style.css">
</head>
<body>
<div class="landing-wrapper">
  <div class="landing-left">
    <div class="brand-badge"><?= __('landing_badge') ?></div>
    <h1><?= __('register') ?></h1>
    <p>Создайте студенческую учётную запись, чтобы получить доступ к материалам курса, заданиям и тестам.</p>
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

  <div class="landing-right">
    <div class="login-box">
      <h2><?= __('register') ?></h2>
      <p class="subtitle">Заполните данные для создания аккаунта студента.</p>

      <?php if (isset($_SESSION['flash'])): ?>
        <?php foreach ($_SESSION['flash'] as $f): ?>
          <div class="alert alert-<?= $f['type'] ?>">
            <?= $f['icon'] ?> <?= htmlspecialchars($f['message']) ?>
          </div>
        <?php endforeach; ?>
        <?php unset($_SESSION['flash']); ?>
      <?php endif; ?>

      <form method="POST" action="index.php?route=register">
        <?= csrf_input() ?>
        <div class="form-group">
          <label class="form-label"><?= __('full_name') ?></label>
          <input type="text" name="full_name" class="form-control" placeholder="<?= __('full_name_placeholder') ?>" required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label"><?= __('username') ?></label>
          <input type="text" name="username" class="form-control" placeholder="<?= __('username_placeholder') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label"><?= __('email') ?></label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label"><?= __('password') ?></label>
          <input type="password" name="password" class="form-control" placeholder="Минимум 6 символов" minlength="6" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px">
          <?= __('register') ?>
        </button>
      </form>

      <p style="text-align:center;margin-top:20px;font-size:.85rem;color:var(--muted)">
        Уже есть учётная запись?
        <a href="index.php?route=landing" style="color:var(--primary);font-weight:600"><?= __('login') ?></a>
      </p>
    </div>
  </div>
</div>
</body>
</html>
