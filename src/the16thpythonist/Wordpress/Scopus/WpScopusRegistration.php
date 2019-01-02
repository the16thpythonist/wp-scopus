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

    /**
     * CHANGELOG
     *
     * Added 18.10.2018
     *
     * Changed 18.11.2018
     * Using this method to also register the packages for logging and saving the data
     */
    public function register() {

        // Registering the needed packages for logging, saving the data and executing commands
        DataPost::register('hh_data');
        LogPost::register('hh_log');
        WpCommands::register();

        // Registering the styles needed
        add_action('init', array($this, 'registerStyle'));

        $this->registerPostTypes();
        $this->registerCommands();
        $this->registerShortcodes();
    }

    public function registerStyle() {
        wp_enqueue_style('wp-scopus-style', plugin_dir_url(__FILE__) . 'scopus.css');
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
     */
    public function registerShortcodes() {
        $metrics = new AuthorMetricsShortcode('author-metrics');
        $metrics->register();
    }
}