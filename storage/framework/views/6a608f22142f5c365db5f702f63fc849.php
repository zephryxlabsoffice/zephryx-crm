
<?php switch($platform):
    case ('Google Meet'): ?>
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <rect x="2" y="6" width="13" height="12" rx="2" fill="#00832D"/>
            <path d="M15 10l5-3v10l-5-3z" fill="#FBBC04"/>
            <path d="M2 6h7v6H2z" fill="#34A853"/>
        </svg>
        <?php break; ?>
    <?php case ('Zoom'): ?>
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <rect x="2" y="6" width="14" height="12" rx="3" fill="#2D8CFF"/>
            <path d="M16 10l5-3v10l-5-3z" fill="#2D8CFF"/>
        </svg>
        <?php break; ?>
    <?php default: ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polygon points="23 7 16 12 23 17 23 7"/>
            <rect x="1" y="5" width="15" height="14" rx="2"/>
        </svg>
<?php endswitch; ?>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/clients/partials/platform-icon.blade.php ENDPATH**/ ?>