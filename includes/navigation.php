<?php
function renderNavigation($currentPage = '') {
    $pages = [
        'index' => ['title' => 'Табло', 'url' => 'index.php'],
        'buildings' => ['title' => 'Сгради', 'url' => 'buildings.php'],
        'apartments' => ['title' => 'Апартаменти', 'url' => 'apartments.php'],
        'fees' => ['title' => 'Такси', 'url' => 'fees.php'],
        'payments' => ['title' => 'Плащания', 'url' => 'payments.php'],
        'reports' => ['title' => 'Отчети', 'url' => 'reports.php']
    ];
    
    $html = '<div class="nav-links">';
    foreach ($pages as $page => $info) {
        $active = $currentPage === $page ? 'active' : '';
        $html .= sprintf(
            '<a href="%s" class="%s">%s</a>',
            $info['url'],
            $active,
            $info['title']
        );
    }
    $html .= '<a href="logout.php" class="btn btn-danger">Изход</a>';
    $html .= '</div>';
    
    return $html;
}
?> 