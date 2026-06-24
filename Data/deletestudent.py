import csv
import os
from tabulate import tabulate
from colorama import Fore, Style, init

# Initialize Colorama
init(autoreset=True)

# File to store student data
DATA_FILE = 'students.csv'

# Define the number of subjects
NUM_SUBJECTS = 7  # You can change this number to set the number of subjects

# Load existing student data from the file
def load_data():
    students = []
    if os.path.exists(DATA_FILE):
        with open(DATA_FILE, mode='r', newline='') as file:
            reader = csv.DictReader(file)
            for row in reader:
                # Convert the 'scores' string back to a list of integers
                row['scores'] = eval(row['scores'])  # Convert string back to list
                row['total_score'] = float(row['total_score'])
                row['average_score'] = float(row['average_score'])
                students.append(row)
    return students

# Save student data to the file
def save_data(data):
    with open(DATA_FILE, mode='w', newline='') as file:
        fieldnames = ['id', 'first_name', 'last_name', 'email', 'scores', 'total_score', 'average_score']
        writer = csv.DictWriter(file, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(data)

# Function to fetch and display all students in a colorful table format
def fetch_students():
    students = load_data()
    table = []
    headers = [
        Fore.RED + 'ID',
        Fore.GREEN + 'First Name',
        Fore.YELLOW + 'Last Name',
        Fore.CYAN + 'Email',
        Fore.MAGENTA + f'Scores (Out of {NUM_SUBJECTS})',
        Fore.BLUE + 'Total Score',
        Fore.WHITE + 'Average Score'
    ]
    
    for student in students:
        table.append([
            Fore.RED + student.get('id', 'N/A'),
            Fore.GREEN + student.get('first_name', 'N/A'),
            Fore.YELLOW + student.get('last_name', 'N/A'),
            Fore.CYAN + student.get('email', 'N/A'),
            Fore.MAGENTA + str(student.get('scores', [0] * NUM_SUBJECTS)),
            Fore.BLUE + str(student.get('total_score', 0)),
            Fore.WHITE + f"{student.get('average_score', 0):.2f}"
        ])
    
    print(tabulate(table, headers, tablefmt='fancy_grid'))

# Function to delete a specific student by ID
def delete_student():
    students = load_data()
    fetch_students()  # Show all students to get the IDs
    student_id = input(Fore.RED + "Enter the ID of the student to delete: ")
    
    # Find and delete the student with the specified ID
    students = [student for student in students if student['id'] != student_id]
    
    if len(students) < len(load_data()):
        save_data(students)
        print(Fore.GREEN + f"Student with ID {student_id} has been deleted.")
    else:
        print(Fore.RED + "Student ID not found. No changes made.")

# Function to delete all student records
def delete_all_students():
    # Confirm deletion
    confirm = input(Fore.RED + "Are you sure you want to delete all student records? (yes/no): ")
    if confirm.lower() != 'yes':
        print(Fore.YELLOW + "Deletion canceled.")
        return

    # Clear the student data file
    save_data([])

    print(Fore.GREEN + "All student records have been deleted.")

# Function to edit student details by ID
def edit_student():
    students = load_data()
    fetch_students()  # Show all students to get the IDs
    student_id = input(Fore.CYAN + "Enter the ID of the student to edit: ")
    
    student_to_edit = next((student for student in students if student['id'] == student_id), None)
    
    if student_to_edit:
        print(Fore.CYAN + "Enter new details (leave blank to keep current):")
        first_name = input(Fore.GREEN + f"New First Name ({student_to_edit['first_name']}): ")
        last_name = input(Fore.GREEN + f"New Last Name ({student_to_edit['last_name']}): ")
        email = input(Fore.GREEN + f"New Email ({student_to_edit['email']}): ")

        if first_name:
            student_to_edit['first_name'] = first_name
        if last_name:
            student_to_edit['last_name'] = last_name
        if email:
            student_to_edit['email'] = email

        # Edit scores
        print(Fore.YELLOW + "Enter new scores (leave blank to keep current):")
        scores = student_to_edit['scores']
        for i in range(NUM_SUBJECTS):
            while True:
                try:
                    new_score = input(Fore.YELLOW + f"New Score for Subject {i + 1} ({scores[i]}): ")
                    if new_score == "":
                        break  # Leave score unchanged
                    new_score = int(new_score)
                    if 0 <= new_score <= 100:
                        scores[i] = new_score
                        break
                    else:
                        print(Fore.RED + "Score must be between 0 and 100. Please try again.")
                except ValueError:
                    print(Fore.RED + "Invalid input. Please enter an integer between 0 and 100.")

        # Recalculate total_score and average_score
        total_score = sum(scores)
        average_score = total_score / len(scores)
        student_to_edit['scores'] = str(scores)  # Convert list to string for CSV
        student_to_edit['total_score'] = total_score
        student_to_edit['average_score'] = average_score

        save_data(students)
        print(Fore.GREEN + f"Student with ID {student_id} has been updated.")
    else:
        print(Fore.RED + "Student ID not found. No changes made.")

# Main menu function
def menu():
    while True:
        print("\n" + Fore.YELLOW + "1. View All Students")
        print(Fore.YELLOW + "2. Edit Student Details")
        print(Fore.YELLOW + "3. Delete a Student")
        print(Fore.YELLOW + "4. Delete All Students")
        print(Fore.YELLOW + "5. Exit")
        choice = input(Fore.CYAN + "Enter your choice: ")
        if choice == '1':
            fetch_students()
        elif choice == '2':
            edit_student()
        elif choice == '3':
            delete_student()
        elif choice == '4':
            delete_all_students()
        elif choice == '5':
            break
        else:
            print(Fore.RED + "Invalid choice. Please select 1, 2, 3, 4, or 5.")

# Uncomment to enable menu-based interaction
menu()