<?php
function renderNavigation($currentPage = '') {
    $navItems = [
        'dashboard' => ['icon' => 'fas fa-tachometer-alt', 'text' => 'Табло', 'url' => 'index.php'],
        'apartments' => ['icon' => 'fas fa-home', 'text' => 'Апартаменти', 'url' => 'apartments.php'],
        'resident_history' => ['icon' => 'fas fa-book', 'text' => 'Домова книга', 'url' => 'resident_history.php'],
        'payments' => ['icon' => 'fas fa-money-bill-wave', 'text' => 'Плащания', 'url' => 'payments.php'],
        'fees' => ['icon' => 'fas fa-file-invoice-dollar', 'text' => 'Такси', 'url' => 'fees.php'],
        'reports' => ['icon' => 'fas fa-chart-bar', 'text' => 'Отчети', 'url' => 'reports.php'],
        'settings' => ['icon' => 'fas fa-cog', 'text' => 'Настройки', 'url' => 'settings.php']
    ];
    
    $html = '<div class="nav-links">';
    foreach ($navItems as $page => $info) {
        $active = $currentPage === $page ? 'active' : '';
        $html .= sprintf(
            '<a href="%s" class="%s">%s</a>',
            $info['url'],
            $active,
            $info['text']
        );
    }
    $html .= '<a href="logout.php" class="btn btn-danger">Изход</a>';
    $html .= '</div>';
    
    return $html;
}
?> 