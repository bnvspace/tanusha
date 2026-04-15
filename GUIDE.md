# Полная инструкция по PHP-версии портала

## Запуск

Параметры подключения лежат в `.env`. При необходимости можно взять `.env.example` как шаблон и скорректировать значения под локальную базу.

Локальный запуск:

```powershell
php -S localhost:8000
```

После запуска откройте:

- `http://localhost:8000/index.php`

На Apache/Nginx откройте корень проекта. `config.php` сам подхватит `.env`, если он лежит в корне проекта.

## Важное про базу и инициализацию

- схема таблиц создаётся из `bootstrap.php`
- стартовый курс и недели создаются автоматически, если база пустая
- стандартные пользователи создаются автоматически, если их нет
- старые Flask/Werkzeug-хеши для `admin`, `teacher`, `student1` автоматически переводятся в PHP-формат

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
config.php           подключение к MariaDB/MySQL + bootstrap
auth.php             авторизация, сессии и CSRF
index.php            роутер
pages/               PHP-страницы
static/              стили и статика
uploads/             загруженные файлы
storage/             служебная директория приложения
lang.php             переводы
portal.db            legacy SQLite-артефакт старой версии
```

## Legacy-хвосты

`portal.db` оставлен в репозитории как исторический артефакт. Рабочий путь проекта начинается с `index.php` и текущей MariaDB/MySQL-конфигурации.

## Smoke-проверка

Для быстрой проверки основных сценариев:

```bash
php scripts/smoke.php --base-url=http://127.0.0.1:8000
```

Скрипт использует текущие DB-настройки из `config.php`, добавляет временные записи и после прогона удаляет их.
