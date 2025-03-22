<div class="text-center">
    <img src="<?php echo ts_htmldecode($image_path); ?>">
    <div class="mtop10 display-flex justify-content-center">
        <a href="<?php echo ts_htmldecode($image_path); ?>" class="btn btn-primary display-flex" download>
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-download">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                <polyline points="7 10 12 15 17 10" />
                <line x1="12" y1="15" x2="12" y2="3" />
            </svg>
            <span class="mleft5">
                <?php echo _l('ts_download'); ?>
            </span>
        </a>
    </div>
</div>

