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
    <link rel="stylesheet" href="/public/css/wemo-scan.css">
    <link rel="stylesheet" href="/public/css/nullhub-scan.css">
    <link rel="stylesheet" href="/public/css/db-validate.css">
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
        <li><button id="openWemoScan">Scan for Wemos</button></li>
        <li><button id="openNullHubScan">Scan for NullHubs</button></li>
        <li><button id="openDbValidate">Validate DB</button></li>
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

<!-- Wemo Scan overlay -->
<div class="modal-overlay wemo-scan-overlay" id="wemoScanOverlay" hidden>
    <div class="modal wemo-scan-modal">
        <h2>Scan for Wemos</h2>

        <div class="wemo-scan-status" id="wemoScanStatus">Ready to scan</div>

        <div class="wemo-scan-progress-wrap" id="wemoScanProgressWrap" hidden>
            <div class="wemo-scan-progress-bar">
                <div class="wemo-scan-progress-fill" id="wemoScanProgressFill"></div>
            </div>
            <div class="wemo-scan-progress-label" id="wemoScanProgressLabel"></div>
        </div>

        <ul class="wemo-scan-found-list" id="wemoScanFoundList" hidden>
            <!-- populated by JS during scan -->
        </ul>

        <div class="modal-actions">
            <button id="wemoScanStart">Start Scan</button>
            <button id="wemoScanCancel" hidden>Cancel</button>
            <button id="wemoScanClose" hidden>Close</button>
        </div>
    </div>
</div>

<!-- NullHub Scan overlay -->
<div class="modal-overlay nullhub-scan-overlay" id="nullhubScanOverlay" hidden>
    <div class="modal nullhub-scan-modal">
        <h2>Scan for NullHubs</h2>

        <div class="nullhub-scan-status" id="nullhubScanStatus">Ready to scan</div>

        <div class="nullhub-scan-progress-wrap" id="nullhubScanProgressWrap" hidden>
            <div class="nullhub-scan-progress-bar">
                <div class="nullhub-scan-progress-fill" id="nullhubScanProgressFill"></div>
            </div>
            <div class="nullhub-scan-progress-label" id="nullhubScanProgressLabel"></div>
        </div>

        <ul class="nullhub-scan-found-list" id="nullhubScanFoundList" hidden>
            <!-- populated by JS during scan -->
        </ul>

        <div class="modal-actions">
            <button id="nullhubScanStart">Start Scan</button>
            <button id="nullhubScanCancel" hidden>Cancel</button>
            <button id="nullhubScanClose" hidden>Close</button>
        </div>
    </div>
</div>

<!-- DB Validation overlay -->
<div class="modal-overlay wemo-scan-overlay" id="dbValidateOverlay" hidden>
    <div class="modal wemo-scan-modal db-validate-modal">
        <h2>Validate Database</h2>

        <div class="db-validate-status" id="dbValidateStatus">Ready</div>

        <div class="db-validate-results" id="dbValidateResults" hidden>
            <!-- populated by JS -->
        </div>

        <div id="dbValidateOrphanSection" hidden>
            <p class="db-validate-section-title">Orphan tables (no model)</p>
            <ul class="db-validate-orphan-list" id="dbValidateOrphanList">
                <!-- populated by JS -->
            </ul>
        </div>

        <div class="db-validate-feedback" id="dbValidateFeedback" hidden>
            <!-- populated by JS -->
        </div>

        <div class="modal-actions">
            <button id="dbValidateRun">Run Validation</button>
            <button id="dbValidateDelete" hidden>Delete Selected</button>
            <button id="dbValidateRefresh" hidden>Refresh</button>
            <button id="dbValidateClose">Close</button>
        </div>
    </div>
</div>

<!-- DB Validate result row template -->
<template id="db-validate-result-row">
    <div class="db-validate-result-row">
        <span class="db-validate-result-model"></span>
        <span class="db-validate-result-table"></span>
        <span class="db-validate-result-status"></span>
    </div>
</template>

<!-- DB Validate orphan item template -->
<template id="db-validate-orphan-item">
    <li class="db-validate-orphan-item">
        <input type="checkbox" checked>
        <label></label>
    </li>
</template>

<!-- room-card template -->
<template id="room-card">
    <div class="room-card">
        <h2 class="room-card-title"></h2>
    </div>
</template>

<!-- Main content -->
<main class="rooms-container" id="roomsContainer">
    <!-- populated by JS -->
</main>

<!-- framework -->
<script src="/public/js/app-events.js"></script>
<script src="/public/js/models/base-model.js"></script>
<script src="/public/js/views/base-view.js"></script>
<script src="/public/js/controllers/base-controller.js"></script>

<!-- models -->
<script src="/public/js/models/room-model.js"></script>
<script src="/public/js/models/validation-model.js"></script>

<!-- views -->
<script src="/public/js/views/room-view.js"></script>
<script src="/public/js/views/validation-view.js"></script>

<!-- controllers -->
<script src="/public/js/controllers/room-controller.js"></script>
<script src="/public/js/controllers/validation-controller.js"></script>

<!-- modules -->
<script src="/public/js/menu.js"></script>
<script src="/public/js/room-form.js"></script>
<script src="/public/js/room-remove.js"></script>
<script src="/public/js/wemo-scan.js"></script>
<script src="/public/js/nullhub-scan.js"></script>

<!-- bootstrap (must be last) -->
<script src="/public/js/app.js"></script>
</body>
</html>
