<?php
function renderNavigation($currentPage = '') {
    $pages = [
        'index' => ['title' => 'Табло', 'url' => 'index.php'],
        'buildings' => ['title' => 'Сгради', 'url' => 'buildings.php'],
        'properties' => ['title' => 'Имоти', 'url' => 'properties.php'],
        'residents' => ['title' => 'Обитатели', 'url' => 'residents.php'],
        'accounting' => ['title' => 'Счетоводство', 'url' => 'accounting.php'],
    ];
    ob_start();
    ?>
    <nav class="main-nav">
        <div class="nav-links desktop-nav d-none d-md-flex">
            <?php foreach ($pages as $page => $info): ?>
                <a href="<?php echo $info['url']; ?>" class="<?php echo $currentPage === $page ? 'active' : ''; ?>"><?php echo $info['title']; ?></a>
            <?php endforeach; ?>
            <a href="logout.php" class="btn btn-danger">Изход</a>
        </div>
        <button class="burger-menu d-md-none" id="burgerMenuBtn" aria-label="Меню">
            <span></span><span></span><span></span>
        </button>
        <div class="mobile-nav-overlay d-md-none" id="mobileNavOverlay"></div>
        <div class="mobile-nav d-md-none" id="mobileNav">
            <div class="mobile-nav-header">
                <span class="mobile-nav-title">Меню</span>
                <button class="close-mobile-nav" id="closeMobileNav" aria-label="Затвори">&times;</button>
            </div>
            <div class="mobile-nav-links">
                <?php foreach ($pages as $page => $info): ?>
                    <a href="<?php echo $info['url']; ?>" class="<?php echo $currentPage === $page ? 'active' : ''; ?>"><?php echo $info['title']; ?></a>
                <?php endforeach; ?>
                <a href="logout.php" class="btn btn-danger w-100 mt-3">Изход</a>
            </div>
        </div>
    </nav>
    <script>
    // Мобилно меню
    document.addEventListener('DOMContentLoaded', function() {
        var burgerBtn = document.getElementById('burgerMenuBtn');
        var mobileNav = document.getElementById('mobileNav');
        var overlay = document.getElementById('mobileNavOverlay');
        var closeBtn = document.getElementById('closeMobileNav');
        function openMenu() {
            mobileNav.classList.add('open');
            overlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        function closeMenu() {
            mobileNav.classList.remove('open');
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        }
        burgerBtn && burgerBtn.addEventListener('click', openMenu);
        closeBtn && closeBtn.addEventListener('click', closeMenu);
        overlay && overlay.addEventListener('click', closeMenu);
        // Затваряне при избор на линк
        document.querySelectorAll('.mobile-nav-links a').forEach(function(link) {
            link.addEventListener('click', closeMenu);
        });
    });
    </script>
    <?php
    return ob_get_clean();
}
?> 