<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#4f46e5">
    <meta name="application-name" content="POSmeister">
    <meta name="apple-mobile-web-app-title" content="POSmeister">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    <title>POSmeister</title>

    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- ── Splash screen ─────────────────────────────────────────────────── --}}
    {{-- Painted directly in the document head so it shows before Vite chunks
         download. The Vue bootstrap hides it once App.vue mounts (see app.js).
         Gradient, logo and tagline match the brand surface exactly. --}}
    <style>
        #posmeister-splash {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 24px;
            background:
                radial-gradient(1200px 600px at 50% -200px, rgba(99,102,241,0.18), transparent 60%),
                linear-gradient(180deg, #0f172a 0%, #1e1b4b 100%);
            color: #e2e8f0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            opacity: 1;
            transition: opacity 360ms ease;
        }
        #posmeister-splash.is-fading { opacity: 0; pointer-events: none; }
        #posmeister-splash .splash-mark {
            width: 64px; height: 64px;
            border-radius: 18px;
            background: linear-gradient(135deg, #6366f1, #818cf8);
            display: grid; place-items: center;
            box-shadow: 0 14px 40px -10px rgba(99,102,241,0.55), 0 0 0 1px rgba(255,255,255,0.06) inset;
            animation: splashPulse 1.8s ease-in-out infinite;
        }
        #posmeister-splash .splash-name {
            font-size: 22px; font-weight: 700; letter-spacing: -0.01em;
            color: #f8fafc;
        }
        #posmeister-splash .splash-tag {
            font-size: 12px; letter-spacing: 0.12em; text-transform: uppercase;
            color: rgba(199,210,254,0.7);
        }
        #posmeister-splash .splash-bar {
            margin-top: 8px;
            width: 120px; height: 3px; border-radius: 999px;
            background: rgba(255,255,255,0.08);
            overflow: hidden;
        }
        #posmeister-splash .splash-bar::after {
            content: ''; display: block; height: 100%;
            width: 35%; border-radius: 999px;
            background: linear-gradient(90deg, #6366f1, #c7d2fe);
            animation: splashSlide 1.4s ease-in-out infinite;
        }
        @keyframes splashPulse {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.06); }
        }
        @keyframes splashSlide {
            0%   { transform: translateX(-100%); }
            100% { transform: translateX(380%); }
        }
    </style>
</head>
<body class="h-full bg-gray-50 font-sans">
    <div id="posmeister-splash" aria-hidden="true">
        <div class="splash-mark">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
            </svg>
        </div>
        <div style="text-align:center">
            <div class="splash-name">POSmeister</div>
            <div class="splash-tag" style="margin-top:6px">Premium Business Platform</div>
        </div>
        <div class="splash-bar"></div>
    </div>
    <div id="app" class="h-full"></div>
</body>
</html>
