<!DOCTYPE html>
<html lang="et">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Laenutus') ?> · Laenutus</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="container header-inner">
            <a href="/" class="logo">Laenutus</a>
            <nav id="main-nav" class="main-nav hidden">
                <a href="/items-page">Vahendid</a>
                <a href="/loans-page">Minu laenutused</a>
                <button type="button" id="logout-btn" class="btn btn-ghost">Logi välja</button>
            </nav>
        </div>
    </header>

    <main class="container">
        <?= $content ?? '' ?>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>Kooli vahendite laenutussüsteem · PHP monoliit</p>
        </div>
    </footer>

    <div id="toast" class="toast hidden"></div>
    <script src="/assets/js/app.js"></script>
</body>
</html>
