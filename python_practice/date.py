import calendar
from termcolor import colored

# List of holidays in (year, month, day) format for the years 2024 and 2025
holidays = [
    (2024, 1, 1),    # New Year's Day
    (2024, 1, 6),    # Epiphany
    (2024, 3, 29),   # Good Friday
    (2024, 3, 31),   # Easter Sunday
    (2024, 4, 1),    # Easter Monday
    (2024, 5, 1),    # May Day
    (2024, 5, 9),    # Ascension Day
    (2024, 5, 19),   # Whit Sunday
    (2024, 6, 21),   # Midsummer's Eve
    (2024, 6, 22),   # Midsummer Day
    (2024, 11, 2),   # All Saints' Day
    (2024, 12, 6),   # Independence Day
    (2024, 12, 24),  # Christmas Eve
    (2024, 12, 25),  # Christmas Day
    (2024, 12, 26),  # 2nd Day of Christmas
    (2025, 1, 1),    # New Year's Day
    (2025, 1, 6),    # Epiphany
    (2025, 4, 18),   # Good Friday
    (2025, 4, 20),   # Easter Sunday
    (2025, 4, 21),   # Easter Monday
    (2025, 5, 1),    # May Day
    (2025, 5, 29),   # Ascension Day
    (2025, 6, 8),    # Whit Sunday
    (2025, 6, 20),   # Midsummer's Eve
    (2025, 6, 21),   # Midsummer Day
    (2025, 11, 1),   # All Saints' Day
    (2025, 12, 6),   # Independence Day
    (2025, 12, 24),  # Christmas Eve
    (2025, 12, 25),  # Christmas Day
    (2025, 12, 26),  # 2nd Day of Christmas
]

def colorful_calendar(start_year, end_year):
    for year in range(start_year, end_year + 1):
        cal = calendar.TextCalendar()

        for quarter in range(0, 4):  # 4 quarters in a year
            # Print month names for the current quarter
            for month in range(1 + quarter * 3, 4 + quarter * 3):
                month_name = calendar.month_name[month]
                print(colored(f"{month_name} {year}".center(20), attrs=['bold']), end="\t")
            print()  # New line after printing month names

            # Print month headers for the current quarter
            for month in range(1 + quarter * 3, 4 + quarter * 3):
                print("Mo Tu We Th Fr Sa Su",  end="\t")
            print()  # New line after printing month headers

            # Get month calendars for the current quarter
            month_calendars = [cal.monthdayscalendar(year, month) for month in range(1 + quarter * 3, 4 + quarter * 3)]

            # Print weeks for the current quarter
            for week in range(6):  # Up to 6 weeks per month
                for month_index, month_days in enumerate(month_calendars):
                    if week < len(month_days):
                        for day in month_days[week]:
                            if day == 0:
                                print("  ", end=" ")
                            else:
                                day_color = 'green'
                                if (year, 1 + quarter * 3 + month_index, day) in holidays:
                                    day_color = 'blue'
                                elif calendar.weekday(year, 1 + quarter * 3 + month_index, day) >= 5:
                                    day_color = 'red'
                                print(colored(f"{day:2}", day_color), end=" ")
                    else:
                        print("                   ", end="")  # Empty space for missing weeks
                    print("\t", end="")  # Space between months
                print()  # New line after printing each week
            print()  # New line after printing each quarter
        print("\n")  # New line after printing each year

# Example usage:
start_year = 2024
end_year = 2025
colorful_calendar(start_year, end_year)
