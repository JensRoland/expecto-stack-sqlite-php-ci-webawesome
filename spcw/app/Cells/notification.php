    <div class="wa-callout <?= implode(' ', $classes) ?>">
<?php if ($showDismissButton): ?>
        <button class="msg-close wa-size-s wa-plain" onclick="event.preventDefault(); this.parentElement.classList.remove('msg-show')">&times;</button>
<?php endif; ?>
        <?= esc($body) ?>
    </div>
