<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>@yield('title', ($year ?? date('Y')) . ' Rewind') - {{ $forumTitle ?? 'Forum' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --rw-bg: #090d16;
            --rw-card-bg: rgba(255, 255, 255, 0.04);
            --rw-card-border: rgba(255, 255, 255, 0.08);
            --rw-card-hover: rgba(255, 255, 255, 0.07);
            --rw-text: #f8fafc;
            --rw-text-muted: #94a3b8;
            --rw-primary: #6366f1;
            --rw-primary-glow: rgba(99, 102, 241, 0.35);
            --rw-secondary: #ec4899;
            --rw-accent: #8b5cf6;
            --rw-amber: #f59e0b;
            --rw-emerald: #10b981;
            --rw-cyan: #06b6d4;
            --rw-gradient-hero: linear-gradient(135deg, #6366f1 0%, #a855f7 50%, #ec4899 100%);
            --rw-gradient-card: linear-gradient(180deg, rgba(255, 255, 255, 0.06) 0%, rgba(255, 255, 255, 0.02) 100%);
            --rw-radius-sm: 8px;
            --rw-radius-md: 16px;
            --rw-radius-lg: 24px;
            --rw-radius-full: 9999px;
            --rw-shadow-card: 0 10px 30px -10px rgba(0, 0, 0, 0.5), 0 0 1px 1px rgba(255, 255, 255, 0.05);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--rw-bg);
            color: var(--rw-text);
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
            background-image: 
                radial-gradient(circle at 15% 20%, rgba(99, 102, 241, 0.15) 0%, transparent 40%),
                radial-gradient(circle at 85% 80%, rgba(236, 72, 153, 0.12) 0%, transparent 40%),
                radial-gradient(circle at 50% 50%, rgba(139, 92, 246, 0.08) 0%, transparent 60%);
            background-attachment: fixed;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .rw-navbar {
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            background: rgba(9, 13, 22, 0.75);
            border-bottom: 1px solid var(--rw-card-border);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .rw-nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--rw-text);
            font-weight: 700;
            font-size: 1.1rem;
        }

        .rw-nav-badge {
            background: var(--rw-gradient-hero);
            padding: 4px 10px;
            border-radius: var(--rw-radius-full);
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #fff;
            box-shadow: 0 2px 10px var(--rw-primary-glow);
        }

        .rw-nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .rw-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: var(--rw-radius-full);
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--rw-card-border);
            background: var(--rw-card-bg);
            color: var(--rw-text);
        }

        .rw-btn:hover {
            background: var(--rw-card-hover);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }

        .rw-btn-primary {
            background: var(--rw-gradient-hero);
            border: none;
            color: #fff;
            box-shadow: 0 4px 14px var(--rw-primary-glow);
        }

        .rw-btn-primary:hover {
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
            transform: translateY(-2px);
        }

        .rw-btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .rw-container {
            max-width: 1080px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }

        .rw-hero {
            text-align: center;
            padding: 40px 20px 60px;
            position: relative;
        }

        .rw-hero-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 16px;
            background: rgba(99, 102, 241, 0.12);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: var(--rw-radius-full);
            color: #a5b4fc;
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 20px;
            animation: fadeInDown 0.6s ease;
        }

        .rw-hero-title {
            font-size: clamp(2.5rem, 6vw, 4.2rem);
            font-weight: 900;
            letter-spacing: -0.03em;
            line-height: 1.1;
            margin-bottom: 16px;
            background: var(--rw-gradient-hero);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: fadeInUp 0.7s ease;
        }

        .rw-hero-subtitle {
            font-size: clamp(1rem, 2.5vw, 1.25rem);
            color: var(--rw-text-muted);
            max-width: 600px;
            margin: 0 auto;
            animation: fadeInUp 0.8s ease;
        }

        .rw-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .rw-grid-2 {
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        }

        .rw-grid-3 {
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }

        .rw-grid-4 {
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        .rw-card {
            background: var(--rw-gradient-card);
            border: 1px solid var(--rw-card-border);
            border-radius: var(--rw-radius-lg);
            padding: 28px;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: var(--rw-shadow-card);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .rw-card:hover {
            transform: translateY(-4px);
            border-color: rgba(255, 255, 255, 0.15);
            box-shadow: 0 16px 36px -12px rgba(0, 0, 0, 0.6), 0 0 20px var(--rw-primary-glow);
        }

        .rw-card--highlight {
            background: linear-gradient(180deg, rgba(99, 102, 241, 0.12) 0%, rgba(236, 72, 153, 0.05) 100%);
            border-color: rgba(99, 102, 241, 0.3);
        }

        .rw-card-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--rw-radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.06);
            color: var(--rw-primary);
        }

        .rw-card-icon--purple { background: rgba(139, 92, 246, 0.15); color: #c084fc; }
        .rw-card-icon--pink { background: rgba(236, 72, 153, 0.15); color: #f472b6; }
        .rw-card-icon--amber { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .rw-card-icon--emerald { background: rgba(16, 185, 129, 0.15); color: #34d399; }
        .rw-card-icon--cyan { background: rgba(6, 182, 212, 0.15); color: #22d3ee; }

        .rw-card-value {
            font-size: clamp(2rem, 4vw, 2.75rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1;
            margin-bottom: 6px;
            color: var(--rw-text);
        }

        .rw-card-label {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--rw-text-muted);
            margin-bottom: 12px;
        }

        .rw-card-desc {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
            line-height: 1.4;
        }

        .rw-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: var(--rw-radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.06);
            color: var(--rw-text-muted);
        }

        .rw-pill--success {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .rw-section-title {
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin: 40px 0 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .rw-avatar {
            width: 72px;
            height: 72px;
            border-radius: var(--rw-radius-full);
            border: 3px solid rgba(255, 255, 255, 0.15);
            object-fit: cover;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        }

        .rw-user-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding: 24px;
            background: var(--rw-gradient-card);
            border: 1px solid var(--rw-card-border);
            border-radius: var(--rw-radius-lg);
        }

        .rw-quote-box {
            position: relative;
            padding: 20px 24px;
            border-left: 4px solid var(--rw-accent);
            background: rgba(139, 92, 246, 0.06);
            border-radius: 0 var(--rw-radius-md) var(--rw-radius-md) 0;
            margin: 14px 0;
            font-style: italic;
            color: #e2e8f0;
            line-height: 1.6;
        }

        .rw-tag-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 12px;
        }

        .rw-tag-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--rw-card-border);
            border-radius: var(--rw-radius-full);
            font-size: 0.85rem;
            color: var(--rw-text);
            transition: all 0.2s ease;
        }

        .rw-tag-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
            transform: scale(1.03);
        }

        .rw-footer {
            text-align: center;
            padding: 40px 20px;
            border-top: 1px solid var(--rw-card-border);
            color: var(--rw-text-muted);
            font-size: 0.875rem;
            margin-top: 40px;
        }

        .rw-footer a {
            color: var(--rw-text);
            text-decoration: none;
            font-weight: 600;
        }

        .rw-footer a:hover {
            text-decoration: underline;
        }

        /* Toast message */
        .rw-toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 20px;
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid var(--rw-card-border);
            border-radius: var(--rw-radius-md);
            color: #fff;
            font-size: 0.875rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            gap: 8px;
            z-index: 999;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 640px) {
            .rw-container {
                padding: 20px 14px 60px;
            }
            .rw-user-header {
                flex-direction: column;
                text-align: center;
            }
            .rw-card {
                padding: 20px;
            }
        }
    </style>
    @yield('head')
</head>
<body>
    <nav class="rw-navbar">
        <a href="{{ $baseUrl ?? '/' }}" class="rw-nav-brand">
            <i class="fas fa-history" style="color: #6366f1;"></i>
            <span>{{ $forumTitle ?? 'Forum' }}</span>
            <span class="rw-nav-badge">{{ $year ?? date('Y') }} Rewind</span>
        </a>
        <div class="rw-nav-actions">
            <button class="rw-btn rw-btn-sm" onclick="shareRewind()" title="Share Recap">
                <i class="fas fa-share-alt"></i>
                <span class="rw-btn-text">Share</span>
            </button>
            <a href="{{ ($baseUrl ?? '') . '/rewind' }}" class="rw-btn rw-btn-sm">
                <i class="fas fa-arrow-left"></i>
                <span>Forum</span>
            </a>
        </div>
    </nav>

    <main class="rw-container">
        @yield('content')
    </main>

    <footer class="rw-footer">
        <p>Generated with <a href="https://github.com/huseyinfiliz/rewind" target="_blank" rel="noopener">Rewind for Flarum</a> &bull; {{ $forumTitle ?? 'Forum' }} &copy; {{ $year ?? date('Y') }}</p>
    </footer>

    <div id="rwToast" class="rw-toast">
        <i class="fas fa-check-circle" style="color: #10b981;"></i>
        <span id="rwToastMsg">Link copied to clipboard!</span>
    </div>

    <script>
        function shareRewind() {
            const url = window.location.href;
            const title = document.title;
            if (navigator.share) {
                navigator.share({ title: title, url: url }).catch(() => {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => {
                    showToast('Link copied to clipboard!');
                });
            } else {
                showToast('URL: ' + url);
            }
        }

        function showToast(msg) {
            const toast = document.getElementById('rwToast');
            const msgEl = document.getElementById('rwToastMsg');
            if (toast && msgEl) {
                msgEl.innerText = msg;
                toast.style.display = 'flex';
                setTimeout(() => {
                    toast.style.display = 'none';
                }, 3000);
            }
        }
    </script>
    @yield('scripts')
</body>
</html>
