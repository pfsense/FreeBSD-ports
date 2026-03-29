<?php
/**
 * NordVPN - Page chrome helpers
 * /usr/local/www/nordvpn/page_chrome.inc
 *
 * Renders pfSense GUI chrome (head/navbar/footer) for NordVPN pages.
 */

function nvpn_page_head(string $title = 'NordVPN Routing'): void {
    global $pgtitle;
    $pgtitle = ['VPN', $title];
    include('head.inc');
    include('fbegin.inc');
    echo '<section class="page-content-main"><div class="container-fluid">';
}

function nvpn_page_foot(): void {
    echo '</div></section>';
    include('foot.inc');
}

function nvpn_url(string $page): string {
    return '/nordvpn/' . ltrim($page, '/');
}
