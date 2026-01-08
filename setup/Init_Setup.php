<?php
/**
 * WordPress REST API Class for User Preferences
 *
 * Registers REST API endpoints:
 * - postmetadata/v1/preferences/update (protected)
 * - postmetadata/v1/preferences/retrieve (protected)
 * - postmetadata/v1/preferences/retrievepublic (public)
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Init_Setup
{
    /**
     * Table name for preferences (without prefix)
     */
    const TABLE_NAME = 'rd_pr_pref';

    /**
     * Constructor
     */
    public function __construct()
    {

    }

    /**
     * Get the full table name with WordPress prefix
     *
     * @return string
     */
    public static function get_table_name()
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create the preferences table
     *
     * @return void
     */
    public static function create_preferences_table()
    {
        global $wpdb;

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `key` varchar(50) NOT NULL,
            `value` varchar(500) NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Drop the preferences table
     *
     * @return void
     */
    public static function drop_preferences_table()
    {
        global $wpdb;

        $table_name = self::get_table_name();
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }

}