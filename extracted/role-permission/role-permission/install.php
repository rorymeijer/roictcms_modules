<?php
/**
 * Role & Permission Module — Install Script
 * Wordt eenmalig uitgevoerd bij installatie.
 */

$db = Database::getInstance();

// Rollen tabel
$db->query("
    CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "rp_roles` (
        `id`          INT AUTO_INCREMENT PRIMARY KEY,
        `name`        VARCHAR(100) NOT NULL,
        `slug`        VARCHAR(100) NOT NULL UNIQUE,
        `description` TEXT,
        `color`       VARCHAR(20) DEFAULT '#2563eb',
        `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Rechten tabel
$db->query("
    CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "rp_permissions` (
        `id`                INT AUTO_INCREMENT PRIMARY KEY,
        `slug`              VARCHAR(100) NOT NULL UNIQUE,
        `name`              VARCHAR(150) NOT NULL,
        `description`       TEXT,
        `permission_group`  VARCHAR(100) DEFAULT 'Algemeen'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Koppeltabel rollen <-> rechten
$db->query("
    CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "rp_role_permissions` (
        `role_id`       INT NOT NULL,
        `permission_id` INT NOT NULL,
        PRIMARY KEY (`role_id`, `permission_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Koppeltabel gebruikers <-> rollen
$db->query("
    CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "rp_user_roles` (
        `user_id` INT NOT NULL,
        `role_id` INT NOT NULL,
        PRIMARY KEY (`user_id`, `role_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Voeg 'lid' toe aan de role-enum voor bestaande installaties
try {
    $db->query("
        ALTER TABLE `" . DB_PREFIX . "users`
        MODIFY `role` ENUM('admin','editor','author','lid') DEFAULT 'author'
    ");
} catch (Exception $e) {
    // Kolom bestaat al of ENUM bevat 'lid' al — geen actie nodig
}

// Seed standaard rechten
$permissions = [
    // Pagina's
    ['slug' => 'manage_pages',   'name' => "Pagina's beheren",    'description' => "Pagina's aanmaken en bewerken.",           'group' => "Pagina's"],
    ['slug' => 'publish_pages',  'name' => "Pagina's publiceren", 'description' => "Pagina's publiceren en depubliceren.",     'group' => "Pagina's"],
    ['slug' => 'delete_pages',   'name' => "Pagina's verwijderen",'description' => "Pagina's permanent verwijderen.",          'group' => "Pagina's"],
    // Nieuws
    ['slug' => 'manage_news',    'name' => 'Nieuws beheren',      'description' => 'Nieuwsberichten aanmaken en bewerken.',    'group' => 'Nieuws'],
    ['slug' => 'publish_news',   'name' => 'Nieuws publiceren',   'description' => 'Nieuwsberichten publiceren en depubliceren.','group' => 'Nieuws'],
    ['slug' => 'delete_news',    'name' => 'Nieuws verwijderen',  'description' => 'Nieuwsberichten permanent verwijderen.',   'group' => 'Nieuws'],
    // Media
    ['slug' => 'manage_media',   'name' => 'Media beheren',       'description' => 'Bestanden uploaden en mediabibliotheek beheren.', 'group' => 'Media'],
    ['slug' => 'delete_media',   'name' => 'Media verwijderen',   'description' => 'Bestanden uit de mediabibliotheek verwijderen.', 'group' => 'Media'],
    // Gebruikers & Rollen
    ['slug' => 'manage_users',   'name' => 'Gebruikers beheren',  'description' => 'Gebruikers aanmaken, bewerken en verwijderen.', 'group' => 'Gebruikers'],
    ['slug' => 'manage_roles',   'name' => 'Rollen beheren',      'description' => 'Rollen en rechten beheren.',               'group' => 'Gebruikers'],
    // Systeem
    ['slug' => 'manage_settings','name' => 'Instellingen beheren','description' => 'Siteweide instellingen wijzigen.',         'group' => 'Systeem'],
    ['slug' => 'manage_modules', 'name' => 'Modules beheren',     'description' => 'Modules installeren, bijwerken en verwijderen.','group' => 'Systeem'],
    ['slug' => 'manage_themes',  'name' => "Thema's beheren",     'description' => "Thema's installeren en activeren.",       'group' => 'Systeem'],
];

foreach ($permissions as $p) {
    $exists = $db->fetch(
        "SELECT id FROM `" . DB_PREFIX . "rp_permissions` WHERE slug = ?",
        [$p['slug']]
    );
    if (!$exists) {
        $db->insert(DB_PREFIX . 'rp_permissions', [
            'slug'             => $p['slug'],
            'name'             => $p['name'],
            'description'      => $p['description'],
            'permission_group' => $p['group'],
        ]);
    }
}

// Seed standaard rollen (Redacteur & Auteur)
$defaultRoles = [
    [
        'name'        => 'Redacteur',
        'slug'        => 'redacteur',
        'description' => 'Kan inhoud beheren en publiceren, maar heeft geen toegang tot systeeminstellingen.',
        'color'       => '#16a34a',
        'permissions' => ['manage_pages','publish_pages','manage_news','publish_news','manage_media'],
    ],
    [
        'name'        => 'Auteur',
        'slug'        => 'auteur',
        'description' => 'Kan eigen nieuws aanmaken en bewerken, maar niet publiceren of verwijderen.',
        'color'       => '#9333ea',
        'permissions' => ['manage_news','manage_media'],
    ],
];

foreach ($defaultRoles as $role) {
    $exists = $db->fetch(
        "SELECT id FROM `" . DB_PREFIX . "rp_roles` WHERE slug = ?",
        [$role['slug']]
    );
    if (!$exists) {
        $roleId = $db->insert(DB_PREFIX . 'rp_roles', [
            'name'        => $role['name'],
            'slug'        => $role['slug'],
            'description' => $role['description'],
            'color'       => $role['color'],
        ]);
        foreach ($role['permissions'] as $permSlug) {
            $perm = $db->fetch(
                "SELECT id FROM `" . DB_PREFIX . "rp_permissions` WHERE slug = ?",
                [$permSlug]
            );
            if ($perm) {
                $db->query(
                    "INSERT IGNORE INTO `" . DB_PREFIX . "rp_role_permissions` (role_id, permission_id) VALUES (?, ?)",
                    [$roleId, $perm['id']]
                );
            }
        }
    }
}
