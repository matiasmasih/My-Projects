import os
from dotenv import load_dotenv

load_dotenv()

class Config:
    SECRET_KEY = os.environ.get('SECRET_KEY', 'coffee-secret-key-2024')
    MYSQL_HOST = os.environ.get('MYSQL_HOST', 'localhost')
    MYSQL_PORT = int(os.environ.get('MYSQL_PORT', 3307))
    MYSQL_USER = os.environ.get('MYSQL_USER', 'coffee_user')
    MYSQL_PASSWORD = os.environ.get('MYSQL_PASSWORD', 'Matias413114312')
    MYSQL_DB = os.environ.get('MYSQL_DB', 'coffee_shop')
