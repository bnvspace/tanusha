import sys
import os

# Добавляем текущую директорию в путь поиска модулей
sys.path.insert(0, os.path.dirname(__file__))

# Phusion Passenger ищет объект с именем 'application'
from app import app as application
