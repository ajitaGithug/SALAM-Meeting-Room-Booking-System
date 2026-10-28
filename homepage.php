<?php
session_start();
if (!isset($_SESSION['staff_id'])) { header("Location: login.php"); exit; }
require "session_check.php";
require_once "config.php";

$is_admin = (($_SESSION['role'] ?? '') === 'admin');
$my_staff_id = $_SESSION['staff_id'];
$today = date('Y-m-d');
$now = date('H:i');

// Accept any valid date in the URL and snap it to the Monday of its week
$this_monday = date('Y-m-d', strtotime('monday this week'));
$req = $_GET['week_start'] ?? '';
$req_date = is_string($req) ? DateTime::createFromFormat('!Y-m-d', $req) : false;
if ($req_date && $req_date->format('Y-m-d') === $req) {
    $week_start = $req_date->modify('monday this week')->format('Y-m-d');
} else {
    $week_start = $this_monday;
}

$days = [];
for ($i = 0; $i < 5; $i++) {
    $days[] = date('Y-m-d', strtotime("$week_start +$i days"));
}
$prev_week = date('Y-m-d', strtotime("$week_start -7 days"));
$next_week = date('Y-m-d', strtotime("$week_start +7 days"));

// "27 – 31 Oct 2026", or "29 Sep – 3 Oct 2026" when the week crosses a month
$first_ts = strtotime($days[0]);
$last_ts  = strtotime($days[4]);
if (date('m', $first_ts) === date('m', $last_ts)) {
    $week_label = date('j', $first_ts) . ' – ' . date('j M Y', $last_ts);
} else {
    $week_label = date('j M', $first_ts) . ' – ' . date('j M Y', $last_ts);
}

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

// A slot has passed once its end time is over (same rule the server uses for staff)
function slot_is_past($date, $slot_end, $today, $now) {
    return $date < $today || ($date === $today && $slot_end <= $now);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Week View - SALAM</title>
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
</head>
<body>
<?php include 'header.php'; ?>
<div class="container" style="max-width: 1300px;">
    <h2>Weekly Booking Overview</h2>

    <?php if (($_GET['msg'] ?? '') === 'cancelled'): ?>
        <div class="success">Booking cancelled successfully.</div>
    <?php elseif (($_GET['msg'] ?? '') === 'updated'): ?>
        <div class="success">Booking updated successfully.</div>
    <?php elseif (($_GET['msg'] ?? '') === 'error'): ?>
        <div class="error">Something went wrong. Please try again.</div>
    <?php endif; ?>
    <div id="week-message" style="display:none;" class="success"></div>

    <div class="week-nav">
        <a href="homepage.php?week_start=<?php echo $prev_week; ?>" class="week-btn">&laquo; Previous Week</a>
        <a href="homepage.php?week_start=<?php echo $this_monday; ?>" class="week-btn week-btn-current">This Week</a>
        <a href="homepage.php?week_start=<?php echo $next_week; ?>" class="week-btn">Next Week &raquo;</a>
    </div>

    <div class="week-toolbar">
        <div class="week-range">
            <?php echo $week_label; ?>
            <?php if ($week_start === $this_monday): ?><span class="week-badge">This week</span><?php endif; ?>
        </div>
        <form method="GET" class="jump-form">
            <label for="jump-date">Jump to date</label>
            <input type="date" id="jump-date" name="week_start" value="<?php echo $week_start; ?>"
                   onchange="if (this.value) this.form.submit();">
        </form>
    </div>

    <div class="grid-legend">
        <span><i class="swatch swatch-meeting"></i>Meeting room free</span>
        <span><i class="swatch swatch-seminar"></i>Seminar room free</span>
        <span><i class="swatch swatch-booked"></i>Booked</span>
        <span><i class="swatch swatch-mine"></i>Booked by your account</span>
        <span><i class="swatch swatch-past"></i>Time has passed</span>
    </div>

    <p class="grid-hint" id="grid-hint">
        <strong>To book:</strong> click a start time, then click an end time in the same column.
        Click the same slot twice for a 30-minute meeting. Click a booked slot to see its details.
    </p>

<!-- Mobile day tabs -->
    <div class="day-tabs">
        <?php foreach ($days as $di => $d): ?>
            <button type="button" class="day-tab<?php echo $d === $today ? ' is-today' : ''; ?>" data-day="<?php echo $di; ?>">
                <?php echo $d === $today ? 'Today' : date('D d', strtotime($d)); ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div style="overflow-x:auto;">
        <table class="week-grid">
            <tr>
                <th rowspan="2">Time</th>
                <?php foreach ($days as $di => $d): ?>
                    <th colspan="2" class="day-col day-<?php echo $di; ?><?php echo $d === $today ? ' today' : ''; ?>">
                        <?php echo date('D d/m', strtotime($d)); ?>
                        <?php if ($d === $today): ?><span class="today-label">Today</span><?php endif; ?>
                    </th>
                <?php endforeach; ?>
            </tr>
            <tr>
                <?php foreach ($days as $di => $d): ?>
                    <th class="sub sub-meeting day-col day-<?php echo $di; ?>">MEETING</th>
                    <th class="sub sub-seminar day-col day-<?php echo $di; ?>">SEMINAR</th>
                <?php endforeach; ?>
            </tr>
            <?php foreach ($slots as $i => $slot):
                $slot_end = $slots[$i+1] ?? "17:00";
            ?>
            <tr>
                <td><?php echo date('g:i A', strtotime($slot)); ?></td>
                <?php foreach ($days as $di => $d): ?>

                    <?php foreach ($rooms as $room_full => $room_abbr):
                        $key = $d . '_' . $room_full . '_' . $slot;
                        $entry = $bookings_map[$key] ?? null;
                    ?>
                        <?php if ($entry): $booking = $entry['row']; ?>
                            <?php if ($entry['first']):
                                $by = $booking['booked_by'] ?? $booking['staff_name'];
                                $mine = ($booking['staff_id'] === $my_staff_id);
                                $b_start = substr($booking['start_time'], 0, 5);
                                $b_end = substr($booking['end_time'], 0, 5);
                                $b_past = slot_is_past($d, $b_end, $today, $now);
                            ?>
                                <td class="cell booked day-col day-<?php echo $di; ?><?php echo $mine ? ' mine' : ''; ?><?php echo $b_past ? ' past' : ''; ?>"
                                    rowspan="<?php echo $entry['span']; ?>"
                                    data-id="<?php echo (int)$booking['id']; ?>"
                                    data-date="<?php echo $d; ?>"
                                    data-room="<?php echo htmlspecialchars($room_full); ?>"
                                    data-start="<?php echo $b_start; ?>"
                                    data-end="<?php echo $b_end; ?>"
                                    data-purpose="<?php echo htmlspecialchars($booking['purpose']); ?>"
                                    data-by="<?php echo htmlspecialchars($by); ?>"
                                    data-mine="<?php echo $mine ? '1' : '0'; ?>"
                                    data-past="<?php echo $b_past ? '1' : '0'; ?>"
                                    title="<?php echo htmlspecialchars($booking['purpose'] . ' — ' . $by); ?>">
                                    <div class="booked-title"><?php echo htmlspecialchars($booking['purpose']); ?></div>
                                    <?php if ($entry['span'] >= 2): ?>
                                        <div class="booked-staff"><?php echo htmlspecialchars($by); ?></div>
                                        <div class="booked-time"><?php echo date('g:i', strtotime($b_start)) . '–' . date('g:i A', strtotime($b_end)); ?></div>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        <?php elseif (!$is_admin && slot_is_past($d, $slot_end, $today, $now)): ?>
                            <td class="cell past day-col day-<?php echo $di; ?>"
                                data-date="<?php echo $d; ?>"
                                data-room="<?php echo htmlspecialchars($room_full); ?>"
                                title="This time has passed"></td>
                        <?php else: ?>
                            <td class="cell available day-col day-<?php echo $di; ?><?php echo slot_is_past($d, $slot_end, $today, $now) ? ' past' : ''; ?>"
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

<!-- Booking popup -->
<div id="booking-popup" class="popup-overlay" style="display:none;">
    <div class="popup-box">
        <h3>Book This Slot</h3>
        <div id="popup-details" class="popup-summary"></div>
        <div id="popup-error" class="popup-error" style="display:none;"></div>
        <label for="popup-purpose">Purpose:</label>
        <input type="text" id="popup-purpose" maxlength="200" placeholder="e.g. Department meeting" required>
        <label for="popup-booked-by">Your Name:</label>
        <input type="text" id="popup-booked-by" maxlength="100" placeholder="Who is making this booking?" required>
        <div style="margin-top:15px;">
            <button type="button" id="popup-confirm" onclick="submitQuickBooking()">Confirm Booking</button>
            <button type="button" onclick="closePopup()" style="background:#888;">Cancel</button>
        </div>
    </div>
</div>

<!-- Booking details popup (opens when a booked slot is clicked) -->
<div id="details-popup" class="popup-overlay" style="display:none;">
    <div class="popup-box">
        <h3 id="details-purpose"></h3>
        <div id="details-summary" class="popup-summary"></div>
        <p class="details-by">Booked by <strong id="details-by"></strong></p>
        <div class="popup-actions" id="details-actions">
            <a href="#" id="details-edit" class="action-btn edit-btn">Edit</a>
            <form method="POST" action="cancel_booking.php" class="inline-form" id="details-cancel-form"
                  onsubmit="return confirm('Cancel this booking?');">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id" id="details-cancel-id" value="">
                <input type="hidden" name="from" value="home">
                <input type="hidden" name="week_start" value="<?php echo $week_start; ?>">
                <button type="submit" class="action-btn cancel-btn">Cancel Booking</button>
            </form>
        </div>
        <button type="button" onclick="closeDetails()" style="background:#888;">Close</button>
    </div>
</div>

<script>
let startCell = null;
let endCell = null;
let pending = null; // {date, room, start, end, cells}

const IS_ADMIN = <?php echo $is_admin ? 'true' : 'false'; ?>;
const WEEK_START = <?php echo json_encode($week_start); ?>;
const popup = document.getElementById('booking-popup');
const popupDetails = document.getElementById('popup-details');
const popupPurpose = document.getElementById('popup-purpose');
const popupBookedBy = document.getElementById('popup-booked-by');
const popupError = document.getElementById('popup-error');
const popupConfirm = document.getElementById('popup-confirm');
const detailsPopup = document.getElementById('details-popup');
const gridHint = document.getElementById('grid-hint');
const defaultHint = gridHint.innerHTML;
const weekGrid = document.querySelector('.week-grid');
const dayTabs = document.querySelectorAll('.day-tab');
const todayIndex = <?php
    $ti = array_search($today, $days);
    echo $ti === false ? 0 : $ti;
?>;

// "Mon, 27 Oct 2026"
function niceDate(ymd) {
    const d = new Date(ymd + 'T00:00:00');
    return d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
}

// "09:30" -> "9:30 AM"
function niceTime(hm) {
    let [h, m] = hm.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return h + ':' + String(m).padStart(2, '0') + ' ' + ampm;
}

// "09:00", "10:30" -> "1h 30m"
function duration(start, end) {
    const toMin = hm => { const [h, m] = hm.split(':').map(Number); return h * 60 + m; };
    const mins = toMin(end) - toMin(start);
    const h = Math.floor(mins / 60), m = mins % 60;
    return (h ? h + 'h' : '') + (h && m ? ' ' : '') + (m ? m + 'm' : '');
}

function roomLabel(room) {
    if (room === 'Bilik Meeting') return 'Meeting';
    if (room === 'Bilik Seminar') return 'Seminar';
    return room;
}

function summaryHtml(room, date, start, end) {
    const esc = s => s.replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    return `<span class="room-tag room-${roomLabel(room).toLowerCase()}">${esc(roomLabel(room))} Room</span>
            <div><strong>${esc(niceDate(date))}</strong></div>
            <div>${esc(niceTime(start))} – ${esc(niceTime(end))} <span class="muted">(${esc(duration(start, end))})</span></div>`;
}

function setHint(html, isWarning) {
    gridHint.innerHTML = html;
    gridHint.classList.toggle('hint-active', !isWarning && html !== defaultHint);
    gridHint.classList.toggle('hint-warning', !!isWarning);
}

// All cells in the same day + room column, in time order
function columnCells(cell) {
    return Array.from(document.querySelectorAll('.week-grid .cell')).filter(
        c => c.dataset.date === cell.dataset.date && c.dataset.room === cell.dataset.room
    );
}

function clearHover() {
    document.querySelectorAll('.week-grid .cell.hover-range').forEach(c => c.classList.remove('hover-range'));
}

function clearSelection() {
    document.querySelectorAll('.week-grid .cell').forEach(c => {
        c.classList.remove('selected-start', 'selected-end', 'in-range', 'hover-range');
    });
    startCell = null;
    endCell = null;
}

function startNewSelection(cell) {
    clearSelection();
    startCell = cell;
    startCell.classList.add('selected-start');
    setHint(`<strong>Start: ${niceTime(cell.dataset.start)}</strong> (${roomLabel(cell.dataset.room)} Room, ${niceDate(cell.dataset.date)}).
             Now click the <strong>end time</strong> in the same column, or click the same slot again for 30 minutes.
             Press Esc to start over.`);
}

function onAvailableClick(cell) {
    // First click, or a new start after a finished selection
    if (!startCell || endCell) {
        startNewSelection(cell);
        return;
    }

    // Different day or room: treat as a new start
    if (cell.dataset.date !== startCell.dataset.date ||
        cell.dataset.room !== startCell.dataset.room) {
        startNewSelection(cell);
        return;
    }

    const cells = columnCells(startCell);
    const startIdx = cells.indexOf(startCell);
    const clickIdx = cells.indexOf(cell);

    // Clicked above the start: restart from here
    if (clickIdx < startIdx) {
        startNewSelection(cell);
        return;
    }

    // A booked slot anywhere in the range blocks the selection
    for (let i = startIdx; i <= clickIdx; i++) {
        if (!cells[i].classList.contains('available')) {
            clearSelection();
            setHint('That range overlaps an existing booking. Please click a new start time.', true);
            return;
        }
    }

    clearHover();
    endCell = cell;
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

    popupDetails.innerHTML = summaryHtml(pending.room, pending.date, pending.start, pending.end);
    popupError.style.display = 'none';
    popupPurpose.value = '';
    try { popupBookedBy.value = localStorage.getItem('salam_booked_by') || ''; } catch (e) { popupBookedBy.value = ''; }
    popupConfirm.disabled = false;
    popupConfirm.textContent = 'Confirm Booking';
    popup.style.display = 'flex';
    popupPurpose.focus();
}

function showDetails(cell) {
    const d = cell.dataset;
    document.getElementById('details-purpose').textContent = d.purpose;
    document.getElementById('details-by').textContent = d.by;
    document.getElementById('details-summary').innerHTML = summaryHtml(d.room, d.date, d.start, d.end);

    // Your own upcoming bookings (or any booking for admins) can be changed from here
    const canEdit = IS_ADMIN || (d.mine === '1' && d.past !== '1');
    const canCancel = IS_ADMIN || (d.mine === '1' && d.date >= <?php echo json_encode($today); ?>);
    document.getElementById('details-edit').href = `edit_booking.php?id=${d.id}&from=home&week_start=${WEEK_START}`;
    document.getElementById('details-edit').style.display = canEdit ? '' : 'none';
    document.getElementById('details-cancel-form').style.display = canCancel ? '' : 'none';
    document.getElementById('details-cancel-id').value = d.id;
    document.getElementById('details-actions').style.display = (canEdit || canCancel) ? '' : 'none';
    detailsPopup.style.display = 'flex';
}

function closeDetails() {
    detailsPopup.style.display = 'none';
}

weekGrid.addEventListener('click', function (e) {
    const cell = e.target.closest('.cell');
    if (!cell) return;
    if (cell.classList.contains('booked')) { clearSelection(); setHint(defaultHint); showDetails(cell); return; }
    if (cell.classList.contains('available')) onAvailableClick(cell);
});

// While choosing an end time, preview the range under the mouse
weekGrid.addEventListener('mouseover', function (e) {
    const cell = e.target.closest('.cell.available');
    if (!startCell || endCell || !cell) return;
    clearHover();
    if (cell.dataset.date !== startCell.dataset.date || cell.dataset.room !== startCell.dataset.room) return;
    const cells = columnCells(startCell);
    const a = cells.indexOf(startCell), b = cells.indexOf(cell);
    if (b <= a) return;
    for (let i = a; i <= b; i++) { if (!cells[i].classList.contains('available')) return; }
    for (let i = a + 1; i <= b; i++) cells[i].classList.add('hover-range');
});
weekGrid.addEventListener('mouseleave', clearHover);

function closePopup() {
    popup.style.display = 'none';
    pending = null;
    clearSelection();
    setHint(defaultHint);
}

function showPopupError(text) {
    popupError.textContent = text;
    popupError.style.display = 'block';
}

// Clicking the dark background closes a popup
[popup, detailsPopup].forEach(p => p.addEventListener('click', e => {
    if (e.target === p) { p === popup ? closePopup() : closeDetails(); }
}));

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        if (detailsPopup.style.display !== 'none') { closeDetails(); return; }
        closePopup();
    }
});
[popupPurpose, popupBookedBy].forEach(inp => inp.addEventListener('keydown', e => {
    if (e.key === 'Enter') submitQuickBooking();
}));

async function submitQuickBooking() {
    const purpose = popupPurpose.value.trim();
    const bookedBy = popupBookedBy.value.trim();

    if (!purpose) { showPopupError('Please enter a purpose.'); popupPurpose.focus(); return; }
    if (!bookedBy) { showPopupError('Please enter your name.'); popupBookedBy.focus(); return; }
    if (!pending || popupConfirm.disabled) return;

    const formData = new FormData();
    formData.append('purpose', purpose);
    formData.append('booked_by', bookedBy);
    formData.append('booking_date', pending.date);
    formData.append('start_time', pending.start);
    formData.append('end_time', pending.end);
    formData.append('room', pending.room);
    formData.append('csrf_token', document.querySelector('meta[name="csrf-token"]').content);

    popupConfirm.disabled = true;
    popupConfirm.textContent = 'Booking...';

    let data;
    try {
        const response = await fetch('book_ajax.php', { method: 'POST', body: formData });
        data = await response.json();
    } catch (e) {
        showPopupError('Could not reach the server. Please check My Bookings before trying again.');
        popupConfirm.disabled = false;
        popupConfirm.textContent = 'Confirm Booking';
        return;
    }
    if (data.error === 'csrf') {
        alert(data.message);
        location.reload();
        return;
    }
    if (data.error) {
        window.location.href = data.error === 'session_expired' ? 'login.php?timeout=1' : 'login.php';
        return;
    }
    if (window.salamResetTimer) window.salamResetTimer();

    if (!data.success) {
        // Keep the popup open so the user can fix it or pick another time
        showPopupError(data.message);
        popupConfirm.disabled = false;
        popupConfirm.textContent = 'Confirm Booking';
        return;
    }

    try { localStorage.setItem('salam_booked_by', bookedBy); } catch (e) {}

    const msg = document.getElementById('week-message');
    msg.textContent = `Booked: ${roomLabel(pending.room)} Room, ${niceDate(pending.date)}, ${niceTime(pending.start)} – ${niceTime(pending.end)}.`;
    msg.className = 'success';
    msg.style.display = 'block';

    const first = pending.cells[0];

    pending.cells.forEach((c, idx) => {
        c.classList.remove('available', 'selected-start', 'selected-end', 'in-range', 'hover-range');
        c.classList.add('booked', 'mine');
        c.title = purpose + ' — ' + bookedBy;
        if (idx > 0) c.remove();
    });

    first.rowSpan = pending.cells.length;
    first.textContent = '';
    Object.assign(first.dataset, {
        id: data.booking.id, start: pending.start, end: pending.end,
        purpose: purpose, by: bookedBy, mine: '1', past: '0'
    });

    const title = document.createElement('div');
    title.className = 'booked-title';
    title.textContent = purpose;
    first.appendChild(title);

    if (pending.cells.length >= 2) {
        const staff = document.createElement('div');
        staff.className = 'booked-staff';
        staff.textContent = bookedBy;
        first.appendChild(staff);

        const time = document.createElement('div');
        time.className = 'booked-time';
        time.textContent = niceTime(pending.start).replace(/ [AP]M$/, '') + '–' + niceTime(pending.end);
        first.appendChild(time);
    }

    closePopup();
    msg.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function showDay(i) {
    weekGrid.dataset.day = i;
    dayTabs.forEach(t => t.classList.toggle('active', t.dataset.day == i));
    clearSelection();
}

dayTabs.forEach(t => t.addEventListener('click', () => showDay(t.dataset.day)));
showDay(todayIndex);
</script>

</body>
</html>
