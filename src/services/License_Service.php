<?php
/**
 * License Service Class
 *
 * Handles license-related operations using the Preference_Service.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class License_Service
{
    /**
     * Preference key for license
     */
    const LICENSE_PREFERENCE_KEY = 'license';

    /**
     * Preferences service instance
     *
     * @var Preferences_Service
     */
    private $preferences_service;

    /**
     * Constructor
     *
     * @param Preferences_Service|null $preferences_service Optional service for dependency injection
     */
    public function __construct($preferences_service = null)
    {
        $this->preferences_service = $preferences_service ?: new Preferences_Service();
    }

    /**
     * Get the license key
     *
     * @return string|null The license key or null if not set
     */
    public function get_license()
    {
        $preference = $this->preferences_service->get_preference_by_key(self::LICENSE_PREFERENCE_KEY);
        return isset($preference['value']) ? $preference['value'] : null;
    }

    /**
     * Save the license key
     *
     * @param string $license_key The license key to save
     * @return bool True on success, false on failure
     */
    public function save_license($license_key)
    {
        $payload = array(
            array(
                'key' => self::LICENSE_PREFERENCE_KEY,
                'value' => $license_key
            )
        );

        $result = $this->preferences_service->update_preferences($payload);
        return !empty($result['successful']);
    }

    /**
     * Clear the license key
     *
     * @return bool True on success, false on failure
     */
    public function clear_license()
    {
        return $this->preferences_service->delete_preference_by_key(self::LICENSE_PREFERENCE_KEY);
    }
}
