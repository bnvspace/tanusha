# Образовательный портал: Профессиональный иностранный язык для теплоэнергетиков

## Быстрый старт

```bash
# 1. Установить зависимости
pip install -r requirements.txt

# 2. Запустить
python app.py
```

Открыть в браузере: **http://localhost:5000**

## Учётные записи по умолчанию

| Роль | Логин | Пароль |
|---|---|---|
| Администратор | `admin` | `admin123` |
| Преподаватель | `teacher` | `teacher123` |
| Студент (тест) | `student1` | `student123` |

> Студенты регистрируются самостоятельно или создаются администратором.

## Структура проекта

```
tanusha/
├── app.py                  # Flask приложение (все роуты + модели)
├── requirements.txt        # Зависимости Python
├── portal.db               # SQLite БД (создаётся автоматически)
├── uploads/                # Загружаемые файлы студентов
├── static/css/style.css    # Стили
└── templates/
    ├── base.html           # Базовый шаблон с сайдбаром
    ├── landing.html        # Страница входа
    ├── register.html       # Регистрация
    ├── dashboard.html      # Главная курса (студент)
    ├── materials.html      # Учебные материалы
    ├── assignments.html    # Список заданий
    ├── assignment.html     # Страница задания + сдача
    ├── grades.html         # Оценки и прогресс
    ├── tests.html          # Список тестов
    ├── test_take.html      # Прохождение теста
    ├── test_result.html    # Результат теста
    └── admin/
        ├── dashboard.html  # Панель администратора
        ├── students.html   # Список студентов
        ├── student_detail.html
        ├── review.html     # Очередь проверки
        ├── review_detail.html
        ├── users.html      # Управление пользователями
        ├── course.html     # Управление курсом/неделями
        ├── add_material.html
        ├── edit_material.html
        ├── add_assignment.html
        ├── edit_assignment.html
        ├── add_test.html   # Конструктор тестов
        └── statistics.html
```

## Роли пользователей

- **Студент** — просматривает материалы, сдаёт задания, проходит тесты, видит оценки
- **Преподаватель** — добавляет контент, проверяет задания, выставляет оценки
- **Администратор** — всё что преподаватель + управление учётными записями

## Деплой на сервер

```bash
pip install gunicorn
gunicorn -w 4 -b 0.0.0.0:5000 app:app
```

В `app.py` перед деплоем изменить `SECRET_KEY` на надёжный случайный ключ.
