# ANSI escape sequences for text colors and styles
class Colors:
    HEADER = '\033[95m'         # Light magenta
    OKBLUE = '\033[94m'         # Light blue
    OKCYAN = '\033[96m'         # Light cyan
    OKGREEN = '\033[92m'        # Light green
    WARNING = '\033[93m'        # Yellow
    FAIL = '\033[91m'           # Light red
    ENDC = '\033[0m'            # Reset to default
    BOLD = '\033[1m'            # Bold
    UNDERLINE = '\033[4m'       # Underline
    BGGREEN = '\033[42m'        # Background green
    BGHEADER = '\033[45m'       # Background magenta (matching HEADER color)

# Define the menu of the restaurant
menu = {
    'Pizza':  40,
    'Pasta':  50,
    'Burger': 30,
    'Salad':  50,
    'Coffee': 20,
    'Tea':    10,
    'Ice cream': 15,
} 

# Greet
print(f"{Colors.BGHEADER}**********************************{Colors.ENDC}")
print(f"{Colors.HEADER}*                                *{Colors.ENDC}")
print(f"{Colors.BOLD}{Colors.HEADER}*  Welcome to Afghan restaurant  *{Colors.ENDC}")
print(f"{Colors.HEADER}*                                *{Colors.ENDC}")
print(f"{Colors.BGHEADER}**********************************{Colors.ENDC}")
print(f"{Colors.BGGREEN}**********************{Colors.ENDC}")
print(f"{Colors.OKGREEN}*                    *{Colors.ENDC}")
print(f"{Colors.OKGREEN}*   Pizza:    40 €   *{Colors.ENDC}")
print(f"{Colors.OKGREEN}*   Pasta:    50 €   *{Colors.ENDC}")
print(f"{Colors.OKGREEN}*   Burger:   30 €   *{Colors.ENDC}")
print(f"{Colors.OKGREEN}*   Salad:    50 €   *{Colors.ENDC}")
print(f"{Colors.OKGREEN}*   Coffee:   20 €   *{Colors.ENDC}")
print(f"{Colors.OKGREEN}*   Tea:      10 €   *{Colors.ENDC}")
print(f"{Colors.OKGREEN}*   Ice cream:15 €   *{Colors.ENDC}")
print(f"{Colors.OKGREEN}*                    *{Colors.ENDC}")
print(f"{Colors.BGGREEN}**********************{Colors.ENDC}")


order_total = 0
ordering = True

while ordering:
    # Ask for an item order
    item = input(f"{Colors.OKCYAN}Enter the name of the item you want to order: {Colors.ENDC}")
    if item in menu:
        order_total += menu[item]
        print(f"{Colors.OKGREEN}Your item '{item}' has been added to your order.{Colors.ENDC}")
    else: 
        print(f"{Colors.FAIL}Ordered item '{item}' is not available!{Colors.ENDC}")

    # Ask if the user wants to order another item
    another_order = input(f"{Colors.OKCYAN}Do you want to add another item? (Yes/No) {Colors.ENDC}")
    if another_order.lower() == "no":
        ordering = False

# Total amount to pay
print(f"{Colors.HEADER}The total amount to pay is {order_total} €{Colors.ENDC}")
