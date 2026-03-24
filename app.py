import os
import json
from datetime import datetime, timezone
from flask import (Flask, render_template, redirect, url_for, request,
                   flash, send_from_directory, abort)
from flask_sqlalchemy import SQLAlchemy
from flask_login import (LoginManager, UserMixin, login_user, logout_user,
                         login_required, current_user)
from werkzeug.security import generate_password_hash, check_password_hash
from werkzeug.utils import secure_filename
from functools import wraps

# ──────────────────────────────────────────
# Конфигурация
# ──────────────────────────────────────────
BASE_DIR = os.path.abspath(os.path.dirname(__file__))

app = Flask(__name__)
app.config['SECRET_KEY'] = 'change-me-in-production-abc123xyz'
app.config['SQLALCHEMY_DATABASE_URI'] = 'sqlite:///' + os.path.join(BASE_DIR, 'portal.db')
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False
app.config['UPLOAD_FOLDER'] = os.path.join(BASE_DIR, 'uploads')
app.config['MAX_CONTENT_LENGTH'] = 50 * 1024 * 1024  # 50 MB

ALLOWED_EXTENSIONS = {'pdf', 'doc', 'docx', 'txt', 'ppt', 'pptx',
                      'xls', 'xlsx', 'mp3', 'mp4', 'png', 'jpg', 'jpeg', 'zip'}

os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)

db = SQLAlchemy(app)
login_manager = LoginManager(app)
login_manager.login_view = 'landing'
login_manager.login_message = 'Пожалуйста, войдите в систему.'
login_manager.login_message_category = 'warning'


# ──────────────────────────────────────────
# Вспомогательные функции
# ──────────────────────────────────────────
def allowed_file(filename):
    return '.' in filename and filename.rsplit('.', 1)[1].lower() in ALLOWED_EXTENSIONS


def role_required(*roles):
    def decorator(f):
        @wraps(f)
        def decorated(*args, **kwargs):
            if not current_user.is_authenticated or current_user.role not in roles:
                abort(403)
            return f(*args, **kwargs)
        return decorated
    return decorator


# ──────────────────────────────────────────
# Модели
# ──────────────────────────────────────────
class User(UserMixin, db.Model):
    __tablename__ = 'users'
    id = db.Column(db.Integer, primary_key=True)
    username = db.Column(db.String(64), unique=True, nullable=False)
    full_name = db.Column(db.String(128), nullable=False)
    email = db.Column(db.String(120), unique=True, nullable=False)
    password_hash = db.Column(db.String(256), nullable=False)
    role = db.Column(db.String(16), nullable=False, default='student')  # student, teacher, admin
    is_active = db.Column(db.Boolean, default=True)
    created_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))

    submissions = db.relationship('Submission', foreign_keys='Submission.user_id', backref='student', lazy=True)
    test_submissions = db.relationship('TestSubmission', foreign_keys='TestSubmission.user_id', backref='student', lazy=True)

    def set_password(self, password):
        self.password_hash = generate_password_hash(password)

    def check_password(self, password):
        return check_password_hash(self.password_hash, password)


@login_manager.user_loader
def load_user(user_id):
    return db.session.get(User, int(user_id))


class Course(db.Model):
    __tablename__ = 'courses'
    id = db.Column(db.Integer, primary_key=True)
    title = db.Column(db.String(256), nullable=False)
    description = db.Column(db.Text)
    goals = db.Column(db.Text)
    objectives = db.Column(db.Text)
    content_info = db.Column(db.Text)
    weeks = db.relationship('Week', backref='course', lazy=True,
                            order_by='Week.number')


class Week(db.Model):
    __tablename__ = 'weeks'
    id = db.Column(db.Integer, primary_key=True)
    course_id = db.Column(db.Integer, db.ForeignKey('courses.id'), nullable=False)
    number = db.Column(db.Integer, nullable=False)
    title = db.Column(db.String(256), nullable=False)
    materials = db.relationship('Material', backref='week', lazy=True)
    assignments = db.relationship('Assignment', backref='week', lazy=True)
    tests = db.relationship('Test', backref='week', lazy=True)


class Material(db.Model):
    __tablename__ = 'materials'
    id = db.Column(db.Integer, primary_key=True)
    week_id = db.Column(db.Integer, db.ForeignKey('weeks.id'), nullable=False)
    title = db.Column(db.String(256), nullable=False)
    material_type = db.Column(db.String(32))  # file, video, audio, link, text, interactive
    content = db.Column(db.Text)
    file_path = db.Column(db.String(512))
    url = db.Column(db.String(1024))
    visible = db.Column(db.Boolean, default=True)
    open_date = db.Column(db.DateTime)
    created_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))


class Assignment(db.Model):
    __tablename__ = 'assignments'
    id = db.Column(db.Integer, primary_key=True)
    week_id = db.Column(db.Integer, db.ForeignKey('weeks.id'), nullable=False)
    title = db.Column(db.String(256), nullable=False)
    description = db.Column(db.Text)
    deadline = db.Column(db.DateTime)
    visible = db.Column(db.Boolean, default=True)
    open_date = db.Column(db.DateTime)
    created_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))
    submissions = db.relationship('Submission', backref='assignment', lazy=True)


class Submission(db.Model):
    __tablename__ = 'submissions'
    id = db.Column(db.Integer, primary_key=True)
    assignment_id = db.Column(db.Integer, db.ForeignKey('assignments.id'), nullable=False)
    user_id = db.Column(db.Integer, db.ForeignKey('users.id'), nullable=False)
    file_path = db.Column(db.String(512))
    text_answer = db.Column(db.Text)
    submitted_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))
    status = db.Column(db.String(16), default='pending')  # pending, reviewed, revision
    grade = db.Column(db.Integer)
    comment = db.Column(db.Text)
    reviewed_at = db.Column(db.DateTime)
    reviewed_by = db.Column(db.Integer, db.ForeignKey('users.id'))


class Test(db.Model):
    __tablename__ = 'tests'
    id = db.Column(db.Integer, primary_key=True)
    week_id = db.Column(db.Integer, db.ForeignKey('weeks.id'), nullable=False)
    title = db.Column(db.String(256), nullable=False)
    description = db.Column(db.Text)
    time_limit = db.Column(db.Integer)  # minutes, None = unlimited
    show_answers = db.Column(db.Boolean, default=True)
    visible = db.Column(db.Boolean, default=True)
    open_date = db.Column(db.DateTime)
    created_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))
    questions = db.relationship('TestQuestion', backref='test', lazy=True,
                                order_by='TestQuestion.order_num')
    submissions = db.relationship('TestSubmission', backref='test', lazy=True)


class TestQuestion(db.Model):
    __tablename__ = 'test_questions'
    id = db.Column(db.Integer, primary_key=True)
    test_id = db.Column(db.Integer, db.ForeignKey('tests.id'), nullable=False)
    question_text = db.Column(db.Text, nullable=False)
    question_type = db.Column(db.String(16), default='single')  # single, multiple, text
    order_num = db.Column(db.Integer, default=0)
    options = db.relationship('TestOption', backref='question', lazy=True)


class TestOption(db.Model):
    __tablename__ = 'test_options'
    id = db.Column(db.Integer, primary_key=True)
    question_id = db.Column(db.Integer, db.ForeignKey('test_questions.id'), nullable=False)
    option_text = db.Column(db.Text, nullable=False)
    is_correct = db.Column(db.Boolean, default=False)


class TestSubmission(db.Model):
    __tablename__ = 'test_submissions'
    id = db.Column(db.Integer, primary_key=True)
    test_id = db.Column(db.Integer, db.ForeignKey('tests.id'), nullable=False)
    user_id = db.Column(db.Integer, db.ForeignKey('users.id'), nullable=False)
    started_at = db.Column(db.DateTime, default=lambda: datetime.now(timezone.utc))
    finished_at = db.Column(db.DateTime)
    score = db.Column(db.Integer, default=0)
    max_score = db.Column(db.Integer, default=0)
    answers = db.relationship('TestAnswer', backref='submission', lazy=True)


class TestAnswer(db.Model):
    __tablename__ = 'test_answers'
    id = db.Column(db.Integer, primary_key=True)
    submission_id = db.Column(db.Integer, db.ForeignKey('test_submissions.id'), nullable=False)
    question_id = db.Column(db.Integer, db.ForeignKey('test_questions.id'), nullable=False)
    answer_text = db.Column(db.Text)
    selected_options = db.Column(db.Text)  # JSON list of option ids
    is_correct = db.Column(db.Boolean)


# ──────────────────────────────────────────
# Роуты: Публичные
# ──────────────────────────────────────────
@app.route('/')
def landing():
    if current_user.is_authenticated:
        if current_user.role in ('teacher', 'admin'):
            return redirect(url_for('admin_dashboard'))
        return redirect(url_for('dashboard'))
    return render_template('landing.html')


@app.route('/login', methods=['POST'])
def login():
    username = request.form.get('username', '').strip()
    password = request.form.get('password', '')
    user = User.query.filter_by(username=username).first()
    if user and user.check_password(password) and user.is_active:
        login_user(user, remember=True)
        if user.role in ('teacher', 'admin'):
            return redirect(url_for('admin_dashboard'))
        return redirect(url_for('dashboard'))
    flash('Неверный логин или пароль.', 'danger')
    return redirect(url_for('landing'))


@app.route('/register', methods=['GET', 'POST'])
def register():
    if request.method == 'POST':
        username = request.form.get('username', '').strip()
        full_name = request.form.get('full_name', '').strip()
        email = request.form.get('email', '').strip()
        password = request.form.get('password', '')
        if User.query.filter_by(username=username).first():
            flash('Пользователь с таким логином уже существует.', 'danger')
            return redirect(url_for('register'))
        if User.query.filter_by(email=email).first():
            flash('Пользователь с таким email уже существует.', 'danger')
            return redirect(url_for('register'))
        user = User(username=username, full_name=full_name, email=email, role='student')
        user.set_password(password)
        db.session.add(user)
        db.session.commit()
        flash('Регистрация прошла успешно! Войдите в систему.', 'success')
        return redirect(url_for('landing'))
    return render_template('register.html')


@app.route('/logout')
@login_required
def logout():
    logout_user()
    return redirect(url_for('landing'))


# ──────────────────────────────────────────
# Роуты: Студент
# ──────────────────────────────────────────
@app.route('/dashboard')
@login_required
def dashboard():
    course = Course.query.first()
    return render_template('dashboard.html', course=course)


@app.route('/materials')
@login_required
def materials():
    course = Course.query.first()
    return render_template('materials.html', course=course)


@app.route('/assignments')
@login_required
def assignments():
    course = Course.query.first()
    now = datetime.now(timezone.utc)
    # собираем все задания студента
    all_assignments = []
    for week in course.weeks:
        for a in week.assignments:
            if not a.visible:
                continue
            if a.open_date and a.open_date.replace(tzinfo=timezone.utc) > now:
                continue
            sub = Submission.query.filter_by(
                assignment_id=a.id, user_id=current_user.id).first()
            all_assignments.append({'assignment': a, 'week': week, 'submission': sub})
    return render_template('assignments.html', items=all_assignments)


@app.route('/assignment/<int:aid>', methods=['GET', 'POST'])
@login_required
def assignment_detail(aid):
    a = Assignment.query.get_or_404(aid)
    sub = Submission.query.filter_by(
        assignment_id=aid, user_id=current_user.id).first()
    if request.method == 'POST':
        text_answer = request.form.get('text_answer', '').strip()
        file_path = None
        if 'file' in request.files:
            f = request.files['file']
            if f and f.filename and allowed_file(f.filename):
                filename = secure_filename(f.filename)
                filename = f"{datetime.now().strftime('%Y%m%d%H%M%S')}_{filename}"
                f.save(os.path.join(app.config['UPLOAD_FOLDER'], filename))
                file_path = filename
        if sub:
            sub.text_answer = text_answer or sub.text_answer
            if file_path:
                sub.file_path = file_path
            sub.submitted_at = datetime.now(timezone.utc)
            sub.status = 'pending'
            sub.grade = None
            sub.comment = None
        else:
            sub = Submission(
                assignment_id=aid, user_id=current_user.id,
                text_answer=text_answer, file_path=file_path)
            db.session.add(sub)
        db.session.commit()
        flash('Ответ отправлен!', 'success')
        return redirect(url_for('assignment_detail', aid=aid))
    return render_template('assignment.html', assignment=a, submission=sub)


@app.route('/grades')
@login_required
def grades():
    course = Course.query.first()
    now = datetime.now(timezone.utc)
    rows = []
    total_grade = 0
    total_count = 0
    for week in course.weeks:
        for a in week.assignments:
            if not a.visible:
                continue
            sub = Submission.query.filter_by(
                assignment_id=a.id, user_id=current_user.id).first()
            rows.append({'type': 'assignment', 'week': week, 'item': a, 'submission': sub})
            if sub and sub.grade is not None:
                total_grade += sub.grade
                total_count += 1
        for t in week.tests:
            if not t.visible:
                continue
            tsub = TestSubmission.query.filter_by(
                test_id=t.id, user_id=current_user.id).filter(
                TestSubmission.finished_at.isnot(None)).first()
            rows.append({'type': 'test', 'week': week, 'item': t, 'tsub': tsub})
            if tsub and tsub.max_score > 0:
                total_grade += int(tsub.score / tsub.max_score * 100)
                total_count += 1
    avg = round(total_grade / total_count) if total_count else 0
    return render_template('grades.html', rows=rows, avg=avg)


@app.route('/tests')
@login_required
def tests():
    course = Course.query.first()
    now = datetime.now(timezone.utc)
    items = []
    for week in course.weeks:
        for t in week.tests:
            if not t.visible:
                continue
            if t.open_date and t.open_date.replace(tzinfo=timezone.utc) > now:
                continue
            tsub = TestSubmission.query.filter_by(
                test_id=t.id, user_id=current_user.id).filter(
                TestSubmission.finished_at.isnot(None)).first()
            items.append({'test': t, 'week': week, 'tsub': tsub})
    return render_template('tests.html', items=items)


@app.route('/test/<int:tid>', methods=['GET', 'POST'])
@login_required
def test_take(tid):
    t = Test.query.get_or_404(tid)
    existing = TestSubmission.query.filter_by(
        test_id=tid, user_id=current_user.id).filter(
        TestSubmission.finished_at.isnot(None)).first()
    if existing:
        return redirect(url_for('test_result', tid=tid, sid=existing.id))
    if request.method == 'POST':
        tsub = TestSubmission(test_id=tid, user_id=current_user.id,
                              max_score=len(t.questions))
        db.session.add(tsub)
        db.session.flush()
        score = 0
        for q in t.questions:
            is_correct = False
            if q.question_type == 'text':
                ans_text = request.form.get(f'q_{q.id}', '').strip()
                correct_texts = [o.option_text.lower() for o in q.options if o.is_correct]
                is_correct = ans_text.lower() in correct_texts
                ta = TestAnswer(submission_id=tsub.id, question_id=q.id,
                                answer_text=ans_text, is_correct=is_correct)
            elif q.question_type == 'multiple':
                selected_ids = [int(x) for x in request.form.getlist(f'q_{q.id}')]
                correct_ids = sorted([o.id for o in q.options if o.is_correct])
                is_correct = sorted(selected_ids) == correct_ids
                ta = TestAnswer(submission_id=tsub.id, question_id=q.id,
                                selected_options=json.dumps(selected_ids),
                                is_correct=is_correct)
            else:  # single
                selected_id = request.form.get(f'q_{q.id}')
                if selected_id:
                    selected_id = int(selected_id)
                    opt = TestOption.query.get(selected_id)
                    is_correct = opt.is_correct if opt else False
                else:
                    selected_id = None
                ta = TestAnswer(submission_id=tsub.id, question_id=q.id,
                                selected_options=json.dumps([selected_id] if selected_id else []),
                                is_correct=is_correct)
            if is_correct:
                score += 1
            db.session.add(ta)
        tsub.score = score
        tsub.finished_at = datetime.now(timezone.utc)
        db.session.commit()
        return redirect(url_for('test_result', tid=tid, sid=tsub.id))
    return render_template('test_take.html', test=t)


@app.route('/test/<int:tid>/result/<int:sid>')
@login_required
def test_result(tid, sid):
    tsub = TestSubmission.query.get_or_404(sid)
    if tsub.user_id != current_user.id and current_user.role not in ('teacher', 'admin'):
        abort(403)
    t = Test.query.get_or_404(tid)
    answers_map = {a.question_id: a for a in tsub.answers}
    return render_template('test_result.html', test=t, tsub=tsub, answers_map=answers_map)


# ──────────────────────────────────────────
# Роуты: Файлы
# ──────────────────────────────────────────
@app.route('/uploads/<path:filename>')
@login_required
def uploaded_file(filename):
    return send_from_directory(app.config['UPLOAD_FOLDER'], filename)


# ──────────────────────────────────────────
# Роуты: Администратор / Преподаватель
# ──────────────────────────────────────────
@app.route('/admin/')
@login_required
@role_required('teacher', 'admin')
def admin_dashboard():
    students = User.query.filter_by(role='student').all()
    pending = Submission.query.filter_by(status='pending').count()
    total_subs = Submission.query.count()
    test_subs = TestSubmission.query.filter(TestSubmission.finished_at.isnot(None)).count()
    return render_template('admin/dashboard.html',
                           students=students, pending=pending,
                           total_subs=total_subs, test_subs=test_subs)


@app.route('/admin/students')
@login_required
@role_required('teacher', 'admin')
def admin_students():
    students = User.query.filter_by(role='student').order_by(User.full_name).all()
    return render_template('admin/students.html', students=students)


@app.route('/admin/student/<int:uid>')
@login_required
@role_required('teacher', 'admin')
def admin_student_detail(uid):
    student = User.query.get_or_404(uid)
    course = Course.query.first()
    return render_template('admin/student_detail.html', student=student, course=course)


@app.route('/admin/users')
@login_required
@role_required('admin')
def admin_users():
    users = User.query.order_by(User.full_name).all()
    return render_template('admin/users.html', users=users)


@app.route('/admin/users/create', methods=['POST'])
@login_required
@role_required('admin')
def admin_create_user():
    username = request.form.get('username', '').strip()
    full_name = request.form.get('full_name', '').strip()
    email = request.form.get('email', '').strip()
    password = request.form.get('password', '')
    role = request.form.get('role', 'student')
    if User.query.filter_by(username=username).first():
        flash('Такой логин уже существует.', 'danger')
    else:
        u = User(username=username, full_name=full_name, email=email, role=role)
        u.set_password(password)
        db.session.add(u)
        db.session.commit()
        flash(f'Пользователь {full_name} создан.', 'success')
    return redirect(url_for('admin_users'))


@app.route('/admin/users/<int:uid>/toggle', methods=['POST'])
@login_required
@role_required('admin')
def admin_toggle_user(uid):
    u = User.query.get_or_404(uid)
    u.is_active = not u.is_active
    db.session.commit()
    return redirect(url_for('admin_users'))


@app.route('/admin/course', methods=['GET', 'POST'])
@login_required
@role_required('teacher', 'admin')
def admin_course():
    course = Course.query.first()
    if request.method == 'POST':
        action = request.form.get('action')
        if action == 'update_course':
            course.title = request.form.get('title', course.title)
            course.description = request.form.get('description', '')
            course.goals = request.form.get('goals', '')
            course.objectives = request.form.get('objectives', '')
            course.content_info = request.form.get('content_info', '')
            db.session.commit()
            flash('Курс обновлён.', 'success')
        elif action == 'add_week':
            num = len(course.weeks) + 1
            title = request.form.get('week_title', f'Неделя {num}')
            w = Week(course_id=course.id, number=num, title=title)
            db.session.add(w)
            db.session.commit()
            flash(f'Неделя {num} добавлена.', 'success')
        elif action == 'delete_week':
            wid = int(request.form.get('week_id'))
            w = Week.query.get(wid)
            if w:
                db.session.delete(w)
                db.session.commit()
                flash('Неделя удалена.', 'success')
        return redirect(url_for('admin_course'))
    return render_template('admin/course.html', course=course)


@app.route('/admin/material/add', methods=['GET', 'POST'])
@login_required
@role_required('teacher', 'admin')
def admin_add_material():
    course = Course.query.first()
    if request.method == 'POST':
        week_id = int(request.form.get('week_id'))
        title = request.form.get('title', '').strip()
        mtype = request.form.get('material_type', 'text')
        content = request.form.get('content', '')
        url = request.form.get('url', '').strip()
        visible = request.form.get('visible') == '1'
        open_date_str = request.form.get('open_date', '').strip()
        open_date = None
        if open_date_str:
            try:
                open_date = datetime.strptime(open_date_str, '%Y-%m-%dT%H:%M')
            except ValueError:
                pass
        file_path = None
        if 'file' in request.files:
            f = request.files['file']
            if f and f.filename and allowed_file(f.filename):
                filename = secure_filename(f.filename)
                filename = f"{datetime.now().strftime('%Y%m%d%H%M%S')}_{filename}"
                f.save(os.path.join(app.config['UPLOAD_FOLDER'], filename))
                file_path = filename
        m = Material(week_id=week_id, title=title, material_type=mtype,
                     content=content, file_path=file_path, url=url,
                     visible=visible, open_date=open_date)
        db.session.add(m)
        db.session.commit()
        flash('Материал добавлен.', 'success')
        return redirect(url_for('admin_course'))
    return render_template('admin/add_material.html', course=course)


@app.route('/admin/material/<int:mid>/edit', methods=['GET', 'POST'])
@login_required
@role_required('teacher', 'admin')
def admin_edit_material(mid):
    m = Material.query.get_or_404(mid)
    course = Course.query.first()
    if request.method == 'POST':
        m.title = request.form.get('title', m.title)
        m.material_type = request.form.get('material_type', m.material_type)
        m.content = request.form.get('content', '')
        m.url = request.form.get('url', '').strip()
        m.visible = request.form.get('visible') == '1'
        open_date_str = request.form.get('open_date', '').strip()
        if open_date_str:
            try:
                m.open_date = datetime.strptime(open_date_str, '%Y-%m-%dT%H:%M')
            except ValueError:
                pass
        if 'file' in request.files:
            f = request.files['file']
            if f and f.filename and allowed_file(f.filename):
                filename = secure_filename(f.filename)
                filename = f"{datetime.now().strftime('%Y%m%d%H%M%S')}_{filename}"
                f.save(os.path.join(app.config['UPLOAD_FOLDER'], filename))
                m.file_path = filename
        db.session.commit()
        flash('Материал обновлён.', 'success')
        return redirect(url_for('admin_course'))
    return render_template('admin/edit_material.html', material=m, course=course)


@app.route('/admin/material/<int:mid>/delete', methods=['POST'])
@login_required
@role_required('teacher', 'admin')
def admin_delete_material(mid):
    m = Material.query.get_or_404(mid)
    db.session.delete(m)
    db.session.commit()
    flash('Материал удалён.', 'success')
    return redirect(url_for('admin_course'))


@app.route('/admin/assignment/add', methods=['GET', 'POST'])
@login_required
@role_required('teacher', 'admin')
def admin_add_assignment():
    course = Course.query.first()
    if request.method == 'POST':
        week_id = int(request.form.get('week_id'))
        title = request.form.get('title', '').strip()
        description = request.form.get('description', '')
        visible = request.form.get('visible') == '1'
        deadline_str = request.form.get('deadline', '').strip()
        deadline = None
        if deadline_str:
            try:
                deadline = datetime.strptime(deadline_str, '%Y-%m-%dT%H:%M')
            except ValueError:
                pass
        open_date_str = request.form.get('open_date', '').strip()
        open_date = None
        if open_date_str:
            try:
                open_date = datetime.strptime(open_date_str, '%Y-%m-%dT%H:%M')
            except ValueError:
                pass
        a = Assignment(week_id=week_id, title=title, description=description,
                       deadline=deadline, visible=visible, open_date=open_date)
        db.session.add(a)
        db.session.commit()
        flash('Задание добавлено.', 'success')
        return redirect(url_for('admin_course'))
    return render_template('admin/add_assignment.html', course=course)


@app.route('/admin/assignment/<int:aid>/edit', methods=['GET', 'POST'])
@login_required
@role_required('teacher', 'admin')
def admin_edit_assignment(aid):
    a = Assignment.query.get_or_404(aid)
    course = Course.query.first()
    if request.method == 'POST':
        a.title = request.form.get('title', a.title)
        a.description = request.form.get('description', '')
        a.visible = request.form.get('visible') == '1'
        deadline_str = request.form.get('deadline', '').strip()
        if deadline_str:
            try:
                a.deadline = datetime.strptime(deadline_str, '%Y-%m-%dT%H:%M')
            except ValueError:
                pass
        open_date_str = request.form.get('open_date', '').strip()
        if open_date_str:
            try:
                a.open_date = datetime.strptime(open_date_str, '%Y-%m-%dT%H:%M')
            except ValueError:
                pass
        db.session.commit()
        flash('Задание обновлено.', 'success')
        return redirect(url_for('admin_course'))
    return render_template('admin/edit_assignment.html', assignment=a, course=course)


@app.route('/admin/assignment/<int:aid>/delete', methods=['POST'])
@login_required
@role_required('teacher', 'admin')
def admin_delete_assignment(aid):
    a = Assignment.query.get_or_404(aid)
    db.session.delete(a)
    db.session.commit()
    flash('Задание удалено.', 'success')
    return redirect(url_for('admin_course'))


@app.route('/admin/review')
@login_required
@role_required('teacher', 'admin')
def admin_review():
    pending = (Submission.query
               .filter_by(status='pending')
               .order_by(Submission.submitted_at)
               .all())
    reviewed = (Submission.query
                .filter(Submission.status != 'pending')
                .order_by(Submission.reviewed_at.desc())
                .limit(50).all())
    return render_template('admin/review.html', pending=pending, reviewed=reviewed)


@app.route('/admin/review/<int:sid>', methods=['GET', 'POST'])
@login_required
@role_required('teacher', 'admin')
def admin_review_detail(sid):
    sub = Submission.query.get_or_404(sid)
    if request.method == 'POST':
        sub.grade = int(request.form.get('grade', 0))
        sub.comment = request.form.get('comment', '')
        sub.status = request.form.get('status', 'reviewed')
        sub.reviewed_at = datetime.now(timezone.utc)
        sub.reviewed_by = current_user.id
        db.session.commit()
        flash('Оценка выставлена.', 'success')
        return redirect(url_for('admin_review'))
    return render_template('admin/review_detail.html', submission=sub)


@app.route('/admin/test/add', methods=['GET', 'POST'])
@login_required
@role_required('teacher', 'admin')
def admin_add_test():
    course = Course.query.first()
    if request.method == 'POST':
        week_id = int(request.form.get('week_id'))
        title = request.form.get('title', '').strip()
        description = request.form.get('description', '')
        visible = request.form.get('visible') == '1'
        show_answers = request.form.get('show_answers') == '1'
        time_limit_str = request.form.get('time_limit', '').strip()
        time_limit = int(time_limit_str) if time_limit_str.isdigit() else None
        open_date_str = request.form.get('open_date', '').strip()
        open_date = None
        if open_date_str:
            try:
                open_date = datetime.strptime(open_date_str, '%Y-%m-%dT%H:%M')
            except ValueError:
                pass
        t = Test(week_id=week_id, title=title, description=description,
                 visible=visible, show_answers=show_answers,
                 time_limit=time_limit, open_date=open_date)
        db.session.add(t)
        db.session.flush()
        # Вопросы
        q_texts = request.form.getlist('q_text[]')
        q_types = request.form.getlist('q_type[]')
        for idx, (qtext, qtype) in enumerate(zip(q_texts, q_types)):
            if not qtext.strip():
                continue
            q = TestQuestion(test_id=t.id, question_text=qtext.strip(),
                             question_type=qtype, order_num=idx)
            db.session.add(q)
            db.session.flush()
            opts = request.form.getlist(f'opt_{idx}[]')
            correct_flags = request.form.getlist(f'correct_{idx}[]')
            for oidx, otext in enumerate(opts):
                if not otext.strip():
                    continue
                is_correct = str(oidx) in correct_flags
                o = TestOption(question_id=q.id, option_text=otext.strip(),
                               is_correct=is_correct)
                db.session.add(o)
        db.session.commit()
        flash('Тест создан.', 'success')
        return redirect(url_for('admin_course'))
    return render_template('admin/add_test.html', course=course)


@app.route('/admin/statistics')
@login_required
@role_required('teacher', 'admin')
def admin_statistics():
    students = User.query.filter_by(role='student').all()
    total_students = len(students)
    total_submissions = Submission.query.count()
    reviewed_submissions = Submission.query.filter(Submission.status != 'pending').count()
    total_test_subs = TestSubmission.query.filter(
        TestSubmission.finished_at.isnot(None)).count()
    # Распределение оценок
    grades_data = db.session.query(Submission.grade).filter(
        Submission.grade.isnot(None)).all()
    grade_dist = {'0-20': 0, '21-40': 0, '41-60': 0, '61-80': 0, '81-100': 0}
    for (g,) in grades_data:
        if g <= 20:
            grade_dist['0-20'] += 1
        elif g <= 40:
            grade_dist['21-40'] += 1
        elif g <= 60:
            grade_dist['41-60'] += 1
        elif g <= 80:
            grade_dist['61-80'] += 1
        else:
            grade_dist['81-100'] += 1
    return render_template('admin/statistics.html',
                           total_students=total_students,
                           total_submissions=total_submissions,
                           reviewed_submissions=reviewed_submissions,
                           total_test_subs=total_test_subs,
                           grade_dist=grade_dist)


# ──────────────────────────────────────────
# Context processor
# ──────────────────────────────────────────
@app.context_processor
def inject_now():
    return {'now': datetime.now(timezone.utc), 'json': json}


@app.template_filter('from_json')
def from_json_filter(value):
    try:
        return json.loads(value) if value else []
    except Exception:
        return []


# ──────────────────────────────────────────
# Инициализация БД и старт
# ──────────────────────────────────────────
def init_db():
    db.create_all()
    # Создаём курс если нет
    if not Course.query.first():
        course = Course(
            title='Профессиональный иностранный язык для теплоэнергетиков',
            description='Курс профессионального иностранного языка разработан специально для специалистов теплоэнергетической отрасли.',
            goals='Освоение профессиональной иноязычной коммуникации в области теплоэнергетики.',
            objectives='• Овладение профессиональной терминологией\n• Чтение и перевод технической документации\n• Деловая переписка на иностранном языке\n• Устное профессиональное общение',
            content_info='Курс состоит из тематических модулей по неделям. Каждый модуль включает лексический материал, грамматику, тексты по специальности, задания и тест.'
        )
        db.session.add(course)
        db.session.flush()
        # Добавляем 4 тестовые недели
        for i in range(1, 5):
            w = Week(course_id=course.id, number=i,
                     title=f'Неделя {i}: {"Введение в терминологию" if i==1 else "Техническая документация" if i==2 else "Деловая коммуникация" if i==3 else "Профессиональное общение"}')
            db.session.add(w)
        db.session.commit()
    # Создаём администратора если нет
    if not User.query.filter_by(role='admin').first():
        admin = User(username='admin', full_name='Администратор', email='admin@portal.ru', role='admin')
        admin.set_password('admin123')
        db.session.add(admin)
        db.session.commit()
        print('Создан администратор: login=admin, password=admin123')
    # Создаём преподавателя если нет
    if not User.query.filter_by(role='teacher').first():
        teacher = User(username='teacher', full_name='Преподаватель', email='teacher@portal.ru', role='teacher')
        teacher.set_password('teacher123')
        db.session.add(teacher)
        db.session.commit()
        print('Создан преподаватель: login=teacher, password=teacher123')


if __name__ == '__main__':
    with app.app_context():
        init_db()
    app.run(debug=True)
