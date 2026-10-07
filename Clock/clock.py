import tkinter as tk
from tkinter import Label
import time
import random

# Create the main window
root = tk.Tk()
root.title("Colorful Digital Clock")

# Create a label to display the time
time_label = Label(root, font=('Arial', 48), bg='white', fg='black')
time_label.pack(padx=20, pady=20)

# List of colors to change the background
colors = ['red', 'blue', 'green', 'yellow', 'purple', 'orange', 'pink', 'cyan']

# Define a function to update the time and change the background color
def update_time_and_color():
    current_time = time.strftime('%I:%M:%S %p')
    time_label.config(text=current_time)
    
    # Change the background color to a random color from the list
    random_color = random.choice(colors)
    time_label.config(bg=random_color)
    
    # Schedule the function to be called again after 1000 ms (1 second)
    root.after(1000, update_time_and_color)

# Call the update_time_and_color function to start the clock and color change
update_time_and_color()

# Run the Tkinter event loop
root.mainloop()
