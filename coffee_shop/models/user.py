from flask_login import UserMixin
from db_helper import get_db

bcrypt = None

def set_bcrypt(bcrypt_instance):
    global bcrypt
    bcrypt = bcrypt_instance

class User(UserMixin):
    def __init__(self, id, username, email, is_admin=False):
        self.id = id
        self.username = username
        self.email = email
        self.is_admin = is_admin
    
    @staticmethod
    def get_by_id(user_id):
        db = get_db()
        cursor = db.cursor()
        cursor.execute("SELECT id, username, email, is_admin FROM users WHERE id = %s", (user_id,))
        data = cursor.fetchone()
        db.close()
        if data:
            return User(**data)
        return None
