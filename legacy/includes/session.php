<?php

/**
 * Session Hardening
 * Phase 6 Requirement
 */

function secure_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure cookie parameters
        $lifetime = 0; // Session cookie
        $path = '/';
        $domain = ''; // Default
        $secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $forwardedProto = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0]));
        $secure = $secure || $forwardedProto === 'https';
        $httponly = true;
        $samesite = 'Lax';

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite
            ]);
        } else {
            session_set_cookie_params($lifetime, $path . '; samesite=' . $samesite, $domain, $secure, $httponly);
        }

        session_start();
    }
}

/**
 * Regenerate session ID after login/privilege change
 */
function secure_session_regenerate() {
    session_regenerate_id(true);
}
