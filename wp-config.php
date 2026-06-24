<?php
/**
 * The base configuration for WordPress
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress_db' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'Matias413114312@#$?' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 * Generated using https://api.wordpress.org/secret-key/1.1/salt/
 */
define( 'AUTH_KEY',         'X5m9$pL2@qR8#vN3&wM7*kL4!tB6^cV1' );
define( 'SECURE_AUTH_KEY',  'yH8#jM3%nC6^vB2&gF5$rT9*wE1@kL7' );
define( 'LOGGED_IN_KEY',    'zK4&mL9$qW2#rT7^yB6*nV3!xC8%jH5' );
define( 'NONCE_KEY',        'aF7#cV3%nM8^jL2&bG5$tR9*wE1@kH6' );
define( 'AUTH_SALT',        'bV2#mK7%nC4^vB8&gF1$rT5*wE9@jL3' );
define( 'SECURE_AUTH_SALT', 'cN8#jL4%mC2^vB6&gF9$rT3*wE7@kH1' );
define( 'LOGGED_IN_SALT',   'dM5#nK1%vB7^cL3&gF8$rT2*wE4@jH9' );
define( 'NONCE_SALT',       'eP6#mL8%nC4^vB1&gF7$rT9*wE2@kH5' );

/**#@-*/

/**
 * WordPress database table prefix.
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 */
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
        define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
