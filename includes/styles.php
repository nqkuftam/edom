<?php
// Общи стилове за всички страници
?>
<style>
    :root {
        --primary-color: #2c3e50;
        --secondary-color: #34495e;
        --accent-color: #3498db;
        --success-color: #2ecc71;
        --warning-color: #f1c40f;
        --danger-color: #e74c3c;
        --light-bg: #f8f9fa;
        --dark-bg: #2c3e50;
        --text-light: #ecf0f1;
        --text-dark: #2c3e50;
        --border-radius: 8px;
        --box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        --transition: all 0.3s ease;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        margin: 0;
        padding: 0;
        background-color: var(--light-bg);
        color: var(--text-dark);
        line-height: 1.6;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .header {
        background-color: var(--primary-color);
        color: var(--text-light);
        padding: 1rem;
        box-shadow: var(--box-shadow);
        margin-bottom: 2rem;
        position: sticky;
        top: 0;
        z-index: 1000;
    }

    .header-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
        max-width: 1200px;
        margin: 0 auto;
    }

    .header h1 {
        margin: 0;
        font-size: 1.8rem;
        font-weight: 600;
    }

    .nav-links {
        display: flex;
        gap: 1rem;
    }

    .nav-links a {
        color: var(--text-light);
        text-decoration: none;
        padding: 0.5rem 1rem;
        border-radius: var(--border-radius);
        transition: var(--transition);
    }

    .nav-links a:hover {
        background-color: var(--accent-color);
    }

    .btn {
        display: inline-block;
        padding: 0.5rem 1rem;
        border: none;
        border-radius: var(--border-radius);
        cursor: pointer;
        text-decoration: none;
        transition: var(--transition);
        font-weight: 500;
    }

    .btn-primary {
        background-color: var(--accent-color);
        color: white;
    }

    .btn-primary:hover {
        background-color: #2980b9;
    }

    .btn-secondary {
        background-color: var(--secondary-color);
        color: white;
    }

    .btn-secondary:hover {
        background-color: #2c3e50;
    }

    .btn-danger {
        background-color: var(--danger-color);
        color: white;
    }

    .btn-danger:hover {
        background-color: #c0392b;
    }

    .btn-warning {
        background-color: var(--warning-color);
        color: var(--text-dark);
    }

    .btn-warning:hover {
        background-color: #f39c12;
    }

    .card {
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        padding: 1.5rem;
        margin-bottom: 1rem;
        transition: var(--transition);
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text-dark);
    }

    .form-control {
        width: 100%;
        padding: 0.5rem;
        border: 1px solid #ddd;
        border-radius: var(--border-radius);
        transition: var(--transition);
    }

    .form-control:focus {
        outline: none;
        border-color: var(--accent-color);
        box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
    }

    .alert {
        padding: 1rem;
        border-radius: var(--border-radius);
        margin-bottom: 1rem;
    }

    .alert-success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .alert-warning {
        background-color: #fff3cd;
        color: #856404;
        border: 1px solid #ffeeba;
    }

    .modal-content {
        border-radius: var(--border-radius);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .modal-header {
        background-color: var(--primary-color);
        color: var(--text-light);
        border-radius: var(--border-radius) var(--border-radius) 0 0;
    }

    .modal-title {
        font-weight: 600;
    }

    .building-info {
        background-color: white;
        padding: 1rem;
        border-radius: var(--border-radius);
        margin-bottom: 1rem;
        box-shadow: var(--box-shadow);
    }

    .building-info h4 {
        margin: 0 0 0.5rem 0;
        color: var(--primary-color);
    }

    .building-info p {
        margin: 0;
        color: var(--secondary-color);
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 1rem;
        background-color: white;
        border-radius: var(--border-radius);
        overflow: hidden;
    }

    .table th,
    .table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    .table th {
        background-color: var(--primary-color);
        color: var(--text-light);
        font-weight: 600;
    }

    .table tr:hover {
        background-color: #f8f9fa;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background-color: white;
        padding: 1.5rem;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        text-align: center;
    }

    .stat-card h3 {
        margin: 0;
        color: var(--primary-color);
        font-size: 2rem;
    }

    .stat-card p {
        margin: 0.5rem 0 0 0;
        color: var(--secondary-color);
        font-size: 1.1rem;
    }

    @media (max-width: 768px) {
        .container {
            padding: 8px;
            max-width: 100vw;
        }
        .header-content {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        .header h1 {
            font-size: 1.2rem;
        }
        .nav-links {
            flex-direction: row;
            gap: 0.5rem;
            width: 100vw;
            overflow-x: auto;
            white-space: nowrap;
            padding-bottom: 0.5rem;
            margin-bottom: 0.5rem;
            background: none;
        }
        .nav-links a {
            min-width: 110px;
            text-align: center;
            display: inline-block;
        }
        .card {
            padding: 0.7rem;
            margin-bottom: 0.7rem;
        }
        .grid {
            grid-template-columns: 1fr;
            gap: 0.7rem;
        }
        .form-group {
            margin-bottom: 0.7rem;
        }
        .form-label {
            font-size: 1rem;
        }
        .btn, .btn-primary, .btn-secondary, .btn-danger, .btn-warning {
            width: 100%;
            font-size: 1.1rem;
            padding: 0.7rem 1rem;
            margin-bottom: 0.5rem;
        }
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        table {
            font-size: 0.95rem;
            min-width: 600px;
        }
        .modal-content {
            padding: 0.5rem;
        }
        .desktop-nav { display: none !important; }
        .burger-menu { 
            display: flex !important;
            position: fixed;
            top: 1.2rem;
            right: 1.2rem;
            z-index: 1300;
        }
        .main-nav { position: relative; }
    }

    @media (max-width: 480px) {
        .header h1 {
            font-size: 1rem;
        }
        .form-label {
            font-size: 0.95rem;
        }
        .btn, .btn-primary, .btn-secondary, .btn-danger, .btn-warning {
            font-size: 1rem;
            padding: 0.6rem 0.7rem;
        }
        table {
            font-size: 0.9rem;
            min-width: 400px;
        }
    }

    /* Стил за лявата колона с бележки и управление */
    .col-md-4.col-lg-3 {
        padding-right: 30px;
    }

    /* --- Мобилно бургер меню и странична навигация --- */
    .burger-menu {
        display: none;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        width: 44px;
        height: 44px;
        background: none;
        border: none;
        cursor: pointer;
        z-index: 1201;
    }
    .burger-menu span {
        display: block;
        width: 28px;
        height: 4px;
        margin: 4px 0;
        background: var(--text-light);
        border-radius: 2px;
        transition: 0.3s;
    }
    @media (max-width: 768px) {
        .desktop-nav { display: none !important; }
        .burger-menu { 
            display: flex !important;
            position: fixed;
            top: 0.4rem;
            right: 1.2rem;
            z-index: 1300;
        }
        .main-nav { position: relative; }
        .mobile-nav-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.4);
            z-index: 1200;
            transition: 0.3s;
        }
        .mobile-nav-overlay.show { display: block; }
        .mobile-nav {
            position: fixed;
            top: 0; right: 0;
            width: 80vw;
            max-width: 320px;
            height: 100vh;
            background: var(--primary-color);
            color: var(--text-light);
            box-shadow: -2px 0 8px rgba(0,0,0,0.15);
            z-index: 1201;
            transform: translateX(100%);
            transition: transform 0.3s;
            display: flex;
            flex-direction: column;
        }
        .mobile-nav.open { transform: translateX(0); }
        .mobile-nav-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #34495e;
        }
        .mobile-nav-title {
            font-size: 1.2rem;
            font-weight: bold;
        }
        .close-mobile-nav {
            background: none;
            border: none;
            color: var(--text-light);
            font-size: 2rem;
            cursor: pointer;
        }
        .mobile-nav-links {
            display: flex;
            flex-direction: column;
            padding: 1rem;
            gap: 0.7rem;
        }
        .mobile-nav-links a {
            color: var(--text-light);
            text-decoration: none;
            font-size: 1.1rem;
            padding: 0.7rem 0.5rem;
            border-radius: var(--border-radius);
            transition: background 0.2s;
        }
        .mobile-nav-links a.active, .mobile-nav-links a:hover {
            background: var(--accent-color);
            color: #fff;
        }
        .mobile-nav-links .btn-danger {
            margin-top: 1rem;
        }
    }
</style> 