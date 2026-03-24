<?php
// pages/landing.php
if (is_authenticated()) {
    $user = get_current_user();
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
  <title>Вход — ИЯ для теплоэнергетиков</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/static/css/style.css">
</head>
<body>
<div class="landing-wrapper">
  <!-- Левая часть — информация о курсе -->
  <div class="landing-left">
    <div class="brand-badge">🔥 Теплоэнергетика · Иностранный язык</div>
    <h1>Профессиональный<br>иностранный язык<br>для теплоэнергетиков</h1>
    <p>Образовательный онлайн-портал для освоения профессиональной иноязычной коммуникации в сфере теплоэнергетики.</p>
    <div class="landing-features">
      <div class="feature-item">
        <span class="ficon">📚</span>
        <span>Учебные материалы, видео и аудио по специальности</span>
      </div>
      <div class="feature-item">
        <span class="ficon">📝</span>
        <span>Еженедельные задания с проверкой преподавателем</span>
      </div>
      <div class="feature-item">
        <span class="ficon">🧪</span>
        <span>Тесты с автоматической проверкой результатов</span>
      </div>
      <div class="feature-item">
        <span class="ficon">📊</span>
        <span>Оценки и прогресс выполнения курса</span>
      </div>
    </div>
  </div>

  <!-- Правая часть — форма входа -->
  <div class="landing-right">
    <div class="login-box">
      <h2>Добро пожаловать</h2>
      <p class="subtitle">Войдите в свою учётную запись</p>

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
          <label class="form-label">Логин</label>
          <input type="text" name="username" class="form-control" placeholder="Введите логин" required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label">Пароль</label>
          <input type="password" name="password" class="form-control" placeholder="Введите пароль" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px">
          Войти →
        </button>
      </form>

      <p style="text-align:center;margin-top:20px;font-size:.85rem;color:var(--muted)">
        Нет учётной записи?
        <a href="index.php?route=register" style="color:var(--primary);font-weight:600">Зарегистрироваться</a>
      </p>
      <p style="text-align:center;margin-top:10px;font-size:.78rem;color:var(--muted)">
        Доступ также предоставляется администратором
      </p>
    </div>
  </div>
</div>
</body>
</html>
