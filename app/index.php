<?php
/**
 * NullHome — Frontend single-page application entry point.
 *
 * PHP renders the shell HTML only. All dynamic content is loaded via the
 * JSON API using vanilla JavaScript. The page is designed to run on a
 * Raspberry Pi local network.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>nullhome</title>
    <link rel="stylesheet" href="/public/css/main.css">
    <link rel="stylesheet" href="/public/css/rooms.css">
</head>
<body>

<header class="app-header">
    <button class="hamburger-btn" id="menuToggle" aria-label="Open menu">
        <svg width="22" height="22" viewBox="0 0 22 22" fill="none" aria-hidden="true">
            <rect y="4"  width="22" height="2" rx="1" fill="currentColor"/>
            <rect y="10" width="22" height="2" rx="1" fill="currentColor"/>
            <rect y="16" width="22" height="2" rx="1" fill="currentColor"/>
        </svg>
    </button>
    <h1 class="app-title">nullhome</h1>
</header>

<!-- Slide-in drawer -->
<div class="drawer-overlay" id="drawerOverlay"></div>
<nav class="drawer" id="drawer">
    <button class="drawer-close" id="drawerClose" aria-label="Close menu">&#x2715;</button>
    <ul class="drawer-menu">
        <li><button id="openAddRoom">Add Room</button></li>
        <li><button id="openRemoveRoom">Remove Room</button></li>
    </ul>
</nav>

<!-- Add Room overlay -->
<div class="modal-overlay" id="addRoomOverlay" hidden>
    <div class="modal">
        <h2>Add Room</h2>
        <label for="roomName">Name</label>
        <input type="text" id="roomName" name="name" placeholder="e.g. living-room">
        <label for="roomDisplayName">Display Name</label>
        <input type="text" id="roomDisplayName" name="display_name" placeholder="e.g. Living Room">
        <p class="form-error" id="addRoomError" hidden></p>
        <div class="modal-actions">
            <button id="submitAddRoom">Add</button>
            <button id="cancelAddRoom">Cancel</button>
        </div>
    </div>
</div>

<!-- Remove Room overlay -->
<div class="modal-overlay" id="removeRoomOverlay" hidden>
    <div class="modal">
        <h2>Remove Room</h2>
        <ul class="room-remove-list" id="roomRemoveList">
            <!-- populated by JS -->
        </ul>
        <div class="modal-actions">
            <button id="cancelRemoveRoom">Close</button>
        </div>
    </div>
</div>

<!-- Main content -->
<main class="rooms-container" id="roomsContainer">
    <!-- populated by JS -->
</main>

<script src="/public/js/menu.js"></script>
<script src="/public/js/rooms.js"></script>
<script src="/public/js/room-form.js"></script>
<script src="/public/js/room-remove.js"></script>
<script src="/public/js/app.js"></script>
</body>
</html>
