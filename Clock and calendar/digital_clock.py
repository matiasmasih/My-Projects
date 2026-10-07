import tkinter as tk
import time
import random

def update_clock():
    # Get the current time and date
    current_time = time.strftime('%I:%M:%S %p')
    current_date = time.strftime('%A, %B %d, %Y')
    
    # Update the time and date labels
    clock_label.config(text=current_time)
    date_label.config(text=current_date)
    
    # Change background and text colors randomly
    new_bg_color = random.choice(['#FFCCCC', '#CCFFCC', '#CCCCFF', '#FFFFCC', '#CCFFFF', '#FFCCFF', '#e6c20b', '#a1e60b', '#0be628', '#0be6e2', '#0b49e6'])
    new_fg_color = random.choice(['#e60f0b'])

    clock_label.config(bg=new_bg_color, fg=new_fg_color)
    date_label.config(bg=new_bg_color, fg=new_fg_color)
    root.config(bg=new_bg_color)
    
    # Change font family randomly
    fonts = ['Helvetica', 'Courier', 'Times', 'Arial', 'Comic Sans MS', 'Verdant', 'Georgia']
    new_font = random.choice(fonts)
    
    clock_label.config(font=(new_font, 48))
    date_label.config(font=(new_font, 24))

    # Update the time every second
    clock_label.after(1000, update_clock)

# Create the main window
root = tk.Tk()
root.title("Colorful Digital Clock with Date")

# Set the geometry of the window to be centered
root.geometry("600x300")
root.eval('tk::PlaceWindow . center')  # This line centers the window on the screen

# Create labels to display the time and date with a border
clock_label = tk.Label(root, font=('Helvetica', 48), bd=5, relief='solid')
clock_label.pack(expand=True, pady=12)

date_label = tk.Label(root, font=('Helvetica', 24), bd=5, relief='solid')
date_label.pack(expand=True, pady=12)

# Start the clock
update_clock()

# Run the main loop
root.mainloop()
