<?php
$current_page = basename($_SERVER['PHP_SELF']);
function nav_active($page, $current) {
    return $page === $current ? 'active' : '';
}
?>
<nav class="navbar" id="navbar">
    <div class="navbar-inner">
        <a href="homepage.php" class="brand">
            <img src="assets/Salam-logo.png" alt="SALAM Logo" class="logo">
            <span class="brand-text">SALAM MEETING/SEMINAR ROOM BOOKING SYSTEM</span>
        </a>

        <button class="nav-toggle" id="nav-toggle" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>

        <div class="nav-links" id="nav-links">
            <a href="homepage.php" class="<?php echo nav_active('homepage.php', $current_page); ?>">Home</a>
            <a href="my_bookings.php" class="<?php echo nav_active('my_bookings.php', $current_page); ?>">My Bookings</a>
            <a href="view_bookings.php" class="<?php echo nav_active('view_bookings.php', $current_page); ?>">View All</a>
            <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                <a href="register.php" class="<?php echo nav_active('register.php', $current_page); ?>">Manage Account</a>
            <?php endif; ?>

            <div class="nav-user">
                <div class="avatar"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($_SESSION['staff_name'], 0, 1))); ?></div>
                <span class="nav-username"><?php echo htmlspecialchars($_SESSION['staff_name']); ?></span>
                <form method="POST" action="logout.php" class="logout-form">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="logout-link">Logout</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<!-- Inactivity warning (the server logs you out after 15 minutes) -->
<div id="timeout-warning" class="timeout-warning" style="display:none;" role="alert">
    <span>You will be signed out in <strong id="timeout-countdown">2:00</strong> because of inactivity.</span>
    <button type="button" id="stay-signed-in">Stay signed in</button>
</div>

<script>
document.getElementById('nav-toggle').addEventListener('click', function() {
    document.getElementById('nav-links').classList.toggle('open');
});

window.addEventListener('scroll', function() {
    document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 8);
});

// Inactivity warning: matches the 15-minute timeout in session_check.php
(function () {
    const TIMEOUT = 15 * 60 * 1000, WARN_BEFORE = 2 * 60 * 1000;
    const box = document.getElementById('timeout-warning');
    const countdown = document.getElementById('timeout-countdown');
    let lastServerHit = Date.now();

    window.salamResetTimer = function () { lastServerHit = Date.now(); box.style.display = 'none'; };

    setInterval(function () {
        const left = TIMEOUT - (Date.now() - lastServerHit);
        // Reload with GET: the server sends us to the login page if the session really ended
        if (left <= 0) { window.location.href = window.location.pathname + window.location.search; return; }
        if (left <= WARN_BEFORE) {
            const s = Math.ceil(left / 1000);
            countdown.textContent = Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
            box.style.display = 'flex';
        }
    }, 1000);

    // Any page request refreshes the session on the server
    document.getElementById('stay-signed-in').addEventListener('click', function () {
        fetch('my_bookings.php', { method: 'HEAD', credentials: 'same-origin' })
            .then(function (r) {
                if (r.redirected && r.url.indexOf('login.php') !== -1) { window.location.href = r.url; return; }
                window.salamResetTimer();
            })
            .catch(function () {});
    });
})();

// Remove ?msg=... from the address bar so a refresh doesn't show the message again
if (window.history.replaceState && /[?&]msg=/.test(window.location.search)) {
    const url = new URL(window.location.href);
    url.searchParams.delete('msg');
    window.history.replaceState(null, '', url.pathname + url.search);
}

document.addEventListener('DOMContentLoaded', function() {
    const backToTopBtn = document.getElementById('back-to-top');
    if (!backToTopBtn) return;

    window.addEventListener('scroll', function() {
        backToTopBtn.classList.toggle('visible', window.scrollY > 300);
    });

    backToTopBtn.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
</script>