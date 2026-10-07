import tkinter as tk
from tkinter import messagebox

# Define the menu of the restaurant
menu = {
    'Pizza':     40,
    'Pasta':     50,
    'Burger':    30,
    'Salad':     50,
    'Coffee':    20,
    'Tea':       10,
    'Ice cream': 15,
}

# Function to add item to the order
def add_item():
    item = item_var.get()
    if item in menu:
        global order_total
        order_total += menu[item]
        order_listbox.insert(tk.END, f"{item}:   {menu[item]} €")
        total_label.config(text=f"Total: {order_total} €")
        item_var.set("")  # Clear the input field after adding the item
    else:
        messagebox.showerror("Error", f"Ordered item '{item}' is not available!")

# Function to finalize the order
def finalize_order():
    total_label.config(text=f"Final Total: {order_total} €")
    add_button.config(state=tk.DISABLED)
    finalize_button.config(state=tk.DISABLED)

# Create the main window
root = tk.Tk()
root.title("Afghan Restaurant")
root.configure(bg="black")  # Set background color

# Create a frame to center all widgets
center_frame = tk.Frame(root, bg="black")
center_frame.pack(expand=True)  # Use pack to center the frame in the window

# Create the greeting label
greet_label = tk.Label(center_frame, text="Welcome to Afghan Restaurant", font=("Helvetica", 16, "bold"), bg="cyan", fg="black")
greet_label.pack(pady=10)

# Create a frame to hold menu and order listboxes side by side
listbox_frame = tk.Frame(center_frame, bg="black")
listbox_frame.pack(pady=10)

# Create the menu listbox
menu_listbox = tk.Listbox(listbox_frame, height=10, width=40, bg="cyan", fg="black", font=("Helvetica", 14))
for item, price in menu.items():
    menu_listbox.insert(tk.END, f"{item}:   {price}  €")
menu_listbox.pack(side=tk.LEFT, padx=10)

# Create a listbox to display ordered items
order_listbox = tk.Listbox(listbox_frame, height=10, width=40, bg="cyan", fg="black", font=("Helvetica", 14))
order_listbox.pack(side=tk.LEFT, padx=10)

# Create an entry field for item input
item_entry_frame = tk.Frame(center_frame, bg="black")
item_entry_frame.pack(pady=10)

item_var = tk.StringVar()
item_entry = tk.Entry(item_entry_frame, textvariable=item_var, font=("Helvetica", 14), bg="cyan", fg="black")
item_entry.pack(side=tk.LEFT, padx=10)

# Create a button to add the item
add_button = tk.Button(item_entry_frame, text="Add Item", command=add_item, font=("Helvetica", 12, "bold"), bg="cyan", fg="black")
add_button.pack(side=tk.LEFT, padx=10)

# Create a button to finalize the order
finalize_button = tk.Button(center_frame, text="Finalize Order", command=finalize_order, font=("Helvetica", 14, "bold"), bg="cyan", fg="black")
finalize_button.pack(pady=10)

# Create a label to display the total amount
total_label = tk.Label(center_frame, text="Total: 0 €", font=("Helvetica", 14, "bold"), bg="black", fg="red")
total_label.pack(pady=10)

# Initialize order total
order_total = 0

# Run the main loop
root.mainloop()
