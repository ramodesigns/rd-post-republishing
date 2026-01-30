<?php
/**
 * Service class for managing WP Cron jobs
 *
 * Handles scheduling and unscheduling of the republishing process
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Cron_Service
{
    /**
     * Cron hook name
     */
    const CRON_HOOK = 'rd_pr_republish_cron';

    /**
     * Preferences service instance
     *
     * @var Preferences_Service
     */
    private $preferences_service;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->preferences_service = new Preferences_Service();
    }

    /**
     * Handle the cron event
     */
    public function handle_cron_event()
    {
        $process_service = new Process_Service();
        $process_service->execute_republish_process();
    }

    /**
     * Manage the cron job based on current preferences
     *
     * @return void
     */
    public function manage_cron()
    {
        $status = $this->get_preference_value('status');
        $wp_cron = $this->get_preference_value('wp_cron');

        if ($status === 'active' && $wp_cron === 'active') {
            $this->schedule_cron();
        } else {
            $this->unschedule_cron();
        }
    }

    /**
     * Schedule the cron job
     *
     * @return void
     */
    private function schedule_cron()
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            // Schedule for every hour
            wp_schedule_event(time(), 'hourly', self::CRON_HOOK);
        }
    }

    /**
     * Unschedule the cron job
     *
     * @return void
     */
    private function unschedule_cron()
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Get a preference value by key
     *
     * @param string $key
     * @return string|null
     */
    private function get_preference_value($key)
    {
        $preference = $this->preferences_service->get_preference_by_key($key);
        return $preference ? $preference['value'] : null;
    }
}
