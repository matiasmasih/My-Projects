
@app.route('/employee/inventory')
@login_required
def employee_inventory():
    if current_user.role not in ['employee', 'admin']:
        flash('Employee access only', 'danger')
        return redirect(url_for('index'))
    
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT * FROM products ORDER BY category, name")
    products = cursor.fetchall()
    db.close()
    
    return render_template('employee_inventory.html', products=products)

@app.route('/employee/customers')
@login_required
def employee_customers():
    if current_user.role not in ['employee', 'admin']:
        flash('Employee access only', 'danger')
        return redirect(url_for('index'))
    
    db = get_db()
    cursor = db.cursor()
    cursor.execute("""
        SELECT u.*, COUNT(o.id) as order_count, COALESCE(SUM(o.total_amount), 0) as total_spent 
        FROM users u 
        LEFT JOIN orders o ON u.id = o.user_id 
        WHERE u.is_admin = 0 
        GROUP BY u.id 
        ORDER BY u.created_at DESC
    """)
    customers = cursor.fetchall()
    db.close()
    
    return render_template('employee_customers.html', customers=customers)

@app.route('/api/employee/orders')
@login_required
def employee_orders_api():
    if current_user.role not in ['employee', 'admin']:
        return jsonify({'error': 'Unauthorized'}), 403
    
    db = get_db()
    cursor = db.cursor()
    
    # Get all orders for today
    cursor.execute("""
        SELECT o.*, u.username as customer_name, u.email as customer_email, u.phone as customer_phone
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE DATE(o.created_at) = CURDATE()
        ORDER BY o.created_at DESC
    """)
    orders = cursor.fetchall()
    
    # Get pending orders
    pending = [o for o in orders if o['status'] == 'pending']
    
    db.close()
    
    return jsonify({
        'orders': orders,
        'pending': pending
    })
