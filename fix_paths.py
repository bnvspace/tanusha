import os

def fix_paths():
    base_dir = r"d:\WORK\3_tasks\1_TASK\tanusha\pages"
    count = 0
    # Обходим все файлы в папке pages
    for root, _, files in os.walk(base_dir):
        for file in files:
            if file.endswith('.php'):
                path = os.path.join(root, file)
                with open(path, 'r', encoding='utf-8') as f:
                    content = f.read()
                
                # Убираем лишние require_once
                new_content = content.replace("require_once 'config.php';", "")
                new_content = new_content.replace("require_once 'auth.php';", "")
                
                if content != new_content:
                    with open(path, 'w', encoding='utf-8') as f:
                        f.write(new_content)
                    print(f"Исправлен: {file}")
                    count += 1

    # Исправляем header.php
    header_path = r"d:\WORK\3_tasks\1_TASK\tanusha\header.php"
    if os.path.exists(header_path):
        with open(header_path, 'r', encoding='utf-8') as f:
            content = f.read()
        new_content = content.replace("require_once 'auth.php';", "")
        if content != new_content:
            with open(header_path, 'w', encoding='utf-8') as f:
                f.write(new_content)
            print("Исправлен: header.php")
            count += 1
            
    print(f"Готово! Исправлено файлов: {count}")

if __name__ == "__main__":
    fix_paths()
