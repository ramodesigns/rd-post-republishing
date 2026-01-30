<?php
/**
 * Service class for authentication tokens
 *
 * Handles generation and retrieval of the 'at_value' preference
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Authentication_Service
{
    /**
     * Preferences service instance
     *
     * @var Preferences_Service
     */
    private $preferences_service;

    /**
     * Authorisation helper instance
     *
     * @var Authorisation_Helper
     */
    private $authorisation_helper;

    /**
     * Constructor
     *
     * @param Preferences_Service $preferences_service
     * @param Authorisation_Helper $authorisation_helper
     */
    public function __construct($preferences_service, $authorisation_helper)
    {
        $this->preferences_service = $preferences_service;
        $this->authorisation_helper = $authorisation_helper;
    }

    /**
     * Generate a new token and save it to 'at_value' preference
     *
     * @return string The generated token
     */
    public function generate_at_token()
    {
        $token = $this->authorisation_helper->generate_token();
        
        $this->preferences_service->update_preferences(array(
            array(
                'key' => 'at_value',
                'value' => $token
            )
        ));

        return $token;
    }

    /**
     * Retrieve the 'at_value' preference
     *
     * @return string|null The token or null if not set
     */
    public function retrieve_at_token()
    {
        $preference = $this->preferences_service->get_preference_by_key('at_value');
        return $preference ? $preference['value'] : null;
    }
}
