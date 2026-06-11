<?php
// Buyer navigation wrapper - loads Tailwind head + header for all buyer pages
include __DIR__ . '/_tailwind_head.php';
include __DIR__ . '/_tailwind_header.php';
?>
<style>
/* Fix layout spacing - header is fixed h-14 = 56px, add 16px gap */
body.portal-page {
    padding-top: 0 !important;
}
.portal-shell {
    padding-top: 80px !important;
    padding-bottom: 80px !important;
    max-width: 1280px !important;
    margin-left: auto !important;
    margin-right: auto !important;
    padding-left: 16px !important;
    padding-right: 16px !important;
}
.portal-shell .portal-panel {
    border-radius: 12px;
}
/* Hide old topbar/commandbar since we use Tailwind header */
.portal-shell > .topbar,
.portal-shell > .buyer-topbar,
.portal-shell > .portal-commandbar {
    display: none !important;
}
</style>
