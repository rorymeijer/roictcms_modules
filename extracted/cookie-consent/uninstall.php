<?php
if (!defined('BASE_URL')) {
    exit('Direct access not allowed.');
}

Settings::delete('cookie_consent_title');
Settings::delete('cookie_consent_text');
Settings::delete('cookie_consent_accept_all');
Settings::delete('cookie_consent_reject');
Settings::delete('cookie_consent_privacy_url');
