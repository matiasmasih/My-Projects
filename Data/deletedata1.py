import pandas as pd
from datetime import datetime
from colorama import init, Fore, Style
from tabulate import tabulate  # Ensure this import is present

# Initialize colorama
init()

# Function to get user input for the date and time to delete transactions
def get_deletion_data():
    date = input("Enter the date of the transaction to delete (YYYY-MM-DD): ")
    time = input("Enter the time of the transaction to delete (HH:MM:SS): ")
    return date, time

# Function to delete transactions from the CSV file based on date and time
def delete_transactions(file_path, date, time):
    try:
        # Load the data from the CSV file
        df = pd.read_csv(file_path, dtype=str)  # Ensure all columns are read as strings
        
        # Check if the DataFrame is empty
        if df.empty:
            print(Fore.YELLOW + "No data found. The file is empty." + Style.RESET_ALL)
            return
        
        # Filter out the transactions that do not match the date and time
        df_before = df.copy()
        df = df[(df['Date'] != date) | (df['Time'] != time)]

        # Check if there are any transactions to delete
        if df_before.shape[0] == df.shape[0]:
            print(Fore.YELLOW + "No transactions found for the specified date and time." + Style.RESET_ALL)
            return
        
        # Save the filtered data back to the CSV file
        df.to_csv(file_path, index=False)
        
        # Provide feedback to the user
        print(Fore.CYAN + f"\nTransactions on {date} at {time} have been deleted successfully." + Style.RESET_ALL)
        
    except FileNotFoundError:
        print(Fore.YELLOW + "No data found. The file does not exist." + Style.RESET_ALL)

# Function to display the data
def display_data(file_path):
    try:
        df = pd.read_csv(file_path, dtype=str)  # Ensure all columns are read as strings
        if df.empty:
            print(Fore.YELLOW + "\n\nNo data found." + Style.RESET_ALL)
        else:
            colorized_data = []
            for index, row in df.iterrows():
                colorized_row = {
                    'Type': Fore.GREEN + str(row['Type']) + Style.RESET_ALL,
                    'Item': Fore.YELLOW + str(row['Item']) + Style.RESET_ALL,
                    'Quantity': Fore.RED + str(row['Quantity']) + Style.RESET_ALL,
                    'Price': Fore.MAGENTA + (str(row['Price']) if row['Price'] is not None else 'N/A') + Style.RESET_ALL,
                    'Date': Fore.BLUE + str(row['Date']) + Style.RESET_ALL,
                    'Time': Fore.CYAN + str(row['Time']) + Style.RESET_ALL
                }
                colorized_data.append(colorized_row)
            colorized_df = pd.DataFrame(colorized_data)
            colorized_headers = [
                Fore.GREEN + 'Type' + Style.RESET_ALL,
                Fore.YELLOW + 'Item' + Style.RESET_ALL,
                Fore.RED + 'Quantity' + Style.RESET_ALL,
                Fore.MAGENTA + 'Price' + Style.RESET_ALL,
                Fore.BLUE + 'Date' + Style.RESET_ALL,
                Fore.CYAN + 'Time' + Style.RESET_ALL
            ]
            print(Fore.CYAN + "\nTransaction Data:\n" + Style.RESET_ALL)
            print(tabulate(colorized_df, headers=colorized_headers, tablefmt='pretty', showindex=False))
    except FileNotFoundError:
        print(Fore.YELLOW + "\nNo data found. The file does not exist." + Style.RESET_ALL)

# Main function
def main():
    # File path for storing data
    file_path = "transactions.csv"
    
    # Get the date and time from the user
    date, time = get_deletion_data()
    
    # Delete transactions matching the specified date and time
    delete_transactions(file_path, date, time)
    
    # Display the updated data
    display_data(file_path)

if __name__ == "__main__":
    main()