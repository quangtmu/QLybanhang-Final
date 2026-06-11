<?php
// Tailwind config for modern UI. Includes Tailwind CDN, Google Fonts, and base styles.
?>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
<?php
$isStore = $isStorePortal ?? false;
$primary = $isStore ? '#0f766e' : '#004ac6';
$primaryContainer = $isStore ? '#ccfbf1' : '#2563eb';
?>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "surface-container-highest": "#e2e2e2",
                        "on-error": "#ffffff",
                        "primary-fixed": "#dbe1ff",
                        "tertiary-fixed": "#d3e4fe",
                        "tertiary-fixed-dim": "#b7c8e1",
                        "secondary": "#565e74",
                        "on-tertiary-fixed-variant": "#38485d",
                        "tertiary-container": "#5e6e85",
                        "on-secondary-container": "#5c647a",
                        "on-surface": "#1a1c1c",
                        "inverse-on-surface": "#f0f1f1",
                        "secondary-fixed": "#dae2fd",
                        "outline-variant": "#c3c6d7",
                        "primary": "<?= $primary ?>",
                        "on-secondary-fixed-variant": "#3f465c",
                        "error": "#ba1a1a",
                        "on-tertiary": "#ffffff",
                        "surface-container-high": "#e8e8e8",
                        "surface-variant": "#e2e2e2",
                        "surface-tint": "<?= $primary ?>",
                        "on-surface-variant": "#434655",
                        "on-tertiary-container": "#e9f0ff",
                        "surface-dim": "#dadada",
                        "surface": "#f9f9f9",
                        "inverse-surface": "#2f3131",
                        "on-background": "#1a1c1c",
                        "surface-container-lowest": "#ffffff",
                        "on-secondary-fixed": "#131b2e",
                        "primary-container": "<?= $primaryContainer ?>",
                        "surface-container": "#eeeeee",
                        "primary-fixed-dim": "#b4c5ff",
                        "outline": "#737686",
                        "background": "#f9f9f9",
                        "tertiary": "#46566c",
                        "secondary-container": "#dae2fd",
                        "on-primary-fixed-variant": "<?= $primary ?>",
                        "on-primary-container": "#eeefff",
                        "on-error-container": "#93000a",
                        "surface-container-low": "#f3f3f3",
                        "error-container": "#ffdad6",
                        "on-tertiary-fixed": "#0b1c30",
                        "on-primary": "#ffffff",
                        "on-primary-fixed": "#00174b",
                        "secondary-fixed-dim": "#bec6e0",
                        "inverse-primary": "#b4c5ff",
                        "on-secondary": "#ffffff",
                        "surface-bright": "#f9f9f9",
                        "buyer-blue": "#2563eb",
                        "buyer-orange": "#f59e0b",
                        "success": "#16a34a",
                        "border-subtle": "#e5e7eb"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px",
                        "2xl": "1rem"
                    },
                    "spacing": {
                        "margin-mobile": "1rem",
                        "stack-md": "1rem",
                        "margin-desktop": "2.5rem",
                        "container-max": "1280px",
                        "stack-sm": "0.5rem",
                        "gutter": "1.5rem",
                        "stack-lg": "2rem"
                    },
                    "fontFamily": {
                        "headline-lg-mobile": ["Manrope"],
                        "body-md": ["Manrope"],
                        "label-sm": ["Manrope"],
                        "headline-lg": ["Manrope"],
                        "headline-md": ["Manrope"],
                        "headline-xl": ["Manrope"],
                        "body-lg": ["Manrope"],
                        "label-md": ["Manrope"]
                    },
                    "fontSize": {
                        "headline-lg-mobile": ["24px", {"lineHeight": "32px", "fontWeight": "700"}],
                        "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
                        "label-sm": ["12px", {"lineHeight": "16px", "fontWeight": "500"}],
                        "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.01em", "fontWeight": "700"}],
                        "headline-md": ["20px", {"lineHeight": "28px", "fontWeight": "600"}],
                        "headline-xl": ["48px", {"lineHeight": "56px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
                        "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}],
                        "label-md": ["14px", {"lineHeight": "20px", "letterSpacing": "0.05em", "fontWeight": "600"}]
                    }
                }
            }
        }
</script>
<style>
    html { scroll-behavior: smooth; }
    body {
        background-color: #f9f9f9;
        color: #1a1c1c;
        font-family: 'Manrope', system-ui, -apple-system, sans-serif;
    }
    ::selection { background: #dbeafe; color: #1e3a5f; }
    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    .material-symbols-outlined.fill, .icon-fill {
        font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }
    .no-scrollbar::-webkit-scrollbar, .hide-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar, .hide-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
    .glass-header { 
        backdrop-filter: blur(12px); 
        -webkit-backdrop-filter: blur(12px); 
    }
    .hover-elevate { 
        transition: box-shadow 0.3s ease, transform 0.3s ease; 
    }
    .hover-elevate:hover { 
        box-shadow: 0 8px 20px -4px rgba(0,0,0,0.08); 
        transform: translateY(-1px); 
    }
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in { animation: fadeInUp 0.3s ease-out; }
</style>
