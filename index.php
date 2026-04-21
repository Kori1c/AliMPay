<?php
session_start();
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AliMPay 控制面板</title>
	    <script>
	        // Always sync with system dark mode preference
	        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
	            document.documentElement.classList.add('dark');
	        }
	    </script>
	    <script>
	        window.tailwind = window.tailwind || {};
	        window.tailwind.config = {
	            darkMode: 'class'
	        };
	    </script>
	    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
	        [x-cloak] { display: none !important; }
	        @keyframes fadeUp {
	            from { opacity: 0; transform: translateY(10px); }
	            to { opacity: 1; transform: translateY(0); }
	        }
	        @keyframes toastIn {
	            from { opacity: 0; transform: translateX(12px) scale(0.98); }
	            to { opacity: 1; transform: translateX(0) scale(1); }
	        }
	        @keyframes softPulse {
	            0%, 100% { transform: scale(1); opacity: 1; }
	            50% { transform: scale(1.08); opacity: 0.75; }
	        }
	        .page-panel {
	            animation: fadeUp 220ms ease-out both;
	        }
	        .settings-content-card {
	            animation: fadeUp 220ms ease-out both;
	        }
	        .motion-card {
	            transition: transform 180ms ease, box-shadow 180ms ease, background-color 180ms ease;
	        }
	        .motion-card:hover {
	            transform: translateY(-2px);
	        }
	        .toast-card {
	            animation: toastIn 180ms ease-out both;
	        }
	        .status-dot {
	            animation: softPulse 1800ms ease-in-out infinite;
	        }
	        .nav-item {
	            position: relative;
	            z-index: 1;
	            transition: transform 180ms ease, background-color 180ms ease, color 180ms ease, box-shadow 180ms ease;
	        }
	        .nav-item:hover {
	            transform: translateX(2px);
	        }
	        .nav-item-active {
	            color: #ffffff;
	        }
	        .mobile-nav-active {
	            color: #ffffff;
	            background: linear-gradient(135deg, #2563eb, #4f46e5);
	            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.28);
	        }
	        .mobile-nav-active:hover {
	            transform: translateY(-1px);
	        }
	        .mobile-slider {
	            position: absolute;
	            top: 0;
	            height: 100%;
	            background: linear-gradient(135deg, #2563eb, #4f46e5);
	            border-radius: 12px;
	            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.28);
	            transition: left 280ms cubic-bezier(.22, 1, .36, 1), width 280ms cubic-bezier(.22, 1, .36, 1);
	            pointer-events: none;
	            z-index: 0;
	        }
	        .mobile-slider ~ button {
	            position: relative;
	            z-index: 1;
	        }
	        .dark body, body.dark .mobile-bottom-nav {
	            background: rgba(2, 6, 23, 0.96);
	            border-color: rgba(51, 65, 85, 0.92);
	            box-shadow: 0 -14px 30px rgba(0, 0, 0, 0.32);
	        }
	        .dark body, body.dark .mobile-nav-active {
	            background: linear-gradient(135deg, #2563eb, #1d4ed8);
	            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.34);
	        }
	        .nav-slider {
	            position: absolute;
	            left: 0;
	            right: 0;
	            top: 0;
	            height: 48px;
	            background: linear-gradient(135deg, #2563eb, #4f46e5);
	            border-radius: 12px;
	            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.24);
	            transition: transform 260ms cubic-bezier(.22, 1, .36, 1), opacity 180ms ease;
	        }
	        .nav-slider::before {
	            content: "";
	            position: absolute;
	            left: 10px;
	            top: 50%;
	            width: 3px;
	            height: 18px;
	            border-radius: 999px;
	            background: rgba(255,255,255,0.72);
	            transform: translateY(-50%);
	        }
	        .form-control {
	            transition: border-color 160ms ease, background-color 160ms ease, box-shadow 160ms ease, color 160ms ease;
	        }
	        .form-control:focus {
	            border-color: #2563eb;
	            box-shadow: 0 0 0 3px rgba(37,99,235,0.16);
	        }
	        input[type="number"]::-webkit-outer-spin-button,
	        input[type="number"]::-webkit-inner-spin-button {
	            -webkit-appearance: none;
	            margin: 0;
	        }
	        input[type="number"] {
	            -moz-appearance: textfield;
	            appearance: textfield;
	        }
	        .switch-knob {
	            transition: transform 180ms ease, background-color 180ms ease;
	        }
	        .switch-track {
	            transition: background-color 180ms ease, box-shadow 180ms ease;
	        }
	        .switch-track:hover {
	            box-shadow: 0 0 0 4px rgba(37,99,235,0.10);
	        }
	        .glass {
	            background: rgba(255, 255, 255, 0.7);
	            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .dark .glass {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
	        body {
	            background: radial-gradient(circle at top left, #f8fafc, #e2e8f0);
	            color: #0f172a;
	            min-height: 100vh;
	            transition: background 180ms ease, color 180ms ease;
	        }
	        .dark body, body.dark {
	            background: radial-gradient(circle at top left, #111827, #020617);
	            color: #f8fafc;
	        }
	        .dark body, body.dark .text-slate-500 {
	            color: #cbd5e1;
	        }
	        .dark body, body.dark .text-slate-400 {
	            color: #94a3b8;
	        }
	        .dark body, body.dark .text-slate-600 {
	            color: #cbd5e1;
	        }
	        .dark body, body.dark .text-slate-800 {
	            color: #f8fafc;
	        }
	        .dark body, body.dark .text-slate-950,
	        .dark body, body.dark h1,
	        .dark body, body.dark h2,
	        .dark body, body.dark h3,
	        .dark body, body.dark h4,
	        .dark body, body.dark strong {
	            color: #f8fafc;
	        }
	        .dark body, body.dark p,
	        .dark body, body.dark label,
	        .dark body, body.dark th,
	        .dark body, body.dark td {
	            color: #dbe4f0;
	        }
	        .dark body, body.dark .text-slate-300 {
	            color: #dbe4f0;
	        }
	        .dark body, body.dark input,
	        .dark body, body.dark textarea,
	        .dark body, body.dark select {
	            background: rgba(15, 23, 42, 0.88);
	            border-color: rgba(148, 163, 184, 0.28);
	            color: #f8fafc;
	        }
	        .dark body, body.dark input[readonly],
	        .dark body, body.dark textarea[readonly] {
	            background: rgba(30, 41, 59, 0.72);
	            color: #cbd5e1;
	        }
	        .dark body, body.dark input::placeholder,
	        .dark body, body.dark textarea::placeholder {
	            color: #64748b;
	        }
	        .dark body, body.dark .log-terminal {
	            background: #020617;
	            color: #dbeafe;
	        }
	        .dark body, body.dark .glass {
	            background: rgba(15, 23, 42, 0.88);
	            border-color: rgba(148, 163, 184, 0.18);
	        }
	        .dark body, body.dark .nav-item:not(.nav-item-active) {
	            color: #cbd5e1;
	        }
	        .dark body, body.dark .nav-item:not(.nav-item-active):hover {
	            background: rgba(30, 41, 59, 0.82);
	            color: #ffffff;
	        }
	        .dark body, body.dark input:focus,
	        .dark body, body.dark textarea:focus,
	        .dark body, body.dark select:focus {
	            border-color: #60a5fa;
	            box-shadow: 0 0 0 3px rgba(96,165,250,0.18);
	        }
	        .dark body, body.dark .settings-panel {
	            background: rgba(15, 23, 42, 0.88);
	        }
	        .dark body, body.dark .surface-muted {
	            background: rgba(30, 41, 59, 0.54);
	            border: 1px solid rgba(148, 163, 184, 0.14);
	        }
	        .settings-tab {
	            background: rgba(255, 255, 255, 0.72);
	            border-color: rgba(203, 213, 225, 0.86);
	            color: #334155;
	            transition: background-color 180ms ease, color 180ms ease, transform 180ms ease, border-color 180ms ease;
	        }
	        .settings-tab:hover {
	            background: rgba(241, 245, 249, 0.96);
	            color: #0f172a;
	            transform: translateY(-1px);
	        }
	        .settings-tab-active {
	            background: #2563eb;
	            border-color: #2563eb;
	            color: #ffffff;
	            box-shadow: 0 10px 22px rgba(37, 99, 235, 0.24);
	        }
	        .dark body, body.dark .settings-tab {
	            background: rgba(30, 41, 59, 0.9);
	            border-color: rgba(100, 116, 139, 0.7);
	            color: #e2e8f0;
	        }
	        .dark body, body.dark .settings-tab:hover {
	            background: rgba(51, 65, 85, 0.96);
	            color: #ffffff;
	        }
	        .dark body, body.dark .settings-tab-active {
	            background: #2563eb;
	            border-color: #60a5fa;
	            color: #ffffff;
	            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.36);
	        }
	        .config-toggle-row {
	            background: rgba(248, 250, 252, 0.9);
	            border: 1px solid rgba(226, 232, 240, 0.86);
	        }
	        .dark body, body.dark .config-toggle-row {
	            background: rgba(15, 23, 42, 0.74);
	            border-color: rgba(71, 85, 105, 0.78);
	        }
	        .dark body, body.dark .config-toggle-row p {
	            color: #f8fafc;
	        }
	        .dark body, body.dark .config-toggle-row .hint-text {
	            color: #94a3b8;
	        }
	        .segmented-control {
	            background: rgba(255, 255, 255, 0.76);
	            border: 1px solid rgba(203, 213, 225, 0.82);
	        }
	        .dark body, body.dark .segmented-control {
	            background: rgba(15, 23, 42, 0.72);
	            border-color: rgba(71, 85, 105, 0.82);
	        }
	        .segmented-option {
	            color: #475569;
	        }
	        .segmented-option:hover {
	            background: rgba(226, 232, 240, 0.75);
	            color: #0f172a;
	        }
	        .dark body, body.dark .segmented-option {
	            color: #cbd5e1;
	        }
	        .dark body, body.dark .segmented-option:hover {
	            background: rgba(51, 65, 85, 0.85);
	            color: #ffffff;
	        }
	        .segmented-option-active {
	            background: #2563eb;
	            color: #ffffff;
	            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.24);
	        }
	        .payment-mode-toggle {
	            position: relative;
	            background: rgba(248, 250, 252, 0.92);
	            border: 1px solid rgba(226, 232, 240, 0.9);
	            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.7);
	        }
	        .payment-mode-slider {
	            position: absolute;
	            top: 6px;
	            bottom: 6px;
	            left: 6px;
	            width: calc(50% - 6px);
	            border-radius: 14px;
	            background: linear-gradient(135deg, #2563eb, #1d4ed8);
	            box-shadow: 0 10px 22px rgba(37, 99, 235, 0.22);
	            transition: transform 220ms cubic-bezier(.22, 1, .36, 1), box-shadow 180ms ease;
	        }
	        .payment-mode-button {
	            position: relative;
	            z-index: 1;
	            color: #475569;
	            transition: color 180ms ease, transform 180ms ease;
	        }
	        .payment-mode-button:hover {
	            color: #0f172a;
	        }
	        .payment-mode-button.payment-mode-button-active {
	            color: #ffffff;
	        }
	        .payment-mode-button.payment-mode-button-active:hover {
	            color: #ffffff;
	            transform: translateY(-1px);
	        }
	        .dark body, body.dark .payment-mode-toggle {
	            background: rgba(15, 23, 42, 0.76);
	            border-color: rgba(71, 85, 105, 0.82);
	            box-shadow: inset 0 1px 0 rgba(148, 163, 184, 0.08);
	        }
	        .dark body, body.dark .payment-mode-slider {
	            background: linear-gradient(135deg, #3b82f6, #2563eb);
	            box-shadow: 0 10px 22px rgba(37, 99, 235, 0.28);
	        }
	        .dark body, body.dark .payment-mode-button {
	            color: #cbd5e1;
	        }
	        .dark body, body.dark .payment-mode-button:hover {
	            color: #ffffff;
	        }
	        .dark body, body.dark .payment-mode-button.payment-mode-button-active {
	            color: #ffffff;
	        }
	        .dark body, body.dark .payment-mode-button.payment-mode-button-active:hover {
	            color: #ffffff;
	        }
	        .monitor-toolbar {
	            min-width: 0;
	        }
	        .monitor-pill {
	            min-width: 0;
	        }
	        .monitor-label {
	            min-width: 0;
	        }
	        .order-status-badge {
	            display: inline-flex;
	            align-items: center;
	            justify-content: center;
	            white-space: nowrap;
	            line-height: 1;
	        }
	        .floating-save {
	            position: fixed;
	            right: 24px;
	            bottom: 24px;
	            z-index: 60;
	            animation: toastIn 180ms ease-out both;
	        }
	        .floating-save button {
	            border-radius: 8px;
	        }
	        .dark body, body.dark thead tr {
	            background: rgba(15, 23, 42, 0.96) !important;
	        }
	        .dark body, body.dark thead th {
	            color: #cbd5e1 !important;
	            border-color: rgba(148, 163, 184, 0.18);
	        }
	        .dark body, body.dark tbody tr {
	            border-color: rgba(148, 163, 184, 0.12);
	        }
	        .dark body, body.dark tbody tr:hover {
	            background: rgba(30, 41, 59, 0.42) !important;
	        }
	        .dark body, body.dark .empty-state {
	            color: #94a3b8 !important;
	        }
	        .dark body, body.dark .metric-label {
	            color: #cbd5e1 !important;
	        }
	        .dark body, body.dark .metric-value {
	            color: #f8fafc !important;
	        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); }

	        input, textarea, select {
	            transition: background-color 160ms ease, border-color 160ms ease, box-shadow 160ms ease, color 160ms ease;
	        }

	        input:focus, textarea:focus, select:focus {
	            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
	        }

        .log-terminal { scrollbar-color: rgba(255,255,255,0.15) transparent; }

	        @media (max-width: 767px) {
	            main {
	                padding-left: 16px !important;
	                padding-right: 16px !important;
	            }
	            .monitor-toolbar {
	                width: 100%;
	                gap: 8px;
	            }
	            .monitor-pill {
	                flex: 1 1 auto;
	                padding: 8px 12px;
	                font-size: 12px;
	            }
	            .monitor-label {
	                overflow: hidden;
	                text-overflow: ellipsis;
	                white-space: nowrap;
	            }
	            .orders-filter {
	                width: 100%;
	                display: grid !important;
	                grid-template-columns: repeat(4, minmax(0, 1fr));
	            }
	            .orders-filter button {
	                padding-left: 4px;
	                padding-right: 4px;
	                font-size: 11px;
	                white-space: nowrap;
	            }
	            .orders-list-wrap,
	            .recent-orders-wrap {
	                overflow: visible;
	                min-height: 0;
	            }
	            .recent-orders-wrap {
	                padding: 12px;
	            }
	            .orders-table,
	            .recent-orders-table,
	            .orders-table tbody,
	            .recent-orders-table tbody,
	            .orders-table tr,
	            .recent-orders-table tr,
	            .orders-table td,
	            .recent-orders-table td {
	                display: block;
	                width: 100%;
	            }
	            .orders-table thead,
	            .recent-orders-table thead {
	                display: none;
	            }
	            .orders-table tbody,
	            .recent-orders-table tbody {
	                display: grid;
	                gap: 12px;
	            }
	            .orders-table tr,
	            .recent-orders-table tr {
	                border: 1px solid rgba(203, 213, 225, 0.72);
	                border-radius: 16px;
	                background: rgba(255, 255, 255, 0.72);
	                padding: 14px;
	                box-shadow: 0 10px 24px rgba(15, 23, 42, 0.05);
	            }
	            .dark body, body.dark .orders-table tr,
	            .dark body, body.dark .recent-orders-table tr {
	                background: rgba(15, 23, 42, 0.86);
	                border-color: rgba(71, 85, 105, 0.7);
	            }
	            .orders-table td,
	            .recent-orders-table td {
	                padding: 0 !important;
	                text-align: left !important;
	            }
	            .order-time-cell {
	                color: #64748b;
	                font-size: 11px;
	                margin-bottom: 6px;
	            }
	            .order-no-cell {
	                color: #94a3b8;
	                font-size: 11px;
	                margin-bottom: 8px;
	                overflow-wrap: anywhere;
	            }
	            .order-name-cell {
	                max-width: none !important;
	                white-space: normal;
	                overflow: visible;
	                text-overflow: clip;
	                font-size: 14px;
	                line-height: 1.35;
	                margin-bottom: 12px;
	            }
	            .order-price-cell,
	            .order-paid-cell {
	                display: inline-flex;
	                align-items: baseline;
	                justify-content: space-between;
	                width: calc(50% - 4px);
	                border-radius: 10px;
	                background: rgba(241, 245, 249, 0.78);
	                padding: 8px 10px !important;
	                margin-bottom: 12px;
	            }
	            .dark body, body.dark .order-price-cell,
	            .dark body, body.dark .order-paid-cell {
	                background: rgba(30, 41, 59, 0.72);
	            }
	            .order-price-cell {
	                margin-right: 8px;
	            }
	            .order-price-cell::before {
	                content: "标价";
	                color: #94a3b8;
	                font-size: 11px;
	                font-weight: 700;
	            }
	            .order-paid-cell::before {
	                content: "实付";
	                color: #94a3b8;
	                font-size: 11px;
	                font-weight: 700;
	            }
	            .order-status-cell,
	            .recent-status-cell,
	            .order-action-cell {
	                display: inline-flex !important;
	                align-items: center;
	                width: auto !important;
	                vertical-align: middle;
	            }
	            .order-status-cell,
	            .recent-status-cell {
	                margin-right: 8px;
	            }
	            .order-action-cell > div {
	                justify-content: flex-start;
	                gap: 10px;
	            }
	            .order-action-cell a,
	            .order-action-cell button {
	                white-space: nowrap;
	            }
	            .order-status-badge {
	                min-width: 52px;
	                padding: 6px 9px !important;
	                font-size: 11px !important;
	            }
	            .floating-save {
	                left: 16px;
	                right: 16px;
	                bottom: 88px;
	            }
	            .floating-save button {
	                width: 100%;
	                justify-content: center;
	            }
	        }

        /* Comprehensive Dark Mode - always active via media query */
        @media (prefers-color-scheme: dark) {
            html.dark, .dark body, body.dark {
                background: #111827 !important;
                background-color: #111827 !important;
                color: #f8fafc !important;
            }
            html.dark .glass, .dark body .glass, body.dark .glass,
            html.dark .page-panel, .dark body .page-panel, body.dark .page-panel,
            html.dark .motion-card, .dark body .motion-card, body.dark .motion-card {
                background: rgba(15, 23, 42, 0.9) !important;
                border-color: rgba(71, 85, 105, 0.5) !important;
            }
            html.dark input, html.dark textarea, html.dark select,
            .dark body input, body.dark input,
            .dark body textarea, body.dark textarea,
            .dark body select, body.dark select {
                background: rgba(15, 23, 42, 0.9) !important;
                border-color: rgba(71, 85, 105, 0.5) !important;
                color: #f8fafc !important;
            }
            html.dark .text-slate-500, .dark body .text-slate-500, body.dark .text-slate-500 { color: #94a3b8 !important; }
            html.dark .text-slate-600, .dark body .text-slate-600, body.dark .text-slate-600 { color: #cbd5e1 !important; }
            html.dark .text-slate-700, .dark body .text-slate-700, body.dark .text-slate-700 { color: #e2e8f0 !important; }
            html.dark .text-slate-800, .dark body .text-slate-800, body.dark .text-slate-800 { color: #f1f5f9 !important; }
            html.dark .text-slate-950, .dark body .text-slate-950, body.dark .text-slate-950 { color: #f8fafc !important; }
            html.dark .text-slate-400, .dark body .text-slate-400, body.dark .text-slate-400 { color: #94a3b8 !important; }
            html.dark .text-slate-300, .dark body .text-slate-300, body.dark .text-slate-300 { color: #cbd5e1 !important; }
            html.dark label, .dark body label, body.dark label { color: #cbd5e1 !important; }
            html.dark th, .dark body th, body.dark th { color: #cbd5e1 !important; }
            html.dark td, .dark body td, body.dark td { color: #cbd5e1 !important; }
            html.dark p, .dark body p, body.dark p { color: #cbd5e1 !important; }
            html.dark h1, .dark body h1, body.dark h1,
            html.dark h2, .dark body h2, body.dark h2,
            html.dark h3, .dark body h3, body.dark h3,
            html.dark h4, .dark body h4, body.dark h4 { color: #f8fafc !important; }
            html.dark strong, .dark body strong, body.dark strong { color: #f8fafc !important; }
            html.dark .border-slate-200, .dark body .border-slate-200, body.dark .border-slate-200 { border-color: rgba(71, 85, 105, 0.5) !important; }
            html.dark .border-slate-300, .dark body .border-slate-300, body.dark .border-slate-300 { border-color: rgba(71, 85, 105, 0.5) !important; }
        }

    </style>
</head>
<body x-data="dashboard()" :class="{ 'dark': darkMode }" x-init="init()">

    <div class="fixed top-6 right-6 z-[100] space-y-2" x-cloak>
        <template x-for="(t, i) in toasts" :key="i">
	            <div class="glass toast-card px-5 py-3 rounded-xl shadow-lg text-sm font-medium flex items-center gap-2"
	                 :class="t.type === 'error' ? 'text-red-500' : t.type === 'success' ? 'text-green-600' : 'text-blue-600'">
                <i :data-lucide="t.type === 'error' ? 'alert-circle' : t.type === 'success' ? 'check-circle' : 'info'" class="w-4 h-4 shrink-0"></i>
                <span x-text="t.message"></span>
            </div>
        </template>
    </div>

    <template x-if="!isLoggedIn">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
	                    <div class="glass page-panel w-full max-w-md p-8 rounded-2xl shadow-2xl">
                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-blue-500/50">
                        <i data-lucide="shield-check" class="text-white w-8 h-8"></i>
                    </div>
                    <h1 class="text-2xl font-bold">AliMPay 管理登录</h1>
                    <p class="text-slate-500 dark:text-slate-400 mt-2">请输入管理员密码以继续</p>
                </div>
                <form @submit.prevent="login()">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">管理员密码</label>
                            <input type="password" x-model="loginPass"
                                   class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-white/50 dark:bg-slate-800/50 focus:ring-2 focus:ring-blue-500 outline-none transition-all"
                                   placeholder="••••••••" required autofocus>
                        </div>
                        <button type="submit" :disabled="loading"
                                class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-blue-500/30 flex items-center justify-center space-x-2">
                            <span x-show="!loading">进入仪表盘</span>
                            <span x-show="loading" class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                        </button>
                    </div>
                    <p x-show="error" x-text="error" class="text-red-500 text-center mt-4 text-sm font-medium"></p>
                </form>
            </div>
        </div>
    </template>

    <template x-if="isLoggedIn">
        <div class="flex h-screen overflow-hidden">
            <aside class="w-64 glass hidden md:flex flex-col border-r border-slate-200/50 dark:border-slate-800/50">
                <div class="p-6">
                    <div class="flex items-center space-x-3 mb-8 px-2">
                        <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold italic shadow-blue-500/40 shadow-sm">A</div>
                        <span class="text-xl font-bold tracking-tight">AliMPay</span>
                    </div>
	                    <nav class="relative space-y-1">
	                        <div class="nav-slider" :style="{ transform: `translateY(${activeMenuIndex * 52}px)` }"></div>
	                        <template x-for="item in menuItems" :key="item.id">
	                            <button @click="activeTab = item.id"
	                                    :class="activeTab === item.id
	                                        ? 'nav-item-active'
	                                        : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/70'"
	                                    class="nav-item w-full flex items-center space-x-3 px-4 py-3 rounded-xl group">
                                <i :data-lucide="item.icon" class="w-5 h-5"></i>
                                <span class="font-medium" x-text="item.name"></span>
                            </button>
                        </template>
                    </nav>
                </div>
                <div class="mt-auto p-6 space-y-4">
                    <button @click="logout()" class="w-full flex items-center space-x-3 px-4 py-2 text-red-500 hover:text-red-600">
                        <i data-lucide="log-out" class="w-5 h-5"></i>
                        <span>登出系统</span>
                    </button>
                </div>
            </aside>

            <main class="flex-1 overflow-y-auto p-4 pb-24 md:p-8">
	                <header class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
	                    <div>
	                        <h2 class="text-2xl font-bold tracking-tight text-slate-950 dark:text-white" x-text="currentMenuName">仪表盘</h2>
	                        <p class="text-slate-500 dark:text-slate-300">欢迎回来，这是您的系统状态总览</p>
	                    </div>
                    <div class="monitor-toolbar flex items-center space-x-3">
                        <div class="monitor-pill glass px-4 py-2 rounded-xl flex items-center space-x-2 text-sm font-medium">
	                            <span class="status-dot w-2 h-2 rounded-full" :class="monitorStatusDotClass"></span>
                            <span class="monitor-label" x-text="monitorStatusText"></span>
                        </div>
                        <button @click="refreshData(true)"
                                :disabled="refreshing"
                                :class="refreshing ? 'scale-95 bg-blue-50 text-blue-600 shadow-lg shadow-blue-500/15 dark:bg-blue-500/10 dark:text-blue-200' : 'hover:bg-white/50'"
                                class="glass p-2 rounded-xl transition disabled:cursor-wait disabled:opacity-100">
                            <i data-lucide="refresh-cw" class="w-5 h-5 transition-transform" :class="{ 'animate-spin': refreshing }"></i>
                        </button>
                    </div>
                </header>

	                <div x-show="activeTab === 'dashboard'" class="page-panel" x-cloak>
	                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
	                        <div class="glass motion-card p-6 rounded-3xl relative overflow-hidden group hover:shadow-lg">
                            <div class="absolute -right-4 -top-4 w-24 h-24 bg-blue-500/10 rounded-full group-hover:scale-110 transition-transform"></div>
                            <div class="flex items-center space-x-3 text-slate-500 mb-2">
                                <i data-lucide="banknote" class="w-5 h-5"></i>
                                <span class="text-sm font-medium">今日营收</span>
                            </div>
                            <h3 class="text-3xl font-bold">¥<span x-text="numberFormat(stats.today_revenue ?? 0)">0.00</span></h3>
                        </div>
	                        <div class="glass motion-card p-6 rounded-3xl relative overflow-hidden group hover:shadow-lg">
                            <div class="absolute -right-4 -top-4 w-24 h-24 bg-purple-500/10 rounded-full group-hover:scale-110 transition-transform"></div>
                            <div class="flex items-center space-x-3 text-slate-500 mb-2">
                                <i data-lucide="history" class="w-5 h-5"></i>
                                <span class="text-sm font-medium">昨日营收</span>
                            </div>
                            <h3 class="text-3xl font-bold">¥<span x-text="numberFormat(stats.yesterday_revenue ?? 0)">0.00</span></h3>
                        </div>
	                        <div class="glass motion-card p-6 rounded-3xl relative overflow-hidden group hover:shadow-lg">
                            <div class="absolute -right-4 -top-4 w-24 h-24 bg-green-500/10 rounded-full group-hover:scale-110 transition-transform"></div>
                            <div class="flex items-center space-x-3 text-slate-500 mb-2">
                                <i data-lucide="trending-up" class="w-5 h-5"></i>
                                <span class="text-sm font-medium">累计营收</span>
                            </div>
                            <h3 class="text-3xl font-bold">¥<span x-text="numberFormat(stats.total_revenue ?? 0)">0.00</span></h3>
                        </div>
	                        <div class="glass motion-card p-6 rounded-3xl relative overflow-hidden group hover:shadow-lg">
                            <div class="absolute -right-4 -top-4 w-24 h-24 bg-amber-500/10 rounded-full group-hover:scale-110 transition-transform"></div>
                            <div class="flex items-center space-x-3 text-slate-500 mb-2">
                                <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                                <span class="text-sm font-medium">今日订单</span>
                            </div>
                            <h3 class="text-3xl font-bold" x-text="stats.order_counts.paid">0</h3>
                        </div>
                    </div>
	                    <div class="glass motion-card rounded-3xl overflow-hidden mt-8">
                        <div class="px-8 py-6 border-b border-slate-200/50 dark:border-slate-800/50 flex items-center justify-between">
                            <h3 class="text-lg font-bold">最近订单</h3>
                            <button @click="activeTab = 'orders'" class="text-sm text-blue-600 font-medium hover:underline">查看全部</button>
                        </div>
                        <div class="recent-orders-wrap overflow-x-auto">
                            <table class="recent-orders-table w-full text-left">
                                <thead>
	                                    <tr class="bg-slate-50/50 dark:bg-slate-900/50">
                                        <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-wider">创建时间</th>
                                        <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-wider">订单号</th>
                                        <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-wider">商品名称</th>
                                        <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-wider">成交金额</th>
                                        <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-wider">状态</th>
                                        <th class="px-8 py-4 text-xs font-bold text-slate-400 uppercase tracking-wider text-right">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                                    <template x-for="order in stats.recent_orders" :key="order.id">
                                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20">
                                            <td class="order-time-cell px-8 py-4 text-sm font-medium" x-text="order.add_time"></td>
                                            <td class="order-no-cell px-8 py-4 text-sm font-mono text-slate-500" x-text="order.out_trade_no"></td>
                                            <td class="order-name-cell px-8 py-4 text-sm font-semibold" x-text="order.name"></td>
                                            <td class="order-paid-cell px-8 py-4 text-sm font-bold text-blue-600">¥<span x-text="numberFormat(order.payment_amount || order.price || 0)"></span></td>
                                            <td class="recent-status-cell px-8 py-4 text-sm">
                                                <span :class="orderStatusBadgeClass(order)" class="order-status-badge px-3 py-1 rounded-full text-xs font-bold" x-text="orderStatusLabel(order)"></span>
                                            </td>
                                            <td class="order-action-cell px-8 py-4 text-right">
                                                <a x-show="order.payment_page_url" :href="paymentPageHref(order)" @click.prevent="openPaymentPage(order)" target="_blank" rel="noopener"
                                                   class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm shadow-blue-600/20 transition hover:bg-blue-700 hover:-translate-y-0.5">
                                                    前往支付
                                                </a>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="!stats.recent_orders || stats.recent_orders.length === 0">
	                                        <td colspan="6" class="empty-state px-8 py-12 text-center text-slate-400">暂无订单数据</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

	                <div x-show="activeTab === 'orders'" class="page-panel" x-cloak>
	                    <div class="glass motion-card rounded-3xl overflow-hidden p-6">
                        <div class="flex flex-col md:flex-row justify-between gap-4 mb-6">
                            <div class="flex-1 max-w-md relative">
                                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                                <input type="text" x-model="orderSearch" @input.debounce.500ms="getOrders(1)"
                                       class="w-full pl-10 pr-4 py-2 rounded-xl bg-slate-100/50 dark:bg-slate-800/50 border-none outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="搜索订单号或名称...">
                            </div>
		                            <div class="orders-filter segmented-control flex items-center gap-1 rounded-xl p-1">
		                                <button type="button" @click="setOrderStatusFilter('')" :class="orderStatusFilterClass('')" class="rounded-lg px-3 py-1.5 text-xs font-bold transition">所有状态</button>
		                                <button type="button" @click="setOrderStatusFilter('1')" :class="orderStatusFilterClass('1')" class="rounded-lg px-3 py-1.5 text-xs font-bold transition">已支付</button>
		                                <button type="button" @click="setOrderStatusFilter('0')" :class="orderStatusFilterClass('0')" class="rounded-lg px-3 py-1.5 text-xs font-bold transition">待支付</button>
		                                <button type="button" @click="setOrderStatusFilter('expired')" :class="orderStatusFilterClass('expired')" class="rounded-lg px-3 py-1.5 text-xs font-bold transition">已过期</button>
	                            </div>
                        </div>
                        <div class="orders-list-wrap overflow-x-auto min-h-[400px]">
                            <table class="orders-table w-full text-left">
                                <thead>
                                    <tr class="text-xs font-bold text-slate-400 uppercase tracking-wider border-b border-slate-200/50 dark:border-slate-800/50">
	                                        <th class="px-4 py-4">时间</th>
                                        <th class="px-4 py-4">订单号</th>
                                        <th class="px-4 py-4">名称</th>
                                        <th class="px-4 py-4 text-right">标价</th>
                                        <th class="px-4 py-4 text-right">实付</th>
                                        <th class="px-4 py-4 text-center">状态</th>
                                        <th class="px-4 py-4 text-right">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50">
                                    <template x-for="order in orders.orders" :key="order.id">
                                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20">
                                            <td class="order-time-cell px-4 py-4 text-xs font-medium" x-text="order.add_time"></td>
                                            <td class="order-no-cell px-4 py-4 text-xs font-mono text-slate-500" x-text="order.out_trade_no"></td>
                                            <td class="order-name-cell px-4 py-4 text-sm font-semibold truncate max-w-[150px]" x-text="order.name"></td>
                                            <td class="order-price-cell px-4 py-4 text-sm font-medium text-right">¥<span x-text="numberFormat(order.price)"></span></td>
                                            <td class="order-paid-cell px-4 py-4 text-sm font-bold text-right text-blue-600">¥<span x-text="numberFormat(order.payment_amount || 0)"></span></td>
                                            <td class="order-status-cell px-4 py-4 text-center">
                                                <span :class="orderStatusBadgeClass(order)" class="order-status-badge px-3 py-1 rounded-full text-[10px] font-bold" x-text="orderStatusLabel(order, true)"></span>
                                            </td>
                                            <td class="order-action-cell px-4 py-4 text-right">
                                                <div class="flex items-center justify-end gap-3">
                                                    <a x-show="order.payment_page_url" :href="paymentPageHref(order)" @click.prevent="openPaymentPage(order)" target="_blank" rel="noopener"
                                                       class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-bold text-white shadow-sm shadow-blue-600/20 transition hover:bg-blue-700 hover:-translate-y-0.5">
                                                        前往支付
                                                    </a>
                                                    <button x-show="order.display_status === 'pending'" @click="updateOrderStatus(order.id, 1)"
                                                            class="text-xs font-bold text-blue-600 hover:text-blue-700 dark:text-blue-300 dark:hover:text-blue-200">标记支付</button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="!orders.orders || orders.orders.length === 0">
	                                        <td colspan="7" class="empty-state px-4 py-12 text-center text-slate-400">暂无订单</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-8 flex items-center justify-between">
                            <p class="text-sm text-slate-500">共 <span x-text="orders.pagination.total">0</span> 条订单</p>
                            <div class="flex space-x-2">
                                <button @click="getOrders(orders.pagination.page - 1)" :disabled="orders.pagination.page <= 1"
                                        class="glass px-4 py-2 rounded-xl text-sm font-bold disabled:opacity-50">上一页</button>
                                <button @click="getOrders(orders.pagination.page + 1)" :disabled="orders.pagination.page >= orders.pagination.total_pages"
                                        class="glass px-4 py-2 rounded-xl text-sm font-bold disabled:opacity-50">下一页</button>
                            </div>
                        </div>
                    </div>
                </div>

		                <div x-show="activeTab === 'settings'" class="page-panel max-w-4xl mx-auto space-y-6" x-cloak>
			                    <div class="glass rounded-2xl p-3">
			                        <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
		                            <button type="button" @click="settingsSection = 'merchant'" :class="settingsTabClass('merchant')" class="rounded-xl border px-3 py-2.5 text-xs font-bold transition">商户配置 (CodePay)</button>
		                            <button type="button" @click="settingsSection = 'alipay'" :class="settingsTabClass('alipay')" class="rounded-xl border px-3 py-2.5 text-xs font-bold transition">支付宝连接</button>
		                            <button type="button" @click="settingsSection = 'payment'" :class="settingsTabClass('payment')" class="rounded-xl border px-3 py-2.5 text-xs font-bold transition">收款模式</button>
		                            <button type="button" @click="settingsSection = 'monitor'" :class="settingsTabClass('monitor')" class="rounded-xl border px-3 py-2.5 text-xs font-bold transition">监控参数</button>
		                        </div>
		                    </div>
		                    <template x-if="editConfig.app_id !== undefined">
		                        <div class="space-y-6">
				                    <template x-if="settingsSection === 'alipay'">
				                    <div id="alipay-config-card" class="glass motion-card settings-content-card scroll-mt-6 rounded-3xl p-8 space-y-6">
                        <div class="flex items-center space-x-4 pb-4 border-b border-slate-200/50 dark:border-slate-800/50">
                            <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center">
                                <i data-lucide="link" class="text-blue-600 w-5 h-5"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold">支付宝连接</h3>
                                <p class="text-xs text-slate-400">开放平台应用配置</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">网关地址</label>
                                <input type="text" x-model="editConfig.server_url" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 font-mono text-sm">
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">App ID</label>
                                <input type="text" x-model="editConfig.app_id" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 font-mono text-sm">
                            </div>
	                            <div class="space-y-1.5">
	                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">收款用户 ID</label>
	                                <input type="text" x-model="editConfig.transfer_user_id" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 font-mono text-sm">
	                            </div>
		                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">应用私钥</label>
                            <textarea x-model="editConfig.private_key" rows="3" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 font-mono text-xs" placeholder="留空则不修改"></textarea>
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">支付宝公钥</label>
                            <textarea x-model="editConfig.alipay_public_key" rows="3" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 font-mono text-xs" placeholder="留空则不修改"></textarea>
                        </div>
                        <div class="flex items-center gap-3 pt-2">
                            <button @click="testAlipay()" :disabled="alipayTesting" class="flex shrink-0 items-center gap-2 rounded-xl bg-green-600 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-green-500/20 transition hover:bg-green-700 disabled:opacity-50">
                                <i data-lucide="shield-check" class="w-4 h-4"></i>
                                <span x-text="alipayTesting ? '测试中...' : '测试连接'"></span>
                            </button>
                            <div x-show="alipayTestResults.length" x-cloak class="relative">
                                <button type="button"
                                        @click="showAlipayTestPopover = !showAlipayTestPopover"
                                        @mouseenter="showAlipayTestPopover = true"
                                        :class="alipayTestFailedCount === 0 ? 'border-green-200 bg-green-50 text-green-700 dark:border-green-500/30 dark:bg-green-500/10 dark:text-green-200' : 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200'"
                                        class="flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-bold shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                                    <svg x-show="alipayTestFailedCount === 0" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 6 9 17l-5-5"></path>
                                        <circle cx="12" cy="12" r="10"></circle>
                                    </svg>
                                    <svg x-show="alipayTestFailedCount !== 0" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <path d="M12 8v4"></path>
                                        <path d="M12 16h.01"></path>
                                    </svg>
                                    <span x-text="alipayTestSummaryText"></span>
                                </button>
                                <div x-show="showAlipayTestPopover"
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                     x-transition:leave="transition ease-in duration-150"
                                     x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                     x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                                     @mouseenter="showAlipayTestPopover = true"
                                     @mouseleave="showAlipayTestPopover = false"
                                     @click.outside="showAlipayTestPopover = false"
                                     class="absolute bottom-full left-0 z-50 mb-2 w-[min(520px,calc(100vw-3rem))] origin-bottom-left rounded-xl border border-slate-200 bg-white p-3 shadow-2xl shadow-slate-900/15 dark:border-slate-700 dark:bg-slate-900 dark:shadow-black/40">
                                    <div class="mb-2 flex items-center justify-between gap-3 border-b border-slate-100 pb-2 dark:border-slate-800">
                                        <p class="text-xs font-black text-slate-900 dark:text-slate-100">支付宝连接检测</p>
                                        <span :class="alipayTestFailedCount === 0 ? 'text-green-600 dark:text-green-300' : 'text-red-600 dark:text-red-300'" class="text-[11px] font-bold" x-text="alipayTestSummaryText"></span>
                                    </div>
                                    <div class="max-h-72 space-y-2 overflow-y-auto pr-1">
                                        <template x-for="check in alipayTestResults" :key="check.name + '-' + check.status">
                                            <div :class="check.status === 'ok' ? 'bg-green-50 text-green-800 dark:bg-green-500/10 dark:text-green-100' : 'bg-red-50 text-red-800 dark:bg-red-500/10 dark:text-red-100'" class="rounded-lg px-3 py-2">
                                                <div class="flex items-start gap-2">
                                                    <svg x-show="check.status === 'ok'" xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M20 6 9 17l-5-5"></path>
                                                    </svg>
                                                    <svg x-show="check.status !== 'ok'" xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <path d="m15 9-6 6"></path>
                                                        <path d="m9 9 6 6"></path>
                                                    </svg>
                                                    <div class="min-w-0">
                                                        <p class="text-xs font-black" x-text="check.name"></p>
                                                        <p class="mt-1 break-words text-xs leading-5 opacity-90" x-text="check.message"></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
	                    </div>
	                    </template>

	                    <template x-if="settingsSection === 'payment'">
	                    <div id="payment-config-card" class="glass motion-card settings-content-card scroll-mt-6 rounded-3xl p-8 space-y-6">
	                        <div class="flex flex-col gap-4 pb-4 border-b border-slate-200/50 dark:border-slate-800/50 md:flex-row md:items-center md:justify-between">
	                            <div class="flex items-center space-x-4">
	                                <div class="w-10 h-10 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center">
	                                    <i data-lucide="qr-code" class="text-green-600 w-5 h-5"></i>
	                                </div>
	                                <div>
	                                    <h3 class="text-lg font-bold">收款模式</h3>
	                                    <p class="text-xs text-slate-400">经营码 / 转账收款配置</p>
	                                </div>
	                            </div>
	                            <div class="payment-mode-toggle inline-flex w-full rounded-2xl p-1.5 md:w-auto md:min-w-[320px]">
	                                <span
	                                    aria-hidden="true"
	                                    class="payment-mode-slider"
	                                    :style="businessQrModeEnabled ? 'transform: translateX(100%);' : 'transform: translateX(0%);'"
	                                ></span>
	                                <button
	                                    type="button"
	                                    @click.prevent.stop="setPaymentMode('transfer')"
	                                    :class="businessQrModeEnabled ? 'payment-mode-button' : 'payment-mode-button payment-mode-button-active'"
	                                    class="payment-mode-button flex-1 rounded-xl px-4 py-3 text-left"
	                                >
	                                    <span class="block text-sm font-bold">转账模式</span>
	                                    <span class="mt-1 block text-[11px] opacity-80">付款带备注，按订单号匹配</span>
	                                </button>
	                                <button
	                                    type="button"
	                                    @click.prevent.stop="setPaymentMode('business_qr')"
	                                    :class="businessQrModeEnabled ? 'payment-mode-button payment-mode-button-active' : 'payment-mode-button'"
	                                    class="payment-mode-button flex-1 rounded-xl px-4 py-3 text-left"
	                                >
	                                    <span class="block text-sm font-bold">经营码模式</span>
	                                    <span class="mt-1 block text-[11px] opacity-80">金额加时间匹配，无需备注</span>
	                                </button>
	                            </div>
	                        </div>
	                        <template x-if="businessQrModeEnabled">
	                            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 pl-2">
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">金额偏移 (元)</label>
                                    <input type="number" step="0.01" x-model="editConfig.payment.business_qr_mode.amount_offset" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 font-mono text-sm">
                                </div>
                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">匹配容差 (秒)</label>
                                    <input type="number" x-model="editConfig.payment.business_qr_mode.match_tolerance" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 font-mono text-sm">
                                </div>
	                                <div class="space-y-1.5">
	                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">付款超时 (秒)</label>
	                                    <input type="number" x-model="editConfig.payment.business_qr_mode.payment_timeout" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 font-mono text-sm">
	                                </div>
	                                <div class="space-y-1.5 md:col-span-3">
	                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">经营码路径</label>
	                                    <input type="text" x-model="editConfig.payment.business_qr_mode.qr_code_path" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 font-mono text-xs">
	                                    <p class="text-[10px] text-slate-400">上传经营码和支付页读取都会使用这个路径</p>
	                                </div>
	                            </div>
	                        </template>
	                        <template x-if="!businessQrModeEnabled">
	                            <div class="space-y-6">
			                            <div class="config-toggle-row flex items-center justify-between p-4 rounded-xl">
			                                <div>
			                                    <p class="font-semibold text-sm">防风控转账 URL</p>
		                                    <p class="hint-text text-xs text-slate-500 mt-0.5">多层嵌套 scheme，降低转账链接触发风控概率</p>
		                                </div>
	                                <button type="button" @click.prevent.stop="toggleAntiRiskUrl()"
	                                        :class="antiRiskUrlEnabled ? 'bg-blue-600' : 'bg-slate-300 dark:bg-slate-600'"
	                                        class="relative w-12 h-6 rounded-full">
		                                    <span :class="antiRiskUrlEnabled ? 'translate-x-6' : 'translate-x-0.5'" class="absolute top-0.5 left-0 w-5 h-5 bg-white rounded-full shadow"></span>
		                                </button>
		                            </div>
		                            <template x-if="antiRiskUrlEnabled">
		                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 pl-2">
		                                    <div class="space-y-1.5">
		                                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">外层 App ID</label>
		                                        <input type="text" x-model="editConfig.payment.anti_risk_url.outer_app_id" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 font-mono text-sm">
		                                    </div>
		                                    <div class="space-y-1.5">
		                                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">内层 App ID</label>
		                                        <input type="text" x-model="editConfig.payment.anti_risk_url.inner_app_id" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 font-mono text-sm">
		                                    </div>
		                                    <div class="space-y-1.5">
		                                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">落地页 URL</label>
		                                        <input type="text" x-model="editConfig.payment.anti_risk_url.base_urls.mdeduct_landing" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 font-mono text-xs">
		                                    </div>
		                                    <div class="space-y-1.5">
		                                        <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">Scheme 渲染 URL</label>
		                                        <input type="text" x-model="editConfig.payment.anti_risk_url.base_urls.render_scheme" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 font-mono text-xs">
		                                    </div>
		                                </div>
		                            </template>
	                            </div>
	                        </template>
	                        <template x-if="businessQrModeEnabled">
	                            <div class="config-toggle-row p-4 rounded-xl space-y-3">
	                                <div class="flex items-center gap-4">
	                                    <div>
	                                        <p class="font-semibold text-sm">经营码二维码</p>
		                                    <p class="hint-text text-xs text-slate-500 mt-0.5">PNG/JPG，最大 2MB</p>
	                                    </div>
	                                    <span x-show="qrInfo.modified" x-text="qrInfo.modified" class="text-[10px] text-slate-400 font-mono"></span>
	                                </div>
	                                <div class="flex items-center gap-4">
	                                    <label class="w-24 h-24 rounded-xl border-2 border-dashed border-slate-300 dark:border-slate-600 flex flex-col items-center justify-center cursor-pointer hover:border-blue-400 hover:bg-blue-50/50 dark:hover:bg-blue-900/20 group" :class="uploading ? 'opacity-50 pointer-events-none' : ''">
	                                        <i data-lucide="upload" class="w-5 h-5 text-slate-400 group-hover:text-blue-500"></i>
	                                        <span class="text-[10px] text-slate-400 mt-1">上传</span>
	                                        <input type="file" accept="image/png,image/jpeg,image/jpg" class="hidden" @change="uploadQrCode($event)">
	                                    </label>
	                                    <div x-show="qrInfo.url" class="w-24 h-24 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden bg-white">
	                                        <img :src="qrInfo.url" :key="qrInfo.url" alt="经营码" class="w-full h-full object-contain">
	                                    </div>
	                                    <div x-show="!qrInfo.url" class="w-24 h-24 rounded-xl border border-slate-200 dark:border-slate-700 flex items-center justify-center bg-slate-100 dark:bg-slate-800">
	                                        <span class="text-[10px] text-slate-400">未上传</span>
	                                    </div>
	                                </div>
	                            </div>
	                        </template>
	                    </div>
	                    </template>

				                    <template x-if="settingsSection === 'monitor'">
				                    <div id="monitor-config-card" class="glass motion-card settings-content-card scroll-mt-6 rounded-3xl p-8 space-y-6">
                        <div class="flex items-center space-x-4 pb-4 border-b border-slate-200/50 dark:border-slate-800/50">
                            <div class="w-10 h-10 bg-amber-100 dark:bg-amber-900/30 rounded-xl flex items-center justify-center">
                                <i data-lucide="activity" class="text-amber-600 w-5 h-5"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold">监控参数</h3>
                                <p class="text-xs text-slate-400">账单轮询与订单管理</p>
                            </div>
                        </div>
	                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
		                            <div class="space-y-1.5">
		                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">查询历史 (分钟)</label>
		                                <input type="number" x-model="editConfig.payment.query_minutes_back" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 font-mono text-sm">
                                <p class="text-[10px] text-slate-400">每次轮询查询多少分钟内的账单</p>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">订单超时 (秒)</label>
                                <input type="number" x-model="editConfig.payment.order_timeout" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 font-mono text-sm">
                                <p class="text-[10px] text-slate-400">超时后订单自动删除</p>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">检查间隔 (秒)</label>
                                <input type="number" x-model="editConfig.payment.check_interval" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 font-mono text-sm">
	                                <p class="text-[10px] text-slate-400">轮询账单的时间间隔</p>
	                            </div>
		                        </div>
                        <div class="config-toggle-row flex items-center justify-between p-4 rounded-xl">
	                            <div>
	                                <p class="font-semibold text-sm">自动清理过期订单</p>
	                                <p class="hint-text text-xs text-slate-500 mt-0.5">每次轮询时自动删除超时的待支付订单</p>
	                            </div>
                            <button type="button" @click.prevent.stop="toggleAutoCleanup()"
                                    :class="autoCleanupEnabled ? 'bg-blue-600' : 'bg-slate-300 dark:bg-slate-600'"
                                    class="relative w-12 h-6 rounded-full">
                                <span :class="autoCleanupEnabled ? 'translate-x-6' : 'translate-x-0.5'" class="absolute top-0.5 left-0 w-5 h-5 bg-white rounded-full shadow"></span>
                            </button>
                        </div>
	                    </div>
	                    </template>

				                    <template x-if="settingsSection === 'merchant'">
				                    <div id="merchant-config-card" class="glass motion-card settings-content-card scroll-mt-6 rounded-3xl p-8 space-y-6">
                        <div class="flex items-center space-x-4 pb-4 border-b border-slate-200/50 dark:border-slate-800/50">
                            <div class="w-10 h-10 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center">
                                <i data-lucide="key-round" class="text-purple-600 w-5 h-5"></i>
                            </div>
                            <div>
	                                <h3 class="text-lg font-bold">商户配置 (CodePay)</h3>
	                                <p class="text-xs text-slate-400">商户凭证、费率、状态与管理密码</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">商户 ID</label>
                                <input type="text" x-model="merchantInfo.merchant_id" readonly class="w-full px-4 py-2.5 rounded-xl bg-slate-100/80 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 font-mono text-sm text-slate-500 cursor-not-allowed">
                                <p class="text-[10px] text-slate-400">只读，对接时使用此 ID</p>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">商户密钥</label>
                                <div class="flex gap-2">
                                    <input :type="showMerchantKey ? 'text' : 'password'" x-model="showMerchantKey ? regeneratedKey : merchantInfo.merchant_key" readonly class="flex-1 px-4 py-2.5 rounded-xl bg-slate-100/80 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 font-mono text-xs text-slate-500 cursor-not-allowed">
                                    <button @click="showMerchantKey = !showMerchantKey" class="px-3 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800" x-text="showMerchantKey ? '隐藏' : '显示'"></button>
                                </div>
                                <button @click="regenerateKey()" class="text-xs text-red-500 hover:text-red-600 font-semibold">重新生成密钥</button>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">管理员密码</label>
                                <input type="password" x-model="merchantInfo.admin_password" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 font-mono text-sm" placeholder="留空则不修改">
                            </div>
	                            <div class="space-y-1.5">
	                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">商户状态</label>
	                                <div class="segmented-control grid grid-cols-2 gap-1 rounded-xl p-1">
	                                    <button type="button" @click="merchantInfo.status = 1" :class="merchantStatusClass(1)" class="rounded-lg px-3 py-2 text-sm font-bold transition">启用</button>
	                                    <button type="button" @click="merchantInfo.status = 0" :class="merchantStatusClass(0)" class="rounded-lg px-3 py-2 text-sm font-bold transition">停用</button>
	                                </div>
	                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">商户费率 (%)</label>
                                <input type="number" min="0" max="100" step="0.01" x-model="merchantInfo.rate" class="w-full px-4 py-2.5 rounded-xl bg-white/50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 font-mono text-sm">
                                <p class="text-[10px] text-slate-400">CodePay 商户查询接口返回值</p>
                            </div>
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider">创建时间</label>
                                <input type="text" x-model="merchantInfo.created_at" readonly class="w-full px-4 py-2.5 rounded-xl bg-slate-100/80 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 font-mono text-sm text-slate-500 cursor-not-allowed">
                            </div>
                        </div>
                        <div class="flex justify-end pt-2">
                            <div class="flex flex-col items-stretch gap-3 rounded-2xl border border-slate-200/70 bg-slate-50/80 px-4 py-4 dark:border-slate-700/70 dark:bg-slate-900/40 sm:flex-row sm:items-center">
                                <div class="min-w-0 sm:mr-2">
                                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-100">备份 / 恢复</p>
                                    <p class="text-[11px] text-slate-400">导出当前配置、订单库和经营码，恢复前会自动创建现场快照</p>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button"
                                            @click="createBackup()"
                                            :disabled="backupLoading || restoreLoading"
                                            class="inline-flex min-w-[112px] items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-center text-xs font-bold leading-none text-slate-700 transition hover:bg-slate-100 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-100 dark:hover:bg-slate-800">
                                        <i data-lucide="archive" class="w-4 h-4"></i>
                                        <span x-text="backupLoading ? '生成中...' : '导出备份'"></span>
                                    </button>
                                    <button type="button"
                                            @click="$refs.backupRestoreInput.click()"
                                            :disabled="backupLoading || restoreLoading"
                                            class="inline-flex min-w-[112px] items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-center text-xs font-bold leading-none text-white shadow-sm shadow-blue-500/20 transition hover:bg-blue-700 disabled:opacity-60">
                                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                                        <span x-text="restoreLoading ? '恢复中...' : '恢复备份'"></span>
                                    </button>
                                    <input type="file"
                                           x-ref="backupRestoreInput"
                                           accept=".zip,application/zip"
                                           class="hidden"
                                           @change="restoreBackup($event)">
                                </div>
                            </div>
                        </div>
	                    </div>
	                    </template>
	                    </div>
	                    </template>

                </div>

                <div x-show="activeTab === 'settings' && settingsDirty" x-transition class="floating-save" x-cloak>
                    <button @click="saveAll()" :disabled="loading"
                            class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-bold py-3 px-6 shadow-xl shadow-blue-500/30 text-sm">
                        <i data-lucide="save" class="w-4 h-4"></i>
                        <span x-text="loading ? '保存中...' : '保存更改'"></span>
                    </button>
                </div>

	                <div x-show="activeTab === 'system'" class="page-panel space-y-8" x-cloak>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div class="glass p-8 rounded-3xl space-y-6">
	                            <h3 class="text-xl font-bold text-slate-950 dark:text-white">健康状况</h3>
	                            <div class="space-y-4">
	                                <div class="flex justify-between items-center text-sm">
	                                    <span class="metric-label text-slate-500 dark:text-slate-300 font-medium">运行状态</span>
	                                    <span :class="monitorStatusTextClass" class="font-bold uppercase tracking-widest text-[10px]" x-text="monitorStatusLabel">UNKNOWN</span>
	                                </div>
	                                <div class="flex justify-between items-center text-sm">
	                                    <span class="metric-label text-slate-500 dark:text-slate-300 font-medium">最后活跃</span>
	                                    <span class="metric-value font-bold text-slate-950 dark:text-white" x-text="health.last_run || 'N/A'"></span>
	                                </div>
	                                <div class="flex justify-between items-center text-sm">
	                                    <span class="metric-label text-slate-500 dark:text-slate-300 font-medium">健康评分</span>
	                                    <span class="font-bold text-blue-600" x-text="(health.health_score || 0) + '%'"></span>
	                                </div>
	                            </div>
                            <button @click="triggerMonitor()" :disabled="loading"
                                    class="w-full glass py-3 rounded-xl text-sm font-bold hover:bg-white/40 flex items-center justify-center space-x-2">
                                <i data-lucide="zap" class="w-4 h-4"></i>
                                <span>强制轮询一次账单</span>
                            </button>
                        </div>
                        <div class="glass p-8 rounded-3xl md:col-span-2 flex flex-col h-[600px]">
                            <div class="flex items-center justify-between mb-6">
	                                <h3 class="text-xl font-bold font-mono text-slate-950 dark:text-white">System Logs</h3>
                                <div class="flex space-x-2">
                                    <button @click="logType = 'info'; getLogs()" :class="logType === 'info' ? 'bg-blue-600 text-white' : 'glass'" class="px-3 py-1 rounded-lg text-xs font-bold">INFO</button>
                                    <button @click="logType = 'error'; getLogs()" :class="logType === 'error' ? 'bg-red-600 text-white' : 'glass'" class="px-3 py-1 rounded-lg text-xs font-bold">ERROR</button>
                                </div>
                            </div>
                            <div class="flex-1 bg-slate-900 rounded-2xl p-4 overflow-y-auto font-mono text-[10px] text-slate-300 whitespace-pre log-terminal border border-white/5">
                                <code x-text="logs">Loading logs...</code>
                            </div>
                        </div>
                    </div>
                </div>

                <nav class="mobile-bottom-nav fixed bottom-0 left-0 right-0 z-40 grid grid-cols-4 gap-1 border-t border-slate-200 bg-white/95 p-2 shadow-lg shadow-slate-900/10 backdrop-blur md:hidden dark:border-slate-800 dark:bg-slate-950/95" x-ref="mobileNav">
                    <div class="mobile-slider" x-ref="mobileSlider"
                         :style="{
                             left: (activeMenuIndex * (100 / menuItems.length)) + '%',
                             width: (100 / menuItems.length) + '%'
                         }"></div>
                    <template x-for="item in menuItems" :key="item.id">
	                        <button @click="activeTab = item.id"
	                                :class="activeTab === item.id ? 'text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800/80'"
	                                class="nav-item flex flex-col items-center justify-center gap-1 rounded-lg px-2 py-2 text-[11px] font-bold transition-colors duration-200">
                            <i :data-lucide="item.icon" class="w-4 h-4"></i>
                            <span x-text="item.name"></span>
                        </button>
                    </template>
                </nav>
            </main>
        </div>
    </template>

    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.getRegistrations()
                .then(registrations => registrations.forEach(registration => registration.unregister()))
                .catch(() => {});
        }

        function dashboard() {
            return {
                isLoggedIn: <?php echo $isLoggedIn ? 'true' : 'false'; ?>,
                csrfToken: '',
                loginPass: '',
                error: '',
                loading: false,
                refreshing: false,
                activeTab: 'dashboard',
                settingsSection: 'merchant',
                darkMode: window.matchMedia('(prefers-color-scheme: dark)').matches,
                stats: { today_revenue: 0, total_revenue: 0, order_counts: { total: 0, paid: 0 }, recent_orders: [] },
                orders: { orders: [], pagination: { total: 0, page: 1, limit: 20 } },
                editConfig: { payment: { business_qr_mode: {}, anti_risk_url: { base_urls: {} } } },
                businessQrModeEnabled: false,
                antiRiskUrlEnabled: false,
                autoCleanupEnabled: true,
                settingsSnapshot: '',
                merchantInfo: { merchant_id: '', merchant_key: '********', admin_password: '********', created_at: '', status: 1, rate: '96' },
                realMerchantKey: '',
                regeneratedKey: '',
                showMerchantKey: false,
                qrInfo: { exists: false, modified: null, url: null },
                uploading: false,
                backupLoading: false,
                restoreLoading: false,
                alipayTesting: false,
                alipayTestResults: [],
                showAlipayTestPopover: false,
                health: {},
                logs: '',
                logType: 'info',
                orderSearch: '',
                orderStatusFilter: '',
                toasts: [],
                menuItems: [
                    { id: 'dashboard', name: '仪表盘', icon: 'layout-dashboard' },
                    { id: 'orders', name: '订单流水', icon: 'list-ordered' },
                    { id: 'settings', name: '系统设置', icon: 'sliders' },
                    { id: 'system', name: '监控状态', icon: 'activity' }
                ],

                init() {
                    // Always sync darkMode with system preference
                    var self = this;
                    this.$watch('darkMode', val => {
                        document.documentElement.classList.toggle('dark', val);
                    });
                    this.$watch('activeTab', val => {
                        this.refreshData();
                        this.$nextTick(() => lucide.createIcons());
                    });
                    this.$watch('settingsSection', () => {
                        this.$nextTick(() => lucide.createIcons());
                    });
                    if (this.isLoggedIn) {
                        this.refreshData();
                        setInterval(() => this.getHealth(), 30000);
                    }
                    this.$nextTick(() => lucide.createIcons());

                    // Listen for system dark mode changes
                    var mq = window.matchMedia('(prefers-color-scheme: dark)');
                    mq.addEventListener('change', function(e) {
                        self.darkMode = e.matches;
                        document.documentElement.classList.toggle('dark', e.matches);
                    });
                },

                updateCsrfToken(token) {
                    if (token) {
                        this.csrfToken = token;
                    }
                },

                async apiRequest(url, options = {}) {
                    const headers = options.headers || {};
                    headers['X-CSRF-Token'] = this.csrfToken;
                    options.headers = headers;
                    const res = await fetch(url, options);
                    const data = await res.json();
                    if (data._csrf_token) {
                        this.updateCsrfToken(data._csrf_token);
                    }
                    return { res, data };
                },

	                get currentMenuName() {
	                    return this.menuItems.find(i => i.id === this.activeTab)?.name || '仪表盘';
	                },

	                get activeMenuIndex() {
	                    return Math.max(0, this.menuItems.findIndex(i => i.id === this.activeTab));
	                },

	                get alipayTestFailedCount() {
	                    return this.alipayTestResults.filter(check => check.status !== 'ok').length;
	                },

	                get alipayTestSummaryText() {
	                    if (!this.alipayTestResults.length) return '';
	                    if (this.alipayTestFailedCount === 0) return '检测通过';
	                    return `${this.alipayTestFailedCount} 项需处理`;
	                },

	                get settingsDirty() {
	                    return this.settingsSnapshot !== '' && this.settingsSnapshot !== this.currentSettingsSnapshot();
	                },

	                get monitorStatusLabel() {
	                    const labels = {
	                        healthy: 'HEALTHY',
	                        running: 'RUNNING',
	                        dormant: 'DORMANT',
	                        stale: 'STALE',
	                        error: 'ERROR',
	                        unknown: 'UNKNOWN'
	                    };
	                    return labels[this.health.status] || 'UNKNOWN';
	                },

	                get monitorStatusText() {
	                    const labels = {
	                        healthy: '监控服务运行中',
	                        running: '监控服务运行中',
	                        dormant: '监控服务待启动',
	                        stale: '监控服务待刷新',
	                        error: '监控服务异常',
	                        unknown: '监控状态未知'
	                    };
	                    return labels[this.health.status] || '监控状态未知';
	                },

	                get monitorStatusDotClass() {
	                    const classes = {
	                        healthy: 'bg-green-500',
	                        running: 'bg-blue-500',
	                        dormant: 'bg-amber-500',
	                        stale: 'bg-amber-500',
	                        error: 'bg-red-500',
	                        unknown: 'bg-slate-400'
	                    };
	                    return classes[this.health.status] || 'bg-slate-400';
	                },

	                get monitorStatusTextClass() {
	                    const classes = {
	                        healthy: 'text-green-500',
	                        running: 'text-blue-500',
	                        dormant: 'text-amber-500',
	                        stale: 'text-amber-500',
	                        error: 'text-red-500',
	                        unknown: 'text-slate-400'
	                    };
	                    return classes[this.health.status] || 'text-slate-400';
	                },

		                settingsTabClass(id) {
		                    if (this.settingsSection === id) {
		                        return 'settings-tab-active';
		                    }

		                    return 'settings-tab';
		                },

		                orderStatusFilterClass(status) {
		                    if (this.orderStatusFilter === status) {
		                        return 'segmented-option-active';
		                    }

	                    return 'segmented-option';
		                },

		                setOrderStatusFilter(status) {
		                    this.orderStatusFilter = status;
		                    this.getOrders(1);
		                },

	                orderStatusLabel(order, compact = false) {
	                    const labels = {
	                        paid: compact ? '已付' : '已支付',
	                        pending: compact ? '待付' : '待支付',
	                        expired: compact ? '过期' : '已过期'
	                    };

	                    const key = order.display_status || (order.status == 1 ? 'paid' : 'pending');
	                    return labels[key] || labels.pending;
	                },

	                orderStatusBadgeClass(order) {
	                    const key = order.display_status || (order.status == 1 ? 'paid' : 'pending');
	                    const classes = {
	                        paid: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
	                        pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
	                        expired: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
	                    };

	                    return classes[key] || classes.pending;
	                },

	                paymentPageHref(order) {
	                    if (!order || !order.payment_page_url) {
	                        return '#';
	                    }

	                    return new URL(order.payment_page_url, window.location.origin).toString();
	                },

	                openPaymentPage(order) {
	                    const href = this.paymentPageHref(order);
	                    if (href === '#') {
	                        this.toast('支付链接不可用', 'error');
	                        return;
	                    }

	                    window.open(href, '_blank', 'noopener');
	                },

	                merchantStatusClass(status) {
	                    if (Number(this.merchantInfo.status) === Number(status)) {
	                        return status === 1 ? 'segmented-option-active' : 'bg-red-600 text-white shadow-sm shadow-red-500/20';
	                    }

	                    return 'segmented-option';
	                },

	                toast(message, type = 'info', duration = 3000) {
                    const toast = { id: Date.now() + Math.random(), message, type };
                    this.toasts.push(toast);
                    setTimeout(() => {
                        this.toasts = this.toasts.filter(t => t.id !== toast.id);
                    }, duration);
                },

                async login() {
                    this.loading = true;
                    this.error = '';
                    try {
                        let formData = new FormData();
                        formData.append('action', 'login');
                        formData.append('password', this.loginPass);
                        const { data } = await this.apiRequest('admin_api.php?action=login', { method: 'POST', body: formData });
                        if (data.success) {
                            this.isLoggedIn = true;
                            this.refreshData();
                            if (data.is_default_password) {
                                setTimeout(() => {
                                    this.activeTab = 'settings';
                                    this.toast('安全提示：您正在使用默认密码，请尽快修改', 'error', 5000);
                                }, 500);
                            }
                        } else {
                            this.error = data.message;
                        }
                    } catch (e) {
                        this.error = '连接服务器失败';
                    } finally {
                        this.loading = false;
                    }
                },

                async logout() {
                    this.isLoggedIn = false;
                    await this.apiRequest('admin_api.php?action=logout', { method: 'POST' });
                },

                async refreshData(showFeedback = false) {
                    if (!this.isLoggedIn) return;
                    const refreshStartedAt = Date.now();
                    if (showFeedback) {
                        this.refreshing = true;
                    }
                    this.loading = true;
                    try {
                        if (this.activeTab === 'dashboard') await this.getStats();
                        if (this.activeTab === 'orders') await this.getOrders(1);
                        if (this.activeTab === 'settings') { await this.getConfig(); await this.getQrCode(); }
                        if (this.activeTab === 'system') await this.getLogs();
                        await this.getHealth();
                        this.$nextTick(() => lucide.createIcons());
                    } finally {
                        this.loading = false;
                        if (showFeedback) {
                            const elapsed = Date.now() - refreshStartedAt;
                            const remaining = Math.max(0, 450 - elapsed);
                            window.setTimeout(() => {
                                this.refreshing = false;
                            }, remaining);
                        }
                    }
                },

                async getStats() {
                    const { data } = await this.apiRequest('admin_api.php?action=get_stats');
                    if (data.success) {
                        this.stats = data.data;
                    } else {
                        this.toast('获取统计数据失败', 'error');
                    }
                },

                async getOrders(page) {
                    try {
                        const query = new URLSearchParams({
                            action: 'get_orders',
                            page: page,
                            search: this.orderSearch,
                            status: this.orderStatusFilter
                        });
                        const { data } = await this.apiRequest('admin_api.php?' + query.toString());
                        if (data.success) {
                            this.orders = data.data;
                        } else {
                            this.toast('获取订单失败', 'error');
                        }
                    } catch (e) {
                        this.toast('获取订单失败: ' + e.message, 'error');
                    }
                },

                async updateOrderStatus(id, status) {
                    let formData = new FormData();
                    formData.append('id', id);
                    formData.append('status', status);
                    const { data } = await this.apiRequest('admin_api.php?action=update_order_status', { method: 'POST', body: formData });
                    if (data.success) {
                        this.toast('订单已标记为已支付', 'success');
                        this.getOrders(this.orders.pagination.page);
                    } else {
                        this.toast(data.message || '操作失败', 'error');
                    }
                },

	                async getConfig() {
	                    const { data } = await this.apiRequest('admin_api.php?action=get_config', { cache: 'no-store' });
	                    if (data.success) {
	                        this.editConfig = JSON.parse(JSON.stringify(data.data.alipay));
	                        this.normalizeConfig();
	                        this.merchantInfo = data.data.merchant;
	                        this.merchantInfo.admin_password = '';
	                        this.showMerchantKey = false;
	                        this.resetSettingsSnapshot();
	                    }
	                },

	                normalizeConfig() {
	                    this.editConfig.sign_type = this.editConfig.sign_type || 'RSA2';
	                    this.editConfig.charset = this.editConfig.charset || 'UTF-8';
	                    this.editConfig.format = this.editConfig.format || 'json';
	                    this.editConfig.bill_query = Object.assign({
	                        default_page_size: 2000,
	                        max_page_size: 2000,
	                        date_format: 'Y-m-d H:i:s'
	                    }, this.editConfig.bill_query || {});
	                    this.editConfig.log = Object.assign({
	                        level: 'debug',
	                        max_file: 30,
	                        type: 'single',
	                        file: ''
	                    }, this.editConfig.log || {});
	                    this.editConfig.payment = Object.assign({
	                        max_wait_time: 300,
	                        check_interval: 3,
	                        query_minutes_back: 30,
	                        order_timeout: 300,
	                        auto_cleanup: true,
	                        qr_code_size: 300,
	                        qr_code_margin: 10
	                    }, this.editConfig.payment || {});
	                    this.editConfig.payment.business_qr_mode = Object.assign({
	                        enabled: true,
	                        qr_code_path: './qrcode/business_qr.png',
	                        amount_offset: 0.01,
	                        match_tolerance: 300,
	                        payment_timeout: 300
	                    }, this.editConfig.payment.business_qr_mode || {});
	                    this.editConfig.payment.anti_risk_url = Object.assign({
	                        enabled: true,
	                        outer_app_id: '20000218',
	                        inner_app_id: '20000116',
	                        base_urls: {}
	                    }, this.editConfig.payment.anti_risk_url || {});
	                    this.editConfig.payment.anti_risk_url.base_urls = Object.assign({
	                        mdeduct_landing: 'https://render.alipay.com/p/c/mdeduct-landing',
	                        render_scheme: 'https://render.alipay.com/p/s/i'
	                    }, this.editConfig.payment.anti_risk_url.base_urls || {});
	                    this.editConfig.payment.business_qr_mode.enabled = this.normalizeBoolean(this.editConfig.payment.business_qr_mode.enabled);
	                    this.editConfig.payment.anti_risk_url.enabled = this.normalizeBoolean(this.editConfig.payment.anti_risk_url.enabled);
	                    this.editConfig.payment.auto_cleanup = this.normalizeBoolean(this.editConfig.payment.auto_cleanup);
	                    this.syncSwitchStateFromConfig();
	                },

	                normalizeBoolean(value) {
	                    if (value === true || value === 1 || value === '1') return true;
	                    if (value === false || value === 0 || value === '0' || value === 'false') return false;
	                    return Boolean(value);
	                },

	                syncSwitchStateFromConfig() {
	                    this.businessQrModeEnabled = this.normalizeBoolean(this.editConfig.payment.business_qr_mode.enabled);
	                    this.antiRiskUrlEnabled = this.normalizeBoolean(this.editConfig.payment.anti_risk_url.enabled);
	                    this.autoCleanupEnabled = this.normalizeBoolean(this.editConfig.payment.auto_cleanup);
	                },

		                syncSwitchStateToConfig() {
		                    this.editConfig.payment.business_qr_mode.enabled = this.normalizeBoolean(this.businessQrModeEnabled);
		                    this.editConfig.payment.anti_risk_url.enabled = this.normalizeBoolean(this.antiRiskUrlEnabled);
		                    this.editConfig.payment.auto_cleanup = this.normalizeBoolean(this.autoCleanupEnabled);
		                },

		                setPaymentMode(mode) {
		                    this.businessQrModeEnabled = mode === 'business_qr';
		                    this.syncSwitchStateToConfig();
		                },

		                currentSettingsSnapshot() {
	                    const alipayConfig = JSON.parse(JSON.stringify(this.editConfig));
	                    alipayConfig.payment = alipayConfig.payment || {};
	                    alipayConfig.payment.business_qr_mode = alipayConfig.payment.business_qr_mode || {};
	                    alipayConfig.payment.anti_risk_url = alipayConfig.payment.anti_risk_url || {};
	                    alipayConfig.payment.business_qr_mode.enabled = this.normalizeBoolean(this.businessQrModeEnabled);
	                    alipayConfig.payment.anti_risk_url.enabled = this.normalizeBoolean(this.antiRiskUrlEnabled);
	                    alipayConfig.payment.auto_cleanup = this.normalizeBoolean(this.autoCleanupEnabled);
	                    return JSON.stringify({
	                        alipay: alipayConfig,
	                        merchant: {
	                            status: Number(this.merchantInfo.status),
	                            rate: String(this.merchantInfo.rate ?? ''),
	                            admin_password: this.merchantInfo.admin_password || ''
	                        }
	                    });
	                },

	                resetSettingsSnapshot() {
	                    this.settingsSnapshot = this.currentSettingsSnapshot();
	                },

	                toggleAntiRiskUrl() {
	                    this.antiRiskUrlEnabled = !this.normalizeBoolean(this.antiRiskUrlEnabled);
	                    this.editConfig.payment.anti_risk_url.enabled = this.antiRiskUrlEnabled;
	                },

	                toggleAutoCleanup() {
	                    this.autoCleanupEnabled = !this.normalizeBoolean(this.autoCleanupEnabled);
	                    this.editConfig.payment.auto_cleanup = this.autoCleanupEnabled;
	                },

                async saveAll() {
                    this.loading = true;
                    try {
                        this.syncSwitchStateToConfig();
                        const { data: alipayData } = await this.apiRequest('admin_api.php?action=save_config', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'action=save_config&config=' + encodeURIComponent(JSON.stringify(this.editConfig))
                        });

                        const merchantParams = new URLSearchParams();
                        merchantParams.set('action', 'save_merchant');
                        merchantParams.set('status', this.merchantInfo.status);
                        merchantParams.set('rate', this.merchantInfo.rate || '0');
                        const pwd = this.merchantInfo.admin_password;
                        if (pwd && pwd !== '********') {
                            merchantParams.set('admin_password', pwd);
                        }
                        const { data: merchantData } = await this.apiRequest('admin_api.php?action=save_merchant', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: merchantParams.toString()
                        });

                        if (alipayData.success && merchantData.success) {
                            if (alipayData.data) {
                                this.businessQrModeEnabled = this.normalizeBoolean(alipayData.data.business_qr_mode_enabled);
                                this.antiRiskUrlEnabled = this.normalizeBoolean(alipayData.data.anti_risk_url_enabled);
                                this.autoCleanupEnabled = this.normalizeBoolean(alipayData.data.auto_cleanup_enabled);
                            }
                            await this.getConfig();
                            await this.getQrCode();
                            this.resetSettingsSnapshot();
                            this.$nextTick(() => lucide.createIcons());
                            this.toast('保存成功', 'success');
                        } else {
                            this.toast('保存失败: ' + (alipayData.message || merchantData.message || '未知'), 'error');
                        }
                    } catch (e) {
                        this.toast('保存失败: ' + e.message, 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async regenerateKey() {
                    this.loading = true;
                    try {
                        const { data } = await this.apiRequest('admin_api.php?action=regenerate_merchant_key', { method: 'POST' });
                        if (data.success) {
                            this.regeneratedKey = data.data.merchant_key;
                            this.showMerchantKey = true;
                            this.toast('新密钥已生成，请立即复制保存', 'success', 8000);
                        } else {
                            this.toast('生成失败: ' + (data.message || ''), 'error');
                        }
                    } catch (e) {
                        this.toast('操作失败: ' + e.message, 'error');
                    } finally {
                        this.loading = false;
                    }
                },

                async uploadQrCode(event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    if (!['image/png', 'image/jpeg', 'image/jpg'].includes(file.type)) {
                        this.toast('仅支持 PNG / JPG 格式', 'error'); return;
                    }
                    if (file.size > 2 * 1024 * 1024) {
                        this.toast('图片大小不能超过 2MB', 'error'); return;
                    }

                    this.uploading = true;
                    try {
                        const fd = new FormData();
                        fd.append('qrcode', file);
                        const { data } = await this.apiRequest('admin_api.php?action=upload_qrcode', { method: 'POST', body: fd });
                        if (data.success) {
                            this.toast('经营码已更新', 'success');
                            await this.getQrCode();
                        } else {
                            this.toast('上传失败: ' + (data.message || ''), 'error');
                        }
                    } catch (e) {
                        this.toast('上传失败: ' + e.message, 'error');
                    } finally {
                        this.uploading = false;
                        event.target.value = '';
                    }
                },

                async getQrCode() {
                    try {
                        const { data } = await this.apiRequest('admin_api.php?action=get_qrcode');
                        if (data.success) this.qrInfo = data.data;
                    } catch (e) {}
                },

                async createBackup() {
                    this.backupLoading = true;
                    try {
                        const { data } = await this.apiRequest('admin_api.php?action=create_backup', { method: 'POST' });
                        if (!data.success || !data.data?.download_url) {
                            throw new Error(data.message || '备份生成失败');
                        }

                        window.open(data.data.download_url, '_blank', 'noopener');
                        this.toast('备份已生成，下载已开始', 'success');
                    } catch (e) {
                        this.toast('备份失败: ' + e.message, 'error', 5000);
                    } finally {
                        this.backupLoading = false;
                    }
                },

                async restoreBackup(event) {
                    const file = event.target.files?.[0];
                    if (!file) return;

                    if (!file.name.toLowerCase().endsWith('.zip')) {
                        this.toast('仅支持 zip 备份文件', 'error');
                        event.target.value = '';
                        return;
                    }

                    const confirmed = window.confirm('恢复会覆盖当前配置、订单库和经营码。系统会先自动创建一份当前快照，确定继续吗？');
                    if (!confirmed) {
                        event.target.value = '';
                        return;
                    }

                    this.restoreLoading = true;
                    try {
                        const fd = new FormData();
                        fd.append('backup', file);
                        const { data } = await this.apiRequest('admin_api.php?action=restore_backup', { method: 'POST', body: fd });
                        if (!data.success) {
                            throw new Error(data.message || '恢复失败');
                        }

                        await this.getConfig();
                        await this.getQrCode();
                        this.resetSettingsSnapshot();
                        this.toast(`恢复成功，已自动创建恢复前快照：${data.data?.pre_restore_backup || '已保存'}`, 'success', 7000);
                    } catch (e) {
                        this.toast('恢复失败: ' + e.message, 'error', 6000);
                    } finally {
                        this.restoreLoading = false;
                        event.target.value = '';
                    }
                },

                async testAlipay() {
                    this.alipayTesting = true;
                    this.alipayTestResults = [];
                    this.showAlipayTestPopover = false;
                    try {
                        const { data } = await this.apiRequest('admin_api.php?action=test_alipay');
                        this.alipayTestResults = data.checks || [];
                    } catch (e) {
                        this.alipayTestResults = [{ name: '请求', status: 'fail', message: e.message }];
                    } finally {
                        this.alipayTesting = false;
                        this.showAlipayTestPopover = this.alipayTestResults.length > 0;
                        this.$nextTick(() => lucide.createIcons());
                    }
                },

                async getHealth() {
                    try {
                        const res = await fetch('health.php?action=status');
                        const data = await res.json();
                        if (data.success && data.data && data.data.services && data.data.services.monitoring) {
                            this.health = data.data.services.monitoring;
                        } else {
                            this.health = { status: 'unknown', health_score: 0, last_run: 'N/A' };
                        }
                    } catch (e) {
                        this.health = { status: 'unknown', health_score: 0, last_run: 'N/A' };
                    }
                },

                async getLogs() {
                    const { data } = await this.apiRequest(`admin_api.php?action=get_logs&type=${this.logType}`);
                    if (data.success) this.logs = data.data;
                },

                async triggerMonitor() {
                    this.loading = true;
                    try {
                        const { data } = await this.apiRequest('admin_api.php?action=trigger_monitor', { method: 'POST' });
                        if (data.success) {
                            this.toast(data.message || '账单轮询已完成', 'success');
                            await this.getHealth();
                            await this.getLogs();
                        } else {
                            this.toast('触发失败: ' + (data.message || '未知错误'), 'error', 5000);
                        }
                    } catch (e) {
                        this.toast('触发失败: ' + e.message, 'error', 5000);
                    } finally {
                        this.loading = false;
                    }
                },

                numberFormat(num) {
                    const n = parseFloat(num);
                    if (isNaN(n)) return '0.00';
                    return n.toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
            };
        }
    </script>
</body>
</html>
