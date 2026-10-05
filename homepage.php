<?php
session_start();
if (!isset($_SESSION['staff_id'])) { header("Location: login.php"); exit; }
require "session_check.php";
require "config.php";

$week_start = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
$days = [];
for ($i = 0; $i < 5; $i++) {
    $days[] = date('Y-m-d', strtotime("$week_start +$i days"));
}
$prev_week = date('Y-m-d', strtotime("$week_start -7 days"));
$next_week = date('Y-m-d', strtotime("$week_start +7 days"));

// Time slots must be built BEFORE the bookings map (the map uses them)
$slots = [];
$t = strtotime("08:30");
$end = strtotime("17:00");
while ($t < $end) { // last slot is 4:30 PM, which ends at 5:00 PM
    $slots[] = date("H:i", $t);
    $t = strtotime("+30 minutes", $t);
}

$last_day = end($days);
$sql = "SELECT * FROM bookings WHERE booking_date BETWEEN ? AND ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $week_start, $last_day);
$stmt->execute();
$result = $stmt->get_result();

// Map: "date_room_slot" => ['row' => booking, 'first' => is this the booking's first slot?]
// Every slot a booking covers gets an entry, not just its start slot.
$bookings_map = [];
while ($row = $result->fetch_assoc()) {
    $b_start = substr($row['start_time'], 0, 5);
    $b_end   = substr($row['end_time'], 0, 5);

    // Collect every visible slot this booking covers
    $covered = [];
    foreach ($slots as $s) {
        if ($s >= $b_start && $s < $b_end) {
            $covered[] = $s;
        }
    }

    foreach ($covered as $idx => $s) {
        $key = $row['booking_date'] . '_' . $row['room'] . '_' . $s;
        $bookings_map[$key] = [
            'row'   => $row,
            'first' => ($idx === 0),        // only the first slot draws the merged cell
            'span'  => count($covered)      // how many rows the merged cell spans
        ];
    }
}

$rooms = ['Bilik Meeting' => 'MR', 'Bilik Seminar' => 'SR'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Week View - SALAM</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'header.php'; ?>
<div class="container" style="max-width: 1300px;">
    <h2>Weekly Booking Overview</h2>
    <div id="week-message" style="display:none;" class="success"></div>

    <div class="week-nav">
        <a href="homepage.php?week_start=<?php echo $prev_week; ?>" class="week-btn">&laquo; Previous Week</a>
        <a href="homepage.php?week_start=<?php echo date('Y-m-d', strtotime('monday this week')); ?>" class="week-btn week-btn-current">This Week</a>
        <a href="homepage.php?week_start=<?php echo $next_week; ?>" class="week-btn">Next Week &raquo;</a>
    </div>

    <p style="font-size:13px; color:#667788; margin-bottom:10px;">
        Click a start time, then click an end time in the same column to book a meeting.
        Click the same slot twice for a 30-minute meeting.
    </p>

    <div style="overflow-x:auto;">
    <table class="week-grid">
        <tr>
            <th rowspan="2">Time</th>
            <?php foreach ($days as $d): ?>
                <th colspan="2"><?php echo date('D d/m', strtotime($d)); ?></th>
            <?php endforeach; ?>
        </tr>
        <tr>
            <?php foreach ($days as $d): ?>
                <th class="sub sub-meeting">MEETING</th>
                <th class="sub sub-seminar">SEMINAR</th>
            <?php endforeach; ?>
        </tr>
        <?php foreach ($slots as $i => $slot):
            $slot_end = $slots[$i+1] ?? "17:00";
        ?>
        <tr>
            <td><?php echo date('g:i A', strtotime($slot)); ?></td>
            <?php foreach ($days as $d): ?>

                <?php foreach ($rooms as $room_full => $room_abbr):
                    $key = $d . '_' . $room_full . '_' . $slot;
                    $entry = $bookings_map[$key] ?? null;
                ?>
                    <?php if ($entry): $booking = $entry['row']; ?>
                        <?php if ($entry['first']): ?>
                            <td class="cell booked"
                                rowspan="<?php echo $entry['span']; ?>"
                                data-date="<?php echo $d; ?>"
                                data-room="<?php echo htmlspecialchars($room_full); ?>"
                                data-start="<?php echo $slot; ?>"
                                data-end="<?php echo $slot_end; ?>"
                                title="<?php echo htmlspecialchars($booking['purpose'] . ' — ' . ($booking['booked_by'] ?? $booking['staff_name'])); ?>">
                                <div class="booked-title"><?php echo htmlspecialchars($booking['purpose']); ?></div>
                                <?php if ($entry['span'] >= 2): ?>
                                    <div class="booked-staff"><?php echo htmlspecialchars($booking['booked_by'] ?? $booking['staff_name']); ?></div>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    <?php else: ?>
                        <td class="cell available"
                            data-date="<?php echo $d; ?>"
                            data-room="<?php echo htmlspecialchars($room_full); ?>"
                            data-start="<?php echo $slot; ?>"
                            data-end="<?php echo $slot_end; ?>">
                            +
                        </td>
                    <?php endif; ?>
                <?php endforeach; ?>

            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
</div>

<!-- Booking popup -->
<div id="booking-popup" class="popup-overlay" style="display:none;">
    <div class="popup-box">
        <h3>Book This Slot</h3>
        <p id="popup-details"></p>
        <label>Purpose:</label>
        <input type="text" id="popup-purpose" maxlength="255" required>
        <label>Your Name:</label>
        <input type="text" id="popup-booked-by" maxlength="100" placeholder="Who is making this booking?" required>
        <div style="margin-top:15px;">
            <button onclick="submitQuickBooking()">Confirm Booking</button>
            <button onclick="closePopup()" style="background:#888;">Cancel</button>
        </div>
    </div>
</div>

<script>
let startCell = null;
let endCell = null;
let pending = null; // {date, room, start, end, cells}

const currentStaffName = <?php echo json_encode($_SESSION['staff_name']); ?>;

const popup = document.getElementById('booking-popup');
const popupDetails = document.getElementById('popup-details');
const popupPurpose = document.getElementById('popup-purpose');

// All cells in the same day + room column, in time order
function columnCells(cell) {
    return Array.from(document.querySelectorAll('.week-grid .cell')).filter(
        c => c.dataset.date === cell.dataset.date && c.dataset.room === cell.dataset.room
    );
}

function clearSelection() {
    document.querySelectorAll('.week-grid .cell').forEach(c => {
        c.classList.remove('selected-start', 'selected-end', 'in-range');
    });
    startCell = null;
    endCell = null;
}

function startNewSelection(cell) {
    clearSelection();
    startCell = cell;
    startCell.classList.add('selected-start');
}

document.querySelectorAll('.cell.available').forEach(cell => {
    cell.addEventListener('click', function () {
        if (this.classList.contains('booked')) return; // cell was booked after page load

        // First click, or a new start after a finished selection
        if (!startCell || endCell) {
            startNewSelection(this);
            return;
        }

        // Different day or room: treat as a new start
        if (this.dataset.date !== startCell.dataset.date ||
            this.dataset.room !== startCell.dataset.room) {
            startNewSelection(this);
            return;
        }

        const cells = columnCells(startCell);
        const startIdx = cells.indexOf(startCell);
        const clickIdx = cells.indexOf(this);

        // Clicked above the start: restart from here
        if (clickIdx < startIdx) {
            startNewSelection(this);
            return;
        }

        // A booked slot anywhere in the range blocks the selection
        for (let i = startIdx; i <= clickIdx; i++) {
            if (cells[i].classList.contains('booked')) {
                alert('Your selection overlaps an existing booking. Please choose a different range.');
                clearSelection();
                return;
            }
        }

        endCell = this;
        endCell.classList.add('selected-end');
        for (let i = startIdx + 1; i < clickIdx; i++) {
            cells[i].classList.add('in-range');
        }

        pending = {
            date:  startCell.dataset.date,
            room:  startCell.dataset.room,
            start: startCell.dataset.start,
            end:   endCell.dataset.end,
            cells: cells.slice(startIdx, clickIdx + 1)
        };

        popupDetails.textContent =
            `${pending.room} on ${pending.date}, ${pending.start} - ${pending.end}`;
        popupPurpose.value = '';
        document.getElementById('popup-booked-by').value = '';
        popup.style.display = 'flex';
        popupPurpose.focus();
    });
});

function closePopup() {
    popup.style.display = 'none';
    pending = null;
    clearSelection();
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closePopup();
});
popupPurpose.addEventListener('keydown', e => {
    if (e.key === 'Enter') submitQuickBooking();
});

async function submitQuickBooking() {
    const purpose = popupPurpose.value.trim();
    const bookedBy = document.getElementById('popup-booked-by').value.trim();

    if (!purpose) { alert('Please enter a purpose.'); return; }
    if (!bookedBy) { alert('Please enter your name.'); return; }
    if (!pending) return;

    const formData = new FormData();
    formData.append('purpose', purpose);
    formData.append('booked_by', bookedBy);
    formData.append('booking_date', pending.date);
    formData.append('start_time', pending.start);
    formData.append('end_time', pending.end);
    formData.append('room', pending.room);

    const response = await fetch('book_ajax.php', { method: 'POST', body: formData });
    const data = await response.json();

    const msg = document.getElementById('week-message');
    msg.textContent = data.message;
    msg.className = data.success ? 'success' : 'error';
    msg.style.display = 'block';

    if (data.success) {
        const first = pending.cells[0];

        pending.cells.forEach((c, idx) => {
            c.classList.remove('available', 'selected-start', 'selected-end', 'in-range');
            c.classList.add('booked');
            c.title = purpose + ' — ' + currentStaffName;
            if (idx > 0) c.remove(); // the merged first cell replaces the rest
        });

        first.rowSpan = pending.cells.length;
        first.textContent = '';

        const title = document.createElement('div');
        title.className = 'booked-title';
        title.textContent = purpose;
        first.appendChild(title);

        if (pending.cells.length >= 2) {
            const staff = document.createElement('div');
            staff.className = 'booked-staff';
            staff.textContent = currentStaffName;
            first.appendChild(staff);
        }
    }
    closePopup();
}
</script>

</body>
</html>