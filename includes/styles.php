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
        border-bottom: none;
        padding-bottom: 0;
    }

    .modal-title {
        font-weight: 600;
    }

    .modal-body {
        padding-top: 0;
    }

    .modal-footer {
        border-top: none;
        padding-top: 0;
    }

    .building-info {
        background-color: white;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .building-info h4 {
        margin: 0;
        font-size: 1.2rem;
        color: var(--primary-color);
        display: flex;
        align-items: center;
        gap: 0.5rem;
        text-align: center;
    }

    .building-info p {
        margin: 0;
        color: #666;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .custom-building-selector {
        width: auto !important; /* Презаписва inline style */
        min-width: 150px; /* Минимална ширина */
        max-width: 250px; /* Максимална ширина */
        display: inline-block; /* За да не отива на нов ред */
        border-radius: 25px; /* По-заоблени ъгли */
        padding: 0.4rem 1.2rem; /* По-малък паддинг */
        font-size: 0.9rem; /* По-малък шрифт */
        border: 1px solid var(--accent-color); /* Цвят на рамката */
        box-shadow: 0 2px 5px rgba(52, 152, 219, 0.2); /* Лека сянка */
        background-color: white;
        -webkit-appearance: none; /* Премахва стандартния стрелки */
        -moz-appearance: none;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%233498db' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 16px 12px;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .custom-building-selector:focus {
        border-color: #2980b9;
        box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        outline: 0;
    }

    /* При нужда за адаптивност */
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
        .building-info {
            flex-direction: column;
            align-items: flex-start;
        }
        .custom-building-selector {
            width: 100% !important;
            max-width: none;
            margin-top: 0.5rem;
        }
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
</style> 