import pandas as pd
from datetime import datetime
from colorama import init, Fore, Style
from tabulate import tabulate
import os

# Initialize colorama
init()

# Function to get user input
def get_user_data():
    name = input("Enter your name: ")
    age = input("Enter your age: ")
    email = input("Enter your email: ")
    phone = input("Enter your phone number: ")  # Prompt for phone number input
    
    # Get current date, time, and day name with new formats
    current_date = datetime.now().strftime("%d.%m.%Y")  # DD.MM.YYYY format
    current_time = datetime.now().strftime("%I:%M:%S %p")  # 12-hour time format with AM/PM
    day_name = datetime.now().strftime("%A")  # Full name of the day
    
    return {"Name": name, "Age": age, "Email": email, "Phone": phone, "Date": current_date, "Time": current_time, "Day": day_name}

# Function to create a colorized DataFrame for display purposes
def create_colorized_df(df):
    colorized_data = []
    colors = [Fore.GREEN, Fore.YELLOW, Fore.RED, Fore.MAGENTA, Fore.BLUE, Fore.CYAN, Fore.GREEN]
    for index, row in df.iterrows():
        colorized_row = {
            'Name': colors[0] + str(row['Name']) + Style.RESET_ALL,
            'Age': colors[1] + str(row['Age']) + Style.RESET_ALL,
            'Email': colors[2] + str(row['Email']) + Style.RESET_ALL,
            'Phone': colors[3] + str(row['Phone']) + Style.RESET_ALL,
            'Date': colors[4] + str(row['Date']) + Style.RESET_ALL,
            'Time': colors[5] + str(row['Time']) + Style.RESET_ALL,
            'Day': colors[6] + str(row['Day']) + Style.RESET_ALL
        }
        colorized_data.append(colorized_row)
    colorized_df = pd.DataFrame(colorized_data)
    return colorized_df

# Function to get colorized headers
def get_colorized_headers(df):
    colors = [Fore.GREEN, Fore.YELLOW, Fore.RED, Fore.MAGENTA, Fore.BLUE, Fore.CYAN, Fore.GREEN]
    headers = df.columns.tolist()
    return [color + header + Style.RESET_ALL for color, header in zip(colors, headers)]

# Function to display the data
def display_data(file_path):
    if not os.path.isfile(file_path):
        print(Fore.YELLOW + "\nNo data found. The file does not exist." + Style.RESET_ALL)
        return

    try:
        df = pd.read_csv(file_path, dtype=str)  # Read all columns as strings
        if df.empty:
            print(Fore.YELLOW + "\n\nNo data found." + Style.RESET_ALL)
        else:
            colorized_df = create_colorized_df(df)  # Create a colorized DataFrame for display
            colorized_headers = get_colorized_headers(df)  # Get colorized headers
            print(Fore.CYAN + "\nUser Data:\n" + Style.RESET_ALL)
            print(tabulate(colorized_df, headers=colorized_headers, tablefmt='pretty', showindex=False))
    except pd.errors.EmptyDataError:
        print(Fore.YELLOW + "\nNo data found. The file is empty." + Style.RESET_ALL)
    except Exception as e:
        print(Fore.RED + f"\nAn error occurred: {e}" + Style.RESET_ALL)

# Main function
def main():
    # File path for storing data
    file_path = "user_data.csv"
    
    # Check if the file exists and is not empty
    try:
        if os.path.isfile(file_path):
            df = pd.read_csv(file_path, dtype=str)  # Read all columns as strings
            if df.empty:
                raise pd.errors.EmptyDataError("File is empty")
        else:
            df = pd.DataFrame(columns=["Name", "Age", "Email", "Phone", "Date", "Time", "Day"])
    except (FileNotFoundError, pd.errors.EmptyDataError):
        df = pd.DataFrame(columns=["Name", "Age", "Email", "Phone", "Date", "Time", "Day"])
    
    # Get data from the user
    user_data = get_user_data()
    
    # Append the new data using concat
    df = pd.concat([df, pd.DataFrame([user_data])], ignore_index=True)
    
    # Save the data to the CSV file
    df.to_csv(file_path, index=False)
    
    print(Fore.CYAN + f"\nData saved successfully to {file_path}" + Style.RESET_ALL)
    
    # Display the data
    display_data(file_path)

if __name__ == "__main__":
    main()