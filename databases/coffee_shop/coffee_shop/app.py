import os
from flask import Flask, render_template, jsonify, request, session, flash, redirect, url_for, Response
from flask_login import LoginManager, login_user, logout_user, login_required, current_user
from flask_bcrypt import Bcrypt
from config import Config
from db_helper import get_db
from deep_translator import GoogleTranslator
import random
import uuid
import json
from datetime import datetime, date
import stripe
from authlib.integrations.flask_client import OAuth
import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart

app = Flask(__name__)
app.config.from_object(Config)
app.secret_key = Config.SECRET_KEY
app.config['MAX_CONTENT_LENGTH'] = 16 * 1024 * 1024

bcrypt = Bcrypt(app)
login_manager = LoginManager(app)
login_manager.login_view = 'login'

os.makedirs('static/uploads', exist_ok=True)

stripe.api_key = os.environ.get('STRIPE_SECRET_KEY', '')

# Gmail configuration
GMAIL_USER = "matiasmasih@gmail.com"
GMAIL_PASSWORD = "anuz wwzb xquz vwge"

oauth = OAuth(app)

google = oauth.register(
    name='google',
    client_id=os.environ.get('GOOGLE_CLIENT_ID', ''),
    client_secret=os.environ.get('GOOGLE_CLIENT_SECRET', ''),
    access_token_url='https://accounts.google.com/o/oauth2/token',
    authorize_url='https://accounts.google.com/o/oauth2/auth',
    api_base_url='https://www.googleapis.com/oauth2/v1/',
    client_kwargs={'scope': 'openid email profile'},
    server_metadata_url='https://accounts.google.com/.well-known/openid-configuration'
)

facebook = oauth.register(
    name='facebook',
    client_id=os.environ.get('FACEBOOK_CLIENT_ID', ''),
    client_secret=os.environ.get('FACEBOOK_CLIENT_SECRET', ''),
    access_token_url='https://graph.facebook.com/oauth/access_token',
    authorize_url='https://www.facebook.com/dialog/oauth',
    api_base_url='https://graph.facebook.com/',
    client_kwargs={'scope': 'email public_profile'},
)

tiktok = oauth.register(
    name='tiktok',
    client_id=os.environ.get('TIKTOK_CLIENT_ID', ''),
    client_secret=os.environ.get('TIKTOK_CLIENT_SECRET', ''),
    access_token_url='https://open.tiktokapis.com/v2/oauth/token/',
    authorize_url='https://www.tiktok.com/v2/auth/authorize/',
    api_base_url='https://open.tiktokapis.com/v2/',
    client_kwargs={'scope': 'user.info.basic'},
)

whatsapp = oauth.register(
    name='whatsapp',
    client_id=os.environ.get('WHATSAPP_CLIENT_ID', ''),
    client_secret=os.environ.get('WHATSAPP_CLIENT_SECRET', ''),
    access_token_url='https://graph.facebook.com/v18.0/oauth/access_token',
    authorize_url='https://www.facebook.com/v18.0/dialog/oauth',
    api_base_url='https://graph.facebook.com/',
    client_kwargs={'scope': 'email,public_profile'},
)

def send_order_email(to_email, order_number, items, total, customer_name):
    items_text = ""
    for item in items:
        qty = item.get('quantity', 1)
        price = item.get('price', 0)
        items_text += f"{item['name']} x{qty}: ${price * qty:.2f}\n"

    subject = f"Order Confirmation #{order_number} - Bean & Brew Coffee"
    body = f"""
Dear {customer_name},

Thank you for your order!

Order #{order_number}
--------------------
{items_text}
Total: ${total:.2f}
--------------------

📍 Pickup Location: Iskoskuja 3 C 111, Vantaa
⏰ Estimated Ready Time: 15-20 minutes

🔗 Track your order: http://127.0.0.1:5000/track-order/{order_number}

Thank you for choosing Bean & Brew Coffee!

Questions? Contact us: info@beanandbrew.com | +358 413114312
"""

    try:
        msg = MIMEMultipart()
        msg['From'] = f"Bean & Brew Coffee <{GMAIL_USER}>"
        msg['To'] = to_email
        msg['Subject'] = subject
        msg['Reply-To'] = GMAIL_USER
        msg['X-Priority'] = '3'
        msg['X-Mailer'] = 'Bean & Brew Coffee Shop'
        msg.attach(MIMEText(body, 'plain'))

        server = smtplib.SMTP('smtp.gmail.com', 587)
        server.starttls()
        server.login(GMAIL_USER, GMAIL_PASSWORD)
        server.send_message(msg)
        server.quit()
        print(f"✅ Email sent to {to_email}")
        return True
    except Exception as e:
        print(f"❌ Email failed: {e}")
        return False

# ============ TRANSLATIONS DICTIONARY ============
translations = {
    'en': {
        'home': 'Home', 'menu': 'Menu', 'cart': 'Cart', 'login': 'Login', 'register': 'Register',
        'logout': 'Logout', 'profile': 'Profile', 'orders': 'Orders', 'favorites': 'Favorites',
        'reviews': 'Reviews', 'wifi': 'WiFi', 'my_receipts': 'My Receipts', 'loyalty_rewards': 'Loyalty Rewards',
        'refer_friend': 'Refer a Friend', 'loyalty_points': 'Loyalty Points', 'edit_profile': 'Edit Profile',
        'full_name': 'Full Name', 'referral_code': 'Referral Code', 'claim_reward': 'Claim Reward',
        'my_orders': 'My Orders', 'update_profile': 'Update Profile', 'checkout': 'Checkout',
        'add_to_cart': 'Add to Cart', 'total': 'Total', 'remove': 'Remove', 'clear_cart': 'Clear Cart',
        'cart_empty': 'Your cart is empty', 'browse_menu': 'Browse Menu', 'order_summary': 'Order Summary',
        'subtotal': 'Subtotal', 'tax': 'Tax (10%)', 'delivery_fee': 'Delivery Fee', 'place_order': 'Place Order',
        'payment_method': 'Payment Method', 'delivery_info': 'Delivery Information', 'pickup_at_store': 'Pickup at store',
        'username': 'Username', 'email': 'Email', 'phone': 'Phone', 'password': 'Password', 'birthday': 'Birthday',
        'delete_account': 'Delete Account', 'pending': 'Pending', 'completed': 'Completed', 'cancelled': 'Cancelled',
        'processing': 'Processing', 'no_items_found': 'No items found', 'all': 'All', 'coffee': 'Coffee',
        'tea': 'Tea', 'pastry': 'Pastry', 'other_drinks': 'Other Drinks', 'newsletter_title': 'Subscribe to Our Newsletter',
        'employee_dashboard': 'Employee Dashboard', 'welcome_back': 'Welcome back', 'total_orders_today': 'Total Orders Today',
        'pending_orders': 'Pending Orders', 'completed_today': 'Completed Today', 'revenue_today': 'Revenue Today',
        'inventory_management': 'Inventory Management', 'customer_management': 'Customer Management',
        'order_management': 'Order Management', 'back_to_dashboard': 'Back to Dashboard', 'total_products': 'Total Products',
        'low_stock': 'Low Stock', 'out_of_stock': 'Out of Stock', 'total_value': 'Total Value',
        'search_products': 'Search products by name...', 'no_products_found': 'No products found',
        'in_stock': 'In Stock', 'active': 'Active', 'inactive': 'Inactive', 'refresh': 'Refresh',
        'preparing': 'Preparing', 'ready': 'Ready', 'status': 'Status', 'order_number': 'Order Number',
        'items': 'Items', 'customer': 'Customer', 'guest': 'Guest', 'order_items': 'Order Items',
        'employee_login': 'Employee Login', 'staff_access_only': 'Staff access only', 'login_as_employee': 'Login as Employee',
        'total_customers': 'Total Customers', 'new_this_month': 'New This Month', 'total_revenue': 'Total Revenue',
        'search_customers': 'Search by name, email, or phone...', 'joined': 'Joined', 'points': 'Points', 'track_order': 'Track Order',
        'chatbot_orders_title': 'Chatbot Orders', 'chatbot_checkout': 'Chatbot Checkout', 'member_since': 'Member Since',
        'total_orders': 'Total Orders', 'free_coffees': 'Free Coffees', 'friends_referred': 'Friends Referred',
        'edit_profile': 'Edit Profile', 'username_cannot_change': 'Username cannot be changed', 'birthday_reward': 'Get 50 bonus points on your birthday!',
        'danger_zone': 'Danger Zone', 'delete_account_warning': 'Once you delete your account, there is no going back.', 'delete_account': 'Delete Account',
        'track_orders': 'Track Your Orders', 'order_summary': 'Order Summary', 'subtotal': 'Subtotal', 'tax': 'Tax (10%)', 'delivery_fee': 'Delivery Fee',
        'total': 'Total','preparing': 'Preparing', 'ready': 'Ready', 'completed': 'Completed', 'pending': 'Pending', 'cancelled': 'Cancelled', 'view_receipt': 'View Receipt',
        'view_details': 'View Details', 'currency': 'Currency', 'order_items': 'Order Items', 'order_number': 'Order Number', 'date': 'Date', 'status': 'Status',
        'amount': 'Amount', 'track_order_placeholder': 'Enter order number (e.g., ORD87DB035E)', 'track_button': 'Track Order', 'track_order': 'Track Your Order',
        'track_order_desc': 'Enter your order number to check status', 'time': 'Time', 'checkout_order': 'Checkout This Order', 'reorder': 'Reorder', 'conceal_order': 'Conceal Order',
        'delete_order': 'Delete Order', 'chatbot_orders_desc': 'Orders placed through our AI Barista Assistant', 'complete_order_title': 'Complete Your Order',
        'payment_details': 'Payment Details', 'payment_status': 'Payment Status', 'unpaid': 'UNPAID', 'name': 'Name', 'phone': 'Phone', 'delivery_address_placeholder': 'Enter your delivery address',
        'pickup_instruction': 'Leave empty for store pickup', 'credit_card': 'Credit Card', 'paypal': 'PayPal', 'mobilepay': 'MobilePay', 'cash_on_pickup': 'Cash on Pickup',
        'confirm_info_correct': 'I confirm that all information is correct', 'subscribe': 'Subscribe', 'newsletter': 'Newsletter', 'blog': 'Blog', 'learn_more': 'Learn More',
        'newsletter_subtitle': 'Get the latest coffee news, exclusive offers, and brewing tips delivered to your inbox', 'newsletter_card_title': 'Stay Updated with Bean & Brew',
        'name_placeholder': 'Enter your full name', 'email_placeholder': 'Enter your email address', 'privacy_consent': 'I agree to receive emails from Bean & Brew Coffee',
        'subscribe_button': 'Subscribe Now', 'benefits_title': 'Why Subscribe?', 'benefit_1_title': 'Exclusive Offers', 'benefit_1_desc': 'Get special discounts and promotions only for subscribers',
        'benefit_2_title': 'Free Coffee', 'benefit_2_desc': 'Win free coffee every month in our subscriber giveaways', 'benefit_3_title': 'Early Access',
        'benefit_3_desc': 'Be the first to know about new coffee blends', 'benefit_4_title': 'Brewing Tips', 'benefit_4_desc': 'Learn professional brewing techniques at home',
        'newsletter_success': 'Thank you for subscribing! Check your email for confirmation.', 'newsletter_already_subscribed': 'This email is already subscribed to our newsletter.',
        'newsletter_error': 'Something went wrong. Please try again.', 'newsletter_check_email': 'Please check your email to confirm your subscription!', 'newsletter_confirmed': 'Email confirmed! Thank you for subscribing!',
        'newsletter_invalid_token': 'Invalid or expired confirmation link.', 'newsletter_unsubscribed': 'You have been unsubscribed from our newsletter.',
        'coffee_menu_description': 'Experience the perfect blend of aroma and taste. Our coffee is sourced from the finest beans and roasted to perfection.',
        'my_favorites': 'My Favorites', 'favorite_items': 'Your favorite coffee items', 'review_items': 'Review your items before checkout', 'cart_empty_msg': "Looks like you haven't added any items yet",
        'notifications_enabled': 'Notifications enabled! You will receive order updates.', 'notifications_blocked': 'Notifications blocked. Please check your browser settings.',
        'no_favorites': 'No favorites yet', 'no_favorites_msg': 'Browse our menu and click the heart icon to add your favorite items!', 'removed_from_favorites': 'Removed from favorites!',
        'added_to_cart': 'Added to cart!', 'login_title': 'Login to your account', 'admin_register': 'Admin Register', 'register_here': 'Register here', 'register_title': 'Create a new account',
        'register_subtitle': 'Join Bean & Brew Coffee today', 'already_have_account': 'Already have an account?', 'login_instead': 'Login instead', 'create_account': 'Create Account',
        'confirm_password': 'Confirm Password', 'welcome_back': 'Welcome Back!', 'remember_me': 'Remember me', 'forgot_password': 'Forgot Password?', 'or_login_with': 'Or login with',
        'no_account': "Don't have an account", 'continue_as_guest': 'Continue as Guest', 'join_us': 'Join Us', 'username_placeholder': 'Choose a username', 'email_placeholder': 'Enter your email',
        'password_placeholder': 'Create a password', 'confirm_password_placeholder': 'Confirm your password', 'full_name_placeholder': 'Enter your full name (optional)',
        'phone_placeholder': 'Enter your phone number (optional)', 'referral_discount': 'You will get 50 bonus points on signup!', 'and': 'and', 'terms_conditions': 'Terms & Conditions',
        'privacy_policy': 'Privacy Policy', 'view_all_receipts': 'View all your receipts', 'action': 'Action', 'download_pdf': 'Download PDF', 'no_receipts': 'No receipts yet',
        'no_receipts_msg': "You haven't completed any orders yet. Place an order to see your receipts here.", 'refer_friend': 'Refer a Friend', 'share_code_earn_coffee': 'Share your code and earn free coffee!',
        'your_referral_code': 'Your Referral Code', 'share_this_link': 'Share this link:', 'how_it_works': 'How it Works', 'step1': 'Share your unique referral code with friends',
        'step2': 'Friend signs up using your code', 'step3': 'Friend places their first order', 'step4': 'You both get 100 bonus loyalty points!', 'your_referrals': 'Your referrals:',
        'friends_joined': 'friends joined', 'points_earned': 'Points earned', 'scan_qr_code': 'Scan QR Code to Share', 'qr_instruction': 'Friends can scan this QR code with their phone to get your referral link instantly!',
        'your_referrals_list': 'Your Referrals', 'friend': 'Friend', 'date_joined': 'Date Joined', 'points_earned_table': 'Points Earned', 'no_referrals_yet': 'No referrals yet. Share your code!',
        'download_qr': 'Download QR Code', 'copy_code': 'Copy Code', 'referral_code_copied': 'Referral code copied!', 'referral_link_copied': 'Referral link copied!',
        'qr_not_ready': 'QR code is not ready yet. Please try again.', 'coffee_times_blog': 'Coffee Times Blog', 'blog_subtitle': 'Stories, news, and tips from Bean & Brew Coffee',
        'no_articles': 'No articles yet', 'no_articles_msg': 'Check back soon for coffee news and stories!', 'add_article': 'Add Article', 'back_to_blog': 'Back to Blog',
        'contact_us': 'Contact Us', 'contact_subtitle': 'Get in touch with us. We would love to hear from you!', 'our_location': 'Our Location', 'phone_number': 'Phone Number',
        'email_us': 'Email Us', 'send_us_message': 'Send Us a Message', 'your_name': 'Your Name', 'your_email': 'Your Email', 'subject': 'Subject', 'subject_placeholder': 'What is this regarding?',
        'message': 'Message', 'message_placeholder': 'Write your message here...', 'send_message': 'Send Message', 'mon_fri_9_17': 'Mon-Fri 9AM - 5PM', 'response_time': 'Response within 24 hours',
        'form_description': 'Fill out the form below and we will get back to you as soon as possible.', 'find_us': 'Find Us', 'map_description': 'Visit our cozy coffee shop in Vantaa',
        'opening_hours': 'Opening Hours', 'name_placeholder': 'Enter your full name', 'monday_friday': 'Monday - Friday', 'saturday_sunday': 'Saturday - Sunday', 'benefits_subtitle': 'Join our coffee community and enjoy exclusive perks!',
        'complete_order': 'Complete Order', 'cancel_order': 'Cancel Order', 'download_receipt': 'Download Receipt', 'loyalty_program': 'Loyalty Program', 'loyalty_subtitle': 'Earn points with every purchase and get free rewards!',
        'your_loyalty_points': 'Your Loyalty Points', 'earn_more_points': 'Earn 100 points for a free coffee!', 'points_note': '1 point = $1 spent', 'how_to_earn': 'How to Earn Points',
        'make_purchase': 'Make a Purchase', 'earn_points_per_dollar': 'Earn 1 point for every $1 spent', 'birthday_bonus': 'Birthday Bonus', 'birthday_bonus_desc': 'Get 50 bonus points on your birthday!',
        'refer_friend_desc': 'Earn 100 points for each referral', 'available_rewards': 'Available Rewards', 'redeem': 'Redeem', 'points': 'points', 'no_rewards': 'No rewards available at the moment.',
        'redeem_confirm': 'Redeem reward?', 'redeem_success': 'Reward redeemed successfully!', 'not_enough_points': 'Not enough points!', 'points_needed': 'points needed',
        'about_us': 'About Us', 'about_subtitle': 'Learn more about our coffee story and passion', 'our_story': 'Our Story', 'story_text_1': 'Bean & Brew Coffee was founded in 2015 with a simple mission: to bring the finest coffee experience to coffee lovers everywhere. What started as a small coffee cart at a local market has grown into a beloved coffee shop in the heart of Vantaa.',
        'story_text_2': 'Our journey began when our founder, Aziz Rahman, discovered his passion for coffee while traveling through the coffee regions of Ethiopia and Colombia. Inspired by the rich flavors and the warm hospitality of the coffee farmers, he decided to bring that experience back home.',
        'story_text_3': 'Today, we source our beans directly from sustainable farms, roast them in small batches to perfection, and serve each cup with love and care. Every cup tells a story of craftsmanship, quality, and community.',
        'our_mission': 'Our Mission', 'mission_text': 'To serve exceptional coffee while building a community around shared passion for quality, sustainability, and human connection.',
        'our_vision': 'Our Vision', 'vision_text': 'To be the most loved coffee shop in Finland, known for exceptional quality, warm hospitality, and positive impact on our community and environment.',
        'our_values': 'Our Values', 'values_text': 'Quality, Sustainability, Community, Innovation, and Passion drive everything we do.', 'meet_our_team': 'Meet Our Team',
        'team_aziz_bio': 'Coffee enthusiast with 15+ years of experience. Aziz personally selects and roasts every batch to ensure the highest quality.', 'team_maria_bio': 'Award-winning barista with a passion for latte art and creating memorable coffee experiences for every customer.',
        'team_johan_bio': 'Travels the world to source the finest beans directly from farmers, ensuring fair trade and sustainable practices.', 'our_core_values': 'Our Core Values',
        'value_sustainability': 'Sustainability', 'value_quality': 'Quality First', 'value_community': 'Community', 'value_innovation': 'Innovation', 'team_management': 'Team Management',
        'add_team_member': 'Add Team Member', 'edit_team_member': 'Edit Team Member', 'team_members': 'Team Members', 'name': 'Name', 'position': 'Position', 'display_order': 'Display Order',
        'status': 'Status', 'active': 'Active', 'inactive': 'Inactive', 'actions': 'Actions', 'save': 'Save', 'update': 'Update', 'cancel': 'Cancel', 'delete_confirm': 'Are you sure you want to delete this team member?',
        'no_team_members': 'No Team Members Yet', 'no_team_members_msg': 'Add your first team member to showcase your amazing team!', 'bio': 'Bio', 'active_status': 'Active (show on website)',
        'team_management': 'Team Management', 'add_team_member': 'Add Team Member', 'edit_team_member': 'Edit Team Member', 'team_members': 'Team Members', 'edit': 'Edit',
        'delete': 'Delete', 'admin': 'Admin', 'admin_dashboard': 'Dashboard', 'admin_inventory': 'Inventory', 'admin_customers': 'Customers', 'admin_export': 'Export',
        'admin_newsletter': 'Newsletter', 'admin_panel': 'Admin Panel', 'newsletter_subscribers': 'Newsletter Subscribers', 'admin_access': 'Admin Access', 'coming_soon': 'Coming Soon!', 
        'admin_access_desc': 'Welcome to the administration area', 'sales_dashboard_desc': 'View sales analytics and reports', 'inventory_desc': 'Manage products and stock levels',
        'customers_desc': 'View and manage customer information', 'export_desc': 'Export data to CSV files', 'newsletter_desc': 'Manage newsletter subscribers', 'team_desc': 'Manage team members',
        'chatbot_orders': 'Chatbot Orders', 'chatbot_orders_desc': 'View orders from chatbot', 'employee_dashboard_desc': 'Employee management panel', 'access': 'Access',
        'admin_access_warning': '⚠️ Restricted Area', 'admin_access_warning_desc': 'This area is only for authorized personnel. Any unauthorized access will be logged.',
        'export_csv': 'Export CSV', 'subscribers_list': 'Subscribers List', 'subscribed_date': 'Subscribed Date', 'confirmed': 'Confirmed', 'no_subscribers': 'No Subscribers Yet',
        'no_subscribers_msg': 'No one has subscribed to the newsletter yet.', 'delete_subscriber_confirm': 'Are you sure you want to delete this subscriber?', 'delete_error': 'Error deleting subscriber',
        'welcome_back_admin': 'Welcome back,', 'admin_dashboard_subtitle': 'Manage your coffee shop from one central dashboard', 'low_stock_alerts': 'Low Stock Alerts',
        'top_selling_products': 'Top Selling Products', 'recent_orders': 'Recent Orders', 'monthly_sales_3d': 'Monthly Sales (3D)', 'quick_actions': 'Quick Actions',
        'management_center': 'Management Center', 'manage_inventory': 'Manage Inventory', 'update_stock_products': 'Update stock levels and products', 'customer_list': 'Customer List',
        'view_manage_customers': 'View and manage all customers', 'export_data': 'Export Data', 'download_reports': 'Download reports and data', 'write_blog': 'Write Blog',
        'publish_articles': 'Publish new articles', 'sales_analytics': 'Sales Analytics', 'view_reports_insights': 'View detailed reports and insights', 'newsletter_hub': 'Newsletter Hub',
        'manage_subscribers_campaigns': 'Manage subscribers and campaigns', 'team_management': 'Team Management', 'manage_team_members': 'Add, edit, or remove team members',
        'chatbot_orders': 'Chatbot Orders', 'view_ai_orders': 'View AI assistant orders', 'news_manager': 'News Manager', 'manage_news_articles': 'Manage coffee news articles',
        'employee_portal': 'Employee Portal', 'staff_management': 'Staff management dashboard', 'access_dashboard': 'Access Dashboard', 'manage_subscribers': 'Manage Subscribers',
        'manage_team': 'Manage Team', 'view_orders': 'View Orders', 'manage_news': 'Manage News', 'access_portal': 'Access Portal', 'loading': 'Loading', 'no_products_found': 'No products found',
        'all_stock_healthy': 'All products have healthy stock levels!', 'restock': 'Restock', 'out_of_stock': 'OUT OF STOCK', 'no_sales_data': 'No sales data yet', 'error_loading_data': 'Error loading data',
        'no_orders_yet': 'No orders yet', 'error_loading_orders': 'Error loading orders', 'no_sales_data_available': 'No sales data available', 'view_all_products': 'View All Products',
        'view_full_report': 'View Full Report', 'view_all_orders': 'View All Orders', 'detailed_analytics': 'Detailed Analytics', 'access': 'Access', 'no_orders': 'No Orders Yet',
        'start_shopping': 'Start shopping to see your orders here', 'browse_menu': 'Browse Menu', 'cancel_order': 'Cancel Order', 'estimated_time': 'Estimated Time',
        'pickup_location': 'Pickup Location', 'support': 'Support', 'reorder_success': 'Items added to cart!', 'reorder_error': 'Error reordering. Please try again.',
        'conceal_order_confirm': 'Hide this order?', 'conceal_order_success': 'Order hidden successfully!', 'conceal_order_error': 'Error hiding order.', 'delete_order_confirm': 'Delete this order permanently?',
        'delete_order_success': 'Order deleted successfully!', 'delete_order_error': 'Error deleting order.', 'no_chatbot_orders': 'No Chatbot Orders', 'chatbot_empty_msg': 'You haven\'t placed any orders through the chatbot yet.',
        'start_chatting': 'Start Chatting', 'loyalty_points': 'Points', 'available_rewards': 'Rewards', 'next_reward': 'Next Reward', 'your_code': 'Your Code', 'earn_points': 'Earn',
        'points_on_order': 'points on this order', 'delivery_payment': 'Delivery & Payment', 'enter_coupon': 'Enter coupon code', 'apply': 'Apply', 'item': 'Item', 'qty': 'Qty',
        'price': 'Price', 'not_provided': 'Not provided', 'delivery_address': 'Delivery Address', 'cash_on_delivery': 'Cash on Delivery', 'place_order': 'Place Order',
        'cancel': 'Cancel', 'cancel_checkout_confirm': 'Are you sure you want to cancel checkout? Your coupon will be removed.', 'loyalty_points': 'Loyalty Points', 'delivery_payment': 'Delivery & Payment',
        'apply_coupon': 'Apply Coupon', 'coupon_applied': 'Coupon applied successfully!', 'coupon_error': 'Error applying coupon', 'sales_dashboard': 'Sales Dashboard',
        'sales_dashboard_desc': 'Track your coffee shop\'s performance, analyze trends, and grow your business', 'period': 'Period', 'this_week': 'This Week', 'this_month': 'This Month',
        'this_year': 'This Year', 'all_time': 'All Time', 'total_sales': 'Total Sales', 'happy_customers': 'Happy Customers', 'total_revenue': 'Total Revenue', 'total_orders': 'Total Orders',
        'total_customers': 'Total Customers', 'avg_order_value': 'Avg Order Value', 'sales_trend': 'Sales Trend', 'revenue_overview': 'Revenue Overview', 'category_distribution': 'Category Distribution',
        'top_products': 'Top Products', 'top_selling_products': 'Top Selling Products', 'loading_data': 'Loading data...', 'sales': 'Sales', 'revenue': 'Revenue', 'sold': 'Sold',
        'no_sales_data': 'No sales data available', 'select_both_dates': 'Please select both start and end dates', 'start_date': 'Start Date', 'end_date': 'End Date', 
        'apply': 'Apply', 'inventory_management': 'Inventory Management', 'inventory_desc': 'Track stock levels, manage products, and monitor inventory value', 'low_stock_alert': 'Low Stock Alert',
        'inventory_value': 'Inventory Value', 'add_product': 'Add Product', 'low_stock': 'Low Stock (<10)', 'out_of_stock': 'Out of Stock', 'id': 'ID', 'product': 'Product',
        'category': 'Category', 'price': 'Price', 'stock': 'Stock', 'status': 'Status', 'actions': 'Actions', 'loading': 'Loading', 'product_name': 'Product Name', 'stock_quantity': 'Stock Quantity',
        'description': 'Description', 'save_product': 'Save Product', 'cancel': 'Cancel', 'update_stock': 'Update Stock', 'current_stock': 'Current Stock', 'new_stock_quantity': 'New Stock Quantity',
        'product_added': 'Product added!', 'stock_updated': 'Stock updated!', 'units': 'units', 'in_stock': 'In Stock', 'customer_management': 'Customer Management', 'customer_management_desc': 'Manage your customers, track their orders, and build lasting relationships',
        'new_this_month': 'New This Month', 'total_spent': 'Total Spent', 'customer': 'Customer', 'orders': 'Orders', 'joined': 'Joined', 'edit_customer': 'Edit Customer',
        'save_changes': 'Save Changes', 'customer_details': 'Customer Details', 'member_since': 'Member Since', 'no_customers_found': 'No customers found', 'view': 'View',
        'customer_updated': 'Customer updated successfully!', 'customer_deleted': 'Customer deleted successfully!', 'customer_delete_error': 'Cannot delete customer with existing orders',
        'delete_customer_confirm': 'Delete customer', 'export_reports': 'Export Reports', 'export_desc': 'Download your data in CSV, Excel, or PDF format', 'formats': 'Formats',
        'date_range': 'Date Range', 'customizable': 'Customizable', 'reports': 'Reports', 'types': 'Types', 'sales_report': 'Sales Report', 'sales_report_desc': 'Orders and revenue data',
        'products_report': 'Products Report', 'products_report_desc': 'Inventory and stock levels', 'customers_report': 'Customers Report', 'customers_report_desc': 'Customer data and loyalty',
        'inventory_report': 'Inventory Report', 'inventory_report_desc': 'Stock value and alerts', 'format': 'Format', 'export_sales': 'Export Sales', 'export_products': 'Export Products',
        'export_customers': 'Export Customers', 'export_inventory': 'Export Inventory', 'data_preview': 'Data Preview', 'click_export_preview': 'Click export to see preview',
        'no_data_available': 'No data available', 'customer_reviews': 'Customer Reviews', 'reviews_subtitle': 'What our coffee lovers say about us', 'average_rating': 'Average Rating',
        'total_reviews': 'Total Reviews', 'happy_customers': 'Happy Customers', 'review1_text': 'Best coffee in town! The atmosphere is amazing and the staff is incredibly friendly.',
        'review2_text': 'Their pastries are fresh and delicious. The croissant with a cappuccino is my perfect morning combo.', 'review3_text': 'Love the cozy atmosphere and free WiFi. Perfect place to work remotely while enjoying excellent coffee.',
        'review4_text': 'The chai latte is amazing! Great selection of teas and friendly baristas. Will definitely come back.', 'review5_text': 'Finally found a place that makes perfect espresso. Their loyalty program is great too.',
        'review6_text': 'Amazing customer service! They remembered my order from last week. The blueberry muffin is to die for!', 'coffee_enthusiast': 'Coffee Enthusiast',
        'regular_customer': 'Regular Customer', 'remote_worker': 'Remote Worker', 'tea_lover': 'Tea Lover', 'coffee_addict': 'Coffee Addict', 'loyal_customer': 'Loyal Customer',
        'share_experience': 'Share Your Experience', 'share_experience_msg': "We'd love to hear about your visit to Bean & Brew", 'write_review': 'Write a Review', 'login_write_review': 'Login to Write a Review',
        'write_review_placeholder': 'Write your review here...', 'submit_review': 'Submit Review', 'select_rating': 'Please select a rating', 'review_thanks': 'Thank you for your review!',
        'subscribers_count': 'Subscribers', 'weekly_emails': 'Weekly Emails', 'exclusive_offers': 'Exclusive Offers', 'news_manager': 'News Manager', 'manage_news_articles': 'Manage your coffee news articles',
        'total': 'Total', 'published': 'Published', 'draft': 'Draft', 'add_article': 'Add Article', 'all': 'All', 'news': 'News', 'tips': 'Tips', 'offers': 'Offers', 'articles_list': 'Articles List',
        'id': 'ID', 'title': 'Title', 'category': 'Category', 'author': 'Author', 'date': 'Date', 'status': 'Status', 'actions': 'Actions', 'view': 'View', 'edit': 'Edit', 'delete': 'Delete',
        'delete_confirm': 'Are you sure you want to delete this article?', 'delete_error': 'Error deleting article', 'published_date': 'Published Date', 'write_new_story': 'Write a New Story',
        'share_coffee_knowledge': 'Share your coffee knowledge with the world', 'auto_translate': 'Auto Translate', 'languages': 'Languages', 'publish_globally': 'Publish Globally',
        'yes': 'Yes', 'estimated_time': 'Estimated Time', 'minutes': 'minutes', 'title': 'Title', 'english': 'English', 'enter_title': 'Enter a captivating title...', 'characters': 'characters',
        'category': 'Category', 'news_updates': 'News & Updates', 'brewing_tips': 'Brewing Tips & Tricks', 'special_offers': 'Special Offers', 'giveaways': 'Giveaways', 'coffee_lifestyle': 'Coffee Lifestyle',
        'coffee_history': 'Coffee History', 'health_benefits': 'Health Benefits', 'image_url': 'Image URL', 'or': 'or', 'preview': 'Preview', 'leave_empty_default': 'Leave empty to use default coffee image',
        'content': 'Content', 'write_article_auto_translate': 'Write your amazing article here... It will be automatically translated to Finnish, Swedish, and Persian!',
        'publish_auto_translate': 'Publish & Auto-Translate', 'cancel': 'Cancel', 'read_more': 'Read More', 'free_wifi': 'Free WiFi', 'wifi_subtitle': 'Stay connected while enjoying your favorite coffee',
        'speed': 'Speed', 'charging_stations': 'Charging Stations', 'available': 'Available', 'secure': 'Secure', 'network_name': 'Network Name', 'password': 'Password', 'quick_connect': 'Quick Connect',
        'scan_qr_connect': 'Scan the QR code to connect instantly', 'available_all_tables': 'Available at all table stands', 'high_speed': 'High Speed', 'high_speed_desc': 'Up to 100 Mbps download speed',
        'charging_stations_desc': 'Multiple outlets and USB ports at every table', 'secure_connection': 'Secure Connection', 'secure_connection_desc': 'Enterprise-grade security to protect your privacy',
        'wifi_tips': 'Tips for Best Experience', 'tip1': 'Connect to the network and enter the password', 'tip2': 'Accept the terms and conditions on the login page',
        'tip3': 'Enjoy unlimited browsing for the duration of your visit', 'tip4': 'Need help? Ask our baristas for assistance', 'click_to_learn': 'Click to learn more',
        'click_to_close': 'Click to close', 'speed_detail_1': '100 Mbps download speed for smooth browsing', 'speed_detail_2': '50 Mbps upload speed for video calls', 'speed_detail_3': 'Low latency for gaming and streaming',
        'speed_detail_4': 'Unlimited data usage with fair use policy', 'charging_detail_1': 'USB-C and USB-A ports at every table', 'charging_detail_2': 'Standard power outlets for laptops',
        'charging_detail_3': 'Wireless charging pads available', 'charging_detail_4': 'Fast charging support for all devices', 'secure_detail_1': 'WPA2-Enterprise encryption for secure connection',
        'secure_detail_2': 'Firewall protection against cyber threats', 'secure_detail_3': 'Regular security updates and monitoring', 'secure_detail_4': 'Your data and privacy are always protected',
        'team_members': 'Team Members', 'years_experience': 'Years Experience', 'coffee_awards': 'Coffee Awards', 'value_sustainability_desc': 'We source from fair-trade farms and use eco-friendly packaging',
        'value_quality_desc': 'Never compromise on quality from bean to cup', 'value_community_desc': 'Building a welcoming space for everyone', 'value_innovation_desc': 'Constantly evolving to bring you the best experience',
   },
    'fi': {
        'home': 'Koti', 'menu': 'Ruokalista', 'cart': 'Ostoskori', 'login': 'Kirjaudu', 'register': 'Rekisteröidy',
        'logout': 'Kirjaudu ulos', 'profile': 'Profiili', 'orders': 'Tilaukset', 'favorites': 'Suosikit',
        'reviews': 'Arvostelut', 'wifi': 'WiFi', 'my_receipts': 'Kuitit', 'loyalty_rewards': 'Kanta-asiakasedut',
        'refer_friend': 'Suosittele kaveria', 'loyalty_points': 'Kanta-asiakaspisteet', 'edit_profile': 'Muokkaa profiilia',
        'full_name': 'Koko nimi', 'referral_code': 'Suosittelukoodi', 'claim_reward': 'Nosta palkinto',
        'my_orders': 'Tilaukseni', 'update_profile': 'Päivitä profiili', 'checkout': 'Kassa',
        'add_to_cart': 'Lisää ostoskoriin', 'total': 'Yhteensä', 'remove': 'Poista', 'clear_cart': 'Tyhjennä ostoskori',
        'cart_empty': 'Ostoskorisi on tyhjä', 'browse_menu': 'Selaa ruokalistaa', 'order_summary': 'Tilausyhteenveto',
        'subtotal': 'Välisumma', 'tax': 'Verot (10%)', 'delivery_fee': 'Toimitusmaksu', 'place_order': 'Tee tilaus',
        'payment_method': 'Maksutapa', 'delivery_info': 'Toimitustiedot', 'pickup_at_store': 'Nouto myymälästä',
        'username': 'Käyttäjätunnus', 'email': 'Sähköposti', 'phone': 'Puhelin', 'password': 'Salasana',
        'birthday': 'Syntymäpäivä', 'delete_account': 'Poista tili', 'pending': 'Odottaa', 'completed': 'Valmis',
        'cancelled': 'Peruttu', 'processing': 'Käsitellään', 'no_items_found': 'Ei tuotteita', 'all': 'Kaikki',
        'coffee': 'Kahvi', 'tea': 'Tee', 'pastry': 'Leivonnaiset', 'other_drinks': 'Muut juomat',
        'employee_dashboard': 'Työntekijän kojelauta', 'welcome_back': 'Tervetuloa takaisin', 'total_orders_today': 'Tilauksia tänään',
        'pending_orders': 'Odottaa käsittelyä', 'completed_today': 'Valmistuneet tänään', 'revenue_today': 'Päivän liikevaihto',
        'inventory_management': 'Varastonhallinta', 'customer_management': 'Asiakashallinta',
        'order_management': 'Tilausten hallinta', 'back_to_dashboard': 'Takaisin kojelautaan', 'total_products': 'Tuotteita yhteensä',
        'low_stock': 'Vähän varastossa', 'out_of_stock': 'Loppu varastosta', 'total_value': 'Kokonaisarvo',
        'search_products': 'Hae tuotteita nimellä...', 'no_products_found': 'Tuotteita ei löytynyt',
        'in_stock': 'Varastossa', 'active': 'Aktiivinen', 'inactive': 'Epäaktiivinen', 'refresh': 'Päivitä',
        'preparing': 'Valmistellaan', 'ready': 'Valmis', 'status': 'Tila', 'order_number': 'Tilausnumero',
        'items': 'Tuotteet', 'customer': 'Asiakas', 'guest': 'Vieras', 'order_items': 'Tilauksen tuotteet', 'coming_soon': 'Tulossa pian!',
        'employee_login': 'Työntekijän kirjautuminen', 'staff_access_only': 'Vain henkilökunnalle', 'login_as_employee': 'Kirjaudu työntekijänä',
        'total_customers': 'Asiakkaat yhteensä', 'new_this_month': 'Uudet tässä kuussa', 'total_revenue': 'Kokonaisliikevaihto',
        'search_customers': 'Hae nimellä, sähköpostilla tai puhelimella...', 'joined': 'Liittynyt', 'points': 'Pisteet',
        'track_order': 'Seuraa tilausta', 'chatbot_orders_title': 'Chatbot tilaukset', 'chatbot_checkout': 'Chatbot kassa',
        'member_since': 'Jäsen alkaen', 'total_orders': 'Tilaukset yhteensä', 'free_coffees': 'Ilmaiset kahvit', 'newsletter_title': 'Tilaa uutiskirjeemme',
        'friends_referred': 'Kutsutut ystävät', 'edit_profile': 'Muokkaa profiilia', 'username_cannot_change': 'Käyttäjätunnusta ei voi muuttaa',
        'birthday_reward': 'Saat 50 bonuspistettä syntymäpäivänäsi!', 'danger_zone': 'Vaara-alue', 'delete_account_warning': 'Kun poistat tilisi, sitä ei voi palauttaa.',
        'delete_account': 'Poista tili', 'track_orders': 'Seuraa tilauksiasi', 'order_summary': 'Tilausyhteenveto', 'subtotal': 'Välisumma', 'tax': 'Verot (10%)',
        'delivery_fee': 'Toimitusmaksu', 'total': 'Yhteensä', 'preparing': 'Valmistellaan', 'ready': 'Valmis', 'completed': 'Valmistunut', 'pending': 'Odottaa',
        'cancelled': 'Peruttu', 'view_receipt': 'Näytä kuitti', 'view_details': 'Näytä tiedot', 'currency': 'Valuutta', 'order_items': 'Tilauksen tuotteet',
        'order_number': 'Tilausnumero', 'date': 'Päivämäärä', 'status': 'Tila', 'amount': 'Määrä', 'track_order_placeholder': 'Syötä tilausnumero (esim. ORD87DB035E)',
        'track_button': 'Seuraa tilausta', 'track_order': 'Seuraa tilaustasi', 'track_order_desc': 'Syötä tilausnumerosi nähdäksesi tilanteen', 'time': 'Aika', 'checkout_order': 'Tilaa tämä tilaus',
        'reorder': 'Tilaa uudelleen', 'conceal_order': 'Piilota tilaus', 'delete_order': 'Poista tilaus', 'chatbot_orders_desc': 'Tilaukset tehty AI Barista-avustajan kautta',
        'complete_order_title': 'Tilaa', 'payment_details': 'Maksutiedot', 'payment_status': 'Maksutila', 'unpaid': 'MAKSAMATTA', 'name': 'Nimi', 'phone': 'Puhelin',
        'delivery_address_placeholder': 'Syötä toimitusosoite', 'pickup_instruction': 'Jätä tyhjäksi noutoa varten', 'credit_card': 'Luottokortti', 'paypal': 'PayPal',
        'mobilepay': 'MobilePay', 'cash_on_pickup': 'Käteinen noudettaessa', 'confirm_info_correct': 'Vahvistan, että tiedot ovat oikein', 'subscribe': 'Tilaa', 'newsletter': 'Uutiskirje', 'blog': 'Blogi',
        'newsletter_subtitle': 'Saat uusimmat kahviuutiset, eksklusiiviset tarjoukset ja kahvinkeittovinkit sähköpostiisi', 'newsletter_card_title': 'Pysy ajan tasalla Bean & Brew:n kanssa',
        'name_placeholder': 'Syötä koko nimesi', 'email_placeholder': 'Syötä sähköpostiosoitteesi', 'privacy_consent': 'Hyväksyn sähköpostien vastaanottamisen Bean & Brew Coffeelta',
        'subscribe_button': 'Tilaa nyt', 'benefits_title': 'Miksi tilata?', 'benefit_1_title': 'Eksklusiiviset tarjoukset', 'benefit_1_desc': 'Saat erikoistarjouksia ja alennuksia vain tilaajille',
        'benefit_2_title': 'Ilmainen kahvi', 'benefit_2_desc': 'Voita ilmaista kahvia joka kuukausi tilaajien arvonnoissa', 'benefit_3_title': 'Ensimmäisenä tiedossa',
        'benefit_3_desc': 'Saat tietää uusista kahvisekoituksista ensimmäisenä', 'benefit_4_title': 'Kahvinkeittovinkit', 'benefit_4_desc': 'Opi ammattimaiset kahvinkeittotekniikat kotona',
        'newsletter_success': 'Kiitos tilauksesta! Tarkista sähköpostisi vahvistusta varten.', 'newsletter_already_subscribed': 'Tämä sähköposti on jo tilattu uutiskirjeeseemme.',
        'newsletter_error': 'Jotain meni pieleen. Yritä uudelleen.', 'newsletter_check_email': 'Tarkista sähköpostisi vahvistaaksesi tilauksesi!', 'newsletter_confirmed': 'Sähköposti vahvistettu! Kiitos tilauksesta!',
        'newsletter_invalid_token': 'Virheellinen tai vanhentunut vahvistuslinkki.', 'newsletter_unsubscribed': 'Olet poistanut tilauksesi uutiskirjeestämme.',
        'coffee_menu_description': 'Koe täydellinen aromien ja makujen yhdistelmä. Kahvimme on tuotettu parhaista pavuista ja paahdettu täydellisyyteen.',
        'my_favorites': 'Omat suosikit', 'favorite_items': 'Suosikki kahvituotteesi', 'review_items': 'Tarkista tuotteet ennen kassalle siirtymistä', 'cart_empty_msg': 'Näyttää siltä, ettet ole lisännyt vielä tuotteita',
        'notifications_enabled': 'Ilmoitukset käytössä!', 'notifications_blocked': 'Ilmoitukset estetty', 'no_favorites': 'Ei suosikkeja vielä', 'learn_more': 'Lue lisää',
        'no_favorites_msg': 'Selaa ruokalistaamme ja klikkaa sydämen kuvaketta lisätäksesi suosikkituotteesi!', 'removed_from_favorites': 'Poistettu suosikeista!',
        'added_to_cart': 'Lisätty ostoskoriin!', 'login_title': 'Kirjaudu tilillesi', 'admin_register': 'Ylläpitäjän rekisteröinti', 'register_here': 'Rekisteröidy tästä',
        'register_title': 'Luo uusi tili', 'register_subtitle': 'Liity Bean & Brew Coffeeen tänään', 'already_have_account': 'Onko sinulla jo tili?', 'login_instead': 'Kirjaudu sen sijaan',
        'create_account': 'Luo tili', 'confirm_password': 'Vahvista salasana', 'welcome_back': 'Tervetuloa takaisin!', 'remember_me': 'Muista minut', 'forgot_password': 'Unohditko salasanan?',
        'or_login_with': 'Tai kirjaudu sisään', 'no_account': 'Ei vielä tiliä', 'continue_as_guest': 'Jatka vieraana', 'join_us': 'Liity jäseneksi', 'username_placeholder': 'Valitse käyttäjätunnus',
        'email_placeholder': 'Syötä sähköpostisi', 'password_placeholder': 'Luo salasana', 'confirm_password_placeholder': 'Vahvista salasanasi', 'full_name_placeholder': 'Syötä koko nimesi (valinnainen)',
        'phone_placeholder': 'Syötä puhelinnumerosi (valinnainen)', 'referral_discount': 'Saat 50 bonuspistettä rekisteröityessäsi!', 'and': 'ja', 'terms_conditions': 'Käyttöehdot',
        'privacy_policy': 'Tietosuojakäytäntö', 'view_all_receipts': 'Näytä kaikki kuitit', 'action': 'Toiminto', 'download_pdf': 'Lataa PDF', 'no_receipts': 'Ei kuitteja vielä',
        'no_receipts_msg': 'Et ole vielä tehnyt yhtään tilausta. Tee tilaus nähdäksesi kuitit täällä.', 'refer_friend': 'Suosittele kaveria', 'share_code_earn_coffee': 'Jaa koodisi ja ansaitse ilmaista kahvia!',
        'your_referral_code': 'Sinun suosittelukoodisi', 'share_this_link': 'Jaa tämä linkki:', 'how_it_works': 'Miten se toimii', 'step1': 'Jaa ainutlaatuinen suosittelukoodisi ystäville',
        'step2': 'Ystävä rekisteröityy koodillasi', 'step3': 'Ystävä tekee ensimmäisen tilauksensa', 'step4': 'Saatte molemmat 100 bonuspistettä!', 'your_referrals': 'Sinun suosittelusi:',
        'friends_joined': 'kaveria liittynyt', 'points_earned': 'Ansaitut pisteet', 'scan_qr_code': 'Skannaa QR-koodi', 'qr_instruction': 'Ystävät voivat skannata tämän QR-koodin saadakseen suosittelulinkkisi!',
        'your_referrals_list': 'Omat suosittelut', 'friend': 'Kaveri', 'date_joined': 'Liittymispäivä', 'points_earned_table': 'Ansaitut pisteet', 'no_referrals_yet': 'Ei vielä suosituksia. Jaa koodisi!',
        'download_qr': 'Lataa QR-koodi', 'copy_code': 'Kopioi koodi', 'referral_code_copied': 'Suosittelukoodi kopioitu!', 'referral_link_copied': 'Suosittelulinkki kopioitu!',
        'qr_not_ready': 'QR-koodi ei ole vielä valmis. Yritä uudelleen.', 'coffee_times_blog': 'Kahvi Times Blogi', 'blog_subtitle': 'Tarinoita, uutisia ja vinkkejä Bean & Brew Coffeelta',
        'no_articles': 'Ei artikkeleita vielä', 'no_articles_msg': 'Palaa pian kahviuutisia ja tarinoita varten!', 'add_article': 'Lisää artikkeli', 'back_to_blog': 'Takaisin blogiin',
        'contact_us': 'Ota yhteyttä', 'contact_subtitle': 'Ota meihin yhteyttä. Kuulemme mielellämme sinusta!', 'our_location': 'Sijaintimme', 'phone_number': 'Puhelinnumero',
        'email_us': 'Sähköposti', 'send_us_message': 'Lähetä viesti', 'your_name': 'Nimesi', 'your_email': 'Sähköpostisi', 'subject': 'Aihe', 'subject_placeholder': 'Mistä on kyse?',
        'message': 'Viesti', 'message_placeholder': 'Kirjoita viestisi tähän...', 'send_message': 'Lähetä viesti', 'mon_fri_9_17': 'Ma-Pe 9-17', 'response_time': 'Vastaus 24 tunnin sisällä',
        'form_description': 'Täytä alla oleva lomake, niin palaamme sinuun mahdollisimman pian.', 'find_us': 'Löydä meidät', 'map_description': 'Vieraile viihtyisässä kahvilassamme Vantaalla',
        'opening_hours': 'Aukioloajat', 'name_placeholder': 'Syötä koko nimesi', 'monday_friday': 'Maanantai - Perjantai', 'saturday_sunday': 'Lauantai - Sunnuntai', 'benefits_subtitle': 'Liity kahviyhteisöömme ja nauti yksinoikeusetuuksista!',
        'complete_order': 'Tilaa', 'cancel_order': 'Peruuta tilaus', 'download_receipt': 'Lataa kuitti', 'loyalty_program': 'Kanta-asiakasohjelma', 'loyalty_subtitle': 'Ansaitse pisteitä jokaisesta ostoksesta ja saa ilmaisia palkintoja!',
        'your_loyalty_points': 'Kanta-asiakaspisteesi', 'earn_more_points': 'Ansaitse 100 pistettä ilmaiseen kahviin!', 'points_note': '1 piste = 1 € käytetty',
        'how_to_earn': 'Kuinka ansaita pisteitä', 'make_purchase': 'Tee ostos', 'earn_points_per_dollar': 'Ansaitse 1 piste jokaista 1 € käytettyä kohden', 'birthday_bonus': 'Syntymäpäiväbonus',
        'birthday_bonus_desc': 'Saat 50 bonuspistettä syntymäpäivänäsi!', 'refer_friend_desc': 'Ansaitse 100 pistettä jokaisesta suosituksesta', 'available_rewards': 'Saatavilla olevat palkinnot',
        'redeem': 'Lunasta', 'points': 'pistettä', 'no_rewards': 'Palkintoja ei ole tällä hetkellä saatavilla.', 'redeem_confirm': 'Lunastetaanko palkinto?', 'redeem_success': 'Palkinto lunastettu onnistuneesti!',
        'not_enough_points': 'Ei tarpeeksi pisteitä!', 'points_needed': 'pistettä tarvitaan', 'about_us': 'Meistä', 'about_subtitle': 'Lue lisää kahvitarinastamme ja intohimostamme',
        'our_story': 'Tarina', 'story_text_1': 'Bean & Brew Coffee perustettiin vuonna 2015 yksinkertaisella tehtävällä: tuoda parasta kahvikokemusta kahvin ystäville kaikkialla. Se, mikä alkoi pienenä kahvivaununa paikallisilla markkinoilla, on kasvanut rakastetuksi kahvilaksi Vantaan sydämessä.',
        'story_text_2': 'Matkamme alkoi, kun perustajamme Aziz Rahman löysi intohimonsa kahviin matkustaessaan Etiopian ja Kolumbian kahvialueilla. Inspiroituneena rikkaista mauista ja kahvinviljelijöiden lämpimästä vieraanvaraisuudesta, hän päätti tuoda kokemuksen kotiin.',
        'story_text_3': 'Tänä päivänä hankimme pavut suoraan kestäviltä tiloilta, paahdamme ne pienissä erissä täydellisyyteen ja tarjoilemme jokaisen kupin rakkaudella ja huolella. Jokainen kuppi kertoo tarinaa ammattitaidosta, laadusta ja yhteisöstä.',
        'our_mission': 'Tehtävämme', 'mission_text': 'Tarjota poikkeuksellista kahvia samalla kun rakennamme yhteisöä, joka jakaa intohimon laatuun, kestävyyteen ja inhimillisiin yhteyksiin.',
        'our_vision': 'Visio', 'vision_text': 'Olla rakastetuin kahvila Suomessa, joka tunnetaan poikkeuksellisesta laadusta, lämpimästä vieraanvaraisuudesta ja positiivisesta vaikutuksesta yhteisöömme ja ympäristöön.',
        'our_values': 'Arvomme', 'values_text': 'Laatu, kestävyys, yhteisö, innovaatio ja intohimo ohjaavat kaikkea tekemistämme.', 'meet_our_team': 'Tapaa tiimimme',
        'team_aziz_bio': 'Kahviharrastaja, jolla on yli 15 vuoden kokemus. Aziz valitsee ja paahtaa henkilökohtaisesti jokaisen erän varmistaakseen korkeimman laadun.',
        'team_maria_bio': 'Palkittu barista, jolla on intohimo lattec- taiteeseen ja unohtumattomien kahvikokemusten luomiseen jokaiselle asiakkaalle.',
        'team_johan_bio': 'Matkustaa ympäri maailmaa hankkimaan parhaat pavut suoraan viljelijöiltä varmistaen reilun kaupan ja kestävät käytännöt.',
        'our_core_values': 'Perusarvomme', 'value_sustainability': 'Kestävyys', 'value_quality': 'Laatu ensin', 'value_community': 'Yhteisö', 'value_innovation': 'Innovointi',
        'team_management': 'Tiimin hallinta', 'add_team_member': 'Lisää tiimin jäsen', 'edit_team_member': 'Muokkaa tiimin jäsentä', 'team_members': 'Tiimin jäsenet',
        'name': 'Nimi', 'position': 'Tehtävä', 'display_order': 'Järjestysnumero', 'status': 'Tila', 'active': 'Aktiivinen', 'inactive': 'Epäaktiivinen', 'actions': 'Toiminnot',
        'save': 'Tallenna', 'update': 'Päivitä', 'cancel': 'Peruuta', 'delete_confirm': 'Haluatko varmasti poistaa tämän tiimin jäsenen?', 'no_team_members': 'Ei tiimin jäseniä vielä',
        'no_team_members_msg': 'Lisää ensimmäinen tiimin jäsen esitelläksesi upean tiimisi!', 'bio': 'Kuvaus', 'active_status': 'Aktiivinen (näytetään verkkosivustolla)',
        'team_management': 'Tiimin hallinta', 'add_team_member': 'Lisää tiimin jäsen', 'edit_team_member': 'Muokkaa tiimin jäsentä', 'team_members': 'Tiimin jäsenet',
        'edit': 'Muokkaa', 'delete': 'Poista', 'admin': 'Ylläpitäjä', 'admin_dashboard': 'Hallintapaneeli', 'admin_inventory': 'Varasto', 'admin_customers': 'Asiakkaat',
        'admin_export': 'Vienti', 'admin_newsletter': 'Uutiskirje', 'admin_panel': 'Ylläpitäjän paneeli', 'newsletter_subscribers': 'Uutiskirjeen tilaajat', 'admin_access': 'Ylläpitäjän pääsy',
        'admin_access_desc': 'Tervetuloa hallintoalueelle', 'sales_dashboard_desc': 'Näytä myyntianalytiikka ja raportit', 'inventory_desc': 'Hallitse tuotteita ja varastotasoja',
        'customers_desc': 'Näytä ja hallinnoi asiakastietoja', 'export_desc': 'Vie tiedot CSV-tiedostoiksi', 'newsletter_desc': 'Hallitse uutiskirjeen tilaajia',
        'team_desc': 'Hallitse tiimin jäseniä', 'chatbot_orders': 'Chatbot tilaukset', 'chatbot_orders_desc': 'Näytä chatbotin tilaukset', 'employee_dashboard_desc': 'Työntekijöiden hallintapaneeli',
        'access': 'Pääsy', 'admin_access_warning': '⚠️ Rajoitettu alue', 'admin_access_warning_desc': 'Tämä alue on vain valtuutetulle henkilökunnalle. Luvaton pääsy kirjataan.',
        'export_csv': 'Vie CSV', 'subscribers_list': 'Tilaajaluettelo', 'subscribed_date': 'Tilauspäivä', 'confirmed': 'Vahvistettu', 'no_subscribers': 'Ei tilaajia vielä',
        'no_subscribers_msg': 'Kukaan ei ole vielä tilannut uutiskirjettä.', 'delete_subscriber_confirm': 'Haluatko varmasti poistaa tämän tilaajan?', 'delete_error': 'Virhe tilaajaa poistettaessa',
        'welcome_back_admin': 'Tervetuloa takaisin,', 'admin_dashboard_subtitle': 'Hallitse kahvilasi yhdestä keskitetystä kojelaudasta', 'low_stock_alerts': 'Vähän varastossa -hälytykset',
        'top_selling_products': 'Myydyimmät tuotteet', 'recent_orders': 'Viimeisimmät tilaukset', 'monthly_sales_3d': 'Kuukausittainen myynti (3D)', 'quick_actions': 'Pikatoiminnot',
        'management_center': 'Hallintakeskus', 'manage_inventory': 'Hallitse varastoa', 'update_stock_products': 'Päivitä varastotasoja ja tuotteita', 'customer_list': 'Asiakaslista',
        'view_manage_customers': 'Näytä ja hallinnoi kaikkia asiakkaita', 'export_data': 'Vie tiedot', 'download_reports': 'Lataa raportteja ja tietoja', 'write_blog': 'Kirjoita blogi',
        'publish_articles': 'Julkaise uusia artikkeleita', 'sales_analytics': 'Myyntianalytiikka', 'view_reports_insights': 'Näytä yksityiskohtaiset raportit ja oivallukset',
        'newsletter_hub': 'Uutiskirjeen keskus', 'manage_subscribers_campaigns': 'Hallitse tilaajia ja kampanjoita', 'team_management': 'Tiimin hallinta', 'manage_team_members': 'Lisää, muokkaa tai poista tiimin jäseniä',
        'chatbot_orders': 'Chatbot-tilaukset', 'view_ai_orders': 'Näytä tekoälyavustajan tilaukset', 'news_manager': 'Uutisten hallinta', 'manage_news_articles': 'Hallitse kahviuutisartikkeleita',
        'employee_portal': 'Työntekijöiden portaali', 'staff_management': 'Henkilöstön hallintapaneeli', 'access_dashboard': 'Käytä kojelautaa', 'manage_subscribers': 'Hallitse tilaajia',
        'manage_team': 'Hallitse tiimiä', 'view_orders': 'Näytä tilaukset', 'manage_news': 'Hallitse uutisia', 'access_portal': 'Käytä portaalia', 'loading': 'Ladataan',
        'no_products_found': 'Tuotteita ei löytynyt', 'all_stock_healthy': 'Kaikilla tuotteilla on terveelliset varastotasot!', 'restock': 'Täydennä', 'out_of_stock': 'LOPPU',
        'no_sales_data': 'Ei myyntitietoja vielä', 'error_loading_data': 'Virhe ladattaessa tietoja', 'no_orders_yet': 'Ei tilauksia vielä', 'error_loading_orders': 'Virhe ladattaessa tilauksia',
        'no_sales_data_available': 'Myyntitietoja ei ole saatavilla', 'view_all_products': 'Näytä kaikki tuotteet', 'view_full_report': 'Näytä koko raportti', 'view_all_orders': 'Näytä kaikki tilaukset',
        'detailed_analytics': 'Yksityiskohtainen analytiikka', 'access': 'Käytä', 'no_orders': 'Ei tilauksia vielä', 'start_shopping': 'Aloita ostokset nähdäksesi tilauksesi täällä',
        'browse_menu': 'Selaa ruokalistaa', 'cancel_order': 'Peruuta tilaus', 'estimated_time': 'Arvioitu aika', 'pickup_location': 'Noutopiste', 'support': 'Tuki', 'reorder_success': 'Tuotteet lisätty ostoskoriin!',
        'reorder_error': 'Virhe uudelleentilauksessa. Yritä uudelleen.', 'conceal_order_confirm': 'Piilota tämä tilaus?', 'conceal_order_success': 'Tilaus piilotettu onnistuneesti!',
        'conceal_order_error': 'Virhe tilauksen piilottamisessa.', 'delete_order_confirm': 'Poista tämä tilaus pysyvästi?', 'delete_order_success': 'Tilaus poistettu onnistuneesti!',
        'delete_order_error': 'Virhe tilauksen poistamisessa.', 'no_chatbot_orders': 'Ei chatbot-tilauksia', 'chatbot_empty_msg': 'Et ole tehnyt vielä tilauksia chatbotin kautta.',
        'start_chatting': 'Aloita keskustelu', 'loyalty_points': 'Pisteet', 'available_rewards': 'Palkinnot', 'next_reward': 'Seuraava', 'your_code': 'Koodisi', 'earn_points': 'Saat',
        'points_on_order': 'pistettä tästä tilauksesta', 'delivery_payment': 'Toimitus ja maksu', 'enter_coupon': 'Syötä kuponkikoodi', 'apply': 'Käytä', 'item': 'Tuote',
        'qty': 'Määrä', 'price': 'Hinta', 'not_provided': 'Ei annettu', 'delivery_address': 'Toimitusosoite', 'cash_on_delivery': 'Käteinen toimituksessa', 'place_order': 'Tee tilaus',
        'cancel': 'Peruuta', 'cancel_checkout_confirm': 'Haluatko varmasti peruuttaa kassan? Kuponki poistetaan.', 'loyalty_points': 'Kanta-asiakaspisteet', 'delivery_payment': 'Toimitus ja maksu',
        'apply_coupon': 'Käytä kuponki', 'coupon_applied': 'Kuponki käytetty onnistuneesti!', 'coupon_error': 'Virhe kupongin käytössä', 'sales_dashboard': 'Myyntihallinta',
        'sales_dashboard_desc': 'Seuraa kahvilasi suorituskykyä, analysoi trendejä ja kasvata liiketoimintaasi', 'period': 'Ajanjakso', 'this_week': 'Tämä viikko', 'this_month': 'Tämä kuukausi',
        'this_year': 'Tämä vuosi', 'all_time': 'Kaikki aika', 'total_sales': 'Kokonaismyynti', 'happy_customers': 'Tyytyväiset asiakkaat', 'total_revenue': 'Kokonaistulot',
        'total_orders': 'Tilaukset yhteensä', 'total_customers': 'Asiakkaat yhteensä', 'avg_order_value': 'Keskimääräinen tilausarvo', 'sales_trend': 'Myyntitrendi', 'revenue_overview': 'Tulojen yleiskatsaus',
        'category_distribution': 'Kategorioiden jakautuminen', 'top_products': 'Suosituimmat tuotteet', 'top_selling_products': 'Myydyimmät tuotteet', 'loading_data': 'Ladataan tietoja...',
        'sales': 'Myynti', 'revenue': 'Tuotto', 'sold': 'Myyty', 'no_sales_data': 'Myyntitietoja ei saatavilla', 'select_both_dates': 'Valitse sekä aloitus- että lopetuspäivämäärä',
        'start_date': 'Aloituspäivä', 'end_date': 'Lopetuspäivä', 'apply': 'Käytä', 'inventory_management': 'Varastonhallinta', 'inventory_desc': 'Seuraa varastotasoja, hallitse tuotteita ja tarkkaile varaston arvoa',
        'low_stock_alert': 'Matala varastohälytys', 'inventory_value': 'Varaston arvo', 'add_product': 'Lisää tuote', 'low_stock': 'Matala varasto (<10)', 'out_of_stock': 'Loppu',
        'id': 'ID', 'product': 'Tuote', 'category': 'Kategoria', 'price': 'Hinta', 'stock': 'Varasto', 'status': 'Tila', 'actions': 'Toiminnot', 'loading': 'Ladataan', 'product_name': 'Tuotteen nimi',
        'stock_quantity': 'Varastomäärä', 'description': 'Kuvaus', 'save_product': 'Tallenna tuote', 'cancel': 'Peruuta', 'update_stock': 'Päivitä varasto', 'current_stock': 'Nykyinen varasto',
        'new_stock_quantity': 'Uusi varastomäärä', 'product_added': 'Tuote lisätty!', 'stock_updated': 'Varasto päivitetty!', 'units': 'kpl', 'in_stock': 'Varastossa',
        'customer_management': 'Asiakashallinta', 'customer_management_desc': 'Hallitse asiakkaita, seuraa heidän tilauksiaan ja rakenna kestäviä suhteita', 'new_this_month': 'Uudet tässä kuussa',
        'total_spent': 'Kulutettu yhteensä', 'customer': 'Asiakas', 'orders': 'Tilaukset', 'joined': 'Liittynyt', 'edit_customer': 'Muokkaa asiakasta', 'save_changes': 'Tallenna muutokset',
        'customer_details': 'Asiakkaan tiedot', 'member_since': 'Jäsen alkaen', 'no_customers_found': ' Asiakkaita ei löytynyt', 'view': 'Näytä', 'customer_updated': 'Asiakas päivitetty!',
        'customer_deleted': 'Asiakas poistettu!', 'customer_delete_error': 'Asiakasta ei voi poistaa, koska hänellä on tilauksia', 'delete_customer_confirm': 'Poista asiakas',
        'export_reports': 'Vie raportit', 'export_desc': 'Lataa tiedot CSV-, Excel- tai PDF-muodossa', 'formats': 'Muodot', 'date_range': 'Aikaväli', 'customizable': 'Mukautettava',
        'reports': 'Raportit', 'types': 'Tyypit', 'sales_report': 'Myyntiraportti', 'sales_report_desc': 'Tilaus- ja tulotiedot', 'products_report': 'Tuoteraportti', 'products_report_desc': 'Varasto- ja saldotiedot',
        'customers_report': 'Asiakasraportti', 'customers_report_desc': 'Asiakas- ja kanta-asiakastiedot', 'inventory_report': 'Varastoraportti', 'inventory_report_desc': 'Varaston arvo ja hälytykset',
        'format': 'Muoto', 'export_sales': 'Vie myynti', 'export_products': 'Vie tuotteet', 'export_customers': 'Vie asiakkaat', 'export_inventory': 'Vie varasto', 'data_preview': 'Tietojen esikatselu',
        'click_export_preview': 'Napsauta vientiä nähdäksesi esikatselun', 'no_data_available': 'Tietoja ei saatavilla', 'customer_reviews': 'Asiakasarvostelut', 'reviews_subtitle': 'Mitä kahvinystävämme sanovat meistä',
        'average_rating': 'Keskiarvo', 'total_reviews': 'Arvostelut yhteensä', 'happy_customers': 'Tyytyväiset asiakkaat', 'review1_text': 'Kaupungin paras kahvi! Tunnelma on uskomaton ja henkilökunta on erittäin ystävällistä.',
        'review2_text': 'Heidän leivonnaisensa ovat tuoreita ja herkullisia. Croissant cappuccinon kanssa on täydellinen aamuyhdistelmä.', 'review3_text': 'Rakastan kodikasta ilmapiiriä ja ilmaista WiFiä. Täydellinen paikka etätyöhön hyvän kahvin äärellä.',
        'review4_text': 'Chai latte on uskomaton! Loistava teevalikoima ja ystävälliset baristat. Tulen varmasti takaisin.', 'review5_text': 'Vihdoin löysin paikan, jossa tehdään täydellinen espresso. Heidän kanta-asiakasohjelmansa on myös loistava.', 
        'review6_text': 'Uskomatonta asiakaspalvelua! He muistivat tilaukseni viime viikolta. Mustikkamuffini on taivaallinen!', 'coffee_enthusiast': 'Kahvin Ystävä', 'regular_customer': 'Vakioasiakas',
        'remote_worker': 'Etätyöntekijä', 'tea_lover': 'Teen Ystävä', 'coffee_addict': 'Kahviaddikti', 'loyal_customer': 'Kanta-asiakas', 'share_experience': 'Jaa Kokemuksesi',
        'share_experience_msg': 'Haluamme kuulla vierailustasi Bean & Brewssä', 'write_review': 'Kirjoita Arvostelu', 'login_write_review': 'Kirjaudu Kirjoittaaksesi Arvostelun',
        'write_review_placeholder': 'Kirjoita arvostelusi tähän...', 'submit_review': 'Lähetä Arvostelu', 'select_rating': 'Valitse arvosana', 'review_thanks': 'Kiitos arvostelustasi!',
        'subscribers_count': 'Tilaajat', 'weekly_emails': 'Viikkosähköpostit', 'exclusive_offers': 'Eksklusiiviset tarjoukset', 'news_manager': 'Uutisten hallinta', 'manage_news_articles': 'Hallitse kahviuutisartikkeleitasi',
        'total': 'Yhteensä', 'published': 'Julkaistu', 'draft': 'Luonnos', 'add_article': 'Lisää artikkeli', 'all': 'Kaikki', 'news': 'Uutiset', 'tips': 'Vinkit', 'offers': 'Tarjoukset',
        'articles_list': 'Artikkelilista', 'id': 'ID', 'title': 'Otsikko', 'category': 'Kategoria', 'author': 'Kirjoittaja', 'date': 'Päivämäärä', 'status': 'Tila', 'actions': 'Toiminnot',
        'view': 'Näytä', 'edit': 'Muokkaa', 'delete': 'Poista', 'delete_confirm': 'Haluatko varmasti poistaa tämän artikkelin?', 'delete_error': 'Virhe artikkelin poistamisessa',
        'published_date': 'Julkaisupäivämäärä', 'write_new_story': 'Kirjoita uusi tarina', 'share_coffee_knowledge': 'Jaa kahvitietosi maailman kanssa', 'auto_translate': 'Automaattinen käännös',
        'languages': 'Kielet', 'publish_globally': 'Julkaise maailmanlaajuisesti', 'yes': 'Kyllä', 'estimated_time': 'Arvioitu aika', 'minutes': 'minuuttia', 'title': 'Otsikko',
        'english': 'Englanti', 'enter_title': 'Kirjoita houkutteleva otsikko...', 'characters': 'merkkiä', 'category': 'Kategoria', 'news_updates': 'Uutiset & Päivitykset',
        'brewing_tips': 'Kahvinkeittovinkit & Niksit', 'special_offers': 'Erikoistarjoukset', 'giveaways': 'Arvonnat', 'coffee_lifestyle': 'Kahvielämäntapa', 'coffee_history': 'Kahvin historia',
        'health_benefits': 'Terveyshyödyt', 'image_url': 'Kuvan URL', 'or': 'tai', 'preview': 'Esikatselu', 'leave_empty_default': 'Jätä tyhjäksi käyttääksesi oletuskuvaa',
        'content': 'Sisältö', 'write_article_auto_translate': 'Kirjoita upea artikkeli tähän... Se käännetään automaattisesti suomeksi, ruotsiksi ja persiaksi!', 'publish_auto_translate': 'Julkaise & Automaattikäännös',
        'cancel': 'Peruuta', 'read_more': 'Lue lisää', 'free_wifi': 'Ilmainen WiFi', 'wifi_subtitle': 'Pysy yhteydessä nauttiessasi kahvistasi', 'speed': 'Nopeus', 'charging_stations': 'Latauspisteet',
        'available': 'Saatavilla', 'secure': 'Turvallinen', 'network_name': 'Verkon nimi', 'password': 'Salasana', 'quick_connect': 'Pikayhteys', 'scan_qr_connect': 'Skannaa QR-koodi muodostaaksesi yhteyden heti',
        'available_all_tables': 'Saatavilla kaikilla pöytäpaikoilla', 'high_speed': 'Suuri nopeus', 'high_speed_desc': 'Jopa 100 Mbps latausnopeus', 'charging_stations_desc': 'Pistorasiat ja USB-portit jokaisessa pöydässä',
        'secure_connection': 'Turvallinen yhteys', 'secure_connection_desc': 'Yritystason tietoturva yksityisyytesi suojaamiseksi', 'wifi_tips': 'Vinkit parhaaseen kokemukseen',
        'tip1': 'Yhdistä verkkoon ja syötä salasana', 'tip2': 'Hyväksy käyttöehdot kirjautumissivulla', 'tip3': 'Nauti rajoittamattomasta selailusta käyntisi ajan', 'tip4': 'Tarvitsetko apua? Kysy baristoiltamme',
        'speed_detail_1': '100 Mbps latausnopeus sujuvaan selailuun', 'speed_detail_2': '50 Mbps lähetysnopeus videopuheluihin', 'speed_detail_3': 'Matala viive pelaamiseen ja suoratoistoon',
        'speed_detail_4': 'Rajoittamaton datankäyttö kohtuullisesti', 'charging_detail_1': 'USB-C ja USB-A -portit jokaisessa pöydässä', 'charging_detail_2': 'Tavalliset pistorasiat kannettaville tietokoneille',
        'charging_detail_3': 'Langattomat latausalustat saatavilla', 'charging_detail_4': 'Pikalataustuki kaikille laitteille', 'secure_detail_1': 'WPA2-Enterprise-salaus turvallista yhteyttä varten',
        'secure_detail_2': 'Palomuurisuoja kyberuhkia vastaan',  'secure_detail_3': 'Säännölliset tietoturvapäivitykset ja seuranta', 'secure_detail_4': 'Tietosi ja yksityisyytesi ovat aina suojattuja',
        'team_members': 'Tiimin jäsenet', 'years_experience': 'Vuosien kokemus', 'coffee_awards': 'Kahvipalkinnot', 'value_sustainability_desc': 'Hankimme reilun kaupan tiloilta ja käytämme ympäristöystävällisiä pakkauksia',
        'value_quality_desc': 'Emme koskaan tingi laadusta pavusta kuppiin', 'value_community_desc': 'Rakennamme kodikasta tilaa kaikille', 'value_innovation_desc': 'Kehitymme jatkuvasti tarjotaksemme parhaan kokemuksen',
    },
    'sv': {
        'home': 'Hem', 'menu': 'Meny', 'cart': 'Varukorg', 'login': 'Logga in', 'register': 'Registrera',
        'logout': 'Logga ut', 'profile': 'Profil', 'orders': 'Beställningar', 'favorites': 'Favoriter',
        'reviews': 'Recensioner', 'wifi': 'WiFi', 'my_receipts': 'Kvitton', 'loyalty_rewards': 'Lojalitetsbelöningar',
        'refer_friend': 'Rekommendera en vän', 'loyalty_points': 'Lojalitetspoäng', 'edit_profile': 'Redigera profil',
        'full_name': 'Fullständigt namn', 'referral_code': 'Hänvisningskod', 'claim_reward': 'Hämta belöning',
        'my_orders': 'Mina beställningar', 'update_profile': 'Uppdatera profil', 'checkout': 'Gå till kassan',
        'add_to_cart': 'Lägg till i varukorg', 'total': 'Totalt', 'remove': 'Ta bort', 'clear_cart': 'Rensa varukorg',
        'cart_empty': 'Din varukorg är tom', 'browse_menu': 'Bläddra i menyn', 'order_summary': 'Order sammanfattning',
        'subtotal': 'Delsumma', 'tax': 'Skatt (10%)', 'delivery_fee': 'Leveransavgift', 'place_order': 'Lägg beställning',
        'payment_method': 'Betalningsmetod', 'delivery_info': 'Leveransinformation', 'pickup_at_store': 'Hämta i butik',
        'username': 'Användarnamn', 'email': 'E-post', 'phone': 'Telefon', 'password': 'Lösenord',
        'birthday': 'Födelsedag', 'delete_account': 'Radera konto', 'pending': 'Väntar', 'completed': 'Slutförd',
        'cancelled': 'Avbruten', 'processing': 'Bearbetar', 'no_items_found': 'Inga artiklar', 'all': 'Alla',
        'coffee': 'Kaffe', 'tea': 'Te', 'pastry': 'Bakverk', 'other_drinks': 'Andra drycker', 'newsletter_title': 'Prenumerera på vårt nyhetsbrev',
        'employee_dashboard': 'Anställd instrumentpanel', 'welcome_back': 'Välkommen tillbaka', 'total_orders_today': 'Order idag',
        'pending_orders': 'Väntande ordrar', 'completed_today': 'Slutförda idag', 'revenue_today': 'Dagens intäkter',
        'inventory_management': 'Lagerhantering', 'customer_management': 'Kundhantering', 'coming_soon': 'Kommer snart!',
        'order_management': 'Orderhantering', 'back_to_dashboard': 'Tillbaka till instrumentpanelen', 'total_products': 'Totalt antal produkter',
        'low_stock': 'Lågt lager', 'out_of_stock': 'Slut i lager', 'total_value': 'Totalt värde',
        'search_products': 'Sök produkter efter namn...', 'no_products_found': 'Inga produkter hittades',
        'in_stock': 'I lager', 'active': 'Aktiv', 'inactive': 'Inaktiv', 'refresh': 'Uppdatera',
        'preparing': 'Förbereder', 'ready': 'Klar', 'status': 'Status', 'order_number': 'Ordernummer',
        'items': 'Artiklar', 'customer': 'Kund', 'guest': 'Gäst', 'order_items': 'Orderartiklar',
        'employee_login': 'Anställd inloggning', 'staff_access_only': 'Endast personal', 'login_as_employee': 'Logga in som anställd',
        'total_customers': 'Totalt antal kunder', 'new_this_month': 'Nya denna månad', 'total_revenue': 'Totala intäkter',
        'search_customers': 'Sök efter namn, e-post eller telefon...', 'joined': 'Blev medlem', 'points': 'Poäng',
        'track_order': 'Spåra order', 'chatbot_orders_title': 'Chatbot beställningar', 'chatbot_checkout': 'Chatbot kassan',
        'member_since': 'Medlem sedan', 'total_orders': 'Totala beställningar', 'free_coffees': 'Gratis kaffe', 'friends_referred': 'Vänners hänvisningar',
        'edit_profile': 'Redigera profil', 'username_cannot_change': 'Användarnamnet kan inte ändras', 'learn_more': 'Läs mer',
        'birthday_reward': 'Få 50 bonuspoäng på din födelsedag!', 'danger_zone': 'Riskzon', 'delete_account_warning': 'När du raderar ditt konto kan det inte återställas.',
        'delete_account': 'Radera konto', 'track_orders': 'Spåra dina beställningar', 'order_summary': 'Order sammanfattning', 'subtotal': 'Delsumma', 'tax': 'Skatt (10%)', 
        'delivery_fee': 'Leveransavgift', 'total': 'Totalt', 'preparing': 'Förbereder', 'ready': 'Klar', 'completed': 'Slutförd', 'pending': 'Väntar', 'cancelled': 'Avbruten',
        'view_receipt': 'Visa kvitto', 'view_details': 'Visa detaljer', 'currency': 'Valuta', 'order_items': 'Orderartiklar', 'order_number': 'Ordernummer', 'date': 'Datum',
        'status': 'Status', 'amount': 'Belopp', 'track_order_placeholder': 'Ange ordernummer (t.ex. ORD87DB035E)', 'track_button': 'Spåra order',
        'track_order': 'Spåra din beställning', 'track_order_desc': 'Ange ditt beställningsnummer för att kontrollera status', 'time': 'Tid', 'checkout_order': 'Checka ut denna beställning',
        'reorder': 'Beställ igen', 'conceal_order': 'Dölj beställning', 'delete_order': 'Radera beställning', 'chatbot_orders_desc': 'Beställningar gjorda via vår AI Barista-assistent',
        'complete_order_title': 'Slutför din beställning', 'payment_details': 'Betalningsinformation', 'payment_status': 'Betalningsstatus', 'unpaid': 'OBETALD', 'name': 'Namn',
        'phone': 'Telefon', 'delivery_address_placeholder': 'Ange din leveransadress', 'pickup_instruction': 'Lämna tomt för upphämtning', 'credit_card': 'Kreditkort',
        'paypal': 'PayPal', 'mobilepay': 'MobilePay', 'cash_on_pickup': 'Kontant vid upphämtning', 'confirm_info_correct': 'Jag bekräftar att all information är korrekt',
        'newsletter_subtitle': 'Få de senaste kaffenyheterna, exklusiva erbjudanden och bryggtips levererade till din inkorg', 'subscribe': 'Prenumerera',
        'newsletter_card_title': 'Håll dig uppdaterad med Bean & Brew', 'name_placeholder': 'Ange ditt fullständiga namn', 'email_placeholder': 'Ange din e-postadress',
        'privacy_consent': 'Jag samtycker till att ta emot e-post från Bean & Brew Coffee', 'subscribe_button': 'Prenumerera nu', 'benefits_title': 'Varför prenumerera?',
        'benefit_1_title': 'Exklusiva erbjudanden', 'benefit_1_desc': 'Få specialrabatter och kampanjer endast för prenumeranter', 'benefit_2_title': 'Gratis kaffe',
        'benefit_2_desc': 'Vinn gratis kaffe varje månad i våra prenumerantutlottningar', 'benefit_3_title': 'Tillgång först', 'benefit_3_desc': 'Få veta om nya kaffeblandningar först',
        'benefit_4_title': 'Bryggtips', 'benefit_4_desc': 'Lär dig professionella bryggtekniker hemma', 'newsletter_success': 'Tack för din prenumeration! Kolla din e-post för bekräftelse.',
        'newsletter_already_subscribed': 'Denna e-post är redan prenumererad på vårt nyhetsbrev.', 'newsletter_error': 'Något gick fel. Vänligen försök igen.',
        'newsletter_check_email': 'Kontrollera din e-post för att bekräfta din prenumeration!', 'newsletter_confirmed': 'E-post bekräftad! Tack för din prenumeration!',
        'newsletter_invalid_token': 'Ogiltig eller utgången bekräftelselänk.', 'newsletter_unsubscribed': 'Du har avslutat din prenumeration på vårt nyhetsbrev.',
        'coffee_menu_description': 'Upplev den perfekta blandningen av arom och smak. Vårt kaffe kommer från de finaste bönorna och rostas till perfektion.', 'newsletter': 'Nyhetsbrev', 'newsletter': 'Nyhetsbrev',
        'my_favorites': 'Mina favoriter', 'favorite_items': 'Dina favoritkaffeprodukter', 'review_items': 'Granska dina artiklar innan du går till kassan', 'cart_empty_msg': 'Det ser ut som du inte har lagt till några artiklar än',
        'notifications_enabled': 'Aviseringar aktiverade!', 'notifications_blocked': 'Aviseringar blockerade', 'no_favorites': 'Inga favoriter än', 'blog': 'Blogg',
        'no_favorites_msg': 'Bläddra i vår meny och klicka på hjärtikonen för att lägga till dina favoritartiklar!', 'removed_from_favorites': 'Borttagen från favoriter!',
        'added_to_cart': 'Lagd i varukorgen!', 'login_title': 'Logga in på ditt konto', 'admin_register': 'Adminregister', 'register_here': 'Registrera här', 'register_title': 'Skapa ett nytt konto',
        'register_subtitle': 'Gå med i Bean & Brew Coffee idag', 'already_have_account': 'Har du redan ett konto?', 'login_instead': 'Logga in istället', 'create_account': 'Skapa konto',
        'confirm_password': 'Bekräfta lösenord', 'welcome_back': 'Välkommen tillbaka!', 'remember_me': 'Kom ihåg mig', 'forgot_password': 'Glömt lösenord?', 'or_login_with': 'Eller logga in med',
        'no_account': 'Har du inget konto', 'continue_as_guest': 'Fortsätt som gäst', 'join_us': 'Gå med', 'username_placeholder': 'Välj ett användarnamn', 'email_placeholder': 'Ange din e-post',
        'password_placeholder': 'Skapa ett lösenord', 'confirm_password_placeholder': 'Bekräfta ditt lösenord', 'full_name_placeholder': 'Ange ditt fullständiga namn (valfritt)',
        'phone_placeholder': 'Ange ditt telefonnummer (valfritt)', 'referral_discount': 'Du får 50 bonuspoäng vid registrering!', 'and': 'och', 'terms_conditions': 'Villkor',
        'privacy_policy': 'Integritetspolicy', 'view_all_receipts': 'Visa alla kvitton', 'action': 'Åtgärd', 'download_pdf': 'Ladda ner PDF', 'no_receipts': 'Inga kvitton än',
        'no_receipts_msg': 'Du har inte gjort några beställningar än. Gör en beställning för att se dina kvitton här.', 'refer_friend': 'Rekommendera en vän', 'share_code_earn_coffee': 'Dela din kod och tjäna gratis kaffe!',
        'your_referral_code': 'Din hänvisningskod', 'share_this_link': 'Dela denna länk:', 'how_it_works': 'Hur det fungerar', 'step1': 'Dela din unika hänvisningskod med vänner',
        'step2': 'Vän registrerar sig med din kod', 'step3': 'Vän lägger sin första beställning', 'step4': 'Ni får båda 100 bonuspoäng!', 'your_referrals': 'Dina hänvisningar:',
        'friends_joined': 'vänner gick med', 'points_earned': 'Intjänade poäng', 'scan_qr_code': 'Skanna QR-kod', 'qr_instruction': 'Vänner kan skanna denna QR-kod för att få din hänvisningslänk!',
        'your_referrals_list': 'Dina hänvisningar', 'friend': 'Vän', 'date_joined': 'Gick med datum', 'points_earned_table': 'Intjänade poäng', 'no_referrals_yet': 'Inga hänvisningar än. Dela din kod!',
        'download_qr': 'Ladda ner QR-kod', 'copy_code': 'Kopiera kod', 'referral_code_copied': 'Hänvisningskod kopierad!', 'referral_link_copied': 'Hänvisningslänk kopierad!',
        'qr_not_ready': 'QR-koden är inte klar än. Försök igen.', 'coffee_times_blog': 'Kaffe Times Blogg', 'blog_subtitle': 'Berättelser, nyheter och tips från Bean & Brew Coffee',
        'no_articles': 'Inga artiklar än', 'no_articles_msg': 'Kom snart tillbaka för kaffenyheter och berättelser!', 'add_article': 'Lägg till artikel', 'back_to_blog': 'Tillbaka till bloggen',
        'contact_us': 'Kontakta oss', 'contact_subtitle': 'Kontakta oss. Vi vill gärna höra från dig!', 'our_location': 'Vår plats', 'phone_number': 'Telefonnummer',
        'email_us': 'E-post', 'send_us_message': 'Skicka meddelande', 'your_name': 'Ditt namn', 'your_email': 'Din e-post', 'subject': 'Ämne', 'subject_placeholder': 'Vad gäller detta?',
        'message': 'Meddelande', 'message_placeholder': 'Skriv ditt meddelande här...', 'send_message': 'Skicka meddelande','mon_fri_9_17': 'Mån-Fre 9-17', 'response_time': 'Svar inom 24 timmar',
        'form_description': 'Fyll i formuläret nedan så återkommer vi till dig så snart som möjligt.', 'find_us': 'Hitta oss', 'map_description': 'Besök vårt mysiga kafé i Vanda',
        'opening_hours': 'Öppettider', 'name_placeholder': 'Ange ditt fullständiga namn', 'monday_friday': 'Måndag - Fredag', 'saturday_sunday': 'Lördag - Söndag', 'benefits_subtitle': 'Gå med i vår kaffegemenskap och njut av exklusiva förmåner!',
        'complete_order': 'Slutför beställning', 'cancel_order': 'Avbryt beställning', 'download_receipt': 'Ladda ner kvitto', 'loyalty_program': 'Lojalitetsprogram',
        'loyalty_subtitle': 'Tjäna poäng med varje köp och få gratis belöningar!', 'your_loyalty_points': 'Dina lojalitetspoäng', 'earn_more_points': 'Tjäna 100 poäng för gratis kaffe!',
        'points_note': '1 poäng = 1 € spenderat', 'how_to_earn': 'Hur man tjänar poäng', 'make_purchase': 'Gör ett köp', 'earn_points_per_dollar': 'Tjäna 1 poäng för varje 1 € spenderat',
        'birthday_bonus': 'Födelsedagsbonus', 'birthday_bonus_desc': 'Få 50 bonuspoäng på din födelsedag!', 'refer_friend_desc': 'Tjäna 100 poäng för varje hänvisning',
        'available_rewards': 'Tillgängliga belöningar', 'redeem': 'Lös in', 'points': 'poäng', 'no_rewards': 'Inga belöningar tillgängliga just nu.', 'redeem_confirm': 'Lös in belöning?',
        'redeem_success': 'Belöning inlöst framgångsrikt!', 'not_enough_points': 'Inte tillräckligt med poäng!', 'points_needed': 'poäng behövs', 'about_us': 'Om oss',
        'about_subtitle': 'Läs mer om vår kaffehistoria och passion', 'our_story': 'Vår historia', 'story_text_1': 'Bean & Brew Coffee grundades 2015 med ett enkelt uppdrag: att ge den bästa kaffeupplevelsen till kaffeälskare överallt. Det som började som en liten kaffevagn på en lokal marknad har vuxit till ett älskat kafé i hjärtat av Vanda.',
        'story_text_2': 'Vår resa började när vår grundare Aziz Rahman upptäckte sin passion för kaffe när han reste genom kafferegionerna i Etiopien och Colombia. Inspirerad av de rika smakerna och kaffeböndernas varma gästfrihet bestämde han sig för att föra den upplevelsen hem.',
        'story_text_3': 'Idag köper vi våra bönor direkt från hållbara gårdar, rost dem i små partier till perfektion och serverar varje kopp med kärlek och omsorg. Varje kopp berättar en historia om hantverk, kvalitet och gemenskap.',
        'our_mission': 'Vårt uppdrag', 'mission_text': 'Att servera exceptionellt kaffe samtidigt som vi bygger en gemenskap kring delad passion för kvalitet, hållbarhet och mänsklig kontakt.',
        'our_vision': 'Vår vision', 'vision_text': 'Att vara det mest älskade kaféet i Finland, känt för exceptionell kvalitet, varm gästfrihet och positiv påverkan på vår gemenskap och miljö.',
        'our_values': 'Våra värderingar', 'values_text': 'Kvalitet, hållbarhet, gemenskap, innovation och passion driver allt vi gör.', 'meet_our_team': 'Möt vårt team',
        'team_aziz_bio': 'Kaffeentusiast med över 15 års erfarenhet. Aziz väljer personligen ut och rost varje sats för att säkerställa högsta kvalitet.', 'team_maria_bio': 'Prisbelönt barista med en passion för lattekonst och att skapa minnesvärda kaffeupplevelser för varje kund.',
        'team_johan_bio': 'Reser världen runt för att köpa de finaste bönorna direkt från bönder, vilket säkerställer fair trade och hållbara metoder.', 'our_core_values': 'Våra kärnvärden',
        'value_sustainability': 'Hållbarhet', 'value_quality': 'Kvalitet först', 'value_community': 'Gemenskap', 'value_innovation': 'Innovation', 'team_management': 'Teamhantering',
        'add_team_member': 'Lägg till teammedlem', 'edit_team_member': 'Redigera teammedlem', 'team_members': 'Teammedlemmar', 'name': 'Namn', 'position': 'Position',
        'display_order': 'Visningsordning', 'status': 'Status', 'active': 'Aktiv', 'inactive': 'Inaktiv', 'actions': 'Åtgärder', 'save': 'Spara', 'update': 'Uppdatera',
        'cancel': 'Avbryt', 'delete_confirm': 'Är du säker på att du vill ta bort denna teammedlem?', 'no_team_members': 'Inga teammedlemmar än', 'no_team_members_msg': 'Lägg till din första teammedlem för att visa ditt fantastiska team!',
        'bio': 'Biografi', 'active_status': 'Aktiv (visas på webbplatsen)', 'team_management': 'Teamhantering', 'add_team_member': 'Lägg till teammedlem',
        'edit_team_member': 'Redigera teammedlem', 'team_members': 'Teammedlemmar', 'edit': 'Redigera', 'delete': 'Radera', 'admin': 'Admin', 'admin_dashboard': 'Instrumentpanel',
        'admin_inventory': 'Lager', 'admin_customers': 'Kunder', 'admin_export': 'Export', 'admin_newsletter': 'Nyhetsbrev', 'admin_panel': 'Adminpanel', 'newsletter_subscribers': 'Nyhetsbrevsprenumeranter',
        'admin_access': 'Adminåtkomst', 'admin_access_desc': 'Välkommen till administrationsområdet', 'sales_dashboard_desc': 'Visa försäljningsanalys och rapporter',
        'inventory_desc': 'Hantera produkter och lagernivåer', 'customers_desc': 'Visa och hantera kundinformation', 'export_desc': 'Exportera data till CSV-filer',
        'newsletter_desc': 'Hantera nyhetsbrevsprenumeranter', 'team_desc': 'Hantera teammedlemmar', 'chatbot_orders': 'Chatbot beställningar', 'chatbot_orders_desc': 'Visa beställningar från chatbot',
        'employee_dashboard_desc': 'Personalhanteringspanel', 'access': 'Åtkomst', 'admin_access_warning': '⚠️ Begränsat område', 'admin_access_warning_desc': 'Detta område är endast för behörig personal. All obehörig åtkomst loggas.',
        'export_csv': 'Exportera CSV', 'subscribers_list': 'Prenumerantlista', 'subscribed_date': 'Prenumerationsdatum', 'confirmed': 'Bekräftad', 'no_subscribers': 'Inga prenumeranter än',
        'no_subscribers_msg': 'Ingen har prenumererat på nyhetsbrevet än.', 'delete_subscriber_confirm': 'Är du säker på att du vill ta bort denna prenumerant?', 'delete_error': 'Fel vid borttagning av prenumerant',
        'welcome_back_admin': 'Välkommen tillbaka,', 'admin_dashboard_subtitle': 'Hantera ditt kafé från en central instrumentpanel', 'low_stock_alerts': 'Lagerlarm',
        'top_selling_products': 'Bästsäljande produkter', 'recent_orders': 'Senaste beställningar', 'monthly_sales_3d': 'Månadsförsäljning (3D)', 'quick_actions': 'Snabba åtgärder',
        'management_center': 'Hanteringscenter', 'manage_inventory': 'Hantera lager', 'update_stock_products': 'Uppdatera lagernivåer och produkter', 'customer_list': 'Kundlista',
        'view_manage_customers': 'Visa och hantera alla kunder', 'export_data': 'Exportera data', 'download_reports': 'Ladda ner rapporter och data', 'write_blog': 'Skriv blogg',
        'publish_articles': 'Publicera nya artiklar', 'sales_analytics': 'Försäljningsanalys', 'view_reports_insights': 'Visa detaljerade rapporter och insikter', 'newsletter_hub': 'Nyhetsbrevcenter',
        'manage_subscribers_campaigns': 'Hantera prenumeranter och kampanjer', 'team_management': 'Teamhantering', 'manage_team_members': 'Lägg till, redigera eller ta bort teammedlemmar',
        'chatbot_orders': 'Chatbot-beställningar', 'view_ai_orders': 'Visa AI-assistentbeställningar', 'news_manager': 'Nyhetshanterare', 'manage_news_articles': 'Hantera kaffenyhetsartiklar',
        'employee_portal': 'Anställd portal', 'staff_management': 'Personalhanteringspanel', 'access_dashboard': 'Tillgång till instrumentpanel', 'manage_subscribers': 'Hantera prenumeranter',
        'manage_team': 'Hantera team', 'view_orders': 'Visa beställningar', 'manage_news': 'Hantera nyheter', 'access_portal': 'Tillgång till portal', 'loading': 'Laddar',
        'no_products_found': 'Inga produkter hittades', 'all_stock_healthy': 'Alla produkter har hälsosamma lagernivåer!', 'restock': 'Fyll på lager', 'out_of_stock': 'SLUT I LAGER',
        'no_sales_data': 'Inga försäljningsdata än', 'error_loading_data': 'Fel vid laddning av data', 'no_orders_yet': 'Inga beställningar än', 'error_loading_orders': 'Fel vid laddning av beställningar',
        'no_sales_data_available': 'Inga försäljningsdata tillgängliga', 'view_all_products': 'Visa alla produkter', 'view_full_report': 'Visa full rapport', 'view_all_orders': 'Visa alla beställningar',
        'detailed_analytics': 'Detaljerad analys', 'access': 'Åtkomst', 'no_orders': 'Inga beställningar än', 'start_shopping': 'Börja handla för att se dina beställningar här',
        'browse_menu': 'Bläddra i menyn', 'cancel_order': 'Avbryt beställning', 'estimated_time': 'Beräknad tid', 'pickup_location': 'Upphämtningsplats', 'support': 'Support',
        'reorder_success': 'Produkter tillagda i varukorgen!', 'reorder_error': 'Fel vid ombeställning. Försök igen.', 'conceal_order_confirm': 'Dölj denna beställning?',
        'conceal_order_success': 'Beställning gömd!', 'conceal_order_error': 'Fel vid döljning av beställning.', 'delete_order_confirm': 'Ta bort denna beställning permanent?',
        'delete_order_success': 'Beställning borttagen!', 'delete_order_error': 'Fel vid borttagning av beställning.', 'no_chatbot_orders': 'Inga chatbot-beställningar',
        'chatbot_empty_msg': 'Du har inte gjort några beställningar via chatboten än.', 'start_chatting': 'Börja chatta', 'loyalty_points': 'Poäng', 'available_rewards': 'Belöningar',
        'next_reward': 'Nästa', 'your_code': 'Din kod', 'earn_points': 'Tjäna', 'points_on_order': 'poäng på denna beställning', 'delivery_payment': 'Leverans & Betalning',
        'enter_coupon': 'Ange kupongkod', 'apply': 'Applicera', 'item': 'Artikel', 'qty': 'Antal', 'price': 'Pris', 'not_provided': 'Ej angiven', 'delivery_address': 'Leveransadress',
        'cash_on_delivery': 'Kontant vid leverans', 'place_order': 'Lägg beställning', 'cancel': 'Avbryt', 'cancel_checkout_confirm': 'Är du säker på att du vill avbryta utcheckningen? Din kupong kommer att tas bort.',
        'loyalty_points': 'Lojalitetspoäng', 'delivery_payment': 'Leverans & Betalning', 'apply_coupon': 'Applicera kupong', 'coupon_applied': 'Kupong applicerad!', 'coupon_error': 'Fel vid applicering av kupong',
        'sales_dashboard': 'Försäljningsinstrumentpanel', 'sales_dashboard_desc': 'Följ ditt kafés prestanda, analysera trender och utveckla din verksamhet', 'period': 'Period',
        'this_week': 'Denna vecka', 'this_month': 'Denna månad', 'this_year': 'Detta år', 'all_time': 'All tid', 'total_sales': 'Total försäljning', 'happy_customers': 'Nöjda kunder',
        'total_revenue': 'Totala intäkter', 'total_orders': 'Totalt antal beställningar', 'total_customers': 'Totalt antal kunder', 'avg_order_value': 'Genomsnittligt ordervärde',
        'sales_trend': 'Försäljningstrend', 'revenue_overview': 'Intäktsöversikt', 'category_distribution': 'Kategorifördelning', 'top_products': 'Topprodukter', 'top_selling_products': 'Bästsäljande produkter',
        'loading_data': 'Laddar data...', 'sales': 'Försäljning', 'revenue': 'Intäkt', 'sold': 'Såld', 'no_sales_data': 'Inga försäljningsdata tillgängliga', 'select_both_dates': 'Välj både start- och slutdatum',
        'start_date': 'Startdatum', 'end_date': 'Slutdatum', 'apply': 'Applicera', 'inventory_management': 'Lagerhantering', 'inventory_desc': 'Spåra lagernivåer, hantera produkter och övervaka lagervärdet',
        'low_stock_alert': 'Låg lagerlarm', 'inventory_value': 'Lagervärde', 'add_product': 'Lägg till produkt', 'low_stock': 'Lågt lager (<10)', 'out_of_stock': 'Slut i lager',
        'id': 'ID', 'product': 'Produkt', 'category': 'Kategori', 'price': 'Pris', 'stock': 'Lager', 'status': 'Status', 'actions': 'Åtgärder', 'loading': 'Laddar', 'product_name': 'Produktnamn',
        'stock_quantity': 'Lagermängd', 'description': 'Beskrivning', 'save_product': 'Spara produkt', 'cancel': 'Avbryt', 'update_stock': 'Uppdatera lager', 'current_stock': 'Nuvarande lager',
        'new_stock_quantity': 'Ny lagermängd', 'product_added': 'Produkt tillagd!', 'stock_updated': 'Lager uppdaterat!', 'units': 'st', 'in_stock': 'I lager', 'customer_management': 'Kundhantering',
        'customer_management_desc': 'Hantera dina kunder, spåra deras beställningar och bygg varaktiga relationer', 'new_this_month': 'Nya denna månad', 'total_spent': 'Totalt spenderat',
        'customer': 'Kund', 'orders': 'Beställningar', 'joined': 'Gick med', 'edit_customer': 'Redigera kund', 'save_changes': 'Spara ändringar', 'customer_details': 'Kunduppgifter',
        'member_since': 'Medlem sedan', 'no_customers_found': 'Inga kunder hittades', 'view': 'Visa', 'customer_updated': 'Kund uppdaterad!', 'customer_deleted': 'Kund borttagen!',
        'customer_delete_error': 'Kan inte ta bort kund med befintliga beställningar', 'delete_customer_confirm': 'Ta bort kund', 'export_reports': 'Exportera rapporter',
        'export_desc': 'Ladda ner dina data i CSV-, Excel- eller PDF-format', 'formats': 'Format', 'date_range': 'Datumintervall', 'customizable': 'Anpassningsbar', 'reports': 'Rapporter',
        'types': 'Typer', 'sales_report': 'Försäljningsrapport', 'sales_report_desc': 'Beställnings- och intäktsdata', 'products_report': 'Produktrapport', 'products_report_desc': 'Lager- och saldodata',
        'customers_report': 'Kundrapport', 'customers_report_desc': 'Kund- och lojalitetsdata', 'inventory_report': 'Lagerrapport', 'inventory_report_desc': 'Lagervärde och varningar',
        'format': 'Format', 'export_sales': 'Exportera försäljning', 'export_products': 'Exportera produkter', 'export_customers': 'Exportera kunder', 'export_inventory': 'Exportera lager',
        'data_preview': 'Förhandsgranskning', 'click_export_preview': 'Klicka på export för att se förhandsgranskning', 'no_data_available': 'Inga data tillgängliga', 'customer_reviews': 'Kundrecensioner',
        'reviews_subtitle': 'Vad våra kaffeälskare säger om oss', 'average_rating': 'Genomsnittligt betyg', 'total_reviews': 'Recensioner totalt', 'happy_customers': 'Nöjda kunder',
        'review1_text': 'Bästa kaffet i stan! Atmosfären är fantastisk och personalen är otroligt vänlig.', 'review2_text': 'Deras bakverk är färska och läckra. Croissant med cappuccino är den perfekta morgonkombinationen.',
        'review3_text': 'Älskar den mysiga atmosfären och gratis WiFi. Perfekt plats för distansarbete med utmärkt kaffe.', 'review4_text': 'Chai latte är fantastisk! Bra teurval och vänliga baristor. Kommer definitivt tillbaka.',
        'review5_text': 'Äntligen hittade jag en plats som gör perfekt espresso. Deras lojalitetsprogram är också bra.', 'review6_text': 'Fantastisk kundservice! De kom ihåg min beställning från förra veckan. Blåbärsmuffinen är himmelsk!',
        'coffee_enthusiast': 'Kaffeälskare', 'regular_customer': 'Regular Kund', 'remote_worker': 'Distansarbetare', 'tea_lover': 'Teälskare', 'coffee_addict': 'Kaffeälskare',
        'loyal_customer': 'Lojal Kund', 'share_experience': 'Dela Din Upplevelse', 'share_experience_msg': 'Vi vill gärna höra om ditt besök på Bean & Brew', 'write_review': 'Skriv en Recension',
        'login_write_review': 'Logga in för att Skriva en Recension', 'write_review_placeholder': 'Skriv din recension här...', 'submit_review': 'Skicka Recension', 'select_rating': 'Välj ett betyg',
        'review_thanks': 'Tack för din recension!', 'subscribers_count': 'Prenumeranter', 'weekly_emails': 'Veckovisa e-postmeddelanden', 'exclusive_offers': 'Exklusiva erbjudanden',
        'news_manager': 'Nyhetshantering', 'manage_news_articles': 'Hantera dina kaffenyhetsartiklar', 'total': 'Totalt', 'published': 'Publicerad', 'draft': 'Utkast', 'add_article': 'Lägg till artikel',
        'all': 'Alla', 'news': 'Nyheter', 'tips': 'Tips', 'offers': 'Erbjudanden', 'articles_list': 'Artikellista', 'id': 'ID', 'title': 'Titel', 'category': 'Kategori',
        'author': 'Författare', 'date': 'Datum', 'status': 'Status', 'actions': 'Åtgärder', 'view': 'Visa', 'edit': 'Redigera', 'delete': 'Ta bort', 'delete_confirm': 'Är du säker på att du vill ta bort denna artikel?',
        'delete_error': 'Fel vid borttagning av artikel', 'published_date': 'Publiceringsdatum', 'write_new_story': 'Skriv en ny berättelse', 'share_coffee_knowledge': 'Dela dina kaffekunskaper med världen',
        'auto_translate': 'Automatisk översättning', 'languages': 'Språk', 'publish_globally': 'Publicera globalt', 'yes': 'Ja', 'estimated_time': 'Beräknad tid', 'minutes': 'minuter',
        'title': 'Titel', 'english': 'Engelska', 'enter_title': 'Ange en fängslande titel...', 'characters': 'tecken', 'category': 'Kategori', 'news_updates': 'Nyheter & Uppdateringar',
        'brewing_tips': 'Bryggtips & Tricks', 'special_offers': 'Specialerbjudanden', 'giveaways': 'Utlottningar', 'coffee_lifestyle': 'Kaffelivsstil', 'coffee_history': 'Kaffehistoria',
        'health_benefits': 'Hälsofördelar', 'image_url': 'Bild-URL', 'or': 'eller', 'preview': 'Förhandsgranskning', 'leave_empty_default': 'Lämna tomt för att använda standardbild',
        'content': 'Innehåll', 'write_article_auto_translate': 'Skriv din fantastiska artikel här... Den kommer att översättas automatiskt till finska, svenska och persiska!',
        'publish_auto_translate': 'Publicera & Auto-översätt', 'cancel': 'Avbryt', 'read_more': 'Läs mer', 'free_wifi': 'Gratis WiFi', 'wifi_subtitle': 'Håll kontakten medan du njuter av ditt kaffe',
        'speed': 'Hastighet', 'charging_stations': 'Laddningsstationer', 'available': 'Tillgänglig', 'secure': 'Säker', 'network_name': 'Nätverksnamn', 'password': 'Lösenord',
        'quick_connect': 'Snabbanslutning', 'scan_qr_connect': 'Skanna QR-koden för att ansluta direkt', 'available_all_tables': 'Tillgängligt vid alla bord', 'high_speed': 'Hög hastighet',
        'high_speed_desc': 'Upp till 100 Mbps nedladdningshastighet', 'charging_stations_desc': 'Uttag och USB-portar vid varje bord', 'secure_connection': 'Säker anslutning',
        'secure_connection_desc': 'Företagssäkerhet för att skydda din integritet', 'wifi_tips': 'Tips för bästa upplevelse', 'tip1': 'Anslut till nätverket och ange lösenordet',
        'tip2': 'Acceptera villkoren på inloggningssidan', 'tip3': 'Njut av obegränsad surfning under ditt besök', 'tip4': 'Behöver du hjälp? Fråga våra baristor', 'speed_detail_1': '100 Mbps nedladdningshastighet för smidig surfning',
        'speed_detail_2': '50 Mbps uppladdningshastighet för videosamtal', 'speed_detail_3': 'Låg latens för spel och streaming', 'speed_detail_4': 'Obegränsad dataanvändning med rimlig användningspolicy',
        'charging_detail_1': 'USB-C och USB-A portar vid varje bord', 'charging_detail_2': 'Standarduttag för bärbara datorer', 'charging_detail_3': 'Trådlösa laddningsplattor tillgängliga',
        'charging_detail_4': 'Snabbladdningsstöd för alla enheter', 'secure_detail_1': 'WPA2-Enterprise-kryptering för säker anslutning', 'secure_detail_2': 'Brandväggsskydd mot cyberhot',
        'secure_detail_3': 'Regelbundna säkerhetsuppdateringar och övervakning', 'secure_detail_4': 'Dina data och integritet är alltid skyddade', 'team_members': 'Teammedlemmar',
        'years_experience': 'Års erfarenhet', 'coffee_awards': 'Kaffepriser', 'value_sustainability_desc': 'Vi köper från rättvisemärkta gårdar och använder miljövänliga förpackningar',
        'value_quality_desc': 'Kompromissar aldrig med kvaliteten från böna till kopp', 'value_community_desc': 'Bygger en välkomnande plats för alla', 'value_innovation_desc': 'Utvecklas ständigt för att ge dig den bästa upplevelsen',
   },
    'fa': {
        'home': 'خانه', 'menu': 'منو', 'cart': 'سبد خرید', 'login': 'ورود', 'register': 'ثبت نام',
        'logout': 'خروج', 'profile': 'پروفایل', 'orders': 'سفارشات', 'favorites': 'علاقه‌مندی‌ها',
        'reviews': 'نظرات', 'wifi': 'وای فای', 'my_receipts': 'رسیدهای من', 'loyalty_rewards': 'جوایز وفاداری',
        'refer_friend': 'معرفی به دوست', 'loyalty_points': 'امتیاز وفاداری', 'edit_profile': 'ویرایش پروفایل',
        'full_name': 'نام کامل', 'referral_code': 'کد معرفی', 'claim_reward': 'دریافت جایزه',
        'my_orders': 'سفارشات من', 'update_profile': 'به روز رسانی پروفایل', 'checkout': 'تسویه حساب',
        'add_to_cart': 'افزودن به سبد خرید', 'total': 'مجموع', 'remove': 'حذف', 'clear_cart': 'تخلیه سبد خرید',
        'cart_empty': 'سبد خرید شما خالی است', 'browse_menu': 'مرور منو', 'order_summary': 'خلاصه سفارش',
        'subtotal': 'جمع جزئی', 'tax': 'مالیات (۱۰٪)', 'delivery_fee': 'هزینه ارسال', 'place_order': 'ثبت سفارش',
        'payment_method': 'روش پرداخت', 'delivery_info': 'اطلاعات تحویل', 'pickup_at_store': 'تحویل حضوری',
        'username': 'نام کاربری', 'email': 'ایمیل', 'phone': 'تلفن', 'password': 'رمز عبور', 'coming_soon': 'به زودی!',
        'birthday': 'تاریخ تولد', 'delete_account': 'حذف حساب', 'pending': 'در انتظار', 'completed': 'تکمیل شده',
        'cancelled': 'لغو شده', 'processing': 'در حال پردازش', 'no_items_found': 'آیتمی یافت نشد', 'all': 'همه',
        'coffee': 'قهوه', 'tea': 'چای', 'pastry': 'شیرینی', 'other_drinks': 'نوشیدنی‌های دیگر',
        'employee_dashboard': 'داشبورد کارکنان', 'welcome_back': 'خوش آمدید', 'total_orders_today': 'سفارش‌های امروز',
        'pending_orders': 'سفارش‌های در انتظار', 'completed_today': 'تکمیل شده امروز', 'revenue_today': 'درآمد امروز',
        'inventory_management': 'مدیریت موجودی', 'customer_management': 'مدیریت مشتریان',
        'order_management': 'مدیریت سفارشات', 'back_to_dashboard': 'بازگشت به داشبورد', 'total_products': 'کل محصولات',
        'low_stock': 'موجودی کم', 'out_of_stock': 'ناموجود', 'total_value': 'ارزش کل', 'learn_more': 'بیشتر بدانید',
        'search_products': 'جستجوی محصولات بر اساس نام...', 'no_products_found': 'محصولی یافت نشد',
        'in_stock': 'موجود', 'active': 'فعال', 'inactive': 'غیرفعال', 'refresh': 'بازخوانی',
        'preparing': 'در حال آماده‌سازی', 'ready': 'آماده', 'status': 'وضعیت', 'order_number': 'شماره سفارش',
        'items': 'محصولات', 'customer': 'مشتری', 'guest': 'میهمان', 'order_items': 'محصولات سفارش', 'newsletter_title': 'در خبرنامه ما عضو شوید',
        'employee_login': 'ورود کارکنان', 'staff_access_only': 'فقط برای کارکنان', 'login_as_employee': 'ورود به عنوان کارمند',
        'total_customers': 'کل مشتریان', 'new_this_month': 'جدید این ماه', 'total_revenue': 'کل درآمد',
        'search_customers': 'جستجو بر اساس نام، ایمیل یا تلفن...', 'joined': 'عضو شده از', 'points': 'امتیاز',
        'track_order': 'پیگیری سفارش', 'chatbot_orders_title': 'سفارشات چت‌بات', 'chatbot_checkout': 'تسویه حساب چت‌بات',
        'member_since': 'عضو از', 'total_orders': 'کل سفارشات', 'free_coffees': 'قهوه رایگان', 'friends_referred': 'دوستان معرفی شده',
        'edit_profile': 'ویرایش پروفایل', 'username_cannot_change': 'نام کاربری قابل تغییر نیست', 'birthday_reward': 'در روز تولد ۵۰ امتیاز پاداش بگیرید!',
        'danger_zone': 'منطقه خطر', 'delete_account_warning': 'پس از حذف حساب کاربری، بازگشتی وجود ندارد.', 'delete_account': 'حذف حساب',
        'track_orders': 'پیگیری سفارشات شما', 'order_summary': 'خلاصه سفارش', 'subtotal': 'جمع جزئی', 'tax': 'مالیات (۱۰٪)', 'delivery_fee': 'هزینه ارسال',
        'total': 'مجموع', 'preparing': 'در حال آماده‌سازی', 'ready': 'آماده', 'completed': 'تکمیل شده', 'pending': 'در انتظار', 'cancelled': 'لغو شده',
        'view_receipt': 'مشاهده رسید', 'view_details': 'مشاهده جزئیات', 'currency': 'ارز', 'order_items': 'محصولات سفارش', 'order_number': 'شماره سفارش',
        'date': 'تاریخ', 'status': 'وضعیت', 'amount': 'مبلغ', 'track_order_placeholder': 'شماره سفارش را وارد کنید (مثال: ORD87DB035E)', 'track_button': 'پیگیری سفارش',
        'track_order': 'پیگیری سفارش شما', 'track_order_desc': 'شماره سفارش خود را برای بررسی وضعیت وارد کنید', 'time': 'زمان', 'checkout_order': 'تسویه این سفارش',
        'reorder': 'سفارش مجدد', 'conceal_order': 'مخفی کردن سفارش', 'delete_order': 'حذف سفارش', 'chatbot_orders_desc': 'سفارشات ثبت شده از طریق دستیار باریستا هوشمند ما',
        'complete_order_title': 'تکمیل سفارش شما', 'payment_details': 'جزئیات پرداخت', 'payment_status': 'وضعیت پرداخت', 'unpaid': 'پرداخت نشده', 'name': 'نام', 'phone': 'تلفن',
        'delivery_address_placeholder': 'آدرس تحویل را وارد کنید', 'pickup_instruction': 'برای تحویل حضوری خالی بگذارید', 'credit_card': 'کارت اعتباری', 'paypal': 'پی‌پال',
        'mobilepay': 'موبایل‌پی', 'cash_on_pickup': 'پول نقد هنگام تحویل', 'confirm_info_correct': 'تأیید می‌کنم که تمام اطلاعات صحیح است', 'subscribe': 'عضویت', 'newsletter': 'خبرنامه', 'blog': 'وبلاگ',
        'newsletter_subtitle': 'آخرین اخبار قهوه، پیشنهادات ویژه و نکات دم‌آوری را در صندوق ورودی خود دریافت کنید', 'newsletter_card_title': 'با Bean & Brew به‌روز بمانید',
        'name_placeholder': 'نام کامل خود را وارد کنید', 'email_placeholder': 'آدرس ایمیل خود را وارد کنید', 'privacy_consent': 'با دریافت ایمیل از Bean & Brew Coffee موافقم',
        'subscribe_button': 'عضویت now', 'benefits_title': 'چرا عضو شوید؟', 'benefit_1_title': 'پیشنهادات ویژه', 'benefit_1_desc': 'تخفیف‌ها و پیشنهادات ویژه فقط برای اعضا',
        'benefit_2_title': 'قهوه رایگان', 'benefit_2_desc': 'هر ماه در قرعه‌کشی اعضا قهوه رایگان برنده شوید', 'benefit_3_title': 'دسترسی زودهنگام', 'benefit_3_desc': 'اولین نفری باشید که از ترکیبات جدید قهوه مطلع می‌شوید',
        'benefit_4_title': 'نکات دم‌آوری', 'benefit_4_desc': 'تکنیک‌های حرفه‌ای دم‌آوری قهوه را در خانه یاد بگیرید', 'newsletter_success': 'از عضویت شما متشکریم! ایمیل خود را برای تأیید بررسی کنید.',
        'newsletter_already_subscribed': 'این ایمیل قبلاً در خبرنامه ما عضو شده است.', 'newsletter_error': 'خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'newsletter_check_email': 'لطفاً ایمیل خود را برای تأیید عضویت بررسی کنید!',
        'newsletter_confirmed': 'ایمیل تأیید شد! از عضویت شما متشکریم!', 'newsletter_invalid_token': 'لینک تأیید نامعتبر یا منقضی شده است.', 'newsletter_unsubscribed': 'عضویت شما در خبرنامه ما لغو شد.',
        'coffee_menu_description': 'ترکیبی عالی از عطر و طعم را تجربه کنید. قهوه ما از بهترین دانه‌ها تهیه شده و به طور کامل برشته شده است.',
        'my_favorites': 'علاقه‌مندی‌های من', 'favorite_items': 'محصولات قهوه مورد علاقه شما', 'review_items': 'موارد خود را قبل از تسویه حساب مرور کنید', 'cart_empty_msg': 'به نظر می رسد هنوز آیتمی اضافه نکرده اید',
        'notifications_enabled': 'اعلان‌ها فعال شد!', 'notifications_blocked': 'اعلان‌ها مسدود شد', 'no_favorites': 'هنوز علاقه‌مندی وجود ندارد',
        'no_favorites_msg': 'در منوی ما بگردید و روی آیکون قلب کلیک کنید تا آیتم‌های مورد علاقه خود را اضافه کنید!', 'removed_from_favorites': 'از علاقه‌مندی‌ها حذف شد!',
        'added_to_cart': 'به سبد خرید اضافه شد!', 'login_title': 'وارد حساب کاربری خود شوید', 'admin_register': 'ثبت نام ادمین', 'register_here': 'ثبت نام از اینجا',
        'register_title': 'ایجاد حساب کاربری جدید', 'register_subtitle': 'امروز به Bean & Brew Coffee بپیوندید', 'already_have_account': 'از قبل حساب کاربری دارید؟',
        'login_instead': 'وارد شوید', 'create_account': 'ایجاد حساب', 'confirm_password': 'تأیید رمز عبور', 'welcome_back': 'خوش آمدید!', 'remember_me': 'مرا به خاطر بسپار',
        'forgot_password': 'رمز عبور را فراموش کرده‌اید؟', 'or_login_with': 'یا وارد شوید با', 'no_account': 'حساب کاربری ندارید؟', 'continue_as_guest': 'ادامه به عنوان مهمان',
        'join_us': 'به ما بپیوندید', 'username_placeholder': 'یک نام کاربری انتخاب کنید', 'email_placeholder': 'ایمیل خود را وارد کنید', 'password_placeholder': 'رمز عبور ایجاد کنید',
        'confirm_password_placeholder': 'رمز عبور را تأیید کنید', 'full_name_placeholder': 'نام کامل خود را وارد کنید (اختیاری)', 'phone_placeholder': 'شماره تلفن خود را وارد کنید (اختیاری)',
        'referral_discount': 'در ثبت نام ۵۰ امتیاز پاداش دریافت می‌کنید!', 'and': 'و', 'terms_conditions': 'شرایط و ضوابط', 'privacy_policy': 'سیاست حریم خصوصی', 'view_all_receipts': 'مشاهده همه رسیدها',
        'action': 'عملیات', 'download_pdf': 'دانلود PDF', 'no_receipts': 'هنوز رسیدی وجود ندارد', 'no_receipts_msg': 'هنوز هیچ سفارشی ثبت نکرده‌اید. برای مشاهده رسیدهای خود سفارش دهید.',
        'refer_friend': 'معرفی به دوست', 'share_code_earn_coffee': 'کد خود را به اشتراک بگذارید و قهوه رایگان دریافت کنید!', 'your_referral_code': 'کد معرفی شما',
        'share_this_link': 'اشتراک‌گذاری این لینک:', 'how_it_works': 'نحوه کار', 'step1': 'کد معرفی خود را با دوستان به اشتراک بگذارید', 'step2': 'دوستان با استفاده از کد شما ثبت نام می‌کنند',
        'step3': 'دوستان اولین سفارش خود را ثبت می‌کنند', 'step4': 'هر دو شما ۱۰۰ امتیاز پاداش دریافت می‌کنید!', 'your_referrals': 'معرفی‌های شما:', 'friends_joined': 'دوستان عضو شده',
        'points_earned': 'امتیاز کسب شده', 'scan_qr_code': 'اسکن کد QR', 'qr_instruction': 'دوستان می‌توانند این کد QR را اسکن کنند تا لینک معرفی شما را دریافت کنند!',
        'your_referrals_list': 'معرفی‌های شما', 'friend': 'دوست', 'date_joined': 'تاریخ عضویت', 'points_earned_table': 'امتیاز کسب شده', 'no_referrals_yet': 'هنوز معرفی نداشته‌اید. کد خود را به اشتراک بگذارید!',
        'download_qr': 'دانلود کد QR', 'copy_code': 'کپی کد', 'referral_code_copied': 'کد معرفی کپی شد!', 'referral_link_copied': 'لینک معرفی کپی شد!', 'qr_not_ready': 'کد QR هنوز آماده نیست. لطفاً دوباره تلاش کنید.',
        'coffee_times_blog': 'وبلاگ قهوه تایمز', 'blog_subtitle': 'داستان‌ها، اخبار و نکات از Bean & Brew Coffee', 'no_articles': 'هنوز مقاله‌ای وجود ندارد', 'no_articles_msg': 'به زودی برای اخبار و داستان‌های قهوه بازگردید!',
        'add_article': 'افزودن مقاله', 'back_to_blog': 'بازگشت به وبلاگ', 'contact_us': 'تماس با ما', 'contact_subtitle': 'با ما در تماس باشید. خوشحال می‌شویم از شما بشنویم!',
        'our_location': 'موقعیت ما', 'phone_number': 'شماره تلفن', 'email_us': 'ایمیل', 'send_us_message': 'برای ما پیام بفرستید', 'your_name': 'نام شما', 'your_email': 'ایمیل شما',
        'subject': 'موضوع', 'subject_placeholder': 'این پیام در مورد چیست؟', 'message': 'پیام', 'message_placeholder': 'پیام خود را اینجا بنویسید...', 'send_message': 'ارسال پیام',
        'mon_fri_9_17': 'شنبه تا پنجشنبه ۹-۱۷', 'response_time': 'پاسخ در عرض ۲۴ ساعت', 'form_description': 'فرم زیر را پر کنید، در اسرع وقت با شما تماس خواهیم گرفت.',
        'find_us': 'ما را پیدا کنید', 'map_description': 'از کافی شاپ دنج ما در وانتا دیدن کنید', 'opening_hours': 'ساعات کاری', 'name_placeholder': 'نام کامل خود را وارد کنید',
        'monday_friday': 'دوشنبه - جمعه', 'saturday_sunday': 'شنبه - یکشنبه', 'benefits_subtitle': 'به جامعه قهوه ما بپیوندید و از مزایای انحصاری لذت ببرید!', 'complete_order': 'تکمیل سفارش',
        'cancel_order': 'لغو سفارش', 'download_receipt': 'دانلود رسید', 'loyalty_program': 'برنامه وفاداری', 'loyalty_subtitle': 'با هر خرید امتیاز کسب کنید و جوایز رایگان دریافت کنید!',
        'your_loyalty_points': 'امتیاز وفاداری شما', 'earn_more_points': 'برای قهوه رایگان ۱۰۰ امتیاز کسب کنید!', 'points_note': '۱ امتیاز = ۱ یورو هزینه شده', 'how_to_earn': 'نحوه کسب امتیاز',
        'make_purchase': 'خرید کنید', 'earn_points_per_dollar': 'به ازای هر ۱ یورو هزینه شده، ۱ امتیاز کسب کنید', 'birthday_bonus': 'پاداش تولد', 'birthday_bonus_desc': 'در روز تولد خود ۵۰ امتیاز پاداش دریافت کنید!',
        'refer_friend_desc': 'به ازای هر معرفی، ۱۰۰ امتیاز کسب کنید', 'available_rewards': 'جوایز موجود', 'redeem': 'دریافت', 'points': 'امتیاز', 'no_rewards': 'در حال حاضر هیچ جایزه‌ای در دسترس نیست.',
        'redeem_confirm': 'جایزه دریافت شود؟', 'redeem_success': 'جایزه با موفقیت دریافت شد!', 'not_enough_points': 'امتیاز کافی نیست!', 'points_needed': 'امتیاز مورد نیاز',
        'about_us': 'درباره ما', 'about_subtitle': 'درباره داستان قهوه و اشتیاق ما بیشتر بدانید', 'our_story': 'داستان ما', 'story_text_1': 'Bean & Brew Coffee در سال ۲۰۱۵ با مأموریتی ساده تأسیس شد: ارائه بهترین تجربه قهوه به دوستداران قهوه در سراسر جهان. آنچه به عنوان یک گاری کوچک قهوه در بازار محلی آغاز شد، به یک کافی‌شاپ محبوب در قلب وانتا تبدیل شده است.',
        'story_text_2': 'سفر ما زمانی آغاز شد که بنیانگذار ما، عزیز رحمان، اشتیاق خود را به قهوه در حین سفر در مناطق قهوه‌خیز اتیوپی و کلمبیا کشف کرد. او که از طعم‌های غنی و مهمان‌نوازی گرم کشاورزان قهوه الهام گرفته بود، تصمیم گرفت آن تجربه را به خانه بیاورد.',
        'story_text_3': 'امروز، ما دانه‌های قهوه را مستقیماً از مزارع پایدار تهیه می‌کنیم، آنها را در دسته‌های کوچک برشته می‌کنیم و هر فنجان را با عشق و مراقبت سرو می‌کنیم. هر فنجان داستانی از مهارت، کیفیت و جامعه را روایت می‌کند.',
        'our_mission': 'ماموریت ما', 'mission_text': 'ارائه قهوه استثنایی در عین ساختن جامعه‌ای حول اشتیاق مشترک به کیفیت، پایداری و ارتباط انسانی.', 'our_vision': 'چشم‌انداز ما',
        'vision_text': 'بودن محبوب‌ترین کافی‌شاپ در فنلاند، شناخته شده برای کیفیت استثنایی، مهمان‌نوازی گرم و تأثیر مثبت بر جامعه و محیط زیست ما.', 'our_values': 'ارزش‌های ما',
        'values_text': 'کیفیت، پایداری، جامعه، نوآوری و اشتیاق همه کارهای ما را هدایت می‌کنند.', 'meet_our_team': 'با تیم ما آشنا شوید', 'team_aziz_bio': 'عاشق قهوه با بیش از ۱۵ سال تجربه. عزیز شخصاً هر دسته را انتخاب و برشته می‌کند تا بالاترین کیفیت را تضمین کند.',
        'team_maria_bio': 'باریستای برنده جوایز با اشتیاق به هنر لاته و ایجاد تجارب به یاد ماندنی قهوه برای هر مشتری.', 'team_johan_bio': 'برای تهیه بهترین دانه‌ها مستقیماً از کشاورزان به سراسر جهان سفر می‌کند و تجارت عادلانه و شیوه‌های پایدار را تضمین می‌کند.',
        'our_core_values': 'ارزش‌های اصلی ما', 'value_sustainability': 'پایداری', 'value_quality': 'کیفیت اول', 'value_community': 'جامعه', 'value_innovation': 'نوآوری',
        'team_management': 'مدیریت تیم', 'add_team_member': 'افزودن عضو تیم', 'edit_team_member': 'ویرایش عضو تیم', 'team_members': 'اعضای تیم', 'name': 'نام', 'position': 'سمت',
        'display_order': 'ترتیب نمایش', 'status': 'وضعیت', 'active': 'فعال', 'inactive': 'غیرفعال', 'actions': 'عملیات', 'save': 'ذخیره', 'update': 'به‌روزرسانی', 'cancel': 'لغو',
        'delete_confirm': 'آیا مطمئن هستید که می‌خواهید این عضو تیم را حذف کنید؟', 'no_team_members': 'هنوز عضو تیمی وجود ندارد', 'no_team_members_msg': 'اولین عضو تیم خود را اضافه کنید تا تیم شگفت‌انگیز خود را به نمایش بگذارید!',
        'bio': 'بیوگرافی', 'active_status': 'فعال (نمایش در وب‌سایت)', 'team_management': 'مدیریت تیم', 'add_team_member': 'افزودن عضو تیم', 'edit_team_member': 'ویرایش عضو تیم',
        'team_members': 'اعضای تیم', 'edit': 'ویرایش', 'delete': 'حذف', 'admin': 'مدیر', 'admin_dashboard': 'داشبورد', 'admin_inventory': 'موجودی', 'admin_customers': 'مشتریان',
        'admin_export': 'خروجی', 'admin_newsletter': 'خبرنامه', 'admin_panel': 'پنل مدیریت', 'newsletter_subscribers': 'مشترکین خبرنامه', 'admin_access': 'دسترسی مدیر',
        'admin_access_desc': 'به منطقه مدیریت خوش آمدید', 'sales_dashboard_desc': 'مشاهده تحلیل فروش و گزارش‌ها', 'inventory_desc': 'مدیریت محصولات و سطوح موجودی',
        'customers_desc': 'مشاهده و مدیریت اطلاعات مشتریان', 'export_desc': 'خروجی داده به فایل‌های CSV', 'newsletter_desc': 'مدیریت مشترکین خبرنامه', 'team_desc': 'مدیریت اعضای تیم',
        'chatbot_orders': 'سفارشات چت‌بات', 'chatbot_orders_desc': 'مشاهده سفارشات از چت‌بات', 'employee_dashboard_desc': 'پنل مدیریت کارکنان', 'access': 'دسترسی',
        'admin_access_warning': '⚠️ منطقه ممنوعه', 'admin_access_warning_desc': 'این منطقه فقط برای پرسنل مجاز است. هرگونه دسترسی غیرمجاز ثبت خواهد شد.', 'export_csv': 'خروجی CSV',
        'subscribers_list': 'لیست مشترکین', 'subscribed_date': 'تاریخ اشتراک', 'confirmed': 'تأیید شده', 'no_subscribers': 'هنوز مشترکی وجود ندارد', 'no_subscribers_msg': 'هنوز کسی در خبرنامه عضو نشده است.',
        'delete_subscriber_confirm': 'آیا مطمئن هستید که می‌خواهید این مشترک را حذف کنید؟', 'delete_error': 'خطا در حذف مشترک', 'welcome_back_admin': 'خوش آمدید،', 'admin_dashboard_subtitle': 'کافی شاپ خود را از یک داشبورد مرکزی مدیریت کنید',
        'low_stock_alerts': 'هشدارهای موجودی کم', 'top_selling_products': 'محصولات پرفروش', 'recent_orders': 'سفارش‌های اخیر', 'monthly_sales_3d': 'فروش ماهانه (۳ بعدی)',
        'quick_actions': 'اقدامات سریع', 'management_center': 'مرکز مدیریت', 'manage_inventory': 'مدیریت موجودی', 'update_stock_products': 'به‌روزرسانی سطوح موجودی و محصولات',
        'customer_list': 'لیست مشتریان', 'view_manage_customers': 'مشاهده و مدیریت همه مشتریان', 'export_data': 'خروجی داده', 'download_reports': 'دانلود گزارش‌ها و داده‌ها',
        'write_blog': 'نوشتن وبلاگ', 'publish_articles': 'انتشار مقالات جدید', 'sales_analytics': 'تحلیل فروش', 'view_reports_insights': 'مشاهده گزارش‌ها و بینش‌های دقیق',
        'newsletter_hub': 'مرکز خبرنامه', 'manage_subscribers_campaigns': 'مدیریت مشترکین و کمپین‌ها', 'team_management': 'مدیریت تیم', 'manage_team_members': 'افزودن، ویرایش یا حذف اعضای تیم',
        'chatbot_orders': 'سفارشات چت‌بات', 'view_ai_orders': 'مشاهده سفارشات دستیار هوشمند', 'news_manager': 'مدیریت اخبار', 'manage_news_articles': 'مدیریت مقالات اخبار قهوه',
        'employee_portal': 'پورتال کارکنان', 'staff_management': 'پنل مدیریت کارکنان', 'access_dashboard': 'دسترسی به داشبورد', 'manage_subscribers': 'مدیریت مشترکین',
        'manage_team': 'مدیریت تیم', 'view_orders': 'مشاهده سفارشات', 'manage_news': 'مدیریت اخبار', 'access_portal': 'دسترسی به پورتال', 'loading': 'در حال بارگذاری',
        'no_products_found': 'محصولی یافت نشد', 'all_stock_healthy': 'همه محصولات دارای سطوح موجودی سالم هستند!', 'restock': 'تکمیل موجودی', 'out_of_stock': 'ناموجود',
        'no_sales_data': 'هنوز داده فروشی وجود ندارد', 'error_loading_data': 'خطا در بارگذاری داده‌ها', 'no_orders_yet': 'هنوز سفارشی وجود ندارد', 'error_loading_orders': 'خطا در بارگذاری سفارشات',
        'no_sales_data_available': 'داده فروشی در دسترس نیست', 'view_all_products': 'مشاهده همه محصولات', 'view_full_report': 'مشاهده گزارش کامل', 'view_all_orders': 'مشاهده همه سفارشات',
        'detailed_analytics': 'تحلیل دقیق', 'access': 'دسترسی', 'no_orders': 'هنوز سفارشی ثبت نشده', 'start_shopping': 'خرید خود را شروع کنید تا سفارشات خود را در اینجا مشاهده کنید',
        'browse_menu': 'مشاهده منو', 'cancel_order': 'لغو سفارش', 'estimated_time': 'زمان تقریبی', 'pickup_location': 'مکان تحویل', 'support': 'پشتیبانی', 'reorder_success': 'محصولات به سبد خرید اضافه شد!',
        'reorder_error': 'خطا در ثبت مجدد سفارش. لطفاً دوباره تلاش کنید.', 'conceal_order_confirm': 'پنهان کردن این سفارش؟', 'conceal_order_success': 'سفارش با موفقیت پنهان شد!',
        'conceal_order_error': 'خطا در پنهان کردن سفارش.', 'delete_order_confirm': 'حذف دائمی این سفارش؟', 'delete_order_success': 'سفارش با موفقیت حذف شد!', 'delete_order_error': 'خطا در حذف سفارش.',
        'no_chatbot_orders': 'سفارش‌های چت‌بات وجود ندارد', 'chatbot_empty_msg': 'هنوز سفارشی از طریق چت‌بات ثبت نکرده‌اید.', 'start_chatting': 'شروع مکالمه', 'loyalty_points': 'امتیازها',
        'available_rewards': 'جوایز', 'next_reward': 'بعدی', 'your_code': 'کد شما', 'earn_points': 'دریافت', 'points_on_order': 'امتیاز از این سفارش', 'delivery_payment': 'تحویل و پرداخت',
        'enter_coupon': 'کد تخفیف را وارد کنید', 'apply': 'اعمال', 'item': 'محصول', 'qty': 'تعداد', 'price': 'قیمت', 'not_provided': 'ثبت نشده', 'delivery_address': 'آدرس تحویل',
        'cash_on_delivery': 'پرداخت نقدی در تحویل', 'place_order': 'ثبت سفارش', 'cancel': 'لغو', 'cancel_checkout_confirm': 'آیا مطمئن هستید که می‌خواهید پرداخت را لغو کنید؟ کد تخفیف حذف خواهد شد.',
        'loyalty_points': 'امتیازات وفاداری', 'delivery_payment': 'تحویل و پرداخت', 'apply_coupon': 'اعمال کد تخفیف', 'coupon_applied': 'کد تخفیف با موفقیت اعمال شد!',
        'coupon_error': 'خطا در اعمال کد تخفیف', 'sales_dashboard': 'داشبورد فروش', 'sales_dashboard_desc': 'عملکرد کافی‌شاپ خود را پیگیری کنید، روندها را تحلیل کنید و کسب‌وکار خود را رشد دهید',
        'period': 'دوره', 'this_week': 'این هفته', 'this_month': 'این ماه', 'this_year': 'امسال', 'all_time': 'همه زمان‌ها', 'total_sales': 'کل فروش', 'happy_customers': 'مشتریان راضی',
        'total_revenue': 'کل درآمد', 'total_orders': 'کل سفارشات', 'total_customers': 'کل مشتریان', 'avg_order_value': 'میانگین ارزش سفارش', 'sales_trend': 'روند فروش',
        'revenue_overview': 'نمای کلی درآمد', 'category_distribution': 'توزیع دسته‌بندی', 'top_products': 'محصولات برتر', 'top_selling_products': 'محصولات پرفروش', 'loading_data': 'در حال بارگذاری داده‌ها...',
        'sales': 'فروش', 'revenue': 'درآمد', 'sold': 'فروخته شده', 'no_sales_data': 'داده‌های فروش موجود نیست', 'select_both_dates': 'لطفاً تاریخ شروع و پایان را انتخاب کنید',
        'start_date': 'تاریخ شروع', 'end_date': 'تاریخ پایان', 'apply': 'اعمال', 'inventory_management': 'مدیریت موجودی', 'inventory_desc': 'سطح موجودی را پیگیری کنید، محصولات را مدیریت کنید و ارزش موجودی را نظارت کنید',
        'low_stock_alert': 'هشدار موجودی کم', 'inventory_value': 'ارزش موجودی', 'add_product': 'افزودن محصول', 'low_stock': 'موجودی کم (کمتر از 10)', 'out_of_stock': 'تمام شده',
        'id': 'شناسه', 'product': 'محصول', 'category': 'دسته‌بندی', 'price': 'قیمت', 'stock': 'موجودی', 'status': 'وضعیت', 'actions': 'عملیات', 'loading': 'در حال بارگذاری',
        'product_name': 'نام محصول', 'stock_quantity': 'تعداد موجودی', 'description': 'توضیحات', 'save_product': 'ذخیره محصول', 'cancel': 'لغو', 'update_stock': 'به‌روزرسانی موجودی',
        'current_stock': 'موجودی فعلی', 'new_stock_quantity': 'تعداد موجودی جدید', 'product_added': 'محصول اضافه شد!', 'stock_updated': 'موجودی به‌روز شد!', 'units': 'واحد',
        'in_stock': 'در انبار', 'customer_management': 'مدیریت مشتریان', 'customer_management_desc': 'مشتریان خود را مدیریت کنید، سفارشات آنها را پیگیری کنید و روابط پایدار بسازید',
        'new_this_month': 'جدید این ماه', 'total_spent': 'مجموع هزینه', 'customer': 'مشتری', 'orders': 'سفارشات', 'joined': 'عضو شده', 'edit_customer': 'ویرایش مشتری',
        'save_changes': 'ذخیره تغییرات', 'customer_details': 'جزئیات مشتری', 'member_since': 'عضو از', 'no_customers_found': 'مشتری‌ای یافت نشد','view': 'مشاهده', 'customer_updated': 'مشتری با موفقیت به‌روز شد!',
        'customer_deleted': 'مشتری با موفقیت حذف شد!', 'customer_delete_error': 'امکان حذف مشتری با سفارشات موجود وجود ندارد', 'delete_customer_confirm': 'حذف مشتری', 'export_reports': 'خروجی گزارش‌ها',
        'export_desc': 'داده‌های خود را در فرمت CSV، Excel یا PDF دانلود کنید','formats': 'فرمت‌ها', 'date_range': 'بازه زمانی', 'customizable': 'قابل تنظیم', 'reports': 'گزارش‌ها',
        'types': 'انواع', 'sales_report': 'گزارش فروش', 'sales_report_desc': 'داده‌های سفارشات و درآمد', 'products_report': 'گزارش محصولات', 'products_report_desc': 'موجودی و سطح موجودی',
        'customers_report': 'گزارش مشتریان', 'customers_report_desc': 'داده‌های مشتریان و وفاداری', 'inventory_report': 'گزارش موجودی', 'inventory_report_desc': 'ارزش موجودی و هشدارها',
        'format': 'فرمت', 'export_sales': 'خروجی فروش', 'export_products': 'خروجی محصولات', 'export_customers': 'خروجی مشتریان', 'export_inventory': 'خروجی موجودی', 'data_preview': 'پیش‌نمایش داده',
        'click_export_preview': 'برای مشاهده پیش‌نمایش روی خروجی کلیک کنید', 'no_data_available': 'داده‌ای موجود نیست', 'customer_reviews': 'نظرات مشتریان', 'reviews_subtitle': 'عاشقان قهوه ما در مورد ما چه می‌گویند',
        'average_rating': 'میانگین امتیاز', 'total_reviews': 'تعداد نظرات', 'happy_customers': 'مشتریان راضی', 'review1_text': 'بهترین قهوه شهر! فضا شگفت‌انگیز است و کارکنان فوق‌العاده دوستانه هستند.',
        'review2_text': 'شیرینی‌های آنها تازه و خوشمزه هستند. کروسان با کاپوچینو ترکیب صبحگاهی عالی است.', 'review3_text': 'عاشق فضای دنج و وای فای رایگان هستم. مکان عالی برای کار از راه دور در حین لذت بردن از قهوه عالی.',
        'review4_text': 'چای لاته شگفت‌انگیز است! انتخاب عالی چای و باریستاهای دوستانه. قطعاً برمی‌گردم.', 'review5_text': 'بالاخره جایی پیدا کردم که اسپرسوی عالی درست می‌کند. برنامه وفاداری آنها نیز عالی است.',
        'review6_text': 'سرویس مشتری شگفت‌انگیز! آنها سفارش هفته گذشته من را به خاطر داشتند. مافین زغال اخته بهشتی است!', 'coffee_enthusiast': 'عاشق قهوه', 'regular_customer': 'مشتری همیشگی',
        'remote_worker': 'کارمند از راه دور', 'tea_lover': 'عاشق چای', 'coffee_addict': 'معتاد به قهوه', 'loyal_customer': 'مشتری وفادار', 'share_experience': 'تجربه خود را به اشتراک بگذارید',
        'share_experience_msg': 'ما دوست داریم در مورد بازدید شما از بین اند برو بشنویم', 'write_review': 'نوشتن نظر', 'login_write_review': 'برای نوشتن نظر وارد شوید',
        'write_review_placeholder': 'نظر خود را اینجا بنویسید...', 'submit_review': 'ارسال نظر', 'select_rating': 'لطفاً امتیاز را انتخاب کنید', 'review_thanks': 'از نظر شما متشکریم!',
        'subscribers_count': 'اعضا', 'weekly_emails': 'ایمیل‌های هفتگی', 'exclusive_offers': 'پیشنهادات ویژه', 'news_manager': 'مدیریت اخبار', 'manage_news_articles': 'مدیریت مقالات خبری قهوه خود',
        'total': 'مجموع', 'published': 'منتشر شده', 'draft': 'پیش‌نویس', 'add_article': 'افزودن مقاله', 'all': 'همه', 'news': 'اخبار', 'tips': 'نکات', 'offers': 'پیشنهادات',
        'articles_list': 'لیست مقالات', 'id': 'شناسه', 'title': 'عنوان', 'category': 'دسته‌بندی', 'author': 'نویسنده', 'date': 'تاریخ', 'status': 'وضعیت', 'actions': 'عملیات',
        'view': 'مشاهده', 'edit': 'ویرایش', 'delete': 'حذف', 'delete_confirm': 'آیا مطمئن هستید که می‌خواهید این مقاله را حذف کنید؟', 'delete_error': 'خطا در حذف مقاله',
        'published_date': 'تاریخ انتشار', 'write_new_story': 'نوشتن داستان جدید', 'share_coffee_knowledge': 'دانش قهوه خود را با جهان به اشتراک بگذارید', 'auto_translate': 'ترجمه خودکار',
        'languages': 'زبان‌ها', 'publish_globally': 'انتشار جهانی', 'yes': 'بله', 'estimated_time': 'زمان تقریبی', 'minutes': 'دقیقه', 'title': 'عنوان', 'english': 'انگلیسی',
        'enter_title': 'عنوان جذاب وارد کنید...', 'characters': 'کاراکتر', 'category': 'دسته‌بندی', 'news_updates': 'اخبار و به‌روزرسانی‌ها', 'brewing_tips': 'نکات و ترفندهای دم‌آوری',
        'special_offers': 'پیشنهادات ویژه', 'giveaways': 'قرعه‌کشی‌ها', 'coffee_lifestyle': 'سبک زندگی قهوه', 'coffee_history': 'تاریخچه قهوه', 'health_benefits': 'مزایای سلامتی',
        'image_url': 'آدرس تصویر', 'or': 'یا', 'preview': 'پیش‌نمایش', 'leave_empty_default': 'برای استفاده از تصویر پیش‌فرض خالی بگذارید', 'content': 'محتوا', 'write_article_auto_translate': 'مقاله شگفت‌انگیز خود را اینجا بنویسید... به طور خودکار به فنلاندی، سوئدی و فارسی ترجمه می‌شود!',
        'publish_auto_translate': 'انتشار و ترجمه خودکار', 'cancel': 'لغو', 'read_more': 'ادامه مطلب', 'free_wifi': 'وای فای رایگان', 'wifi_subtitle': 'در حین لذت بردن از قهوه خود متصل بمانید',
        'speed': 'سرعت', 'charging_stations': 'ایستگاه‌های شارژ', 'available': 'در دسترس', 'secure': 'امن', 'network_name': 'نام شبکه', 'password': 'رمز عبور', 'quick_connect': 'اتصال سریع',
        'scan_qr_connect': 'برای اتصال فوری کد QR را اسکن کنید', 'available_all_tables': 'در دسترس در تمام میزها', 'high_speed': 'سرعت بالا', 'high_speed_desc': 'سرعت دانلود تا ۱۰۰ مگابیت بر ثانیه',
        'charging_stations_desc': 'پریز و پورت USB در هر میز', 'secure_connection': 'اتصال امن', 'secure_connection_desc': 'امنیت در سطح سازمانی برای محافظت از حریم خصوصی شما',
        'wifi_tips': 'نکاتی برای بهترین تجربه', 'tip1': 'به شبکه متصل شوید و رمز عبور را وارد کنید', 'tip2': 'شرایط و ضوابط را در صفحه ورود بپذیرید', 'tip3': 'در طول مدت بازدید خود از مرور نامحدود لذت ببرید',
        'tip4': 'به کمک نیاز دارید؟ از باریستاهای ما بپرسید', 'speed_detail_1': 'سرعت دانلود ۱۰۰ مگابیت بر ثانیه برای مرور روان', 'speed_detail_2': 'سرعت آپلود ۵۰ مگابیت بر ثانیه برای تماس‌های ویدیویی',
        'speed_detail_3': 'تأخیر کم برای بازی و استریم', 'speed_detail_4': 'مصرف نامحدود داده با سیاست استفاده منصفانه', 'charging_detail_1': 'پورت‌های USB-C و USB-A در هر میز',
        'charging_detail_2': 'پریزهای برق استاندارد برای لپ‌تاپ‌ها', 'charging_detail_3': 'پدهای شارژ بی‌سیم موجود', 'charging_detail_4': 'پشتیبانی از شارژ سریع برای همه دستگاه‌ها',
        'secure_detail_1': 'رمزگذاری WPA2-Enterprise برای اتصال امن', 'secure_detail_2': 'محافظت فایروال در برابر تهدیدات سایبری', 'secure_detail_3': 'به‌روزرسانی‌های امنیتی منظم و نظارت',
        'secure_detail_4': 'داده‌ها و حریم خصوصی شما همیشه محافظت می‌شود', 'team_members': 'اعضای تیم', 'years_experience': 'سال تجربه', 'coffee_awards': 'جوایز قهوه',
        'value_sustainability_desc': 'ما از مزارع تجارت منصفانه تهیه می‌کنیم و از بسته‌بندی سازگار با محیط زیست استفاده می‌کنیم', 'value_quality_desc': 'هرگز در کیفیت از دانه تا فنجان مصالحه نمی‌کنیم',
        'value_community_desc': 'ایجاد فضایی گرم و پذیرا برای همه', 'value_innovation_desc': 'به طور مداوم در حال تکامل برای ارائه بهترین تجربه به شما',
    }
}

def t(key):
    lang = session.get('lang', 'en')
    if lang in translations and key in translations[lang]:
        return translations[lang][key]
    if 'en' in translations and key in translations['en']:
        return translations['en'][key]
    return key

# ============ CURRENCY CONFIGURATION ============
EXCHANGE_RATES = {'USD': 1.0, 'EUR': 0.92, 'SEK': 10.5, 'AFN': 71.5}
CURRENCY_SYMBOLS = {'USD': '$', 'EUR': '€', 'SEK': 'kr', 'AFN': 'AFN'}

def convert_currency(amount_usd, target_currency='USD'):
    if not target_currency or target_currency not in EXCHANGE_RATES:
        target_currency = 'USD'
    try:
        return float(amount_usd) * EXCHANGE_RATES[target_currency]
    except (TypeError, ValueError):
        return 0

def format_currency(amount_usd, currency=None):
    if currency is None:
        currency = session.get('currency', 'USD')
    if currency not in CURRENCY_SYMBOLS:
        currency = 'USD'
    converted = convert_currency(amount_usd, currency)
    symbol = CURRENCY_SYMBOLS.get(currency, '$')
    if currency == 'AFN':
        return f"{converted:,.0f} {symbol}"
    return f"{symbol} {converted:,.2f}"

@app.context_processor
def utility_processor():
    def now():
        return datetime.now()
    def currency():
        curr = session.get('currency', 'USD')
        symbols = {'USD': '$', 'EUR': '€', 'SEK': 'kr', 'AFN': 'AFN'}
        return symbols.get(curr, '$')
    def convert_price(price):
        price_float = float(price)
        currency_code = session.get('currency', 'USD')
        if currency_code == 'EUR':
            return price_float * 0.92
        elif currency_code == 'SEK':
            return price_float * 10.5
        elif currency_code == 'AFN':
            return price_float * 71.5
        return price_float
    def format_price(price):
        try:
            converted = convert_price(price)
            currency_code = session.get('currency', 'USD')
            if currency_code == 'EUR':
                return f"{converted:.2f} €"
            elif currency_code == 'SEK':
                return f"{converted:.2f} kr"
            elif currency_code == 'AFN':
                return  f"\u200E{converted:,.0f} AFN"
            return f"{converted:.2f} $"
        except:
            return f"{price} $"
    return dict(now=now, currency=currency, convert_price=convert_price, format_price=format_price, format_currency=format_currency, t=t)

@app.route('/set-lang/<lang>')
def set_language(lang):
    if lang in ['en', 'fi', 'sv', 'fa']:
        session['lang'] = lang
        if lang == 'en':
            session['currency'] = 'USD'
        elif lang == 'fi':
            session['currency'] = 'EUR'
        elif lang == 'sv':
            session['currency'] = 'SEK'
        elif lang == 'fa':
            session['currency'] = 'AFN'
    return redirect(request.referrer or url_for('index'))

@app.route('/change-currency/<currency_code>')
def change_currency(currency_code):
    if currency_code in ['USD', 'EUR', 'SEK', 'AFN']:
        session['currency'] = currency_code
        if currency_code == 'USD':
            session['lang'] = 'en'
        elif currency_code == 'EUR':
            session['lang'] = 'fi'
        elif currency_code == 'SEK':
            session['lang'] = 'sv'
        elif currency_code == 'AFN':
            session['lang'] = 'fa'
    return redirect(request.referrer or url_for('index'))

# ============ USER CLASS ============
class User:
    def __init__(self, id, username, email, is_admin=False, loyalty_points=0, full_name=None, phone=None, referral_code=None, referred_by=None, birthday=None, last_birthday_redeemed=None, profile_picture=None, role='customer'):
        self.id = id
        self.username = username
        self.email = email
        self.is_admin = is_admin
        self.loyalty_points = loyalty_points
        self.full_name = full_name
        self.phone = phone
        self.referral_code = referral_code
        self.referred_by = referred_by
        self.birthday = birthday
        self.last_birthday_redeemed = last_birthday_redeemed
        self.profile_picture = profile_picture
        self.role = role
        self.is_authenticated = True
        self.is_active = True
        self.is_anonymous = False
    def get_id(self):
        return str(self.id)
    @staticmethod
    def get_by_id(user_id):
        db = get_db()
        cursor = db.cursor()
        cursor.execute("SELECT id, username, email, is_admin, loyalty_points, full_name, phone, referral_code, referred_by, birthday, last_birthday_redeemed, profile_picture, role FROM users WHERE id = %s", (user_id,))
        data = cursor.fetchone()
        db.close()
        if data:
            return User(data['id'], data['username'], data['email'], data['is_admin'], data.get('loyalty_points', 0), data.get('full_name'), data.get('phone'), data.get('referral_code'), data.get('referred_by'), data.get('birthday'), data.get('last_birthday_redeemed'), data.get('profile_picture'), data.get('role', 'customer'))
        return None
    @staticmethod
    def get_by_username(username):
        db = get_db()
        cursor = db.cursor()
        cursor.execute("SELECT id, username, email, is_admin, loyalty_points, full_name, phone, referral_code, referred_by, birthday, last_birthday_redeemed, profile_picture, password_hash, role FROM users WHERE username = %s", (username,))
        data = cursor.fetchone()
        db.close()
        if data:
            user = User(data['id'], data['username'], data['email'], data['is_admin'], data.get('loyalty_points', 0), data.get('full_name'), data.get('phone'), data.get('referral_code'), data.get('referred_by'), data.get('birthday'), data.get('last_birthday_redeemed'), data.get('profile_picture'), data.get('role', 'customer'))
            user.password_hash = data['password_hash']
            return user
        return None
    def check_password(self, password):
        return bcrypt.check_password_hash(self.password_hash, password)
    @staticmethod
    def create_user(username, email, password, full_name=None, phone=None):
        db = get_db()
        cursor = db.cursor()
        password_hash = bcrypt.generate_password_hash(password).decode('utf-8')
        referral_code = 'COFFEE' + str(uuid.uuid4())[:6].upper()
        cursor.execute("INSERT INTO users (username, email, password_hash, full_name, phone, referral_code) VALUES (%s, %s, %s, %s, %s, %s)", (username, email, password_hash, full_name, phone, referral_code))
        db.commit()
        db.close()
    def add_loyalty_points(self, points, reason="Purchase"):
        db = get_db()
        cursor = db.cursor()
        cursor.execute("UPDATE users SET loyalty_points = loyalty_points + %s WHERE id = %s", (points, self.id))
        cursor.execute("INSERT INTO loyalty_transactions (user_id, points, reason) VALUES (%s, %s, %s)", (self.id, points, reason))
        db.commit()
        db.close()
        self.loyalty_points += points
        return True
    def check_birthday_reward(self):
        if not self.birthday:
            return False
        today = date.today()
        birthday_this_year = date(today.year, self.birthday.month, self.birthday.day)
        if birthday_this_year == today and self.last_birthday_redeemed != today.year:
            return True
        return False
    def claim_birthday_reward(self):
        if self.check_birthday_reward():
            db = get_db()
            cursor = db.cursor()
            cursor.execute("UPDATE users SET loyalty_points = loyalty_points + 50, last_birthday_redeemed = %s WHERE id = %s", (date.today().year, self.id))
            cursor.execute("INSERT INTO loyalty_transactions (user_id, points, reason) VALUES (%s, %s, %s)", (self.id, 50, "Birthday Reward"))
            db.commit()
            db.close()
            self.loyalty_points += 50
            self.last_birthday_redeemed = date.today().year
            return True
        return False

@login_manager.user_loader
def load_user(user_id):
    return User.get_by_id(int(user_id))

# ============ PRODUCT CLASS ============
class Product:
    @staticmethod
    def get_all():
        db = get_db()
        cursor = db.cursor()
        cursor.execute("SELECT * FROM products WHERE is_available = 1")
        products = cursor.fetchall()
        db.close()
        return products
    @staticmethod
    def get_by_id(product_id):
        db = get_db()
        cursor = db.cursor()
        cursor.execute("SELECT * FROM products WHERE id = %s", (product_id,))
        product = cursor.fetchone()
        db.close()
        return product
    @staticmethod
    def get_categories():
        db = get_db()
        cursor = db.cursor()
        cursor.execute("SELECT DISTINCT category FROM products")
        categories = [row['category'] for row in cursor.fetchall()]
        db.close()
        return categories

# ============ ORDER CLASS ============
class Order:
    def __init__(self, **kwargs):
        self.id = kwargs.get('id')
        self.user_id = kwargs.get('user_id')
        self.order_number = kwargs.get('order_number')
        self.total_amount = float(kwargs.get('total_amount', 0))
        self.status = kwargs.get('status', 'pending')
        self.payment_method = kwargs.get('payment_method')
        self.delivery_address = kwargs.get('delivery_address')
        self.special_instructions = kwargs.get('special_instructions')
        self.created_at = kwargs.get('created_at')
    @staticmethod
    def get_by_id(order_id):
        db = get_db()
        cursor = db.cursor()
        cursor.execute("SELECT * FROM orders WHERE id = %s", (order_id,))
        data = cursor.fetchone()
        db.close()
        if data:
            return Order(**data)
        return None
    @staticmethod
    def get_by_order_number(order_number):
        db = get_db()
        cursor = db.cursor()
        cursor.execute("SELECT * FROM orders WHERE order_number = %s", (order_number,))
        data = cursor.fetchone()
        db.close()
        if data:
            return Order(**data)
        return None
    @staticmethod
    def get_user_orders(user_id, limit=50):
        db = get_db()
        cursor = db.cursor()
        cursor.execute("SELECT * FROM orders WHERE user_id = %s ORDER BY created_at DESC LIMIT %s", (user_id, limit))
        data = cursor.fetchall()
        db.close()
        return [Order(**row) for row in data]
    def get_items(self):
        db = get_db()
        cursor = db.cursor()
        cursor.execute("SELECT oi.*, p.name, p.image_url FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = %s", (self.id,))
        items = cursor.fetchall()
        db.close()
        return items

# ============ MAIN ROUTES ============
@app.route('/')
def index():
    products = Product.get_all()
    return render_template('index.html', featured_products=products[:6])

@app.route('/menu')
def menu():
    products = Product.get_all()
    categories = Product.get_categories()
    products_by_category = {}
    for cat in categories:
        products_by_category[cat] = [p for p in products if p['category'] == cat]
    return render_template('menu.html', products_by_category=products_by_category, categories=categories)

@app.route('/cart')
def cart():
    cart_items = session.get('cart', [])
    items = []
    total = 0.0
    for item in cart_items:
        product = Product.get_by_id(item['product_id'])
        if product:
            price = float(product['price'])
            subtotal = price * item['quantity']
            total += subtotal
            items.append({'product': product, 'quantity': item['quantity'], 'subtotal': subtotal})
    return render_template('cart.html', cart_items=items, total=total)

@app.route('/checkout', methods=['GET', 'POST'])
@login_required
def checkout():
    cart_items = session.get('cart', [])
    if not cart_items:
        flash('Your cart is empty', 'warning')
        return redirect(url_for('menu'))
    total = 0.0
    for item in cart_items:
        product = Product.get_by_id(item['product_id'])
        if product:
            total += float(product['price']) * item['quantity']
    coupon_discount = 0
    coupon_code = None
    if 'coupon' in session:
        coupon_discount = session['coupon'].get('discount', 0)
        coupon_code = session['coupon'].get('code')
        total = max(0, total - coupon_discount)
    if request.method == 'POST':
        order_number = "ORD" + str(uuid.uuid4())[:8].upper()
        db = get_db()
        cursor = db.cursor()
        cursor.execute("INSERT INTO orders (order_number, user_id, total_amount, status, payment_method, delivery_address, special_instructions, coupon_code, discount_amount) VALUES (%s, %s, %s, 'pending', %s, %s, %s, %s, %s)", (order_number, current_user.id, total, request.form.get('payment_method'), request.form.get('delivery_address'), request.form.get('special_instructions'), coupon_code, coupon_discount))
        order_id = cursor.lastrowid
        for item in cart_items:
            product = Product.get_by_id(item['product_id'])
            if product:
                cursor.execute("INSERT INTO order_items (order_id, product_id, quantity, price_at_time) VALUES (%s, %s, %s, %s)", (order_id, item['product_id'], item['quantity'], float(product['price'])))
        db.commit()
        db.close()
        points_earned = int(total)
        current_user.add_loyalty_points(points_earned, f"Order {order_number}")
        items_list = []
        for item in cart_items:
            product = Product.get_by_id(item['product_id'])
            if product:
                items_list.append({'name': product['name'], 'quantity': item['quantity'], 'price': float(product['price'])})
        send_order_email(current_user.email, order_number, items_list, total, current_user.full_name or current_user.username)
        if 'coupon' in session:
            del session['coupon']
        session['cart'] = []
        flash(f'Order placed successfully! You earned {points_earned} loyalty points!', 'success')
        return redirect(url_for('order_history'))
    final_total = total * 1.1 + 2.5
    if 'coupon' in session:
        final_total = max(0, final_total - coupon_discount)
    return render_template('checkout.html', total=total, final_total=final_total, loyalty_points=current_user.loyalty_points, stripe_public_key=os.environ.get('STRIPE_PUBLIC_KEY', ''))

@app.route('/order-history')
@login_required
def order_history():
    currency = request.args.get('currency')
    if currency and currency in ['USD', 'EUR', 'SEK', 'AFN']:
        session['currency'] = currency
    elif 'currency' not in session:
        session['currency'] = 'USD'
    web_orders = Order.get_user_orders(current_user.id)
    return render_template('order_history.html', orders=web_orders, current_currency=session.get('currency', 'USD'))

@app.route('/profile', methods=['GET', 'POST'])
@login_required
def profile():
    if request.method == 'POST':
        full_name = request.form.get('full_name')
        phone = request.form.get('phone')
        birthday = request.form.get('birthday') or None
        db = get_db()
        cursor = db.cursor()
        cursor.execute("UPDATE users SET full_name = %s, phone = %s, birthday = %s WHERE id = %s", (full_name, phone, birthday, current_user.id))
        db.commit()
        db.close()
        flash('Profile updated successfully!', 'success')
        return redirect(url_for('profile'))
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT COUNT(*) as count FROM orders WHERE user_id = %s", (current_user.id,))
    orders_count = cursor.fetchone()['count']
    db.close()
    return render_template('profile.html', user=current_user, orders_count=orders_count)

@app.route('/claim-birthday', methods=['POST'])
@login_required
def claim_birthday():
    if current_user.claim_birthday_reward():
        flash('🎂 Happy Birthday! You received 50 bonus loyalty points!', 'success')
    else:
        flash('No birthday reward available at this time.', 'warning')
    return redirect(url_for('profile'))

@app.route('/api/upload-avatar', methods=['POST'])
@login_required
def upload_avatar():
    if 'avatar' not in request.files:
        return jsonify({'error': 'No file'}), 400
    file = request.files['avatar']
    if file.filename == '':
        return jsonify({'error': 'No file'}), 400
    ext = file.filename.rsplit('.', 1)[1].lower() if '.' in file.filename else 'jpg'
    filename = f"avatar_{current_user.id}.{ext}"
    filepath = os.path.join('static/uploads', filename)
    file.save(filepath)
    db = get_db()
    cursor = db.cursor()
    cursor.execute("UPDATE users SET profile_picture = %s WHERE id = %s", (f'/static/uploads/{filename}', current_user.id))
    db.commit()
    db.close()
    return jsonify({'success': True, 'avatar_url': f'/static/uploads/{filename}'})

@app.route('/api/delete-account', methods=['POST'])
@login_required
def delete_account():
    try:
        db = get_db()
        cursor = db.cursor()
        cursor.execute("DELETE FROM orders WHERE user_id = %s", (current_user.id,))
        cursor.execute("DELETE FROM loyalty_transactions WHERE user_id = %s", (current_user.id,))
        cursor.execute("DELETE FROM referrals WHERE referrer_id = %s OR referred_user_id = %s", (current_user.id, current_user.id))
        cursor.execute("DELETE FROM users WHERE id = %s", (current_user.id,))
        db.commit()
        db.close()
        return jsonify({'success': True})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/wifi')
def wifi():
    return render_template('wifi.html')

@app.route('/reviews')
def reviews():
    return render_template('reviews.html')

@app.route('/track-order')
def track_order():
    return render_template('track_order.html')

@app.route('/track-order/<order_number>')
def track_order_with_number(order_number):
    return render_template('track_order.html', order_number=order_number)

@app.route('/receipt/<order_number>')
@login_required
def receipt(order_number):
    order = Order.get_by_order_number(order_number)
    if not order:
        flash('Order not found', 'error')
        return redirect(url_for('order_history'))
    if order.user_id != current_user.id and not current_user.is_admin:
        flash('Unauthorized', 'error')
        return redirect(url_for('order_history'))
    items = order.get_items()
    return render_template('receipt.html', order=order, items=items, current_user=current_user)

@app.route('/my-receipts')
@login_required
def my_receipts():
    orders = Order.get_user_orders(current_user.id)
    return render_template('my_receipts.html', orders=orders)

@app.route('/loyalty')
@login_required
def loyalty():
    return render_template('loyalty.html')

@app.route('/referral')
@login_required
def referral():
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT COUNT(*) as count FROM referrals WHERE referrer_id = %s", (current_user.id,))
    referrals_count = cursor.fetchone()['count']
    cursor.execute("SELECT SUM(points_earned) as total FROM referrals WHERE referrer_id = %s", (current_user.id,))
    points_data = cursor.fetchone()
    points_earned = points_data['total'] or 0
    cursor.execute("SELECT r.*, u.username, u.created_at FROM referrals r JOIN users u ON r.referred_user_id = u.id WHERE r.referrer_id = %s ORDER BY r.created_at DESC", (current_user.id,))
    referrals = cursor.fetchall()
    db.close()
    return render_template('referral.html', user=current_user, referrals_count=referrals_count, points_earned=points_earned, referrals=referrals)

# ============ AUTH ROUTES ============
@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        user = User.get_by_username(request.form['username'])
        if user and user.check_password(request.form['password']):
            login_user(user)
            flash('Welcome back!', 'success')
            if user.check_birthday_reward():
                flash('🎂 Happy Birthday! You have a birthday reward waiting! Go to your profile to claim it.', 'info')
            return redirect(url_for('index'))
        flash('Invalid username or password', 'danger')
    return render_template('login.html')

@app.route('/register', methods=['GET', 'POST'])
def register():
    ref_code = request.args.get('ref', '')
    if request.method == 'POST':
        try:
            full_name = request.form.get('full_name', '')
            phone = request.form.get('phone', '')
            ref_code = request.form.get('ref_code', '')
            User.create_user(request.form['username'], request.form['email'], request.form['password'], full_name, phone)
            if ref_code:
                db = get_db()
                cursor = db.cursor()
                cursor.execute("SELECT id FROM users WHERE referral_code = %s", (ref_code,))
                referrer = cursor.fetchone()
                if referrer:
                    new_user = User.get_by_username(request.form['username'])
                    cursor.execute("INSERT INTO referrals (referrer_id, referred_user_id, points_earned) VALUES (%s, %s, 100)", (referrer['id'], new_user.id))
                    cursor.execute("UPDATE users SET loyalty_points = loyalty_points + 100 WHERE id = %s", (referrer['id'],))
                    cursor.execute("UPDATE users SET loyalty_points = loyalty_points + 50 WHERE id = %s", (new_user.id,))
                    db.commit()
                db.close()
            flash('Registration successful! Please login.', 'success')
            return redirect(url_for('login'))
        except:
            flash('Username or email already exists', 'danger')
    return render_template('register.html', ref_code=ref_code)

@app.route('/logout')
@login_required
def logout():
    logout_user()
    flash('Logged out', 'info')
    return redirect(url_for('index'))

# ============ SOCIAL LOGIN ROUTES ============
@app.route('/login/google')
def login_google():
    redirect_uri = url_for('authorize_google', _external=True)
    return google.authorize_redirect(redirect_uri)

@app.route('/login/google/callback')
def authorize_google():
    try:
        token = google.authorize_access_token()
        resp = google.get('userinfo')
        user_info = resp.json()
        email = user_info.get('email')
        name = user_info.get('name')
        if not email:
            flash('Email not available from Google', 'danger')
            return redirect(url_for('login'))
        username = email.split('@')[0]
        user = User.get_by_username(username)
        if not user:
            User.create_user(username, email, str(uuid.uuid4()), name)
            user = User.get_by_username(username)
        login_user(user)
        flash('Logged in with Google!', 'success')
        return redirect(url_for('index'))
    except Exception as e:
        flash(f'Google login error: {str(e)}', 'danger')
        return redirect(url_for('login'))

@app.route('/login/facebook')
def login_facebook():
    redirect_uri = url_for('authorize_facebook', _external=True)
    return facebook.authorize_redirect(redirect_uri)

@app.route('/login/facebook/callback')
def authorize_facebook():
    try:
        token = facebook.authorize_access_token()
        resp = facebook.get('me?fields=id,name,email')
        user_info = resp.json()
        email = user_info.get('email')
        name = user_info.get('name')
        if not email:
            flash('Email not available from Facebook', 'danger')
            return redirect(url_for('login'))
        username = email.split('@')[0]
        user = User.get_by_username(username)
        if not user:
            User.create_user(username, email, str(uuid.uuid4()), name)
            user = User.get_by_username(username)
        login_user(user)
        flash('Logged in with Facebook!', 'success')
        return redirect(url_for('index'))
    except Exception as e:
        flash(f'Facebook login error: {str(e)}', 'danger')
        return redirect(url_for('login'))

@app.route('/login/tiktok')
def login_tiktok():
    redirect_uri = url_for('authorize_tiktok', _external=True)
    return tiktok.authorize_redirect(redirect_uri)

@app.route('/login/tiktok/callback')
def authorize_tiktok():
    try:
        token = tiktok.authorize_access_token()
        resp = tiktok.get('user/info/', token=token)
        user_info = resp.json()
        data = user_info.get('data', {}).get('user', {})
        open_id = data.get('open_id')
        display_name = data.get('display_name', 'TikTok User')
        if not open_id:
            flash('Could not get user info from TikTok', 'danger')
            return redirect(url_for('login'))
        username = f"tiktok_{open_id[:8]}"
        email = f"{open_id}@tiktok.user"
        user = User.get_by_username(username)
        if not user:
            User.create_user(username, email, str(uuid.uuid4()), display_name)
            user = User.get_by_username(username)
        login_user(user)
        flash('Logged in with TikTok!', 'success')
        return redirect(url_for('index'))
    except Exception as e:
        flash(f'TikTok login error: {str(e)}', 'danger')
        return redirect(url_for('login'))

@app.route('/login/whatsapp')
def login_whatsapp():
    redirect_uri = url_for('authorize_whatsapp', _external=True)
    return whatsapp.authorize_redirect(redirect_uri)

@app.route('/login/whatsapp/callback')
def authorize_whatsapp():
    try:
        token = whatsapp.authorize_access_token()
        resp = whatsapp.get('me?fields=id,name,email', token=token)
        user_info = resp.json()
        email = user_info.get('email')
        name = user_info.get('name', 'WhatsApp User')
        fb_id = user_info.get('id')
        if email:
            username = email.split('@')[0]
        else:
            username = f"wa_{fb_id[:8]}" if fb_id else f"user_{uuid.uuid4().hex[:8]}"
            email = f"{username}@whatsapp.user"
        user = User.get_by_username(username)
        if not user:
            User.create_user(username, email, str(uuid.uuid4()), name)
            user = User.get_by_username(username)
        login_user(user)
        flash('Logged in with WhatsApp!', 'success')
        return redirect(url_for('index'))
    except Exception as e:
        flash(f'WhatsApp login error: {str(e)}', 'danger')
        return redirect(url_for('login'))

# ============ API ROUTES ============
@app.route('/api/cart/add', methods=['POST'])
def add_to_cart():
    data = request.json
    cart = session.get('cart', [])
    product_id = data['product_id']
    for item in cart:
        if item['product_id'] == product_id:
            item['quantity'] += 1
            break
    else:
        cart.append({'product_id': product_id, 'quantity': 1})
    session['cart'] = cart
    return jsonify({'success': True, 'count': len(cart)})

@app.route('/api/cart/remove', methods=['POST'])
def remove_from_cart():
    data = request.json
    cart = session.get('cart', [])
    cart = [item for item in cart if item['product_id'] != data['product_id']]
    session['cart'] = cart
    return jsonify({'success': True})

@app.route('/api/cart/count')
def cart_count():
    return jsonify({'count': len(session.get('cart', []))})

@app.route('/api/cart/update', methods=['POST'])
def update_cart():
    data = request.json
    cart = session.get('cart', [])
    product_id = data['product_id']
    quantity = data['quantity']
    for item in cart:
        if item['product_id'] == product_id:
            if quantity <= 0:
                cart.remove(item)
            else:
                item['quantity'] = quantity
            break
    session['cart'] = cart
    return jsonify({'success': True})

@app.route('/api/cart/clear', methods=['POST'])
def clear_cart():
    session['cart'] = []
    return jsonify({'success': True})

@app.route('/api/chatbot', methods=['POST'])
def chatbot():
    data = request.json
    msg = data.get('message', '').lower()
    if 'hour' in msg or 'open' in msg:
        response = "We're open Monday-Friday 7AM-9PM, Saturday 8AM-10PM, Sunday 8AM-8PM"
    elif 'wifi' in msg:
        response = "Yes! Free WiFi available. Password: 'coffeelove2024'"
    elif 'menu' in msg:
        response = "Our menu: Espresso($3.50), Cappuccino($4.50), Latte($4.50), Mocha($5.00), Caramel Macchiato($5.50), Cold Brew($4.50), Hot Chocolate($4.00)"
    elif 'address' in msg or 'location' in msg:
        response = "📍 Iskoskuja 3 C 111, Vantaa"
    elif 'phone' in msg or 'contact' in msg:
        response = "📞 +(358) 413114312"
    else:
        response = "☕ Hello! Type 'menu' to see our items, 'hours' for opening times, 'wifi' for password."
    return jsonify({'response': response})

@app.route('/api/chatbot/save-order', methods=['POST'])
def save_chatbot_order():
    try:
        data = request.json
        order_number = data.get('order_number', 'CHAT' + str(uuid.uuid4())[:8].upper())
        db = get_db()
        cursor = db.cursor()
        items_data = data.get('items', [])
        if current_user.is_authenticated:
            customer_email = current_user.email
            customer_name = current_user.full_name or current_user.username
            customer_phone = current_user.phone or 'Not provided'
        else:
            customer_email = data.get('customer_email', 'guest@coffeeshop.com')
            customer_name = data.get('customer_name', 'Guest')
            customer_phone = data.get('customer_phone', 'Not provided')
        cursor.execute("INSERT INTO chatbot_orders (order_number, customer_name, customer_email, customer_phone, delivery_address, items, total_amount, status, payment_status) VALUES (%s, %s, %s, %s, %s, %s, %s, 'pending', 'unpaid')", (order_number, customer_name, customer_email, customer_phone, data.get('delivery_address', 'pickup'), json.dumps(items_data), data.get('total_amount', 0)))
        db.commit()
        db.close()
        return jsonify({'success': True, 'order_number': order_number})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500

@app.route('/api/update-profile', methods=['POST'])
@login_required
def update_profile():
    data = request.json
    db = get_db()
    cursor = db.cursor()
    cursor.execute("UPDATE users SET full_name = %s, phone = %s WHERE id = %s", (data.get('full_name'), data.get('phone'), current_user.id))
    db.commit()
    db.close()
    return jsonify({'success': True})

@app.route('/api/cancel-order/<order_number>', methods=['POST'])
@login_required
def cancel_order(order_number):
    db = get_db()
    cursor = db.cursor()
    cursor.execute("UPDATE orders SET status = 'cancelled' WHERE order_number = %s AND user_id = %s", (order_number, current_user.id))
    db.commit()
    db.close()
    return jsonify({'success': True})

@app.route('/api/apply-coupon', methods=['POST'])
@login_required
def apply_coupon():
    data = request.json
    coupon_code = data.get('coupon_code', '').upper()
    subtotal = float(data.get('subtotal', 0))
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT * FROM coupons WHERE code = %s AND is_active = TRUE AND (valid_until IS NULL OR valid_until > NOW()) AND (usage_limit IS NULL OR used_count < usage_limit)", (coupon_code,))
    coupon = cursor.fetchone()
    db.close()
    if not coupon:
        return jsonify({'success': False, 'message': 'Invalid or expired coupon'})
    if subtotal < float(coupon['min_order_amount']):
        return jsonify({'success': False, 'message': f'Minimum order amount ${coupon["min_order_amount"]} required'})
    if coupon['discount_type'] == 'percentage':
        discount = subtotal * (float(coupon['discount_value']) / 100)
    else:
        discount = float(coupon['discount_value'])
    session['coupon'] = {'code': coupon_code, 'discount': discount, 'type': coupon['discount_type'], 'value': float(coupon['discount_value'])}
    return jsonify({'success': True, 'discount': discount, 'message': f'Coupon applied! Saved ${discount:.2f}'})

@app.route('/clear-coupon')
def clear_coupon_route():
    if 'coupon' in session:
        del session['coupon']
    flash('Coupon removed from your cart!', 'info')
    return redirect(url_for('cart'))

# ============ FAVORITES API ============
@app.route('/api/favorites', methods=['GET'])
@login_required
def get_favorites():
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT p.* FROM products p JOIN user_favorites f ON p.id = f.product_id WHERE f.user_id = %s", (current_user.id,))
    favorites = cursor.fetchall()
    db.close()
    return jsonify(favorites)

@app.route('/favorites')
@login_required
def favorites_page():
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT p.* FROM products p JOIN user_favorites f ON p.id = f.product_id WHERE f.user_id = %s", (current_user.id,))
    favorites = cursor.fetchall()
    db.close()
    return render_template('favorites.html', favorites=favorites)

@app.route('/api/favorites/toggle', methods=['POST'])
@login_required
def toggle_favorite():
    data = request.json
    product_id = data.get('product_id')
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT * FROM user_favorites WHERE user_id = %s AND product_id = %s", (current_user.id, product_id))
    exists = cursor.fetchone()
    if exists:
        cursor.execute("DELETE FROM user_favorites WHERE user_id = %s AND product_id = %s", (current_user.id, product_id))
        added = False
    else:
        cursor.execute("INSERT INTO user_favorites (user_id, product_id) VALUES (%s, %s)", (current_user.id, product_id))
        added = True
    db.commit()
    db.close()
    return jsonify({'added': added})

# ============ REVIEWS API ============
@app.route('/api/reviews/add', methods=['POST'])
@login_required
def add_review():
    data = request.json
    product_id = data.get('product_id')
    rating = data.get('rating')
    comment = data.get('comment', '')
    if not product_id or not rating:
        return jsonify({'success': False, 'message': 'Missing data'}), 400
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT * FROM product_reviews WHERE product_id = %s AND user_id = %s", (product_id, current_user.id))
    existing = cursor.fetchone()
    if existing:
        cursor.execute("UPDATE product_reviews SET rating = %s, comment = %s WHERE id = %s", (rating, comment, existing['id']))
    else:
        cursor.execute("INSERT INTO product_reviews (product_id, user_id, rating, comment) VALUES (%s, %s, %s, %s)", (product_id, current_user.id, rating, comment))
    db.commit()
    db.close()
    return jsonify({'success': True})

@app.route('/api/reviews/product/<int:product_id>', methods=['GET'])
def get_product_reviews(product_id):
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM product_reviews WHERE product_id = %s", (product_id,))
    stats = cursor.fetchone()
    cursor.execute("SELECT pr.*, u.username FROM product_reviews pr JOIN users u ON pr.user_id = u.id WHERE pr.product_id = %s ORDER BY pr.created_at DESC LIMIT 5", (product_id,))
    reviews = cursor.fetchall()
    db.close()
    return jsonify({'average_rating': float(stats['avg_rating']) if stats['avg_rating'] else 0, 'total_reviews': stats['total'] or 0, 'reviews': reviews})

# ============ LOYALTY API ============
@app.route('/api/loyalty/points')
@login_required
def get_loyalty_points():
    return jsonify({'points': current_user.loyalty_points, 'next_reward': 100 - (current_user.loyalty_points % 100) if current_user.loyalty_points % 100 != 0 else 0})

@app.route('/api/loyalty/rewards')
@login_required
def get_rewards():
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT id, name, name_fi, name_sv, name_fa, description, description_fi, description_sv, description_fa, points_required, is_active FROM rewards WHERE is_active = 1")
    rewards = cursor.fetchall()
    db.close()
    return jsonify(rewards)

@app.route('/api/loyalty/redeem', methods=['POST'])
@login_required
def redeem_reward():
    data = request.json
    reward_id = data.get('reward_id')
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT * FROM rewards WHERE id = %s", (reward_id,))
    reward = cursor.fetchone()
    db.close()
    if reward and current_user.loyalty_points >= reward['points_required']:
        current_user.loyalty_points -= reward['points_required']
        db = get_db()
        cursor = db.cursor()
        cursor.execute("UPDATE users SET loyalty_points = %s WHERE id = %s", (current_user.loyalty_points, current_user.id))
        cursor.execute("INSERT INTO loyalty_transactions (user_id, points, reason) VALUES (%s, %s, %s)", (current_user.id, -reward['points_required'], f"Redeemed: {reward['name']}"))
        db.commit()
        db.close()
        return jsonify({'success': True, 'message': f"Redeemed {reward['name']}!", 'points': current_user.loyalty_points})
    return jsonify({'success': False, 'message': 'Not enough points'}), 400

# ============ STRIPE PAYMENT ROUTES ============
@app.route('/api/create-payment-intent', methods=['POST'])
@login_required
def create_payment_intent():
    if not stripe.api_key:
        return jsonify({'error': 'Stripe not configured'}), 400
    data = request.json
    amount = int(float(data.get('amount', 0)) * 100)
    try:
        intent = stripe.PaymentIntent.create(amount=amount, currency='usd', metadata={'user_id': current_user.id})
        return jsonify({'clientSecret': intent.client_secret})
    except Exception as e:
        return jsonify({'error': str(e)}), 400

@app.route('/payment-success')
@login_required
def payment_success():
    flash('Payment successful! Your order has been confirmed.', 'success')
    return redirect(url_for('order_history'))

@app.route('/payment-cancel')
@login_required
def payment_cancel():
    flash('Payment cancelled.', 'warning')
    return redirect(url_for('cart'))

# ============ ADMIN FEATURES ============
@app.route('/admin/sales-dashboard')
@login_required
def sales_dashboard():
    if not current_user.is_admin:
        flash('Admin access required', 'danger')
        return redirect(url_for('index'))
    return render_template('admin/sales_dashboard.html')

@app.route('/api/admin/sales-data')
@login_required
def sales_data():
    if not current_user.is_admin:
        return jsonify({'error': 'Unauthorized'}), 403
    period = request.args.get('period', 'week')
    db = get_db()
    cursor = db.cursor()
    if period == 'week':
        cursor.execute("SELECT DATE(created_at) as date, COUNT(*) as orders, SUM(total_amount) as revenue FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY date")
    elif period == 'month':
        cursor.execute("SELECT DATE(created_at) as date, COUNT(*) as orders, SUM(total_amount) as revenue FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY date")
    elif period == 'year':
        cursor.execute("SELECT MONTH(created_at) as month, COUNT(*) as orders, SUM(total_amount) as revenue FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY MONTH(created_at) ORDER BY month")
    else:
        cursor.execute("SELECT DATE(created_at) as date, COUNT(*) as orders, SUM(total_amount) as revenue FROM orders GROUP BY DATE(created_at) ORDER BY date")
    sales_data = cursor.fetchall()
    cursor.execute("SELECT p.category, SUM(oi.quantity) as total_sold, SUM(oi.quantity * oi.price_at_time) as revenue FROM order_items oi JOIN products p ON oi.product_id = p.id GROUP BY p.category ORDER BY revenue DESC")
    category_data = cursor.fetchall()
    cursor.execute("SELECT p.name, p.category, SUM(oi.quantity) as total_quantity, SUM(oi.quantity * oi.price_at_time) as revenue FROM order_items oi JOIN products p ON oi.product_id = p.id GROUP BY p.id ORDER BY revenue DESC LIMIT 10")
    top_products = cursor.fetchall()
    cursor.execute("SELECT COUNT(*) as total, COALESCE(SUM(total_amount), 0) as revenue FROM orders")
    totals = cursor.fetchone()
    cursor.execute("SELECT COUNT(*) as total FROM users WHERE is_admin = 0")
    customer_count = cursor.fetchone()
    db.close()
    return jsonify({
        'total_revenue': float(totals['revenue']),
        'total_orders': totals['total'],
        'total_customers': customer_count['total'],
        'avg_order_value': float(totals['revenue'] / totals['total']) if totals['total'] > 0 else 0,
        'sales_labels': [row['date'] if 'date' in row else row['month'] for row in sales_data],
        'sales_data': [float(row['revenue']) for row in sales_data],
        'category_labels': [row['category'] for row in category_data],
        'category_data': [float(row['revenue']) for row in category_data],
        'top_products': [{'name': p['name'], 'category': p['category'], 'total_quantity': p['total_quantity'], 'revenue': float(p['revenue'])} for p in top_products]
    })

@app.route('/admin/inventory')
@login_required
def inventory_page():
    if not current_user.is_admin:
        flash('Admin access required', 'danger')
        return redirect(url_for('index'))
    return render_template('admin/inventory.html')

@app.route('/api/admin/inventory')
@login_required
def get_inventory():
    if not current_user.is_admin:
        return jsonify({'error': 'Unauthorized'}), 403
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SHOW COLUMNS FROM products LIKE 'stock'")
    if not cursor.fetchone():
        cursor.execute("ALTER TABLE products ADD COLUMN stock INT DEFAULT 10")
    cursor.execute("SELECT * FROM products ORDER BY id")
    products = cursor.fetchall()
    cursor.execute("SELECT COUNT(*) as total FROM products")
    total = cursor.fetchone()
    cursor.execute("SELECT COUNT(*) as count FROM products WHERE stock < 10 AND stock > 0")
    low_stock = cursor.fetchone()
    cursor.execute("SELECT COUNT(*) as count FROM products WHERE stock <= 0")
    out_stock = cursor.fetchone()
    cursor.execute("SELECT SUM(price * stock) as value FROM products")
    total_value = cursor.fetchone()
    db.close()
    return jsonify({'products': products, 'total_products': total['total'], 'low_stock_count': low_stock['count'], 'out_stock_count': out_stock['count'], 'total_value': float(total_value['value'] or 0)})

@app.route('/api/admin/add-product', methods=['POST'])
@login_required
def add_product():
    if not current_user.is_admin:
        return jsonify({'error': 'Unauthorized'}), 403
    data = request.json
    db = get_db()
    cursor = db.cursor()
    cursor.execute("INSERT INTO products (name, description, price, category, stock, is_available) VALUES (%s, %s, %s, %s, %s, 1)", (data['name'], data.get('description', ''), data['price'], data['category'], data.get('stock', 10)))
    db.commit()
    db.close()
    return jsonify({'success': True})

@app.route('/api/admin/update-stock', methods=['POST'])
@login_required
def update_stock():
    if not current_user.is_admin:
        return jsonify({'error': 'Unauthorized'}), 403
    data = request.json
    db = get_db()
    cursor = db.cursor()
    cursor.execute("UPDATE products SET stock = %s WHERE id = %s", (data['stock'], data['product_id']))
    cursor.execute("UPDATE products SET is_available = %s WHERE id = %s", (1 if data['stock'] > 0 else 0, data['product_id']))
    db.commit()
    db.close()
    return jsonify({'success': True})

@app.route('/admin/customers')
@login_required
def customers_page():
    if not current_user.is_admin:
        flash('Admin access required', 'danger')
        return redirect(url_for('index'))
    return render_template('admin/customers.html')

@app.route('/api/admin/customers')
@login_required
def get_customers():
    if not current_user.is_admin:
        return jsonify({'error': 'Unauthorized'}), 403
    search = request.args.get('search', '')
    db = get_db()
    cursor = db.cursor()
    query = "SELECT u.*, COUNT(o.id) as order_count, COALESCE(SUM(o.total_amount), 0) as total_spent FROM users u LEFT JOIN orders o ON u.id = o.user_id WHERE u.is_admin = 0"
    params = []
    if search:
        query += " AND (u.username LIKE %s OR u.email LIKE %s OR u.full_name LIKE %s)"
        search_param = f"%{search}%"
        params = [search_param, search_param, search_param]
    query += " GROUP BY u.id ORDER BY u.created_at DESC"
    cursor.execute(query, params)
    customers = cursor.fetchall()
    cursor.execute("SELECT COUNT(*) as count FROM users WHERE is_admin = 0 AND MONTH(created_at) = MONTH(CURRENT_DATE())")
    new_this_month = cursor.fetchone()
    cursor.execute("SELECT COUNT(*) as total FROM users WHERE is_admin = 0")
    total_customers = cursor.fetchone()
    cursor.execute("SELECT COUNT(*) as total FROM orders")
    total_orders = cursor.fetchone()
    cursor.execute("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders")
    total_spent = cursor.fetchone()
    db.close()
    return jsonify({'customers': customers, 'total_customers': total_customers['total'], 'new_this_month': new_this_month['count'], 'total_orders': total_orders['total'], 'total_spent': float(total_spent['total'] or 0)})

@app.route('/api/admin/customer/<int:customer_id>')
@login_required
def get_customer_detail(customer_id):
    if not current_user.is_admin:
        return jsonify({'error': 'Unauthorized'}), 403
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT u.*, COUNT(o.id) as order_count, COALESCE(SUM(o.total_amount), 0) as total_spent FROM users u LEFT JOIN orders o ON u.id = o.user_id WHERE u.id = %s GROUP BY u.id", (customer_id,))
    customer = cursor.fetchone()
    db.close()
    return jsonify(customer)

@app.route('/api/admin/update-customer', methods=['POST'])
@login_required
def update_customer():
    if not current_user.is_admin:
        return jsonify({'error': 'Unauthorized'}), 403
    data = request.json
    db = get_db()
    cursor = db.cursor()
    cursor.execute("UPDATE users SET full_name = %s, email = %s, phone = %s, loyalty_points = %s WHERE id = %s", (data['full_name'], data['email'], data['phone'], data['loyalty_points'], data['id']))
    db.commit()
    db.close()
    return jsonify({'success': True})

@app.route('/api/admin/delete-customer', methods=['POST'])
@login_required
def delete_customer():
    if not current_user.is_admin:
        return jsonify({'error': 'Unauthorized'}), 403
    data = request.json
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT COUNT(*) as count FROM orders WHERE user_id = %s", (data['id'],))
    orders = cursor.fetchone()
    if orders['count'] > 0:
        return jsonify({'success': False, 'message': 'Cannot delete customer with existing orders'}), 400
    cursor.execute("DELETE FROM users WHERE id = %s", (data['id'],))
    db.commit()
    db.close()
    return jsonify({'success': True})

@app.route('/admin/export')
@login_required
def export_page():
    if not current_user.is_admin:
        flash('Admin access required', 'danger')
        return redirect(url_for('index'))
    return render_template('admin/export.html')

@app.route('/api/admin/export/<report_type>')
@login_required
def export_report(report_type):
    if not current_user.is_admin:
        return jsonify({'error': 'Unauthorized'}), 403
    import csv
    from io import StringIO
    from datetime import datetime
    start_date = request.args.get('start')
    end_date = request.args.get('end')
    limit = request.args.get('limit')
    db = get_db()
    cursor = db.cursor()
    if report_type == 'sales':
        query = "SELECT o.order_number, o.created_at, o.total_amount, o.status, o.payment_method, u.username, u.email FROM orders o LEFT JOIN users u ON o.user_id = u.id WHERE 1=1"
        params = []
        if start_date:
            query += " AND DATE(o.created_at) >= %s"
            params.append(start_date)
        if end_date:
            query += " AND DATE(o.created_at) <= %s"
            params.append(end_date)
        query += " ORDER BY o.created_at DESC"
        if limit:
            query += f" LIMIT {limit}"
        cursor.execute(query, params)
        data = cursor.fetchall()
        output = StringIO()
        writer = csv.writer(output)
        writer.writerow(['Order Number', 'Date', 'Total Amount', 'Status', 'Payment Method', 'Customer', 'Email'])
        for row in data:
            writer.writerow([row['order_number'], row['created_at'], f"${row['total_amount']}", row['status'], row['payment_method'] or 'N/A', row['username'] or 'Guest', row['email'] or 'N/A'])
    elif report_type == 'products':
        cursor.execute("SELECT p.id, p.name, p.category, p.price, p.stock, p.is_available, COALESCE(SUM(oi.quantity), 0) as total_sold, COALESCE(SUM(oi.quantity * oi.price_at_time), 0) as total_revenue FROM products p LEFT JOIN order_items oi ON p.id = oi.product_id GROUP BY p.id ORDER BY total_sold DESC")
        data = cursor.fetchall()
        output = StringIO()
        writer = csv.writer(output)
        writer.writerow(['ID', 'Product Name', 'Category', 'Price', 'Stock', 'Status', 'Total Sold', 'Revenue'])
        for row in data:
            writer.writerow([row['id'], row['name'], row['category'], f"${row['price']}", row['stock'] or 0, 'Available' if row['is_available'] else 'Unavailable', row['total_sold'], f"${row['total_revenue']}"])
    elif report_type == 'customers':
        cursor.execute("SELECT u.id, u.username, u.email, u.full_name, u.phone, u.loyalty_points, u.created_at, COUNT(o.id) as order_count, COALESCE(SUM(o.total_amount), 0) as total_spent FROM users u LEFT JOIN orders o ON u.id = o.user_id WHERE u.is_admin = 0 GROUP BY u.id ORDER BY total_spent DESC")
        data = cursor.fetchall()
        output = StringIO()
        writer = csv.writer(output)
        writer.writerow(['ID', 'Username', 'Full Name', 'Email', 'Phone', 'Loyalty Points', 'Joined', 'Orders', 'Total Spent'])
        for row in data:
            writer.writerow([row['id'], row['username'], row['full_name'] or '', row['email'], row['phone'] or '', row['loyalty_points'], row['created_at'], row['order_count'], f"${row['total_spent']}"])
    elif report_type == 'inventory':
        cursor.execute("SELECT id, name, category, price, stock, is_available, CASE WHEN stock <= 0 THEN 'Out of Stock' WHEN stock < 10 THEN 'Low Stock' ELSE 'In Stock' END as stock_status FROM products ORDER BY stock ASC")
        data = cursor.fetchall()
        output = StringIO()
        writer = csv.writer(output)
        writer.writerow(['ID', 'Product', 'Category', 'Price', 'Stock', 'Status', 'Stock Level'])
        for row in data:
            writer.writerow([row['id'], row['name'], row['category'], f"${row['price']}", row['stock'] or 0, 'Active' if row['is_available'] else 'Inactive', row['stock_status']])
    db.close()
    filename = f"{report_type}_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.csv"
    response = Response(output.getvalue(), mimetype='text/csv')
    response.headers['Content-Disposition'] = f'attachment; filename={filename}'
    return response

@app.route('/admin/chatbot-orders')
@login_required
def admin_chatbot_orders():
    if not current_user.is_admin:
        flash('Admin access required', 'danger')
        return redirect(url_for('index'))
    import json
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT * FROM chatbot_orders ORDER BY created_at DESC")
    orders = cursor.fetchall()
    for order in orders:
        if order.get('items'):
            try:
                items_data = json.loads(order['items'])
                item_names = [item.get('name', 'Unknown') for item in items_data]
                order['item_names'] = ', '.join(item_names)
            except:
                order['item_names'] = str(order['items'])[:50]
        else:
            order['item_names'] = '-'
    db.close()
    return render_template('admin/chatbot_orders.html', orders=orders)

@app.route('/api/admin/delete-chatbot-order', methods=['POST'])
@login_required
def delete_admin_chatbot_order():
    data = request.json
    order_id = data.get('order_id')
    db = get_db()
    cursor = db.cursor()
    cursor.execute("DELETE FROM chatbot_orders WHERE id = %s", (order_id,))
    db.commit()
    db.close()
    return jsonify({'success': True})

# ============ ADMIN LOGIN & REGISTRATION ============
@app.route('/admin-login', methods=['GET', 'POST'])
def admin_login():
    if request.method == 'POST':
        username = request.form.get('username')
        password = request.form.get('password')
        user = User.get_by_username(username)
        if user and user.check_password(password) and user.is_admin:
            login_user(user)
            flash('Welcome Admin!', 'success')
            return redirect(url_for('sales_dashboard'))
        else:
            flash('Invalid admin credentials', 'danger')
    return render_template('admin_login.html')

@app.route('/admin-register', methods=['GET', 'POST'])
def admin_register_page():
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT COUNT(*) as count FROM users WHERE is_admin = 1")
    result = cursor.fetchone()
    db.close()
    admin_exists = result['count'] > 0
    if admin_exists:
        flash('Admin already registered. Please login.', 'warning')
        return redirect(url_for('admin_login'))
    if request.method == 'POST':
        admin_key = request.form.get('admin_key')
        ADMIN_SECRET_KEY = "AdminKey2024"
        if admin_key != ADMIN_SECRET_KEY:
            flash('Invalid admin registration key!', 'danger')
            return render_template('admin_register_page.html')
        username = request.form.get('username')
        email = request.form.get('email')
        password = request.form.get('password')
        existing = User.get_by_username(username)
        if existing:
            flash('Username already exists', 'danger')
            return render_template('admin_register_page.html')
        password_hash = bcrypt.generate_password_hash(password).decode('utf-8')
        referral_code = 'ADMIN' + str(uuid.uuid4())[:6].upper()
        db = get_db()
        cursor = db.cursor()
        cursor.execute("INSERT INTO users (username, email, password_hash, referral_code, is_admin, loyalty_points) VALUES (%s, %s, %s, %s, 1, 0)", (username, email, password_hash, referral_code))
        db.commit()
        db.close()
        flash('✅ Admin account created successfully! Please login.', 'success')
        return redirect(url_for('admin_login'))
    return render_template('admin_register_page.html')

@app.route('/admin-access')
@login_required
def admin_access():
    if not current_user.is_admin:
        flash('Admin access required', 'danger')
        return redirect(url_for('index'))
    return render_template('admin_access.html')

# ============ EMPLOYEE FEATURES ============
@app.route('/employee-login', methods=['GET', 'POST'])
def employee_login():
    if request.method == 'POST':
        username = request.form.get('username')
        password = request.form.get('password')
        user = User.get_by_username(username)
        if user and user.check_password(password) and user.role in ['employee', 'admin']:
            login_user(user)
            flash('Welcome Employee!', 'success')
            return redirect(url_for('employee_dashboard'))
        else:
            flash('Invalid credentials or not an employee', 'danger')
    return render_template('employee_login.html')

@app.route('/employee-dashboard')
@login_required
def employee_dashboard():
    if current_user.role not in ['employee', 'admin']:
        flash('Employee access only', 'danger')
        return redirect(url_for('index'))
    return render_template('employee_dashboard.html')

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
    cursor.execute("""
        SELECT o.*, u.username as customer_name, u.email as customer_email, u.phone as customer_phone
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE DATE(o.created_at) = CURDATE()
        ORDER BY o.created_at DESC
    """)
    orders = cursor.fetchall()
    pending = [o for o in orders if o['status'] == 'pending']
    db.close()
    return jsonify({'orders': orders, 'pending': pending})

@app.route('/api/update-order-status/<int:order_id>', methods=['POST'])
@login_required
def update_order_status(order_id):
    if current_user.role not in ['employee', 'admin']:
        return jsonify({'success': False, 'message': 'Unauthorized'}), 403
    data = request.json
    new_status = data.get('status')
    db = get_db()
    cursor = db.cursor()
    cursor.execute("UPDATE orders SET status = %s WHERE id = %s", (new_status, order_id))
    db.commit()
    db.close()
    return jsonify({'success': True, 'message': f'Order status updated to {new_status}'})

@app.route('/employee/pending-orders')
@login_required
def employee_pending_orders():
    if current_user.role not in ['employee', 'admin']:
        flash('Employee access only', 'danger')
        return redirect(url_for('index'))
    return render_template('employee_pending_orders.html')

@app.route('/api/employee/inventory-data')
@login_required
def employee_inventory_data():
    if current_user.role not in ['employee', 'admin']:
        return jsonify({'error': 'Unauthorized'}), 403
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT id, name, category, price, stock, is_available FROM products ORDER BY category, name")
    products = cursor.fetchall()
    total_products = len(products)
    low_stock = 0
    out_stock = 0
    total_value = 0
    for p in products:
        stock = p.get('stock', 0) or 0
        if 0 < stock < 10:
            low_stock += 1
        elif stock <= 0:
            out_stock += 1
        total_value += float(p.get('price', 0)) * stock
    db.close()
    return jsonify({
        'products': products,
        'stats': {'total': total_products, 'low_stock': low_stock, 'out_stock': out_stock, 'total_value': total_value}
    })

@app.route('/api/employee/customers-data')
@login_required
def employee_customers_data():
    if current_user.role not in ['employee', 'admin']:
        return jsonify({'error': 'Unauthorized'}), 403
    from datetime import date
    db = get_db()
    cursor = db.cursor()
    cursor.execute("""
        SELECT u.id, u.username, u.full_name, u.email, u.phone, u.loyalty_points, u.created_at,
               COUNT(o.id) as order_count,
               COALESCE(SUM(o.total_amount), 0) as total_spent
        FROM users u
        LEFT JOIN orders o ON u.id = o.user_id
        WHERE u.is_admin = 0
        GROUP BY u.id
        ORDER BY u.created_at DESC
    """)
    customers = cursor.fetchall()
    total_customers = len(customers)
    new_this_month = 0
    total_orders = 0
    total_revenue = 0
    for c in customers:
        if c.get('created_at') and c['created_at'].month == date.today().month:
            new_this_month += 1
        total_orders += c.get('order_count', 0)
        total_revenue += c.get('total_spent', 0)
    db.close()
    return jsonify({
        'customers': customers,
        'stats': {'total': total_customers, 'new_this_month': new_this_month, 'total_orders': total_orders, 'total_revenue': float(total_revenue)}
    })

# ============ CONCEAL ORDER ============
@app.route('/api/conceal-order', methods=['POST'])
@login_required
def conceal_order():
    data = request.json
    order_id = data.get('order_id')
    order_type = data.get('order_type', 'web')
    db = get_db()
    cursor = db.cursor()
    if order_type == 'chatbot':
        cursor.execute("DELETE FROM chatbot_orders WHERE id = %s", (order_id,))
    else:
        cursor.execute("DELETE FROM orders WHERE id = %s AND user_id = %s", (order_id, current_user.id))
    db.commit()
    db.close()
    return jsonify({'success': True})

@app.route('/api/order-items/<int:order_id>')
@login_required
def get_order_items(order_id):
    db = get_db()
    cursor = db.cursor()
    cursor.execute("""
        SELECT oi.*, p.name FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = %s
    """, (order_id,))
    items = cursor.fetchall()
    db.close()
    return jsonify(items)

@app.route('/api/reorder', methods=['POST'])
@login_required
def reorder():
    try:
        data = request.json
        order_id = data.get('order_id')
        order_type = data.get('order_type', 'web')
        if not order_id:
            return jsonify({'success': False, 'message': 'Order ID required'}), 400
        db = get_db()
        cursor = db.cursor()
        items = []
        if order_type == 'chatbot':
            cursor.execute("SELECT * FROM chatbot_orders WHERE id = %s", (order_id,))
            order = cursor.fetchone()
            if not order:
                db.close()
                return jsonify({'success': False, 'message': 'Order not found'}), 404
            order_items = json.loads(order['items']) if order['items'] else []
            for item in order_items:
                items.append({'product_id': item.get('id', item.get('product_id')), 'quantity': item.get('quantity', 1)})
        else:
            cursor.execute("SELECT id FROM orders WHERE id = %s AND user_id = %s", (order_id, current_user.id))
            order = cursor.fetchone()
            if not order:
                db.close()
                return jsonify({'success': False, 'message': 'Order not found'}), 404
            cursor.execute("SELECT product_id, quantity FROM order_items WHERE order_id = %s", (order_id,))
            items = cursor.fetchall()
        db.close()
        if not items:
            return jsonify({'success': False, 'message': 'No items found'}), 400
        session['cart'] = []
        cart = []
        for item in items:
            cart.append({'product_id': item['product_id'], 'quantity': item['quantity']})
        session['cart'] = cart
        return jsonify({'success': True, 'message': f'{len(items)} items added to cart!'})
    except Exception as e:
        return jsonify({'success': False, 'message': str(e)}), 500

@app.route('/api/delete-my-chatbot-order', methods=['POST'])
@login_required
def delete_my_chatbot_order():
    data = request.json
    order_id = data.get('order_id')
    db = get_db()
    cursor = db.cursor()
    cursor.execute("DELETE FROM chatbot_orders WHERE id = %s AND customer_email = %s", (order_id, current_user.email))
    db.commit()
    db.close()
    return jsonify({'success': True, 'message': 'Order deleted successfully'})

@app.route('/chatbot-orders')
@login_required
def user_chatbot_orders():
    import json
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT * FROM chatbot_orders WHERE customer_email = %s ORDER BY created_at DESC", (current_user.email,))
    orders = cursor.fetchall()
    db.close()

    for order in orders:
        if not order.get('customer_name'):
            order['customer_name'] = current_user.username

        # Parse the items JSON field into items_list
        items_data = order.get('items')
        order['items_list'] = []

        if items_data:
            try:
                # If items_data is a string, parse it as JSON
                if isinstance(items_data, str):
                    items = json.loads(items_data)
                else:
                    items = items_data

                # Convert to the format expected by the template
                for item in items:
                    order['items_list'].append({
                        'name': item.get('name', 'Unknown'),
                        'quantity': item.get('quantity', 1),
                        'price': float(item.get('price', 0))
                    })
            except Exception as e:
                print(f"Error parsing items for order {order.get('order_number')}: {e}")
                order['items_list'] = []

    return render_template('chatbot_orders.html', orders=orders)

@app.route('/chatbot-checkout', methods=['GET', 'POST'])
@login_required
def chatbot_checkout_page():
    order_id = request.args.get('order_id') or request.form.get('order_id')
    if not order_id:
        flash('Order ID required', 'warning')
        return redirect(url_for('user_chatbot_orders'))
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT * FROM chatbot_orders WHERE id = %s AND customer_email = %s", (order_id, current_user.email))
    order_data = cursor.fetchone()
    if not order_data:
        db.close()
        flash('Order not found', 'error')
        return redirect(url_for('user_chatbot_orders'))
    total = float(order_data['total_amount'])
    coupon_discount = 0
    coupon_code = None
    if 'coupon' in session:
        coupon_discount = session['coupon'].get('discount', 0)
        coupon_code = session['coupon'].get('code')
        total = max(0, total - coupon_discount)
    if request.method == 'POST':
        delivery_address = request.form.get('delivery_address')
        payment_method = request.form.get('payment_method')
        cursor.execute("UPDATE chatbot_orders SET delivery_address = %s, payment_method = %s, coupon_code = %s, discount_amount = %s, status = 'completed', payment_status = 'paid' WHERE id = %s AND customer_email = %s", (delivery_address, payment_method, coupon_code, coupon_discount, order_id, current_user.email))
        db.commit()
        db.close()
        if 'coupon' in session:
            del session['coupon']
        flash('Order confirmed successfully! Thank you for your purchase.', 'success')
        return redirect(url_for('user_chatbot_orders'))
    items = []
    try:
        items_data = json.loads(order_data['items']) if order_data['items'] else []
        for item in items_data:
            items.append({'name': item.get('name', 'Unknown'), 'quantity': item.get('quantity', 1), 'price': float(item.get('price', 0))})
    except:
        pass
    final_total = total * 1.1 + 2.5
    if 'coupon' in session:
        final_total = max(0, final_total - coupon_discount)
    db.close()
    return render_template('chatbot_checkout.html', order=order_data, items=items, total=total, final_total=final_total, coupon_discount=coupon_discount, coupon_code=coupon_code)

@app.route('/download-receipt/<order_number>')
@login_required
def download_receipt(order_number):
    from weasyprint import HTML
    import json
    order = Order.get_by_order_number(order_number)
    is_chatbot = False
    if not order:
        db = get_db()
        cursor = db.cursor()
        cursor.execute("SELECT * FROM chatbot_orders WHERE order_number = %s AND customer_email = %s", (order_number, current_user.email))
        chatbot_order = cursor.fetchone()
        db.close()
        if chatbot_order:
            is_chatbot = True
            class TempOrder: pass
            order = TempOrder()
            order.order_number = chatbot_order['order_number']
            order.total_amount = float(chatbot_order['total_amount'])
            order.created_at = chatbot_order['created_at']
            order.status = chatbot_order['status']
            order.payment_method = chatbot_order.get('payment_method')
            order.delivery_address = chatbot_order.get('delivery_address')
            items = []
            try:
                items_data = json.loads(chatbot_order['items']) if chatbot_order['items'] else []
                for item in items_data:
                    items.append({'name': item.get('name', 'Unknown'), 'quantity': item.get('quantity', 1), 'price_at_time': float(item.get('price', 0))})
            except:
                pass
            order.get_items = lambda: items
    if not order:
        flash('Order not found', 'error')
        return redirect(url_for('order_history'))
    if not is_chatbot and order.user_id != current_user.id and not current_user.is_admin:
        flash('Unauthorized', 'error')
        return redirect(url_for('order_history'))
    items = order.get_items() if hasattr(order, 'get_items') else []
    html = render_template('pdf_receipt.html', order=order, items=items, current_user=current_user)
    pdf = HTML(string=html).write_pdf()
    response = Response(pdf, mimetype='application/pdf')
    response.headers['Content-Disposition'] = f'attachment; filename=receipt_{order_number}.pdf'
    return response

@app.route('/check')
def check_session():
    return f"Lang: {session.get('lang', 'None')}, Currency: {session.get('currency', 'None')}"

@app.route('/debug-session')
def debug_session():
    return {'lang': session.get('lang', 'not_set'), 'currency': session.get('currency', 'not_set')}

@app.route('/api/chatbot/order-status/<order_number>')
def chatbot_order_status(order_number):
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT order_number, status, total_amount, created_at, 'web' as source FROM orders WHERE order_number = %s UNION ALL SELECT order_number, status, total_amount, created_at, 'chatbot' as source FROM chatbot_orders WHERE order_number = %s", (order_number, order_number))
    order = cursor.fetchone()
    db.close()
    if order:
        return jsonify({'order_number': order['order_number'], 'status': order['status'], 'total_amount': float(order['total_amount']), 'created_at': order['created_at'].isoformat() if order['created_at'] else None, 'source': order['source']})
    return jsonify({'error': 'Order not found'}), 404

@app.route('/api/cancel-order/<int:order_id>', methods=['POST'])
@login_required
def cancel_order_api(order_id):
    db = get_db()
    cursor = db.cursor()
    cursor.execute("UPDATE orders SET status = 'cancelled' WHERE id = %s AND user_id = %s", (order_id, current_user.id))
    db.commit()
    db.close()
    return jsonify({'success': True, 'message': 'Order cancelled successfully'})

@app.route('/api/delete-order/<int:order_id>', methods=['POST'])
@login_required
def delete_order(order_id):
    db = get_db()
    cursor = db.cursor()
    cursor.execute("DELETE FROM order_items WHERE order_id = %s", (order_id,))
    cursor.execute("DELETE FROM orders WHERE id = %s AND user_id = %s", (order_id, current_user.id))
    db.commit()
    db.close()
    return jsonify({'success': True, 'message': 'Order deleted successfully'})

@app.route('/api/track-order/<order_number>')
def api_track_order(order_number):
    """API endpoint to get order details for tracking"""
    db = get_db()
    cursor = db.cursor()

    cursor.execute("""
        SELECT order_number, total_amount, status, created_at
        FROM orders
        WHERE order_number = %s
    """, (order_number,))
    order = cursor.fetchone()

    if not order:
        cursor.execute("""
            SELECT order_number, total_amount, status, created_at
            FROM chatbot_orders
            WHERE order_number = %s
        """, (order_number,))
        order = cursor.fetchone()

    db.close()

    if order:
        return jsonify({
            'order_number': order['order_number'],
            'total_amount': float(order['total_amount']),
            'status': order['status'],
            'created_at': order['created_at'].isoformat() if order['created_at'] else None
        })
    else:
        return jsonify({'error': 'Order not found'}), 404

# ============ NEWSLETTER ROUTES ============

@app.route('/admin/newsletter')
@login_required
def admin_newsletter():
    if not current_user.is_admin:
        flash('Admin access required', 'danger')
        return redirect(url_for('index'))
    
    # Redirect to the newsletter subscribers page
    return redirect(url_for('admin_newsletter_subscribers'))


@app.route('/newsletter', methods=['GET', 'POST'])
def newsletter():
    if request.method == 'POST':
        full_name = request.form.get('full_name')
        email = request.form.get('email')

        if not full_name or not email:
            flash('Please fill all fields', 'danger')
            return redirect(url_for('newsletter'))

        db = get_db()
        cursor = db.cursor()
        cursor.execute("INSERT INTO newsletter_subscribers (full_name, email, subscribed_at) VALUES (%s, %s, NOW())", (full_name, email))
        db.commit()
        db.close()
        flash('Thank you for subscribing!', 'success')
        return redirect(url_for('newsletter'))

    return render_template('newsletter.html')


@app.route('/admin/newsletter-subscribers')
@login_required
def admin_newsletter_subscribers():
    if not current_user.is_admin:
        flash('Admin access required', 'danger')
        return redirect(url_for('index'))

    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT * FROM newsletter_subscribers ORDER BY subscribed_at DESC")
    subscribers = cursor.fetchall()
    db.close()

    return render_template('admin/newsletter_subscribers.html', subscribers=subscribers)


@app.route('/admin/export-newsletter')
@login_required
def export_newsletter():
    if not current_user.is_admin:
        flash('Admin access required', 'danger')
        return redirect(url_for('index'))

    import csv
    from io import StringIO
    from datetime import datetime
    from flask import Response

    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT full_name, email, subscribed_at FROM newsletter_subscribers ORDER BY subscribed_at DESC")
    subscribers = cursor.fetchall()
    db.close()

    output = StringIO()
    writer = csv.writer(output)
    writer.writerow(['Full Name', 'Email', 'Subscribed Date'])

    for sub in subscribers:
        writer.writerow([sub['full_name'], sub['email'], sub['subscribed_at']])

    filename = f"newsletter_subscribers_{datetime.now().strftime('%Y%m%d_%H%M%S')}.csv"
    response = Response(output.getvalue(), mimetype='text/csv')
    response.headers['Content-Disposition'] = f'attachment; filename={filename}'
    return response


@app.route('/api/admin/delete-subscriber', methods=['POST'])
@login_required
def delete_subscriber():
    if not current_user.is_admin:
        return jsonify({'error': 'Unauthorized'}), 403

    data = request.json
    db = get_db()
    cursor = db.cursor()
    cursor.execute("DELETE FROM newsletter_subscribers WHERE id = %s", (data['id'],))
    db.commit()
    db.close()

    return jsonify({'success': True})


# ============ BLOG ROUTES ============

@app.route('/blog')
def blog():
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT * FROM blog_posts WHERE is_published = 1 ORDER BY published_at DESC")
    articles = cursor.fetchall()
    db.close()
    return render_template('blog.html', articles=articles)


@app.route('/blog/<int:article_id>')
def blog_detail(article_id):
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT * FROM blog_posts WHERE id = %s AND is_published = 1", (article_id,))
    article = cursor.fetchone()
    db.close()
    if not article:
        flash('Article not found', 'danger')
        return redirect(url_for('blog'))
    return render_template('blog_detail.html', article=article)

def auto_translate(text, target_lang):
    """Auto translate text to target language"""
    try:
        lang_map = {'fi': 'fi', 'sv': 'sv', 'fa': 'fa'}
        if target_lang not in lang_map:
            return text
        translator = GoogleTranslator(source='auto', target=lang_map[target_lang])
        return translator.translate(text)
    except:
        return text

@app.route('/admin/add-blog', methods=['GET', 'POST'])
@login_required
def add_blog():
    if not current_user.is_admin:
        flash('Admin access required', 'danger')
        return redirect(url_for('index'))
    
    if request.method == 'POST':
        title = request.form.get('title')
        category = request.form.get('category')
        content = request.form.get('content')
        image_url = request.form.get('image_url')
        
        # Auto translate to Finnish, Swedish, Persian
        translator_fi = GoogleTranslator(source='en', target='fi')
        translator_sv = GoogleTranslator(source='en', target='sv')
        translator_fa = GoogleTranslator(source='en', target='fa')
        
        title_fi = translator_fi.translate(title)
        title_sv = translator_sv.translate(title)
        title_fa = translator_fa.translate(title)
        content_fi = translator_fi.translate(content[:3000])  # Limit to avoid API limits
        content_sv = translator_sv.translate(content[:3000])
        content_fa = translator_fa.translate(content[:3000])
        
        db = get_db()
        cursor = db.cursor()
        cursor.execute("""
            INSERT INTO blog_posts (title, category, content, image_url, author, 
                                     title_fi, content_fi, 
                                     title_sv, content_sv, 
                                     title_fa, content_fa, 
                                     is_published) 
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 1)
        """, (title, category, content, image_url, current_user.username,
              title_fi, content_fi,
              title_sv, content_sv,
              title_fa, content_fa))
        db.commit()
        db.close()
        
        flash('Blog post published and auto-translated to Finnish, Swedish, and Persian!', 'success')
        return redirect(url_for('blog'))
    
    return render_template('admin/add_blog.html')

# ============ CONTACT ROUTE ============

@app.route('/contact', methods=['GET', 'POST'])
def contact():
    if request.method == 'POST':
        name = request.form.get('name')
        email = request.form.get('email')
        subject = request.form.get('subject')
        message = request.form.get('message')

        if not name or not email or not subject or not message:
            flash('Please fill all fields', 'danger')
            return redirect(url_for('contact'))

        # Send email to admin
        try:
            msg = MIMEMultipart()
            msg['From'] = email
            msg['To'] = GMAIL_USER
            msg['Subject'] = f"Contact Form: {subject}"

            body = f"""
Name: {name}
Email: {email}
Subject: {subject}

Message:
{message}

---
Sent from Bean & Brew Coffee Contact Form
"""
            msg.attach(MIMEText(body, 'plain'))

            server = smtplib.SMTP('smtp.gmail.com', 587)
            server.starttls()
            server.login(GMAIL_USER, GMAIL_PASSWORD)
            server.send_message(msg)
            server.quit()

            flash('Thank you for your message! We will get back to you soon.', 'success')
        except Exception as e:
            print(f"Email error: {e}")
            flash('Something went wrong. Please try again later.', 'danger')

        return redirect(url_for('contact'))

    return render_template('contact.html')


# ============ ABOUT US ROUTE ============

@app.route('/about')
def about():
    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT * FROM team_members WHERE is_active = 1 ORDER BY display_order")
    team_members = cursor.fetchall()
    db.close()
    return render_template('about.html', team_members=team_members)

# ============ TEAM MANAGEMENT ADMIN ============

@app.route('/admin/team')
@login_required
def admin_team():
    if not current_user.is_admin:
        flash('Admin access required', 'danger')
        return redirect(url_for('index'))

    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT * FROM team_members ORDER BY display_order")
    team_members = cursor.fetchall()
    db.close()
    return render_template('admin/team_members.html', team_members=team_members)


@app.route('/admin/team/add', methods=['GET', 'POST'])
@login_required
def admin_team_add():
    if not current_user.is_admin:
        flash('Admin access required', 'danger')
        return redirect(url_for('index'))

    if request.method == 'POST':
        name = request.form.get('name')
        position = request.form.get('position')
        bio = request.form.get('bio')
        display_order = request.form.get('display_order', 0)

        db = get_db()
        cursor = db.cursor()
        cursor.execute("""
            INSERT INTO team_members (name, position, bio, display_order, is_active)
            VALUES (%s, %s, %s, %s, 1)
        """, (name, position, bio, display_order))
        db.commit()
        db.close()

        flash('Team member added successfully!', 'success')
        return redirect(url_for('admin_team'))

    return render_template('admin/team_add.html')


@app.route('/admin/team/edit/<int:member_id>', methods=['GET', 'POST'])
@login_required
def admin_team_edit(member_id):
    if not current_user.is_admin:
        flash('Admin access required', 'danger')
        return redirect(url_for('index'))

    db = get_db()
    cursor = db.cursor()

    if request.method == 'POST':
        name = request.form.get('name')
        position = request.form.get('position')
        bio = request.form.get('bio')
        display_order = request.form.get('display_order', 0)
        is_active = 1 if request.form.get('is_active') else 0

        cursor.execute("""
            UPDATE team_members
            SET name = %s, position = %s, bio = %s, display_order = %s, is_active = %s
            WHERE id = %s
        """, (name, position, bio, display_order, is_active, member_id))
        db.commit()
        db.close()

        flash('Team member updated successfully!', 'success')
        return redirect(url_for('admin_team'))

    cursor.execute("SELECT * FROM team_members WHERE id = %s", (member_id,))
    member = cursor.fetchone()
    db.close()

    if not member:
        flash('Team member not found', 'danger')
        return redirect(url_for('admin_team'))

    return render_template('admin/team_edit.html', member=member)


@app.route('/admin/team/delete/<int:member_id>')
@login_required
def admin_team_delete(member_id):
    if not current_user.is_admin:
        flash('Admin access required', 'danger')
        return redirect(url_for('index'))

    db = get_db()
    cursor = db.cursor()
    cursor.execute("DELETE FROM team_members WHERE id = %s", (member_id,))
    db.commit()
    db.close()

    flash('Team member deleted successfully!', 'success')
    return redirect(url_for('admin_team'))


@app.route('/admin/news')
@login_required
def admin_news_list():
    if not current_user.is_admin:
        flash('Admin access required', 'danger')
        return redirect(url_for('index'))

    db = get_db()
    cursor = db.cursor()
    cursor.execute("SELECT * FROM blog_posts ORDER BY published_at DESC")
    news_list = cursor.fetchall()
    db.close()

    return render_template('admin/news_list.html', news_list=news_list)


@app.route('/admin/news/add')
@login_required
def admin_news_add():
    if not current_user.is_admin:
        flash('Admin access required', 'danger')
        return redirect(url_for('index'))
    return redirect(url_for('add_blog'))


@app.route('/admin/news/edit/<int:news_id>', methods=['GET', 'POST'])
@login_required
def admin_news_edit(news_id):
    if not current_user.is_admin:
        flash('Admin access required', 'danger')
        return redirect(url_for('index'))
    
    db = get_db()
    cursor = db.cursor()
    
    if request.method == 'POST':
        # Handle form submission
        title = request.form.get('title')
        category = request.form.get('category')
        content = request.form.get('content')
        is_published = 1 if request.form.get('is_published') else 0
        
        cursor.execute("""
            UPDATE blog_posts 
            SET title = %s, category = %s, content = %s, is_published = %s
            WHERE id = %s
        """, (title, category, content, is_published, news_id))
        db.commit()
        db.close()
        
        flash('Article updated successfully!', 'success')
        return redirect(url_for('admin_news_list'))
    
    # Handle GET request - show edit form
    cursor.execute("SELECT * FROM blog_posts WHERE id = %s", (news_id,))
    news = cursor.fetchone()
    db.close()
    
    if not news:
        flash('Article not found', 'danger')
        return redirect(url_for('admin_news_list'))
    
    return render_template('admin/news_edit.html', news=news)


@app.route('/admin/news/delete/<int:news_id>', methods=['POST'])
@login_required
def admin_news_delete(news_id):
    if not current_user.is_admin:
        flash('Admin access required', 'danger')
        return redirect(url_for('index'))

    db = get_db()
    cursor = db.cursor()
    cursor.execute("DELETE FROM blog_posts WHERE id = %s", (news_id,))
    db.commit()
    db.close()

    flash('Article deleted successfully!', 'success')
    return redirect(url_for('admin_news_list'))

if __name__ == '__main__':
    app.run(debug=True, host='0.0.0.0', port=5000)





