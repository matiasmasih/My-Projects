<?php
session_start();
include 'config.php';

// Allow ONLY Patient (6)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 6) {
    header("Location: login.php");
    exit;
}

// Get patient info
$patient = null;
$patient_id = 0;
try {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE email = ?");
    $stmt->execute([$_SESSION['email'] ?? '']);
    $patient = $stmt->fetch();
    $patient_id = $patient ? $patient['id'] : 0;
} catch (Exception $e) {}

$success_message = '';
$error_message = '';

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id = intval($_POST['doctor_id'] ?? 0);
    $scheduled_date = $_POST['scheduled_date'] ?? '';
    $scheduled_time = $_POST['scheduled_time'] ?? '';
    $duration = intval($_POST['duration'] ?? 30);
    $reason = trim($_POST['reason'] ?? '');

    if (empty($doctor_id) || empty($scheduled_date) || empty($scheduled_time)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        $scheduled_at = $scheduled_date . ' ' . $scheduled_time . ':00';
        try {
            $stmt = $pdo->prepare("
                INSERT INTO appointments (patient_id, doctor_id, scheduled_at, duration_minutes, status, reason, created_by)
                VALUES (?, ?, ?, ?, 'scheduled', ?, ?)
            ");
            $stmt->execute([$patient_id, $doctor_id, $scheduled_at, $duration, $reason, $_SESSION['user_id']]);
            $success_message = '✅ Appointment booked successfully!';
        } catch (Exception $e) {
            $error_message = 'Failed to book appointment: ' . $e->getMessage();
        }
    }
}

// Get doctors
$doctors = [];
try {
    $stmt = $pdo->query("
        SELECT d.id, u.first_name, u.last_name, d.consultation_fee
        FROM doctors d
        JOIN users u ON d.user_id = u.id
        WHERE d.status = 'active'
        ORDER BY u.first_name ASC
    ");
    $doctors = $stmt->fetchAll();
} catch (Exception $e) {}

$page_title = 'Book Appointment';
include 'patient_header.php';
?>

<!-- Welcome Banner -->
<div class="welcome-banner">
    <h2>Book Appointment 📅</h2>
    <p>Schedule a new appointment with a doctor</p>
    <div class="clock">
        <div class="time" id="clock">--:--:--</div>
        <div class="date" id="date">Loading...</div>
    </div>
</div>

<?php if (!empty($success_message)): ?>
<div class="alert-success"><?php echo $success_message; ?></div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
<div class="alert-error"><?php echo $error_message; ?></div>
<?php endif; ?>

<!-- Booking Form -->
<div class="table-card" style="overflow: visible;">
    <h5><i class="bi bi-calendar-plus"></i> New Appointment</h5>

    <?php if (count($doctors) > 0): ?>
    <form method="POST" action="" class="booking-form">

        <!-- Doctor -->
        <div class="bf-group">
            <label class="bf-label"><i class="bi bi-person-badge"></i> Select Doctor <span class="req">*</span></label>
            <div class="bf-select-wrap">
                <select name="doctor_id" required class="bf-select">
                    <option value="">-- Choose a doctor --</option>
                    <?php foreach ($doctors as $doc): ?>
                        <option value="<?php echo $doc['id']; ?>">
                            Dr. <?php echo htmlspecialchars($doc['first_name'] . ' ' . $doc['last_name']); ?>
                            (€<?php echo number_format($doc['consultation_fee'] ?? 0, 2); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <i class="bi bi-chevron-down bf-icon"></i>
            </div>
        </div>

        <!-- Date + Time -->
        <div class="bf-row">
            <!-- Date -->
            <div class="bf-group">
                <label class="bf-label"><i class="bi bi-calendar-event"></i> Date <span class="req">*</span></label>
                <div class="bf-input-wrap">
                    <input type="text" name="scheduled_date" id="scheduledDate" required
                           placeholder="Select a date" class="bf-input" autocomplete="off">
                    <i class="bi bi-calendar3 bf-icon"></i>
                </div>
            </div>

            <!-- Time -->
            <div class="bf-group">
                <label class="bf-label"><i class="bi bi-clock"></i> Time <span class="req">*</span></label>
                <div class="tp-wrap" id="tpWrap">
                    <div class="tp-trigger" onclick="tpToggle(event)">
                        <span class="tp-text" id="tpText"><i class="bi bi-clock"></i> Select a time</span>
                        <i class="bi bi-chevron-down tp-icon"></i>
                    </div>
                    <div class="tp-dropdown" id="tpDropdown">
                        <div class="tp-section">
                            <div class="tp-section-title">🌅 Morning</div>
                            <div class="tp-grid">
                                <?php foreach (['08:00','08:30','09:00','09:30','10:00','10:30','11:00','11:30'] as $t): ?>
                                    <div class="tp-slot" data-value="<?php echo $t; ?>" data-display="<?php echo date('h:i A', strtotime($t)); ?>"><?php echo date('h:i A', strtotime($t)); ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="tp-section">
                            <div class="tp-section-title">☀️ Afternoon</div>
                            <div class="tp-grid">
                                <?php foreach (['12:00','12:30','13:00','13:30','14:00','14:30','15:00','15:30'] as $t): ?>
                                    <div class="tp-slot" data-value="<?php echo $t; ?>" data-display="<?php echo date('h:i A', strtotime($t)); ?>"><?php echo date('h:i A', strtotime($t)); ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="tp-section">
                            <div class="tp-section-title">🌆 Evening</div>
                            <div class="tp-grid">
                                <?php foreach (['16:00','16:30','17:00','17:30','18:00'] as $t): ?>
                                    <div class="tp-slot" data-value="<?php echo $t; ?>" data-display="<?php echo date('h:i A', strtotime($t)); ?>"><?php echo date('h:i A', strtotime($t)); ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="scheduled_time" id="scheduledTime" required>
                </div>
            </div>
        </div>

        <!-- Duration -->
        <div class="bf-group">
            <label class="bf-label"><i class="bi bi-hourglass-split"></i> Duration</label>
            <div class="bf-select-wrap">
                <select name="duration" class="bf-select">
                    <option value="15">15 minutes</option>
                    <option value="30" selected>30 minutes</option>
                    <option value="45">45 minutes</option>
                    <option value="60">60 minutes</option>
                </select>
                <i class="bi bi-chevron-down bf-icon"></i>
            </div>
        </div>

        <!-- Reason -->
        <div class="bf-group">
            <label class="bf-label"><i class="bi bi-chat-left-text"></i> Reason for Visit</label>
            <textarea name="reason" rows="4" placeholder="Describe your symptoms or reason for the visit..." class="bf-textarea"></textarea>
        </div>

        <!-- Submit -->
        <div class="bf-group">
            <button type="submit" class="bf-submit">
                <i class="bi bi-calendar-plus"></i> Book Appointment
            </button>
        </div>

    </form>
    <?php else: ?>
        <p style="color: var(--text-muted); text-align: center; padding: 30px;">No doctors available</p>
    <?php endif; ?>
</div>

<style>
/* ============================================
   ALERTS
============================================ */
.alert-success {
    background: rgba(34, 197, 94, 0.15);
    color: #16a34a;
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-weight: 600;
}
.alert-error {
    background: rgba(239, 68, 68, 0.15);
    color: #dc2626;
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    font-weight: 600;
}

/* ============================================
   BOOKING FORM
============================================ */
.booking-form {
    display: grid;
    gap: 22px;
}

.bf-group {
    display: flex;
    flex-direction: column;
}

.bf-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.bf-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 10px;
    font-size: 0.95rem;
}

.bf-label i {
    color: var(--primary);
    font-size: 1.05rem;
}

.req {
    color: #dc2626;
}

.bf-input-wrap,
.bf-select-wrap {
    position: relative;
    width: 100%;
}

.bf-input,
.bf-select {
    width: 100%;
    padding: 14px 45px 14px 18px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-family: 'Inter', sans-serif;
    font-size: 1rem;
    background: var(--card-bg);
    color: var(--text);
    transition: all 0.3s;
    cursor: pointer;
}

.bf-input::placeholder {
    color: var(--text-muted);
}

.bf-input:hover,
.bf-select:hover {
    border-color: var(--primary-light);
}

.bf-input:focus,
.bf-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
}

.bf-select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-image: none;
}

.bf-select option {
    background: var(--card-bg);
    color: var(--text);
    padding: 10px;
}

.bf-icon {
    position: absolute;
    right: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--primary);
    font-size: 1.1rem;
    pointer-events: none;
}

.bf-textarea {
    width: 100%;
    padding: 14px 18px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-family: 'Inter', sans-serif;
    font-size: 1rem;
    background: var(--card-bg);
    color: var(--text);
    transition: all 0.3s;
    resize: vertical;
    min-height: 110px;
}

.bf-textarea::placeholder {
    color: var(--text-muted);
}

.bf-textarea:hover {
    border-color: var(--primary-light);
}

.bf-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
}

.bf-submit {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border: none;
    padding: 16px 32px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    box-shadow: 0 6px 20px rgba(14, 165, 233, 0.3);
    font-family: 'Inter', sans-serif;
}

.bf-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(14, 165, 233, 0.4);
}

/* ============================================
   CUSTOM TIME PICKER
============================================ */
.tp-wrap {
    position: relative;
    width: 100%;
}

.tp-trigger {
    width: 100%;
    padding: 14px 45px 14px 18px;
    border: 2px solid var(--border);
    border-radius: 12px;
    font-family: 'Inter', sans-serif;
    font-size: 1rem;
    background: var(--card-bg);
    color: var(--text);
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.tp-trigger:hover {
    border-color: var(--primary-light);
}

.tp-trigger.open {
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1);
}

.tp-text {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--text-muted);
}

.tp-text.has-value {
    color: var(--text);
    font-weight: 600;
}

.tp-icon {
    color: var(--primary);
    font-size: 1rem;
    transition: transform 0.3s;
}

.tp-trigger.open .tp-icon {
    transform: rotate(180deg);
}

.tp-dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    background: var(--card-bg);
    border: 2px solid var(--primary);
    border-radius: 12px;
    padding: 15px;
    max-height: 380px;
    overflow-y: auto;
    z-index: 9999;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.tp-dropdown.open {
    display: block;
    animation: tpFade 0.25s ease;
}

@keyframes tpFade {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

.tp-dropdown::-webkit-scrollbar {
    width: 6px;
}

.tp-dropdown::-webkit-scrollbar-thumb {
    background: var(--primary-light);
    border-radius: 3px;
}

.tp-section {
    margin-bottom: 15px;
}

.tp-section:last-child {
    margin-bottom: 0;
}

.tp-section-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 10px;
    padding-bottom: 6px;
    border-bottom: 1px solid var(--border);
}

.tp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(85px, 1fr));
    gap: 8px;
}

.tp-slot {
    background: rgba(14, 165, 233, 0.05);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 9px 6px;
    text-align: center;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.82rem;
    color: var(--text);
    transition: all 0.2s;
    user-select: none;
}

.tp-slot:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
}

.tp-slot.selected {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4);
}

/* ============================================
   FLATPICKR MODERN THEME
============================================ */
.flatpickr-calendar {
    border-radius: 14px !important;
    box-shadow: 0 15px 50px rgba(0, 0, 0, 0.2) !important;
    border: 2px solid var(--primary) !important;
    font-family: 'Inter', sans-serif !important;
    background: var(--card-bg) !important;
    width: 320px !important;
    padding: 0 !important;
    z-index: 9999 !important;
}

.flatpickr-calendar.arrowTop:before,
.flatpickr-calendar.arrowTop:after {
    border-bottom-color: var(--primary) !important;
}

.flatpickr-months {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important;
    border-radius: 10px 10px 0 0 !important;
    padding: 12px 0 !important;
}

.flatpickr-months .flatpickr-month {
    background: transparent !important;
    color: white !important;
    height: 40px !important;
}

.flatpickr-current-month {
    color: white !important;
    font-size: 1.1rem !important;
    font-weight: 700 !important;
    padding-top: 6px !important;
}

.flatpickr-current-month .flatpickr-monthDropdown-months {
    background: transparent !important;
    color: white !important;
    font-weight: 700 !important;
    appearance: none;
    -webkit-appearance: none;
    font-size: 1.1rem !important;
    padding: 0 8px !important;
}

.flatpickr-current-month .flatpickr-monthDropdown-months option {
    background: var(--card-bg) !important;
    color: var(--text) !important;
}

.flatpickr-current-month .numInputWrapper {
    width: 70px !important;
}

.flatpickr-current-month .numInputWrapper input {
    color: white !important;
    font-weight: 700 !important;
    font-size: 1.1rem !important;
}

.flatpickr-prev-month,
.flatpickr-next-month {
    fill: white !important;
    color: white !important;
    padding: 12px !important;
}

.flatpickr-prev-month:hover svg,
.flatpickr-next-month:hover svg {
    fill: rgba(255,255,255,0.7) !important;
}

.flatpickr-weekdays {
    background: rgba(14, 165, 233, 0.08) !important;
    padding: 8px 0 !important;
}

.flatpickr-weekday {
    color: var(--primary) !important;
    font-weight: 700 !important;
    font-size: 0.8rem !important;
    text-transform: uppercase;
}

.flatpickr-days {
    padding: 10px !important;
}

.flatpickr-day {
    color: var(--text) !important;
    border-radius: 8px !important;
    font-weight: 500 !important;
    height: 38px !important;
    line-height: 38px !important;
    max-width: 38px !important;
    margin: 1px !important;
}

.flatpickr-day:hover {
    background: rgba(14, 165, 233, 0.15) !important;
    border-color: transparent !important;
}

.flatpickr-day.today {
    border-color: var(--primary) !important;
    color: var(--primary) !important;
    font-weight: 700 !important;
}

.flatpickr-day.selected,
.flatpickr-day.selected:hover {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important;
    border-color: var(--primary) !important;
    color: white !important;
    box-shadow: 0 4px 12px rgba(14, 165, 233, 0.4) !important;
}

.flatpickr-day.flatpickr-disabled,
.flatpickr-day.flatpickr-disabled:hover {
    color: var(--text-muted) !important;
    opacity: 0.4 !important;
}

/* ============================================
   RESPONSIVE
============================================ */
@media (max-width: 768px) {
    .bf-row {
        grid-template-columns: 1fr;
    }
    .flatpickr-calendar {
        width: 100% !important;
        max-width: 320px !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // ============================================
    // FLATPICKR CALENDAR
    // ============================================
    flatpickr("#scheduledDate", {
        dateFormat: "Y-m-d",
        minDate: "today",
        altInput: true,
        altFormat: "F j, Y",
        theme: "material_pink",
        animate: true,
        monthSelectorType: "static",
        disableMobile: false
    });

    // ============================================
    // CUSTOM TIME PICKER
    // ============================================
    document.querySelectorAll('.tp-slot').forEach(slot => {
        slot.addEventListener('click', function() {
            const value = this.dataset.value;
            const display = this.dataset.display;

            document.getElementById('scheduledTime').value = value;

            const text = document.getElementById('tpText');
            text.innerHTML = '<i class="bi bi-clock-fill"></i> ' + display;
            text.classList.add('has-value');

            document.querySelectorAll('.tp-slot').forEach(s => s.classList.remove('selected'));
            this.classList.add('selected');

            tpClose();
        });
    });

    // Close when clicking outside
    document.addEventListener('click', function(e) {
        const wrap = document.getElementById('tpWrap');
        if (wrap && !wrap.contains(e.target)) {
            tpClose();
        }
    });
});

function tpToggle(e) {
    if (e) e.stopPropagation();
    document.getElementById('tpDropdown').classList.toggle('open');
    document.querySelector('.tp-trigger').classList.toggle('open');
}

function tpClose() {
    document.getElementById('tpDropdown').classList.remove('open');
    document.querySelector('.tp-trigger').classList.remove('open');
}
</script>

<?php include 'patient_footer.php'; ?>
