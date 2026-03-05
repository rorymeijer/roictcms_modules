<?php
if (!defined('BASE_URL')) {
    exit('Direct access not allowed.');
}

Settings::set('cookie_consent_title', 'Cookievoorkeuren');
Settings::set('cookie_consent_text', 'Wij gebruiken cookies voor een optimale werking van de website.');
Settings::set('cookie_consent_accept_all', 'Alles accepteren');
Settings::set('cookie_consent_reject', 'Alleen noodzakelijk');
Settings::set('cookie_consent_privacy_url', '');
