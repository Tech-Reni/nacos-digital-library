<?php
/**
 * includes/meta.php
 * ---------------------------------------------------------------------------
 * Central, reusable SEO + Social (Open Graph / Twitter Card) + PWA meta tag
 * renderer for the NACOS App.
 *
 * Include it once (db.php loads it for every page) and call render_meta([...])
 * at the top of each page's <head>.
 * ---------------------------------------------------------------------------
 */

if (!function_exists('render_meta')) {

    /**
     * Build an absolute base URL from the current request scheme + host.
     */
    function nacos_absolute_base() {
        static $base = null;
        if ($base !== null) return $base;

        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? '';
        $baseUrl = $GLOBALS['BASE_URL'] ?? '/nacos-app/';

        $base = rtrim($scheme . '://' . $host . $baseUrl, '/') . '/';
        return $base;
    }

    /**
     * HTML-entity-encode a value safely.
     */
    function nacos_escape($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Render the complete, consistent <meta>/<link> head block.
     *
     * @param array $opts  Optional overrides:
     *   - title        : page title (defaults to site name)
     *   - description  : unique, keyword-rich page description
     *   - keywords     : comma separated keywords
     *   - author       : content author / owner
     *   - robots       : explicit robots directive (default "index, follow")
     *   - noindex      : bool shortcut -> "noindex, nofollow" (set true for auth/admin/protected)
     *   - canonical    : canonical URL (defaults to current canonical path)
     *   - og_type      : Open Graph type (website|article|profile|book ...)
     *   - og_image     : share image (absolute or app-relative)
     *   - twitter_card : summary | summary_large_image
     *   - theme_color  : browser / PWA theme colour
     */
    function render_meta(array $opts = []) {
$base = nacos_absolute_base();

        $defaultTitle = 'NACOS App';
        $defaultDesc  = 'NACOS App connects Yaba College of Technology students with academic resources, student services and campus updates.';
        $logo         = $base . 'assets/images/NACOS_LOGO.png';
        $shareImage   = $base . 'assets/images/YCT_LOGO.png';

        // Canonical URL: current request path (query string stripped).
        $path       = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
        $canonical  = $opts['canonical'] ?? $base . ltrim($path, '/');

        $title       = $opts['title']       ?? $defaultTitle;
        $description = $opts['description'] ?? $defaultDesc;
        $keywords    = $opts['keywords']    ?? 'NACOS App, Yaba College of Technology, student services, academic resources, study materials, campus updates';
        $author      = $opts['author']      ?? 'NACOS Yaba College of Technology';
        $robots      = $opts['robots']      ?? 'index, follow';
        $ogType      = $opts['og_type']     ?? 'website';
        $ogImage     = $opts['og_image']    ?? $shareImage;
        // resolve relative og:image to absolute
        if (!preg_match('~^https?://~i', $ogImage)) {
            $ogImage = $base . ltrim($ogImage, '/');
        }
        $twitterCard = $opts['twitter_card'] ?? 'summary_large_image';
        $themeColor  = $opts['theme_color'] ?? '#ffffff';

        // noindex shortcut overrides robots.
        if (!empty($opts['noindex'])) {
            $robots = 'noindex, nofollow';
        }

        $tags = [];

        // ----- Core / charset / viewport -----
        $tags[] = '<meta charset="UTF-8">';
        $tags[] = '<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">';
        $tags[] = '<meta name="format-detection" content="telephone=no">';
        $tags[] = '<title>' . nacos_escape($title) . '</title>';
        $tags[] = '<meta name="description" content="' . nacos_escape($description) . '">';
        $tags[] = '<meta name="keywords" content="' . nacos_escape($keywords) . '">';
        $tags[] = '<meta name="author" content="' . nacos_escape($author) . '">';
        $tags[] = '<meta name="robots" content="' . nacos_escape($robots) . '">';
        $tags[] = '<meta name="color-scheme" content="light">';
        $tags[] = '<link rel="canonical" href="' . nacos_escape($canonical) . '">';
// ----- PWA / theme colour -----
        $tags[] = '<meta name="theme-color" content="' . nacos_escape($themeColor) . '">';
        $tags[] = '<meta name="application-name" content="NACOS App">';
        $tags[] = '<meta name="apple-mobile-web-app-title" content="NACOS App">';
        $tags[] = '<meta name="apple-mobile-web-app-capable" content="yes">';
        $tags[] = '<meta name="mobile-web-app-capable" content="yes">';
        $tags[] = '<meta name="apple-mobile-web-app-status-bar-style" content="default">';
        $tags[] = '<meta name="msapplication-TileColor" content="' . nacos_escape($themeColor) . '">';
        $tags[] = '<link rel="apple-touch-icon" href="' . nacos_escape($logo) . '">';
        $tags[] = '<link rel="manifest" href="' . nacos_escape($base . 'manifest.json') . '">';

        // ----- Open Graph (Facebook, WhatsApp, LinkedIn ...) -----
        $tags[] = '<meta property="og:site_name" content="NACOS App">';
        $tags[] = '<meta property="og:type" content="' . nacos_escape($ogType) . '">';
        $tags[] = '<meta property="og:locale" content="en_US">';
        $tags[] = '<meta property="og:locale:alternate" content="en_GB">';
        $tags[] = '<meta property="og:url" content="' . nacos_escape($canonical) . '">';
        $tags[] = '<meta property="og:title" content="' . nacos_escape($title) . '">';
        $tags[] = '<meta property="og:description" content="' . nacos_escape($description) . '">';
        $tags[] = '<meta property="og:image" content="' . nacos_escape($ogImage) . '">';
        $tags[] = '<meta property="og:image:alt" content="NACOS App">';

        // ----- Twitter Card -----
        $tags[] = '<meta name="twitter:card" content="' . nacos_escape($twitterCard) . '">';
        $tags[] = '<meta name="twitter:title" content="' . nacos_escape($title) . '">';
        $tags[] = '<meta name="twitter:description" content="' . nacos_escape($description) . '">';
        $tags[] = '<meta name="twitter:image" content="' . nacos_escape($ogImage) . '">';

        foreach ($tags as $line) {
            echo "    " . $line . "\n";
        }
    }
}