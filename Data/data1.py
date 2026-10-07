import os
import pandas as pd
from datetime import datetime
from colorama import init, Fore, Style
from tabulate import tabulate

# Initialize colorama
init()

# Function to get user input for transactions
def get_transaction_data():
    transaction_type = input("Enter transaction type (receive/buy/sale): ")
    item = input("Enter item name: ")
    quantity = int(input("Enter quantity: "))
    price = input("Enter price (leave blank if not applicable): ")
    
    # Keep the euro symbol in the price and convert the numeric part to float
    if price:
        price_numeric = ''.join(filter(lambda x: x.isdigit() or x == '.', price))  # Extract numeric part
        price = f"{price_numeric} €"
    else:
        price = None
    
    # Get current date and time
    current_datetime = datetime.now()
    current_date = current_datetime.strftime("%Y-%m-%d")
    current_time = current_datetime.strftime("%H:%M:%S")
    
    return {"Item": item, "Type": transaction_type, "Quantity": quantity, "Price": price, "Date": current_date, "Time": current_time}

# Function to create a colorized DataFrame for display purposes
def create_colorized_df(df):
    colorized_data = []
    for index, row in df.iterrows():
        colorized_row = {
            'Item': Fore.YELLOW + str(row['Item']) + Style.RESET_ALL,
            'Type': Fore.GREEN + str(row['Type']) + Style.RESET_ALL,
            'Quantity': Fore.RED + str(row['Quantity']) + Style.RESET_ALL,
            'Price': Fore.MAGENTA + (str(row['Price']) if row['Price'] is not None else 'N/A') + Style.RESET_ALL,
            'Date': Fore.BLUE + str(row['Date']) + Style.RESET_ALL,
            'Time': Fore.CYAN + str(row['Time']) + Style.RESET_ALL
        }
        colorized_data.append(colorized_row)
    colorized_df = pd.DataFrame(colorized_data)
    return colorized_df

# Function to get colorized headers
def get_colorized_headers(df):
    headers = df.columns.tolist()
    colorized_headers = [
        Fore.YELLOW + headers[0] + Style.RESET_ALL,
        Fore.GREEN + headers[1] + Style.RESET_ALL,
        Fore.RED + headers[2] + Style.RESET_ALL,
        Fore.MAGENTA + headers[3] + Style.RESET_ALL,
        Fore.BLUE + headers[4] + Style.RESET_ALL,
        Fore.CYAN + headers[5] + Style.RESET_ALL
    ]
    return colorized_headers

# Function to display the data
def display_data(file_path):
    try:
        # Check if the file exists
        if not os.path.isfile(file_path):
            print(Fore.YELLOW + "\nNo data found. The file does not exist." + Style.RESET_ALL)
            return

        # Load the data from the CSV file
        df = pd.read_csv(file_path, dtype=str)  # Ensure all columns are read as strings

        # Check if the DataFrame is empty
        if df.empty:
            print(Fore.YELLOW + "\nNo data found. The file is empty." + Style.RESET_ALL)
        else:
            colorized_df = create_colorized_df(df)  # Create a colorized DataFrame for display
            colorized_headers = get_colorized_headers(df)  # Get colorized headers
            print(Fore.CYAN + "\nTransaction Data:\n" + Style.RESET_ALL)
            print(tabulate(colorized_df, headers=colorized_headers, tablefmt='pretty', showindex=False))
    except FileNotFoundError:
        print(Fore.YELLOW + "\nNo data found. The file does not exist." + Style.RESET_ALL)
    except pd.errors.EmptyDataError:
        print(Fore.YELLOW + "\nNo data found. The file is empty." + Style.RESET_ALL)

# Main function
def main():
    # File path for storing data
    file_path = "transactions.csv"
    
    # Check if the file exists, and create an empty DataFrame if it does not
    if os.path.isfile(file_path):
        try:
            # Read CSV and reorder columns
            df = pd.read_csv(file_path, dtype=str)  # Ensure all columns are read as strings
            
            # Reorder columns to Item, Type, Quantity, Price, Date, Time
            df = df[['Item', 'Type', 'Quantity', 'Price', 'Date', 'Time']]
        except pd.errors.EmptyDataError:
            df = pd.DataFrame(columns=["Item", "Type", "Quantity", "Price", "Date", "Time"])
    else:
        df = pd.DataFrame(columns=["Item", "Type", "Quantity", "Price", "Date", "Time"])
    
    # Get transaction data from the user
    transaction_data = get_transaction_data()
    
    # Append the new data using concat
    df = pd.concat([df, pd.DataFrame([transaction_data])], ignore_index=True)
    
    # Save the data to the CSV file
    df.to_csv(file_path, index=False)
    
    print(Fore.CYAN + f"\nData saved successfully to {file_path}" + Style.RESET_ALL)
    
    # Display the data
    display_data(file_path)

if __name__ == "__main__":
    main()