<?php
session_start();
if (!isset($_SESSION['staff_id'])) {
    header("Location: login.php");
    exit;
}
require "session_check.php";
require "config.php";

$message = "";
$message_type = "";

// Handle booking submission right here
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $staff_name   = $_SESSION['staff_name'];
    $staff_id     = $_SESSION['staff_id'];
    $purpose      = $_POST['purpose'];
    $booking_date = $_POST['booking_date'];
    $start_time   = $_POST['start_time'];
    $end_time     = $_POST['end_time'];
    $room         = $_POST['room'];

    if ($end_time <= $start_time) {
        $message = "End time must be after start time.";
        $message_type = "error";
    } else {
        $check_sql = "SELECT * FROM bookings 
                      WHERE room = ? AND booking_date = ? 
                      AND start_time < ? AND end_time > ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ssss", $room, $booking_date, $end_time, $start_time);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $message = "Sorry, $room is already booked during that time on $booking_date.";
            $message_type = "error";
        } else {
            $insert_sql = "INSERT INTO bookings (staff_name, staff_id, purpose, booking_date, start_time, end_time, room)
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sssssss", $staff_name, $staff_id, $purpose, $booking_date, $start_time, $end_time, $room);
            $stmt->execute();
            $message = "Booking confirmed for $room on $booking_date from $start_time to $end_time.";
            $message_type = "success";
        }
        $stmt->close();
    }
}

// Fetch this staff's upcoming bookings for the quick view
$staff_id = $_SESSION['staff_id'];
$upcoming_sql = "SELECT * FROM bookings WHERE staff_id = ? AND booking_date >= CURDATE() ORDER BY booking_date ASC, start_time ASC LIMIT 5";
$stmt2 = $conn->prepare($upcoming_sql);
$stmt2->bind_param("s", $staff_id);
$stmt2->execute();
$upcoming = $stmt2->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <title>SALAM Meeting Room Booking</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <div class="container">
        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['staff_name']); ?></h2>

        <div id="booking-message" class="success"></div>

        <?php if ($message): ?>
            <div class="<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="dashboard">
            <div class="card">
                <h3>Book a Meeting Room</h3>
                <form id="booking-form">
                <label>Purpose:</label>
                <input type="text" name="purpose" required>

                <label>Date:</label>
                <input type="date" name="booking_date" id="booking_date" required>

                <label>Select Time (click start, then click end):</label>
<div class="time-picker-wrapper">
    <div class="time-picker" id="time-picker">
        <?php
        $time = strtotime("08:30");
        $end = strtotime("17:00");
        while ($time <= $end) {
            $formatted = date("H:i", $time);
            $label = date("g:i A", $time);
            echo "<div class=\"slot\" data-time=\"$formatted\">$label</div>";
            $time = strtotime("+30 minutes", $time);
        }
        ?>
    </div>
</div>

                <!-- Hidden fields that actually get submitted -->
                <input type="hidden" name="start_time" id="start_time_input" required>
                <input type="hidden" name="end_time" id="end_time_input" required>
                <p id="time-range-display" style="font-weight:600; margin-top:8px;"></p>

                <label>Room:</label>
                <select name="room" id="room" required>
                    <option value="Bilik Meeting">Bilik Meeting</option>
                    <option value="Bilik Seminar">Bilik Seminar</option>
                </select>

                <button type="submit">Book Now</button>
                </form>
            </div>

                <div class="card">
                    <h3>My Upcoming Bookings</h3>
                    <table id="upcoming-table">
                    <tr><th>Purpose</th><th>Date</th><th>Time</th><th>Room</th></tr>
                        <?php if ($upcoming->num_rows > 0): ?>
                            <?php while ($row = $upcoming->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                                    <td><?php echo htmlspecialchars($row['booking_date']); ?></td>
                                    <td><?php echo htmlspecialchars($row['start_time']) . " - " . htmlspecialchars($row['end_time']); ?></td>
                                    <td><?php echo htmlspecialchars($row['room']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4">No upcoming bookings.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>

    <script>
    document.querySelector("form").addEventListener("submit", function(e) {
        const start = document.querySelector("[name='start_time']").value;
        const end = document.querySelector("[name='end_time']").value;
        if (end <= start) {
            alert("End time must be after start time.");
            e.preventDefault();
        }
    });
    </script>


    <script>

    let startCell = null;
    let endCell = null;

    document.querySelectorAll("#time-picker .slot").forEach(cell => {
        cell.addEventListener("click", function() {
            if (this.classList.contains("booked")) return; // ignore disabled slots

            if (!startCell || (startCell && endCell)) {
                // First click of a new selection: reset everything
                clearSelection();
                startCell = this;
                startCell.classList.add("selected-start");
                updateDisplay();
            } else {
                // Second click: this becomes the end
                const allCells = Array.from(document.querySelectorAll("#time-picker .slot"));
                const startIndex = allCells.indexOf(startCell);
                const clickedIndex = allCells.indexOf(this);

                if (clickedIndex <= startIndex) {
                    // Clicked before or on start — restart selection instead
                    clearSelection();
                    startCell = this;
                    startCell.classList.add("selected-start");
                    updateDisplay();
                    return;
                }

                // Check if any slot in the range is already booked
                let rangeHasConflict = false;
                for (let i = startIndex; i < clickedIndex; i++) {
                    if (allCells[i].classList.contains("booked")) {
                        rangeHasConflict = true;
                        break;
                    }
                }

                if (rangeHasConflict) {
                    alert("Your selected range overlaps an already-booked slot. Please choose a different range.");
                    clearSelection();
                    return;
                }

                endCell = this;
                endCell.classList.add("selected-end");

                for (let i = startIndex + 1; i < clickedIndex; i++) {
                    allCells[i].classList.add("in-range");
                }
                updateDisplay();

            }
        });
    });

    function clearSelection() {
        document.querySelectorAll("#time-picker .slot").forEach(c => {
            c.classList.remove("selected-start", "selected-end", "in-range");
        });
        startCell = null;
        endCell = null;
    }

    function updateDisplay() {
        const startInput = document.getElementById("start_time_input");
        const endInput = document.getElementById("end_time_input");
        const display = document.getElementById("time-range-display");

        if (startCell && endCell) {
            // End time is the NEXT slot's time (since clicking a slot marks it as included)
            const allCells = Array.from(document.querySelectorAll("#time-picker .slot"));
            const endIndex = allCells.indexOf(endCell);
            const nextSlot = allCells[endIndex + 1];
            const actualEndTime = nextSlot ? nextSlot.dataset.time : "17:00";

            startInput.value = startCell.dataset.time;
            endInput.value = actualEndTime;
            display.textContent = `Selected: ${startCell.textContent} - ${nextSlot ? nextSlot.textContent : "5:00 PM"}`;
        } else if (startCell) {
            startInput.value = "";
            endInput.value = "";
            display.textContent = `Start: ${startCell.textContent} — now click the end time`;
        }
    }

        //BOOKING FORM FUNCTION
        document.getElementById("booking-form").addEventListener("submit", async function(e) {
            e.preventDefault(); // stop normal form submission (page reload)

            const start = this.start_time.value;
            const end = this.end_time.value;
            if (end <= start) {
                alert("End time must be after start time.");
                return;
            }

            const formData = new FormData(this);

            const response = await fetch("book_ajax.php", {
                method: "POST",
                body: formData
            });
            const data = await response.json();

            const messageBox = document.getElementById("booking-message");
            messageBox.textContent = data.message;
            messageBox.className = data.success ? "success" : "error";
            messageBox.style.display = "block";

            if (data.success) {
                this.reset(); // clear the form
                // Add the new booking to the table instantly, no reload needed
                const table = document.getElementById("upcoming-table");
                const row = table.insertRow(1); // insert right after the header row
                row.innerHTML = `
                    <td>${data.booking.purpose}</td>
                    <td>${data.booking.date}</td>
                    <td>${data.booking.start} - ${data.booking.end}</td>
                    <td>${data.booking.room}</td>
                `;
            }
        });
    </script>

    <script>
        async function refreshAvailability() {
        const room = document.getElementById("room").value;
        const date = document.getElementById("booking_date").value;

        // Reset any previous booked marks and selection
        clearSelection();
        document.querySelectorAll("#time-picker .slot").forEach(slot => {
            slot.classList.remove("booked");
        });

        if (!room || !date) return; // wait until both are chosen

        const response = await fetch(`get_booked_slots.php?room=${encodeURIComponent(room)}&date=${encodeURIComponent(date)}`);
        const bookings = await response.json();

        const allSlots = Array.from(document.querySelectorAll("#time-picker .slot"));

        bookings.forEach(booking => {
            allSlots.forEach(slot => {
                const slotTime = slot.dataset.time;
                // Mark this slot as booked if it falls within [start, end) of an existing booking
                if (slotTime >= booking.start && slotTime < booking.end) {
                    slot.classList.add("booked");
                }
            });
        });
    }

    // Re-check availability whenever room or date changes
    document.getElementById("room").addEventListener("change", refreshAvailability);
    document.getElementById("booking_date").addEventListener("change", refreshAvailability);
        </script>

</body>
</html>
<?php $conn->close(); ?>