# SALAM Meeting Room Booking

Web-based meeting room booking system for SALAM Shah Alam Specialist Hospital, replacing the previous manual, hand-written booking form.

## How to Run This Project Locally
See **Setup Instructions** below.

## Features

**Core features**
- Staff login/logout with hashed passwords
- Session timeout after inactivity, with a message shown on re-login
- Interactive weekly grid homepage — click a start slot, then an end slot, to book a room directly on the grid (no separate booking page)
- Auto conflict detection — no double-booking a room, checked both visually (greyed-out slots) and on the server
- Multi-slot bookings display as one merged cell spanning the full duration
- View all bookings, filterable by date/room
- Edit/cancel your own bookings (My Bookings page)

**Admin only**
- Admin-only staff registration (no public sign-up)
- Admin-driven password reset (no email dependency)
- Activate/deactivate staff accounts (preserves booking history instead of deleting)
- View, edit, or cancel *any* booking (shown inline on the same "View All" page, not a separate admin page)
- Self-protection: an admin cannot deactivate their own account

**Additional features — optional / not yet built**

1. Natural-language booking (type → auto-fill form)
   Staff type something like "book Bilik Seminar tomorrow 2-3pm for a finance meeting", and AI extracts purpose/date/time/room to pre-fill the existing booking flow for confirmation. The conflict check and validation are already built and tested — AI here would just be a smarter way to fill in a form that's already trusted. Estimated ~1.5–2 weeks.

## Tech Stack
- PHP (backend logic)
- MySQL (database)
- HTML/CSS/JavaScript (frontend, with AJAX for the booking grid)
- XAMPP (local development environment)

## Database Schema

### users
| Column     | Type                     | Notes                         |
|------------|--------------------------|--------------------------------|
| id         | INT, PK, auto-increment  |                                |
| staff_name | VARCHAR(100)             |                                |
| staff_id   | VARCHAR(20), UNIQUE      |                                |
| password   | VARCHAR(255)             | Hashed with password_hash()   |
| role       | ENUM('staff','admin')    | Default 'staff'               |
| status     | ENUM('active','inactive')| Default 'active'              |
| created_at | TIMESTAMP                |                                |

### bookings
| Column       | Type                              | Notes                              |
|--------------|------------------------------------|-------------------------------------|
| id           | INT, PK, auto-increment           |                                      |
| staff_name   | VARCHAR(100)                       |                                      |
| staff_id     | VARCHAR(20), FK → users.staff_id  |                                      |
| purpose      | VARCHAR(255)                       |                                      |
| booking_date | DATE                                |                                      |
| start_time   | TIME                                |                                      |
| end_time     | TIME                                |                                      |
| room         | ENUM('Bilik Meeting','Bilik Seminar') |                                   |
| created_at   | TIMESTAMP                          |                                      |

## Setup Instructions
1. Install XAMPP, start Apache + MySQL
2. Create database `salam_meeting_room_booking` (utf8mb4_general_ci)
3. Run the SQL scripts to create `users` and `bookings` tables, including the `status` column and the foreign key constraint (see `/sql/schema.sql`)
4. Place project files in `htdocs/SALAM-Meeting-Room-Booking-System/`
5. Update `config.php` with your database credentials if different from default
6. Manually insert the first admin account directly into the `users` table (with a `password_hash()`-generated password and `role = 'admin'`), since registration is admin-only and there's no public sign-up page. All other accounts are then created via Manage Staff.

## File Structure
- `config.php` — database connection
- `session_check.php` — session timeout logic
- `header.php` — shared navigation bar (active-link highlighting, mobile collapse)
- `style.css` — shared styling
- `login.php` / `logout.php` — authentication
- `register.php` — admin-only: add staff, reset password, activate/deactivate staff (all three combined on one "Manage Staff" page)
- `homepage.php` — weekly grid view and main booking interface (click-to-select time range, AJAX submission)
- `book_ajax.php` — AJAX endpoint that validates and inserts a booking (conflict check happens here)
- `view_bookings.php` — all bookings, filterable by date/room; shows Edit/Cancel actions inline for admins
- `my_bookings.php` — logged-in staff's own bookings, with edit/cancel
- `edit_booking.php` / `cancel_booking.php` — shared logic for both staff and admin, role-aware (admin can act on any booking, redirects differ by role)

**Removed:** `admin_bookings.php` was merged into `view_bookings.php`. `admin_reset_password.php` was merged into `register.php`. The old dropdown-based `booking.php` was merged into the homepage grid. Get_booked_slots.php was removed because the weekly grid loads the whole week in one query.

## Status

**V1**
- [x] Booking (via interactive weekly grid, click-to-select time range)
- [x] Database setup
- [x] Conflict detection (server-side + visual greying of booked slots)
- [x] Login/session handling
- [x] Session timeout
- [x] Homepage (weekly grid, replaces old dropdown-based booking page)
- [x] View bookings (filterable, admin actions shown inline)
- [x] My bookings / cancel / edit own booking
- [x] Admin view (merged into View All rather than a separate page)
- [x] Foreign key constraint (bookings.staff_id → users.staff_id)
- [x] Admin-only staff registration
- [x] Admin-driven password reset
- [x] Staff activate/deactivate (preserves history, no hard delete)
- [x] UI styling applied consistently across all pages (style.css + shared header, logo, login page redesign)

**V2**
- [ ] Email notifications (stretch goal)
- [ ] Natural-language booking assistant (stretch goal)

## Known Limitations
- Only 2 fixed rooms (Bilik Meeting / Bilik Seminar) — adding more requires editing the `room` ENUM in both the database and every form that lists rooms
- No email/SMS notifications yet
- No hard-delete for staff accounts by design — deactivation is used instead to preserve booking history and avoid foreign key conflicts
- Tested only in a local (XAMPP) environment; not yet deployed to a live server
- No automated tests — all functionality verified through manual walkthroughs

## Future Enhancements
- AI natural-language booking assistant
- Email confirmation/reminders
- Deployment to a live/shared server