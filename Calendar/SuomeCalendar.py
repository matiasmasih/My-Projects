import calendar
from datetime import datetime
import tkinter as tk
from tkinter import ttk

# List of holidays in (year, month, day) format for the years 2024, 2025, and 2026
holidays = [
    (2024, 1, 1),    # Uudenvuodenpäivä (New Year's Day)
    (2024, 1, 6),    # Loppiainen (Epiphany)
    (2024, 3, 29),   # Pitkäperjantai (Good Friday)
    (2024, 3, 31),   # Pääsiäispäivä (Easter Sunday)
    (2024, 4, 1),    # 2. pääsiäispäivä (Easter Monday)
    (2024, 5, 1),    # Vappu (May Day)
    (2024, 5, 9),    # Helatorstai (Ascension Day)
    (2024, 5, 19),   # Helluntaipäivä (Whit Sunday)
    (2024, 6, 21),   # Juhannusaatto (Midsummer's Eve)
    (2024, 6, 22),   # Juhannuspäivä (Midsummer Day)
    (2024, 11, 2),   # Pyhäinpäivä (All Saints' Day)
    (2024, 12, 6),   # Itsenäisyyspäivä (Independence Day)
    (2024, 12, 24),  # Jouluaatto (Christmas Eve)
    (2024, 12, 25),  # Joulupäivä (Christmas Day)
    (2024, 12, 26),  # Tapaninpäivä (2nd Day of Christmas)
    (2025, 1, 1),    # Uudenvuodenpäivä (New Year's Day)
    (2025, 1, 6),    # Loppiainen (Epiphany)
    (2025, 4, 18),   # Pitkäperjantai (Good Friday)
    (2025, 4, 20),   # Pääsiäispäivä (Easter Sunday)
    (2025, 4, 21),   # 2. pääsiäispäivä (Easter Monday)
    (2025, 5, 1),    # Vappu (May Day)
    (2025, 5, 29),   # Helatorstai (Ascension Day)
    (2025, 6, 8),    # Helluntaipäivä (Whit Sunday)
    (2025, 6, 20),   # Juhannusaatto (Midsummer's Eve)
    (2025, 6, 21),   # Juhannuspäivä (Midsummer Day)
    (2025, 11, 1),   # Pyhäinpäivä (All Saints' Day)
    (2025, 12, 6),   # Itsenäisyyspäivä (Independence Day)
    (2025, 12, 24),  # Jouluaatto (Christmas Eve)
    (2025, 12, 25),  # Joulupäivä (Christmas Day)
    (2025, 12, 26),  # Tapaninpäivä (2nd Day of Christmas)
    (2026, 1, 1),    # Uudenvuodenpäivä (New Year's Day)
    (2026, 1, 6),    # Loppiainen (Epiphany)
    (2026, 4, 3),    # Pitkäperjantai (Good Friday)
    (2026, 4, 5),    # Pääsiäispäivä (Easter Sunday)
    (2026, 4, 6),    # 2. pääsiäispäivä (Easter Monday)
    (2026, 5, 1),    # Vappu (May Day)
    (2026, 5, 14),   # Helatorstai (Ascension Day)
    (2026, 5, 24),   # Helluntaipäivä (Whit Sunday)
    (2026, 6, 19),   # Juhannusaatto (Midsummer's Eve)
    (2026, 6, 20),   # Juhannuspäivä (Midsummer Day)
    (2026, 10, 31),  # Pyhäinpäivä (All Saints' Day)
    (2026, 12, 6),   # Itsenäisyyspäivä (Independence Day)
    (2026, 12, 24),  # Jouluaatto (Christmas Eve)
    (2026, 12, 25),  # Joulupäivä (Christmas Day)
    (2026, 12, 26),  # Tapaninpäivä (2nd Day of Christmas)
]

# Colors
HOLIDAY_COLOR = 'lime'
WEEKEND_COLOR = 'red'
TODAY_COLOR = 'cyan'
DEFAULT_COLOR = 'pink'
MAIN_BG_COLOR = 'CadetBlue1'  # Set the desired background color here

# Finnish month names and day names
finnish_month_names = [
    "", "Tammikuu", "Helmikuu", "Maaliskuu", "Huhtikuu", "Toukokuu", "Kesäkuu",
    "Heinäkuu", "Elokuu", "Syyskuu", "Lokakuu", "Marraskuu", "Joulukuu"
]
finnish_day_names = ["Ma", "Ti", "Ke", "To", "Pe", "La", "Su"]

def get_day_color(year, month, day):
    today = datetime.today()
    if (year, month, day) == (today.year, today.month, today.day):
        return TODAY_COLOR
    if (year, month, day) in holidays:
        return HOLIDAY_COLOR
    if calendar.weekday(year, month, day) >= 5:  # Saturday or Sunday
        return WEEKEND_COLOR
    return DEFAULT_COLOR

class CalendarApp(tk.Tk):
    def __init__(self, start_year, end_year, months_per_row=6):
        super().__init__()
        self.title("Värikäs Kalenteri")  # "Colorful Calendar" in Finnish
        self.configure(bg=MAIN_BG_COLOR)  # Set main background color for the root window
        self.start_year = start_year
        self.end_year = end_year
        self.months_per_row = months_per_row
        self.create_widgets()
    
    def create_widgets(self):
        container = ttk.Frame(self)
        container.pack(fill=tk.BOTH, expand=True)

        # Create a canvas and scrollbar to handle scrolling of the calendar
        canvas = tk.Canvas(container, bg=MAIN_BG_COLOR)  # Set background color for the canvas
        scrollbar = ttk.Scrollbar(container, orient="vertical", command=canvas.yview)
        scrollable_frame = ttk.Frame(canvas, style="TFrame")

        # Set background color for the scrollable frame
        scrollable_frame.configure(style='TFrame')
        style = ttk.Style()
        style.configure('TFrame', background=MAIN_BG_COLOR)

        scrollable_frame.bind(
            "<Configure>",
            lambda e: canvas.configure(scrollregion=canvas.bbox("all"))
        )
        
        canvas.create_window((0, 0), window=scrollable_frame, anchor="nw")
        canvas.pack(side="left", fill="both", expand=True)
        scrollbar.pack(side="right", fill="y")
        canvas.configure(yscrollcommand=scrollbar.set)
        
        for year in range(self.start_year, self.end_year + 1):
            self.create_year_view(scrollable_frame, year)
    
    def create_year_view(self, container, year):
        ttk.Label(container, text=str(year), font=("Helvetica", 16, "bold"), background=MAIN_BG_COLOR).pack(pady=10)
        
        # Calculate the number of rows needed for the year
        months = list(range(1, 13))
        for row_start in range(0, len(months), self.months_per_row):
            row_frame = ttk.Frame(container, style="TFrame")
            row_frame.pack(fill=tk.X, pady=5)
            
            # Configure grid columns for this row
            for col in range(self.months_per_row):
                row_frame.grid_columnconfigure(col, weight=1)
            
            self.create_months_in_row(row_frame, year, months[row_start:row_start + self.months_per_row])
    
    def create_months_in_row(self, frame, year, months):
        for index, month in enumerate(months):
            month_frame = ttk.Frame(frame, borderwidth=1, relief="solid", style="TFrame")
            month_frame.grid(row=0, column=index, padx=5, pady=5, sticky="nsew")
            self.create_month_view(month_frame, year, month)
    
    def create_month_view(self, frame, year, month):
        cal = calendar.TextCalendar(firstweekday=0)
        
        # Display the month name in Finnish
        header_frame = ttk.Frame(frame, style="TFrame")
        header_frame.pack()

        ttk.Label(header_frame, text=finnish_month_names[month], font=("Helvetica", 14, "bold"), background=MAIN_BG_COLOR).pack()
        
        # Display the day names in Finnish
        days_frame = ttk.Frame(frame, style="TFrame")
        days_frame.pack()
        for day_name in finnish_day_names:
            tk.Label(days_frame, text=day_name, font=("Courier New", 12, "bold"), bg="lightgreen", width=2, height=1, relief="solid").grid(row=0, column=finnish_day_names.index(day_name), padx=1, pady=1)
        
        # Generate month days for grid layout
        days = cal.monthdayscalendar(year, month)
        
        for week_index, week in enumerate(days):
            week_frame = ttk.Frame(frame, style="TFrame")
            week_frame.pack()
            for day_index, day in enumerate(week):
                if day == 0:
                    # Empty cell for days not in this month
                    tk.Label(week_frame, text='  ', bg='lightblue', width=3, height=2, relief="solid").grid(row=week_index, column=day_index, padx=1, pady=1)
                else:
                    # Colored cell for days in this month
                    color = get_day_color(year, month, day)
                    text = f"{day:2}"
                    tk.Label(week_frame, text=text, bg=color, width=3, height=2, relief="solid").grid(row=week_index, column=day_index, padx=1, pady=1)

if __name__ == "__main__":
    app = CalendarApp(start_year=2024, end_year=2026, months_per_row=6)
    app.mainloop()
