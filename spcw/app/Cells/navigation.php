<button id="sidebar-toggle" type="button" aria-label="Menu" onclick="event.preventDefault(); toggleSidebarState()">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-panel-left"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M9 3v18"/></svg>
</button>

<aside id="sidebar">
    <nav class="wa-split:column">
        <div class="iconlinks wa-stack wa-gap-2xs" style="width:100%;">
            <header>
                <a href="/">
                    <img id="logotype" class="silhouette" src="<?= base_url('assets/images/site-logo.png') ?>" alt="Site Logo" />
                </a>
            </header>
            <ul>
                <li><a href="/"><wa-icon name="house" label="Home"></wa-icon><span>Home</span></a></li>
            </ul>
<?php if (! empty($menuGroups)): ?>
<?php foreach ($menuGroups as $menuGroup): ?>
            <h2><?= esc($menuGroup['title']) ?></h2>
            <ul>
<?php foreach ($menuGroup['items'] as $item): ?>
                <li><a href="<?= esc($item['url']) ?>"><?= !empty($item['iconElement']) ? $item['iconElement'] : '<wa-icon fixed-width name="minus" label="' . esc($item['title'])  . '"></wa-icon>' ?><span><?= esc($item['title']) ?></span></a></li>
<?php endforeach; ?>
            </ul>
<?php endforeach; ?>
<?php endif; ?>
        </div>

        <div class="iconlinks wa-stack wa-gap-2xs" style="width:100%;">
            <wa-divider style="margin: var(--wa-space-m) 0;"></wa-divider>
            <ul>
<?php if ($isLoggedIn): ?>
                <li><a href="/profile"><wa-icon name="user" label="My Profile"></wa-icon><span><?= auth()->user()->username ?></span></a></li>
                <li><a href="/logout"><wa-icon name="right-from-bracket" label="Sign out"></wa-icon><span>Sign out</span></a></li>
<?php else: ?>
                <li><a href="/login"><wa-icon name="user-lock" label="Sign in"></wa-icon><span>Sign in</span></a></li>
                <li><a href="/register"><wa-icon name="user-plus" label="Register"></wa-icon><span>Register</span></a></li>
<?php endif; ?>
            </ul>
        </div>
    </nav>
</aside>
