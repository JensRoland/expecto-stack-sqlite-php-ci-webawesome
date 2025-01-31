<footer>
    <figure class="logo">
        <img class="silhouette" src="<?= base_url('assets/images/site-logo.png') ?>" style="max-height: 3.2rem;" alt="Site Logo" />
    </figure>

    <ul class="footer-links">
        <li><a href="/">Home</a></li>
        <li><a href="/guide">User Guide</a></li>
        <li><a href="/pricing">Pricing</a></li>
        <li><a href="/news">News</a></li>
        <li><a href="/contact">Contact</a></li>
    </ul>

    <wa-divider></wa-divider>

    <div class="wa-split">
        <div class="copyright">
            <p>&copy; <?= date('Y') ?> SPCW. All rights reserved.</p>
        </div>
        <div class="server-timing">
            <p>Page rendered in {elapsed_time} seconds using {memory_usage} MB of memory.</p>
        </div>
    </div>
</footer>
