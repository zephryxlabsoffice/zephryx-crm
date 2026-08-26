
<?php $s = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'; ?>

<?php switch($icon):
    case ('dashboard'): ?>
        <svg <?php echo $s; ?>><path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/></svg>
        <?php break; ?>
    <?php case ('clients'): ?>
        <svg <?php echo $s; ?>><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <?php break; ?>
    <?php case ('employees'): ?>
        <svg <?php echo $s; ?>><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <?php break; ?>
    <?php case ('teams'): ?>
        <svg <?php echo $s; ?>><circle cx="12" cy="8" r="3"/><circle cx="5" cy="17" r="2.5"/><circle cx="19" cy="17" r="2.5"/><path d="M12 11v3M12 14l-5 1M12 14l5 1"/></svg>
        <?php break; ?>
    <?php case ('projects'): ?>
        <svg <?php echo $s; ?>><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
        <?php break; ?>
    <?php case ('tasks'): ?>
        <svg <?php echo $s; ?>><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 12l2 2 4-4"/></svg>
        <?php break; ?>
    <?php case ('leads'): ?>
        <svg <?php echo $s; ?>><path d="M22 12h-4l-3 9-6-18-3 9H2"/></svg>
        <?php break; ?>
    <?php case ('tickets'): ?>
        <svg <?php echo $s; ?>><path d="M21 10V8a2 2 0 0 0-2-2h-4l-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2"/><path d="M21 10a2 2 0 0 0 0 4"/></svg>
        <?php break; ?>
    <?php case ('invoices'): ?>
        <svg <?php echo $s; ?>><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/></svg>
        <?php break; ?>
    <?php case ('salary'): ?>
        <svg <?php echo $s; ?>><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 12h.01M18 12h.01"/></svg>
        <?php break; ?>
    <?php case ('attendance'): ?>
        <svg <?php echo $s; ?>><circle cx="12" cy="12" r="10"/><path d="M12 7v5l3 2"/></svg>
        <?php break; ?>
    <?php case ('leave'): ?>
        <svg <?php echo $s; ?>><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M8 14h2l1 3 2-5 1 2h2"/></svg>
        <?php break; ?>
    <?php case ('meetings'): ?>
        <svg <?php echo $s; ?>><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
        <?php break; ?>
    <?php case ('calendar'): ?>
        <svg <?php echo $s; ?>><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        <?php break; ?>
    <?php case ('reports'): ?>
        <svg <?php echo $s; ?>><path d="M3 3v18h18"/><path d="M7 14l4-4 4 3 5-6"/></svg>
        <?php break; ?>
    <?php case ('announcements'): ?>
        <svg <?php echo $s; ?>><path d="M3 11l18-8v18l-18-8z"/><path d="M11 14v4a2 2 0 0 0 4 0v-2"/></svg>
        <?php break; ?>
    <?php case ('profile'): ?>
        <svg <?php echo $s; ?>><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <?php break; ?>
    <?php case ('settings'): ?>
        <svg <?php echo $s; ?>><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.04 1.56V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.11-1.56 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.7 1.7 0 0 0 .34-1.87 1.7 1.7 0 0 0-1.56-1.04H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.65 8.6a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34H9a1.7 1.7 0 0 0 1-1.56V3a2 2 0 1 1 4 0v.09a1.7 1.7 0 0 0 1 1.56 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87V9a1.7 1.7 0 0 0 1.56 1H21a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.56 1z"/></svg>
        <?php break; ?>
    <?php default: ?>
        <svg <?php echo $s; ?>><circle cx="12" cy="12" r="9"/></svg>
<?php endswitch; ?>
<?php /**PATH C:\Users\Santanu Dev\Downloads\PROJECT - ZEPHRYX CRM\CRM\resources\views/partials/nav-icon.blade.php ENDPATH**/ ?>