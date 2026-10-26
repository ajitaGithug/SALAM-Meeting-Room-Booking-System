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
                <div class="avatar"><?php echo strtoupper(substr($_SESSION['staff_name'], 0, 1)); ?></div>
                <span class="nav-username"><?php echo htmlspecialchars($_SESSION['staff_name']); ?></span>
                <form method="POST" action="logout.php" class="logout-form">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="logout-link">Logout</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<script>
document.getElementById('nav-toggle').addEventListener('click', function() {
    document.getElementById('nav-links').classList.toggle('open');
});

window.addEventListener('scroll', function() {
    document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 8);
});

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