<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 03.09.18
 * Time: 16:30
 */

namespace the16thpythonist\Wordpress\Scopus;

use Log\LogPost;
use the16thpythonist\Wordpress\Data\DataPost;
use the16thpythonist\Wordpress\WpCommands;

/**
 * Class WpScopusRegistration
 *
 * CHANGELOG
 *
 * Added 18.10.2018
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class WpScopusRegistration
{

    public function __construct()
    {
    }

    // **************************************
    // FUNCTIONS FOR REGISTERING IN WORDPRESS
    // **************************************

    /**
     * CHANGELOG
     *
     * Added 18.10.2018
     *
     * Changed 18.11.2018
     * Using this method to also register the packages for logging and saving the data
     *
     * Changed 11.02.2019
     * Added the call to the method "registerOptionsPage", which registers the settings section for this package.
     * Replaced the manual hook in of the stylesheets with the new call to the method "registerAssets".
     */
    public function register() {

        // Registering the needed packages for logging, saving the data and executing commands
        DataPost::register('hh_data');
        LogPost::register('hh_log');
        WpCommands::register();

        $this->registerPostTypes();
        $this->registerCommands();
        $this->registerShortcodes();

        // 11.02.2019
        // Registers the options page to be correctly displayed for the scopus wp package
        $this->registerOptionsPage();
        // Enqueues the stylesheets and scripts for this package with wordpress, so that wordpress adds them to
        // the header of the generated html pages
        $this->registerAssets();
    }

    /**
     * Registers all the post types needed for the package
     *
     * CHANGELOG
     *
     * Added 21.10.2018
     */
    public function registerPostTypes() {
        AuthorPost::register('author');
        PublicationPost::register('publication');
    }

    /**
     * Registers all the possible background commands/tasks available
     *
     * CHANGELOG
     *
     * Added 23.10.2018
     */
    public function registerCommands() {
        GenerateAuthorMetricsCommand::register('generate-author-metrics');
        FetchPublicationsCommand::register('fetch-scopus-publications');
        UpdatePublicationsCommand::register('update-scopus-publications');
        UpdateKITOpenCommand::register('update-kitopen');
    }

    /**
     * Registers all the shortcodes to be provided by the scopus plugin
     *
     * CHANGELOG
     *
     * Added 23.10.2018
     *
     * Changed 13.01.2019
     * Added the registration for the shortcode, which displays a listing of the most recent publications published.
     */
    public function registerShortcodes() {
        $metrics = new AuthorMetricsShortcode('author-metrics');
        $metrics->register();

        // 13.01.2019
        $recent_publications_shortcode = new RecentPublicationsShortcode();
        $recent_publications_shortcode->register();
    }

    /**
     * This function register the option page for the plugin within wordpress.
     *
     * CHANGELOG
     *
     * Added 11.02.2019
     */
    public function registerOptionsPage() {
        $scopus_options_registration = new ScopusOptionsRegistration();
        $scopus_options_registration->register();
    }

    /**
     * Hooks in the scripts and the stylesheets for the scopuswp package
     *
     * CHANGELOG
     *
     * Added 11.02.2018
     */
    public function registerAssets() {
        add_action('init', array($this, 'enqueueStylesheets'));
        add_action('init', array($this, 'enqueueScripts'));
    }

    // ***************************
    // STYLESHEETS FOR THE PACKAGE
    // ***************************

    /**
     * Calls the wordpress "wp_enqueue_style" for all the stylesheets relevant to the scopuswp package.
     * This function needs to be executed in the "init" hook of wordpress.
     *
     * CHANGELOG
     *
     * Added 11.02.2019
     */
    public function enqueueStylesheets() {
        wp_enqueue_style(
            'wp-scopus-style',
            plugin_dir_url(__FILE__) . 'scopus.css'
        );
    }

    // ***********************
    // SCRIPTS FOR THE PACKAGE
    // ***********************

    /**
     * Calls the wordpress "wp_enqueue_script" for the VueJS build file of this package.
     *
     * CHANGELOG
     *
     * Added 11.02.2019
     */
    public function enqueueScripts() {
        wp_enqueue_script(
            'scopus-build',
            plugin_dir_url(__FILE__) . 'scopuswp-build.js',
            [],
            '0.0.0.0',
            true
        );
    }
}
