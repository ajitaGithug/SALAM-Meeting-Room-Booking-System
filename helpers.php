<?php
// Shared booking rules. Used by book_ajax.php and edit_booking.php.

const SALAM_ROOMS = ['Bilik Meeting', 'Bilik Seminar'];

// "9:00", "09:00" or "09:00:00" -> "09:00"; anything else -> null
function normalize_time($t) {
    if (!is_string($t) || !preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $t, $m)) {
        return null;
    }
    return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
}

// Returns [cleaned_values, null] on success, or [null, "error message"] on failure.
function validate_booking(array $in, bool $is_admin = false): array {
    $get = function ($key) use ($in) {
        return (isset($in[$key]) && is_string($in[$key])) ? $in[$key] : '';
    };

    $purpose = trim($get('purpose'));
    $room    = $get('room');
    $date    = $get('booking_date');
    $start   = normalize_time($get('start_time'));
    $end     = normalize_time($get('end_time'));

    if ($purpose === '')           return [null, "Please enter a purpose."];
    if (mb_strlen($purpose) > 200) return [null, "Purpose must be 200 characters or fewer."];
    if (!in_array($room, SALAM_ROOMS, true)) return [null, "Invalid room."];

    $d = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) return [null, "Invalid date."];
    if ((int)$d->format('N') > 5) return [null, "Bookings are only available Monday to Friday."];

    if ($start === null || $end === null) return [null, "Invalid time."];
    foreach ([$start, $end] as $t) {
        if (!in_array(substr($t, 3, 2), ['00', '30'], true)) {
            return [null, "Times must be on the hour or half hour."];
        }
    }
    if ($start < '08:30' || $end > '17:00') return [null, "Bookings must be between 8:30 AM and 5:00 PM."];
    if ($end <= $start) return [null, "End time must be after start time."];

    // Remove this check if staff should be allowed to book times that have passed
    if (!$is_admin && strtotime("$date $end") <= time()) {
        return [null, "You cannot book a time that has already passed."];
    }

    return [[
        'purpose'      => $purpose,
        'room'         => $room,
        'booking_date' => $date,
        'start_time'   => $start,
        'end_time'     => $end,
    ], null];
}

// True if another booking overlaps this room/date/time. $exclude_id skips the booking being edited.
function booking_conflict($conn, $room, $date, $start, $end, $exclude_id = 0) {
    $stmt = $conn->prepare(
        "SELECT 1 FROM bookings
         WHERE room = ? AND booking_date = ? AND start_time < ? AND end_time > ? AND id <> ?
         LIMIT 1"
    );
    $stmt->bind_param("ssssi", $room, $date, $end, $start, $exclude_id);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $found;
}

// One booking change at a time, so two people can't take the same slot together.
// MySQL also releases the lock automatically when the script ends.
function booking_lock($conn) {
    $row = $conn->query("SELECT GET_LOCK('salam_booking', 5) AS l")->fetch_assoc();
    return (int)$row['l'] === 1;
}

function booking_unlock($conn) {
    $conn->query("SELECT RELEASE_LOCK('salam_booking')");
}
