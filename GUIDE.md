# Полная инструкция по PHP-версии портала

## Запуск

Локально:

```bash
php -S localhost:8000
```

После запуска откройте:

- `http://localhost:8000/index.php`

На Apache/Nginx откройте корень проекта и используйте тот же `index.php`.

## Важное про базу и миграцию

- `storage/portal.db` создаётся автоматически при первом запуске
- если в корне проекта уже есть `portal.db`, приложение переносит данные в `storage/portal.db`
- схема таблиц создаётся из `bootstrap.php`
- стартовый курс и недели создаются автоматически, если база пустая
- стандартные пользователи создаются автоматически, если их нет
- старые Flask/Werkzeug-хеши для `admin`, `teacher`, `student1` автоматически переводятся в PHP-формат

Ручную миграцию не нужно запускать после каждого коммита. Это одноразовая история для старой базы.

## Стандартные аккаунты

| Роль | Логин | Пароль |
|---|---|---|
| Администратор | `admin` | `admin123` |
| Преподаватель | `teacher` | `teacher123` |
| Студент | `student1` | `student123` |

## Основные маршруты

### Публичные

- Вход: `index.php`
- Регистрация студента: `index.php?route=register`
- Выход: `index.php?route=logout`

### Студент

- Главная: `index.php?route=dashboard`
- Материалы: `index.php?route=materials`
- Задания: `index.php?route=assignments`
- Тесты: `index.php?route=tests`
- Оценки: `index.php?route=grades`

### Преподаватель / админ

- Дашборд: `index.php?route=admin_dashboard`
- Студенты: `index.php?route=admin_students`
- Проверка работ: `index.php?route=admin_review`
- Курс: `index.php?route=admin_course`
- Статистика: `index.php?route=admin_statistics`

### Только админ

- Пользователи: `index.php?route=admin_users`

## Как устроено создание пользователей

- Регистрация через публичную форму создаёт пользователя с ролью `student`
- Создание через админку позволяет выбрать `student`, `teacher` или `admin`
- Новые пользователи сразу записываются с `PHP password_hash(...)`

## Структура проекта

```text
bootstrap.php        инициализация схемы и сидов
config.php           подключение SQLite + bootstrap
auth.php             авторизация и сессии
index.php            роутер
pages/               PHP-страницы
static/              стили и статика
uploads/             загруженные файлы
storage/             рабочая база SQLite вне git
lang.php             переводы
portal.db            legacy база для переноса
```

## Legacy-хвосты

Если в репозитории ещё лежат старые Python/Flask-файлы, они больше не нужны для работы PHP-версии. Рабочий путь проекта теперь начинается с `index.php`.
