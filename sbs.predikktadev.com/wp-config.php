<?php

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'devpredi_wp208');

/** MySQL database username */
define('DB_USER', 'devpredi_wp208');

/** MySQL database password */
define('DB_PASSWORD', '6rS[3p!70K');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'zrddscvalam4yjhytd74rmoqhlcpsbcvrieyp0wwxivfsrwda2tbfz1ijb6mdihv');
define('SECURE_AUTH_KEY',  'l2e0zlvsmibwvh4uxo9vooqxcylalf8zgwcvm3uz4xcqw5ldinfxoimwvvpqz8p1');
define('LOGGED_IN_KEY',    'scxbapsxe4xi1f2kxsfgjdvag9s2t2vyjfykhimtqwotqd3f4th9gpv43w2mm2w8');
define('NONCE_KEY',        '14nbs2zueyyy3qvovss7fkzydunc3oompj7mzrsrbpq2p1iydl2syvgzesaytrey');
define('AUTH_SALT',        'fli0bhx3umwgsysogahzb2vl2tamkjnyk20vxcwokrscskyh4kptgybjo5r2cv06');
define('SECURE_AUTH_SALT', '5igvevsc4rxzjffimll2frcsvzymsf9hx9ldyyqwmzca0icc8p89j2h1uweesvph');
define('LOGGED_IN_SALT',   'skxrh0ghigs9kdmpqp9ofwsgjlog6hnym7haaficonirk5zqot8a1ozjhvdmwue4');
define('NONCE_SALT',       'z7hzde7nmqo3wndp9amkof6avautacteybfzohl6ifpq5ze1yuobo0rxjfy4nynx');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wpsj_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
