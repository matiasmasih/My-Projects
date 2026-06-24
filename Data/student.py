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

# Initialize the CSV file with headers if it doesn't exist
def initialize_csv():
    if not os.path.exists(DATA_FILE):
        fieldnames = ['id', 'first_name', 'last_name', 'email'] + [f'score_{i}' for i in range(1, NUM_SUBJECTS + 1)] + ['total_score', 'average_score']
        with open(DATA_FILE, mode='w', newline='') as file:
            writer = csv.DictWriter(file, fieldnames=fieldnames)
            writer.writeheader()
        print(Fore.GREEN + f"Initialized CSV file with headers: {fieldnames}")

# Load existing student data from the file
def load_data():
    students = []
    if os.path.exists(DATA_FILE):
        with open(DATA_FILE, mode='r', newline='') as file:
            reader = csv.DictReader(file)
            headers = reader.fieldnames
            print(Fore.GREEN + f"CSV headers found: {headers}")
            for row in reader:
                scores = [int(row[f'score_{i}']) for i in range(1, NUM_SUBJECTS + 1)]
                student = {
                    'id': row['id'],
                    'first_name': row['first_name'],
                    'last_name': row['last_name'],
                    'email': row['email'],
                    'scores': scores,
                    'total_score': float(row['total_score']),
                    'average_score': float(row['average_score'])
                }
                students.append(student)
    return students

# Save student data to the file
def save_data(data):
    fieldnames = ['id', 'first_name', 'last_name', 'email'] + [f'score_{i}' for i in range(1, NUM_SUBJECTS + 1)] + ['total_score', 'average_score']
    with open(DATA_FILE, mode='w', newline='') as file:
        writer = csv.DictWriter(file, fieldnames=fieldnames)
        writer.writeheader()
        for student in data:
            row = {
                'id': student['id'],
                'first_name': student['first_name'],
                'last_name': student['last_name'],
                'email': student['email'],
                'total_score': student['total_score'],
                'average_score': student['average_score']
            }
            for i in range(1, NUM_SUBJECTS + 1):
                row[f'score_{i}'] = student['scores'][i-1]
            writer.writerow(row)

# Function to insert a new student into the data
def insert_student():
    print(Fore.CYAN + "Enter the details of the new student:")
    first_name = input(Fore.GREEN + "First Name: ")
    last_name = input(Fore.GREEN + "Last Name: ")
    email = input(Fore.GREEN + "Email: ")
    
    # Collect scores for the defined number of subjects
    scores = []
    print(Fore.YELLOW + f"Enter scores for {NUM_SUBJECTS} subjects:")
    for i in range(1, NUM_SUBJECTS + 1):
        while True:
            try:
                score = int(input(Fore.YELLOW + f"Score for Subject {i}: "))
                if 0 <= score <= 100:
                    scores.append(score)
                    break
                else:
                    print(Fore.RED + "Score must be between 0 and 100. Please try again.")
            except ValueError:
                print(Fore.RED + "Invalid input. Please enter an integer between 0 and 100.")

    total_score = sum(scores)
    average_score = total_score / len(scores)
    students = load_data()
    new_student = {
        'id': str(len(students) + 1),
        'first_name': first_name,
        'last_name': last_name,
        'email': email,
        'scores': scores,
        'total_score': total_score,
        'average_score': average_score
    }
    students.append(new_student)
    save_data(students)
    print(Fore.GREEN + f'Student {first_name} {last_name} added successfully.')
    fetch_students()  # Display the table after adding a new student

# Function to fetch and display all students in a colorful table format
def fetch_students():
    students = load_data()
    table = []
    headers = [
        Fore.RED + 'ID',
        Fore.GREEN + 'First Name',
        Fore.YELLOW + 'Last Name',
        Fore.CYAN + 'Email'
    ] + [Fore.MAGENTA + f'Score {i}' for i in range(1, NUM_SUBJECTS + 1)] + [
        Fore.BLUE + 'Total Score',
        Fore.WHITE + 'Average Score'
    ]
    
    for student in students:
        row = [
            Fore.RED + student.get('id', 'N/A'),
            Fore.GREEN + student.get('first_name', 'N/A'),
            Fore.YELLOW + student.get('last_name', 'N/A'),
            Fore.CYAN + student.get('email', 'N/A')
        ]
        for i in range(1, NUM_SUBJECTS + 1):
            row.append(Fore.MAGENTA + str(student['scores'][i-1]))
        row.append(Fore.BLUE + str(student.get('total_score', 0)))
        row.append(Fore.WHITE + f"{student.get('average_score', 0):.2f}")
        table.append(row)
    
    print(tabulate(table, headers, tablefmt='fancy_grid'))

# Main menu function
def menu():
    initialize_csv()  # Ensure CSV file is initialized with headers
    while True:
        print("\n" + Fore.YELLOW + "1. Add Student")
        print(Fore.YELLOW + "2. View All Students")
        print(Fore.YELLOW + "3. Exit")
        choice = input(Fore.CYAN + "Enter your choice: ")
        if choice == '1':
            insert_student()
        elif choice == '2':
            fetch_students()
        elif choice == '3':
            break
        else:
            print(Fore.RED + "Invalid choice. Please select 1, 2, or 3.")

# Uncomment to enable menu-based interaction
menu()
