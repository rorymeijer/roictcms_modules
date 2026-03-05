<?php
Database::getInstance()->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "redirects`");
Database::getInstance()->delete(DB_PREFIX . 'settings', '`key` = ?', ['redirects_log_hits']);
