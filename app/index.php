<?php
/**
 * NullHome — Frontend single-page application entry point.
 *
 * PHP renders the shell HTML; all dynamic content is loaded via the JSON API
 * using jQuery. The page is designed to run on a Raspberry Pi local network.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NullHome</title>
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>

<header>
    <h1>NullHome</h1>
    <nav>
        <button class="active" data-section="lights">Lights</button>
    </nav>
</header>

<main>
    <!-- Lights Section -->
    <section id="section-lights" class="section active">
        <div class="section-header">
            <h2>Lights</h2>
        </div>

        <!-- Add light form -->
        <form id="add-light-form" class="form-row" style="margin-bottom:1.25rem;">
            <div class="form-group">
                <label for="light-name">Name</label>
                <input type="text" id="light-name" placeholder="Living room lamp" required>
            </div>
            <div class="form-group">
                <label for="light-location">Location</label>
                <input type="text" id="light-location" placeholder="Living room">
            </div>
            <button type="submit" class="btn btn-primary">Add Light</button>
        </form>

        <!-- Lights grid — populated by JS -->
        <div id="lights-grid" class="card-grid">
            <p class="empty">Loading…</p>
        </div>
    </section>
</main>

<!-- Toast notification -->
<div id="toast"></div>

<!-- jQuery from CDN with local fallback -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"
        integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo="
        crossorigin="anonymous"></script>
<script>window.jQuery || document.write('<script src="/public/js/jquery.min.js"><\/script>')</script>
<script src="/public/js/app.js"></script>
</body>
</html>
