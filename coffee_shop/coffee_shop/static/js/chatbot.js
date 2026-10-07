// Coffee Shop AI Chatbot - Fixed Version
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('chatbotToggle');
    const chatContainer = document.getElementById('chatbotContainer');
    const closeBtn = document.querySelector('.close-chat');
    const sendBtn = document.getElementById('sendMessage');
    const userInput = document.getElementById('userMessage');
    const messagesDiv = document.getElementById('chatMessages');
    
    let currentOrder = [];
    let ordering = false;

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            chatContainer.classList.toggle('active');
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            chatContainer.classList.remove('active');
            currentOrder = [];
            ordering = false;
        });
    }

    function addMessage(text, isUser) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'message ' + (isUser ? 'user' : 'bot');
        msgDiv.innerHTML = '<div>' + text.replace(/\n/g, '<br>') + '</div>';
        messagesDiv.appendChild(msgDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }

    const menuItems = {
        'espresso': { name: 'Espresso', price: 3.50 },
        'cappuccino': { name: 'Cappuccino', price: 4.50 },
        'latte': { name: 'Latte', price: 4.50 },
        'mocha': { name: 'Mocha', price: 5.00 },
        'caramel macchiato': { name: 'Caramel Macchiato', price: 5.50 },
        'cold brew': { name: 'Cold Brew', price: 4.50 },
        'green tea': { name: 'Green Tea', price: 3.00 },
        'chai latte': { name: 'Chai Latte', price: 4.50 },
        'croissant': { name: 'Croissant', price: 3.00 },
        'blueberry muffin': { name: 'Blueberry Muffin', price: 3.50 },
        'chocolate chip cookie': { name: 'Chocolate Chip Cookie', price: 2.50 },
        'hot chocolate': { name: 'Hot Chocolate', price: 4.00 }
    };

    async function saveOrder(orderItems, total) {
        try {
            const orderNumber = 'CHAT' + Math.random().toString(36).substring(2, 8).toUpperCase();
            
            const response = await fetch('/api/chatbot/save-order', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_number: orderNumber,
                    customer_name: 'Chatbot Customer',
                    customer_email: 'chatbot@coffeeshop.com',
                    customer_phone: '00000000',
                    delivery_address: 'pickup',
                    items: orderItems,
                    total_amount: total
                })
            });
            const result = await response.json();
            console.log("Order saved:", result);
            return result;
        } catch (error) {
            console.error('Error saving order:', error);
            return null;
        }
    }

    async function getAIResponse(message) {
        const msg = message.toLowerCase().trim();

        // Start order
        if (msg.includes('order') && !ordering) {
            ordering = true;
            currentOrder = [];
            return "🛍️ What would you like to order?\n\nType: latte, cappuccino, croissant, green tea\nType 'done' when finished\nType 'cancel' to cancel";
        }
        
        // Cancel order
        if (msg.includes('cancel') && ordering) {
            ordering = false;
            currentOrder = [];
            return "❌ Order cancelled. Type 'order' to start a new order.";
        }
        
        // Add items to order
        if (ordering && !msg.includes('done') && !msg.includes('cancel')) {
            for (const [key, item] of Object.entries(menuItems)) {
                if (msg.includes(key)) {
                    currentOrder.push(item);
                    let total = currentOrder.reduce((sum, i) => sum + i.price, 0);
                    return `✅ ${item.name} ($${item.price}) added!\n\nCurrent total: $${total.toFixed(2)}\nAdd more or type 'done' to finish.`;
                }
            }
            return "I don't recognize that item. Try: latte, cappuccino, croissant, green tea";
        }
        
        // Finish order and save to database
        if (msg.includes('done') && ordering && currentOrder.length > 0) {
            let total = currentOrder.reduce((sum, item) => sum + item.price, 0);
            let items = currentOrder.map(item => ({ name: item.name, price: item.price, quantity: 1 }));
            
            addMessage("⏳ Placing your order...", false);
            
            const result = await saveOrder(items, total);
            
            if (result && result.success) {
                ordering = false;
                let orderNum = result.order_number;
                currentOrder = [];
                return `✅ ORDER PLACED! 🎉\n\n📋 Order #: ${orderNum}\n💰 Total: $${total.toFixed(2)}\n📍 Pickup in 15-20 min\n📍 Iskoskuja 3 C 111, Vantaa\n\nThank you! ☕\n\nType 'order' to start a new order.`;
            } else {
                ordering = false;
                currentOrder = [];
                return "❌ Error placing order. Please try again.";
            }
        }
        
        // If user types 'done' but no items
        if (msg.includes('done') && ordering && currentOrder.length === 0) {
            ordering = false;
            return "No items in your order. Type 'order' to start over.";
        }
        
        // Menu
        if (msg.includes('menu')) {
            return "☕ MENU:\n\nCoffee: Espresso($3.50), Latte($4.50), Cappuccino($4.50), Mocha($5.00), Caramel Macchiato($5.50), Cold Brew($4.50)\n\nTea: Green Tea($3.00), Chai Latte($4.50)\n\nPastries: Croissant($3.00), Blueberry Muffin($3.50), Cookie($2.50)\n\nBeverage: Hot Chocolate($4.00)\n\nType 'order' to place an order!";
        }
        
        // Hours
        if (msg.includes('hour') || msg.includes('open')) {
            return "📅 Open daily:\nMon-Fri: 7AM - 9PM\nSat-Sun: 8AM - 10PM";
        }
        
        // Location
        if (msg.includes('address') || msg.includes('location')) {
            return "📍 Iskoskuja 3 C 111, Vantaa";
        }
        
        // WiFi
        if (msg.includes('wifi')) {
            return "📶 FREE WiFi\nNetwork: Bean&Brew_Coffee\nPassword: coffeelove2024";
        }
        
        // Default
        return "☕ Hello! Type 'menu' to see items, 'order' to place an order, 'hours' for opening times.";
    }

    async function sendMessage() {
        const message = userInput.value.trim();
        if (!message) return;
        
        addMessage(message, true);
        userInput.value = '';
        
        const typingDiv = document.createElement('div');
        typingDiv.className = 'message bot';
        typingDiv.id = 'typing';
        typingDiv.innerHTML = '<div>☕...</div>';
        messagesDiv.appendChild(typingDiv);
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
        
        const response = await getAIResponse(message);
        
        const typing = document.getElementById('typing');
        if (typing) typing.remove();
        
        addMessage(response, false);
    }

    if (sendBtn) sendBtn.addEventListener('click', sendMessage);
    if (userInput) userInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') sendMessage(); });
});
