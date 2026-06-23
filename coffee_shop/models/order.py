# Order model for managing customer orders
import datetime
import pymysql
import random
import string
from db_helper import get_db

class Order:
    """Order model class"""
    
    def __init__(self, id, user_id, order_number, total_amount, status,
                 payment_method=None, delivery_address=None, 
                 special_instructions=None, created_at=None):
        self.id = id
        self.user_id = user_id
        self.order_number = order_number
        self.total_amount = float(total_amount) if total_amount else 0
        self.status = status
        self.payment_method = payment_method
        self.delivery_address = delivery_address
        self.special_instructions = special_instructions
        self.created_at = created_at if created_at else datetime.datetime.now()
    
    @staticmethod
    def generate_order_number():
        """Generate unique order number"""
        return ''.join(random.choices(string.ascii_uppercase + string.digits, k=10))
    
    @staticmethod
    def create_order(user_id, cart_items, total_amount, payment_method, 
                     delivery_address, special_instructions):
        """Create new order from cart"""
        db = get_db()
        cursor = db.cursor()
        
        order_number = Order.generate_order_number()
        
        cursor.execute(
            """INSERT INTO orders (user_id, order_number, total_amount, status, 
               payment_method, delivery_address, special_instructions)
               VALUES (%s, %s, %s, %s, %s, %s, %s)""",
            (user_id, order_number, total_amount, 'pending', 
             payment_method, delivery_address, special_instructions)
        )
        
        order_id = cursor.lastrowid
        
        for item in cart_items:
            cursor.execute(
                """INSERT INTO order_items (order_id, product_id, quantity, price_at_time)
                   VALUES (%s, %s, %s, %s)""",
                (order_id, item['product_id'], item['quantity'], item['price'])
            )
        
        db.commit()
        db.close()
        
        return Order.get_by_id(order_id)
    
    @staticmethod
    def get_by_id(order_id):
        db = get_db()
        cursor = db.cursor()
        cursor.execute("SELECT * FROM orders WHERE id = %s", (order_id,))
        order_data = cursor.fetchone()
        db.close()
        if order_data:
            return Order(**order_data)
        return None
    
    @staticmethod
    def get_by_order_number(order_number):
        db = get_db()
        cursor = db.cursor()
        cursor.execute("SELECT * FROM orders WHERE order_number = %s", (order_number,))
        order_data = cursor.fetchone()
        db.close()
        if order_data:
            return Order(**order_data)
        return None
    
    @staticmethod
    def get_user_orders(user_id, limit=50):
        db = get_db()
        cursor = db.cursor()
        cursor.execute(
            "SELECT * FROM orders WHERE user_id = %s ORDER BY created_at DESC LIMIT %s",
            (user_id, limit)
        )
        orders_data = cursor.fetchall()
        db.close()
        return [Order(**order) for order in orders_data]
    
    @staticmethod
    def get_all_orders(status=None):
        db = get_db()
        cursor = db.cursor()
        if status:
            cursor.execute(
                "SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE o.status = %s ORDER BY o.created_at DESC",
                (status,)
            )
        else:
            cursor.execute(
                "SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC"
            )
        orders_data = cursor.fetchall()
        db.close()
        return orders_data
    
    def get_items(self):
        db = get_db()
        cursor = db.cursor()
        cursor.execute(
            "SELECT oi.*, p.name, p.image_url FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = %s",
            (self.id,)
        )
        items = cursor.fetchall()
        db.close()
        return items
    
    def update_status(self, new_status):
        db = get_db()
        cursor = db.cursor()
        cursor.execute("UPDATE orders SET status = %s WHERE id = %s", (new_status, self.id))
        db.commit()
        db.close()
        self.status = new_status
