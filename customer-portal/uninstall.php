<?php
/**
 * Customer Portal – uninstall.php
 * Wordt uitgevoerd vlak voor het verwijderen van de module.
 */

$db = Database::getInstance();
$p  = DB_PREFIX;

$db->query("DROP TABLE IF EXISTS `{$p}cp_invoice_items`");
$db->query("DROP TABLE IF EXISTS `{$p}cp_invoices`");
$db->query("DROP TABLE IF EXISTS `{$p}cp_quote_items`");
$db->query("DROP TABLE IF EXISTS `{$p}cp_quotes`");
$db->query("DROP TABLE IF EXISTS `{$p}cp_customers`");

// Verwijder module-instellingen
$settingKeys = [
    'cp_portal_slug', 'cp_company_name', 'cp_company_address',
    'cp_company_postcode', 'cp_company_city', 'cp_company_kvk',
    'cp_company_btw', 'cp_company_iban', 'cp_invoice_prefix',
    'cp_invoice_counter', 'cp_quote_prefix', 'cp_quote_counter',
    'cp_payment_days', 'cp_quote_valid_days', 'cp_tax_rate',
    'cp_portal_enabled',
];

foreach ($settingKeys as $key) {
    $db->query("DELETE FROM `" . DB_PREFIX . "settings` WHERE `key` = ?", [$key]);
}
