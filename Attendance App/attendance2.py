import tkinter as tk
from tkinter import messagebox
import csv
from datetime import datetime
from PIL import Image, ImageTk

# Define a dictionary to hold attendance data
attendance_data = {}

# Load data from CSV file
def load_data():
    global attendance_data
    try:
        with open('attendance_data1.csv', mode='r') as file:
            reader = csv.reader(file)
            headers = next(reader, None)  # Read the header row
            if headers != ["ID", "First Name", "Last Name", "Email", "Phone", "Date", "Sign In Time", "Sign Out Time"]:
                print("CSV headers do not match expected format.")
                return
            attendance_data = {}  # Clear existing data
            for row in reader:
                unique_id = int(row[0])
                attendance_data[unique_id] = {
                    'First Name': row[1],
                    'Last Name': row[2],
                    'Email': row[3],
                    'Phone': row[4],
                    'Date': row[5],
                    'Sign In Time': row[6],
                    'Sign Out Time': row[7]
                }
        print("Data loaded successfully.")
    except FileNotFoundError:
        print("No previous data found, starting fresh.")
    except Exception as e:
        messagebox.showerror("Error", f"Error loading data: {e}")

# Save data to CSV file
def save_data():
    try:
        with open('attendance_data1.csv', mode='w', newline='') as file:
            writer = csv.writer(file)
            writer.writerow(["ID", "First Name", "Last Name", "Email", "Phone", "Date", "Sign In Time", "Sign Out Time"])
            for unique_id, data in attendance_data.items():
                writer.writerow([unique_id, data['First Name'], data['Last Name'], data['Email'], data['Phone'], data['Date'], data['Sign In Time'], data['Sign Out Time']])
        print("Data successfully saved to CSV.")
    except Exception as e:
        messagebox.showerror("Error", f"Error saving data: {e}")

# Add a new worker to the attendance system
def add_worker():
    first_name = first_name_entry.get().strip()
    last_name = last_name_entry.get().strip()
    email = email_entry.get().strip()
    phone = phone_entry.get().strip()

    if not first_name or not last_name:
        messagebox.showwarning("Input Error", "First Name and Last Name are required.")
        return

    unique_id = len(attendance_data) + 1
    attendance_data[unique_id] = {
        'First Name': first_name,
        'Last Name': last_name,
        'Email': email,
        'Phone': phone,
        'Date': '',
        'Sign In Time': '',
        'Sign Out Time': ''
    }

    update_table()
    save_data()

    first_name_entry.delete(0, tk.END)
    last_name_entry.delete(0, tk.END)
    email_entry.delete(0, tk.END)
    phone_entry.delete(0, tk.END)

# Record attendance (sign in or sign out)
def record_attendance(unique_id, action):
    current_time = datetime.now().strftime("%I:%M %p")
    current_date = datetime.now().strftime("%Y-%m-%d")

    if unique_id in attendance_data:
        if action == "Sign In":
            attendance_data[unique_id]['Sign In Time'] = current_time
            attendance_data[unique_id]['Date'] = current_date
        elif action == "Sign Out":
            attendance_data[unique_id]['Sign Out Time'] = current_time

    update_table()
    save_data()

# Delete a worker from the attendance system
def delete_worker(unique_id):
    if unique_id in attendance_data:
        del attendance_data[unique_id]
        update_table()
        save_data()
    else:
        messagebox.showwarning("Error", "Worker not found.")

# Edit the time of a worker
def edit_time(unique_id, sign_in_time, sign_out_time):
    if unique_id in attendance_data:
        attendance_data[unique_id]['Sign In Time'] = sign_in_time
        attendance_data[unique_id]['Sign Out Time'] = sign_out_time
        update_table()
        save_data()
    else:
        messagebox.showwarning("Error", "Worker not found.")

def update_table():
    # Clear previous table content
    for widget in table_inner_frame.winfo_children():
        widget.destroy()

    headers = ["ID", "First Name", "Last Name", "Email Address", "Phone", "Date", "Sign In Time", "Sign Out Time", "Sign In", "Sign Out", "Delete", "Edit Sign In Time", "Edit Sign In Time Input", "Edit Sign Out Time", "Edit Sign Out Time Input", "Save Time"]
    
    # Configure the column expansion to ensure full width usage
    for j, header in enumerate(headers):
        table_inner_frame.grid_columnconfigure(j, weight=1)
        # Set border and styling for headers
        tk.Label(table_inner_frame, text=header, bg='lightcyan', font=('Arial', 12, 'bold'), bd=1, relief="solid").grid(row=0, column=j, padx=5, pady=5, sticky="ew")

    # Iterate over attendance data and display it in the table
    for i, (unique_id, data) in enumerate(attendance_data.items(), start=1):
        table_inner_frame.grid_rowconfigure(i, weight=1)
        
        # Add a solid border to each data cell to create a line between rows
        tk.Label(table_inner_frame, text=unique_id, bg='white', font=('Arial', 12, '')).grid(row=i, column=0, padx=5, pady=2, sticky='ew')
        tk.Label(table_inner_frame, text=data['First Name'], bg='white', font=('Arial', 12, '')).grid(row=i, column=1, padx=5, pady=2, sticky='ew')
        tk.Label(table_inner_frame, text=data['Last Name'], bg='white', font=('Arial', 12, '')).grid(row=i, column=2, padx=5, pady=2, sticky='ew')
        tk.Label(table_inner_frame, text=data['Email'], bg='white', font=('Arial', 12, '')).grid(row=i, column=3, padx=5, pady=2, sticky='ew')
        tk.Label(table_inner_frame, text=data['Phone'], bg='white', font=('Arial', 12, '')).grid(row=i, column=4, padx=5, pady=2, sticky='ew')
        tk.Label(table_inner_frame, text=data['Date'], bg='white', font=('Arial', 12, '')).grid(row=i, column=5, padx=5, pady=2, sticky='ew')
        tk.Label(table_inner_frame, text=data['Sign In Time'], bg='white', font=('Arial', 12, '')).grid(row=i, column=6, padx=5, pady=2, sticky='ew')
        tk.Label(table_inner_frame, text=data['Sign Out Time'], bg='white', font=('Arial', 12, '')).grid(row=i, column=7, padx=5, pady=2, sticky='ew')

        # Sign In and Sign Out Buttons
        tk.Button(table_inner_frame, text="Sign In", bg='lightgreen', font=('Arial', 12, ''), bd=1, relief="solid", command=lambda unique_id=unique_id: record_attendance(unique_id, "Sign In")).grid(row=i, column=8, padx=5, pady=2, sticky='ew')
        tk.Button(table_inner_frame, text="Sign Out", bg='orange', font=('Arial', 12, ''), bd=1, relief="solid", command=lambda unique_id=unique_id: record_attendance(unique_id, "Sign Out")).grid(row=i, column=9, padx=5, pady=2, sticky='ew')
        # Delete Buttons
        tk.Button(table_inner_frame, text="Delete", bg='red', font=('Arial', 12, ''), bd=1, relief="solid", command=lambda unique_id=unique_id: delete_worker(unique_id)).grid(row=i, column=10, padx=5, pady=2, sticky='ew')

        # Edit Sign In Time and Input Entry
        tk.Label(table_inner_frame, text="Edit Sign In Time:", bg='white', font=('Arial', 12, '')).grid(row=i, column=11, padx=5, pady=2, sticky='ew')
        sign_in_time_entry = tk.Entry(table_inner_frame, width=10, font=('Arial', 12, ''), bd=1, relief="solid")
        sign_in_time_entry.grid(row=i, column=12, padx=5, pady=2, sticky='ew')
        sign_in_time_entry.insert(0, data['Sign In Time'])

        # Edit Sign Out Time and Input Entry
        tk.Label(table_inner_frame, text="Edit Sign Out Time:", bg='white', font=('Arial', 12, '')).grid(row=i, column=13, padx=5, pady=2, sticky='ew')
        sign_out_time_entry = tk.Entry(table_inner_frame, width=10, font=('Arial', 12, ''), bd=1, relief="solid")
        sign_out_time_entry.grid(row=i, column=14, padx=5, pady=2, sticky='ew')
        sign_out_time_entry.insert(0, data['Sign Out Time'])

        # Save Time Button
        tk.Button(table_inner_frame, text="Save Time", bg='lightgreen', font=('Arial', 12, ''), bd=1, relief="solid", command=lambda unique_id=unique_id, sign_in=sign_in_time_entry, sign_out=sign_out_time_entry: edit_time(unique_id, sign_in.get(), sign_out.get())).grid(row=i, column=15, padx=5, pady=2, sticky='ew')

# Close the application
def close_application():
    save_data()  # Save data before closing
    app.quit()

# Main application window
app = tk.Tk()
app.title("Attendance Management System")
app.geometry("1500x1000")  # Set the size of the window

# Load and display background image
img_path = 'c:/Users/azizn/pexels-photo-4042391.jpeg'  # Update this path
try:
    img = Image.open(img_path)
    img = img.resize((1500, 1000))  # Resize image to fit your layout
    img_tk = ImageTk.PhotoImage(img)
    bg_label = tk.Label(app, image=img_tk)
    bg_label.image = img_tk  # Keep a reference to avoid garbage collection
    bg_label.place(relwidth=1, relheight=1)  # Stretch image to fit window
except Exception as e:
    print(f"Error loading image: {e}")

# Input Section (with separate background color)
input_frame = tk.Frame(app, bg='#5dc2f5', bd=1, relief="solid", padx=30, pady=20)
input_frame.pack(pady=10)

tk.Label(input_frame, text="First Name", bg='#5dc2f5', font=('Arial', 12, 'bold')).grid(row=0, column=0, sticky="w")
first_name_entry = tk.Entry(input_frame, bd=1, relief="solid", font=('Arial', 12, ''), width=25)
first_name_entry.grid(row=0, column=1, pady=10)

tk.Label(input_frame, text="Last Name", bg='#5dc2f5', font=('Arial', 12, 'bold')).grid(row=1, column=0, sticky="w")
last_name_entry = tk.Entry(input_frame, bd=1, relief="solid", font=('Arial', 12, ''), width=25)
last_name_entry.grid(row=1, column=1, pady=10)

tk.Label(input_frame, text="Email", bg='#5dc2f5', font=('Arial', 12, 'bold')).grid(row=2, column=0, sticky="w")
email_entry = tk.Entry(input_frame, bd=1, relief="solid", font=('Arial', 12, ''), width=25)
email_entry.grid(row=2, column=1, pady=10)

tk.Label(input_frame, text="Phone", bg='#5dc2f5', font=('Arial', 12, 'bold')).grid(row=3, column=0, sticky="w")
phone_entry = tk.Entry(input_frame, bd=1, relief="solid", font=('Arial', 12, ''), width=25)
phone_entry.grid(row=3, column=1, pady=10)

# Buttons
button_frame = tk.Frame(input_frame, bg='#5dc2f5', bd=0)
button_frame.grid(row=4, column=0, columnspan=2, pady=10, sticky="ew")

tk.Button(button_frame, text="Add Worker", bg='lightgreen', font=('Arial', 12, 'bold'), command=add_worker).pack(side="left", padx=5)
tk.Button(button_frame, text="Exit", bg='red', font=('Arial', 12, 'bold'), command=close_application).pack(side="right", padx=5)

input_frame.pack(pady=10)

# Table Section (with separate background color)
table_frame = tk.Frame(app, bg='white', bd=1, relief="solid")
table_canvas = tk.Canvas(table_frame, bg='white')
table_inner_frame = tk.Frame(table_canvas, bg='white')

xscrollbar = tk.Scrollbar(table_frame, orient="horizontal", command=table_canvas.xview)
xscrollbar.pack(side="bottom", fill="x")

yscrollbar = tk.Scrollbar(table_frame, orient="vertical", command=table_canvas.yview)
yscrollbar.pack(side="right", fill="y")

table_canvas.configure(xscrollcommand=xscrollbar.set, yscrollcommand=yscrollbar.set)
table_canvas.pack(side="left", fill="both", expand=True)
table_canvas.create_window((0, 0), window=table_inner_frame, anchor="nw")

table_inner_frame.bind("<Configure>", lambda e: table_canvas.configure(scrollregion=table_canvas.bbox("all")))

table_frame.pack(fill="both", expand=True, padx=20, pady=10)

load_data()
update_table()

app.mainloop()
