import os

def rename_function():
    base_dir = r"d:\WORK\3_tasks\1_TASK\tanusha"
    count = 0
    # Обходим все PHP файлы в корне и подпапках
    for root, _, files in os.walk(base_dir):
        if ".git" in root or ".gemini" in root:
            continue
        for file in files:
            if file.endswith('.php'):
                path = os.path.join(root, file)
                with open(path, 'r', encoding='utf-8') as f:
                    content = f.read()
                
                # Заменяем get_current_user на get_logged_in_user
                new_content = content.replace("get_current_user", "get_logged_in_user")
                
                if content != new_content:
                    with open(path, 'w', encoding='utf-8') as f:
                        f.write(new_content)
                    print(f"Исправлен: {os.path.relpath(path, base_dir)}")
                    count += 1

    print(f"Готово! Исправлено файлов: {count}")

if __name__ == "__main__":
    rename_function()
