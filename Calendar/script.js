document.addEventListener('DOMContentLoaded', function () {
    const monthNames = ["January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"];

    const prevBtn = document.querySelector('.prev');
    const nextBtn = document.querySelector('.next');
    const monthText = document.getElementById('month');
    const daysContainer = document.querySelector('.days');

    let currentMonth = new Date().getMonth(); // Current month index (0-11)
    let currentYear = new Date().getFullYear();

    updateCalendar(currentMonth, currentYear);

    prevBtn.addEventListener('click', function () {
        currentMonth--;
        if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
        }
        updateCalendar(currentMonth, currentYear);
    });

    nextBtn.addEventListener('click', function () {
        currentMonth++;
        if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
        }
        updateCalendar(currentMonth, currentYear);
    });

    function updateCalendar(month, year) {
        monthText.textContent = monthNames[month] + " " + year;
        const date_str = document.getElementById('date_str');
        date_str.textContent = new Date(year, month).toDateString(); // Just an example, you can customize this as needed

        // Clear previous days
        daysContainer.innerHTML = '';

        // Get the total number of days in the current month
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        // Get the day of the week for the first day of the month
        const firstDayOfWeek = new Date(year, month, 1).getDay();

        // Add previous month's days if necessary to complete the first week
        for (let i = 0; i < firstDayOfWeek; i++) {
            const prevMonthDay = document.createElement('div');
            prevMonthDay.classList.add('prev_date');
            prevMonthDay.textContent = new Date(year, month, -firstDayOfWeek + i + 1).getDate();
            daysContainer.appendChild(prevMonthDay);
        }

        // Add current month's days
        let dayIndex = firstDayOfWeek;
        for (let i = 1; i <= daysInMonth; i++) {
            const day = document.createElement('div');
            day.textContent = i;
            if (i === new Date().getDate() && month === new Date().getMonth() && year === new Date().getFullYear()) {
                day.classList.add('current_day');
            }
            daysContainer.appendChild(day);
            dayIndex++;
            if (dayIndex % 7 === 0) { // Start a new row for a new week
                daysContainer.appendChild(document.createElement('br'));
            }
        }
    }
});
