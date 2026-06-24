import pandas as pd
from datetime import datetime
from colorama import init, Fore, Style
from tabulate import tabulate

# Initialize colorama
init()

# Function to get user input
def get_user_data():
    name = input("Enter your name: ")
    age = input("Enter your age: ")
    email = input("Enter your email: ")
    phone = input("Enter your phone number: ")  # Prompt for phone number input
    
    # Get current date, time, and day name
    current_date = datetime.now().strftime("%d.%m.%Y")
    current_time = datetime.now().strftime("%I:%M:%S %p")  # 12-hour format with AM/PM
    day_name = datetime.now().strftime("%A")  # Full name of the day
    
    return {"Name": name, "Age": age, "Email": email, "Phone": phone, "Date": current_date, "Time": current_time, "Day": day_name}

# Function to append user data to CSV file
def append_to_csv(file_path, user_data):
    df = pd.DataFrame([user_data])
    df.to_csv(file_path, mode='a', header=not pd.io.common.file_exists(file_path), index=False)

# Function to create a colorized DataFrame for display purposes
def create_colorized_df(df):
    colorized_data = []
    for index, row in df.iterrows():
        colorized_row = {
            'Name': Fore.GREEN + str(row['Name']) + Style.RESET_ALL,
            'Age': Fore.YELLOW + str(row['Age']) + Style.RESET_ALL,
            'Email': Fore.RED + str(row['Email']) + Style.RESET_ALL,
            'Phone': Fore.MAGENTA + str(row['Phone']) + Style.RESET_ALL,
            'Date': Fore.BLUE + str(row['Date']) + Style.RESET_ALL,
            'Time': Fore.CYAN + str(row['Time']) + Style.RESET_ALL,
            'Day': Fore.GREEN + str(row['Day']) + Style.RESET_ALL
        }
        colorized_data.append(colorized_row)
    colorized_df = pd.DataFrame(colorized_data)
    return colorized_df

# Function to get colorized headers
def get_colorized_headers(df):
    headers = df.columns.tolist()
    colorized_headers = [
        Fore.GREEN + headers[0] + Style.RESET_ALL,
        Fore.YELLOW + headers[1] + Style.RESET_ALL,
        Fore.RED + headers[2] + Style.RESET_ALL,
        Fore.MAGENTA + headers[3] + Style.RESET_ALL,
        Fore.BLUE + headers[4] + Style.RESET_ALL,
        Fore.CYAN + headers[5] + Style.RESET_ALL,
        Fore.GREEN + headers[6] + Style.RESET_ALL
    ]
    return colorized_headers

# Function to display the data
def display_data(file_path):
    try:
        df = pd.read_csv(file_path, dtype=str)  # Read all columns as strings
        if df.empty:
            print(Fore.YELLOW + "\n\nNo data found." + Style.RESET_ALL)
        else:
            colorized_df = create_colorized_df(df)  # Create a colorized DataFrame for display
            colorized_headers = get_colorized_headers(df)  # Get colorized headers
            print(Fore.CYAN + "\nUser Data:\n" + Style.RESET_ALL)
            print(tabulate(colorized_df, headers=colorized_headers, tablefmt='pretty', showindex=False))
    except FileNotFoundError:
        print(Fore.YELLOW + "\nNo data found. The file does not exist." + Style.RESET_ALL)

# Function to delete specific data by Date and Time
def delete_user_data(file_path):
    try:
        df = pd.read_csv(file_path, dtype=str)  # Read all columns as strings
        
        if df.empty:
            print(Fore.YELLOW + "\nNo data found." + Style.RESET_ALL)
            return
        
        # Display current data
        display_data(file_path)

        # Get user input for deletion
        date_to_delete = input("\nEnter the date of the user you want to delete (dd.mm.yyyy): ").strip()
        time_to_delete = input("Enter the time of the user you want to delete (hh:mm:ss): ").strip()
        
        # Add AM/PM distinction to the entered time to match the stored format
        time_to_delete_am = time_to_delete + " AM"
        time_to_delete_pm = time_to_delete + " PM"

        # Check if the Date and Time exists in the DataFrame
        condition_am = (df['Date'] == date_to_delete) & (df['Time'] == time_to_delete_am)
        condition_pm = (df['Date'] == date_to_delete) & (df['Time'] == time_to_delete_pm)
        
        if condition_am.any() or condition_pm.any():
            df = df[~condition_am & ~condition_pm]  # Delete rows with the specified Date and Time
            df.to_csv(file_path, index=False)  # Save the updated DataFrame back to the CSV file
            print(Fore.CYAN + f"\nEntries with Date '{date_to_delete}' and Time '{time_to_delete}' have been deleted successfully." + Style.RESET_ALL)
        else:
            print(Fore.RED + "\nNo entries found with that Date and Time." + Style.RESET_ALL)
    
    except FileNotFoundError:
        print(Fore.YELLOW + "\nNo data found. The file does not exist." + Style.RESET_ALL)

# Main function
def main():
    # File path for storing data
    file_path = "user_data.csv"
    
    # Main menu
    while True:
        print("\n1. Display user data")
        print("2. Delete specific user data by Date and Time")
        print("3. Exit")  # Removed the delete all data option
        choice = input("\nEnter your choice: ")

        if choice == '1':
            display_data(file_path)
        
        elif choice == '2':
            delete_user_data(file_path)
        
        elif choice == '3':
            break
        
        else:
            print(Fore.RED + "\nInvalid choice. Please enter a valid option." + Style.RESET_ALL)

if __name__ == "__main__":
    main()