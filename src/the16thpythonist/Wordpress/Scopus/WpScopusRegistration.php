<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 03.09.18
 * Time: 16:30
 */

namespace the16thpythonist\Wordpress\Scopus;


class WpScopusRegistration
{

    public function __construct()
    {
    }

    public function register() {
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
        DeletePublicationsCommand::register('delete-all-publications');
    }

    /**
     * Registers all the shortcodes to be provided by the scopus plugin
     */
    public function registerShortcodes() {
        $metrics = new AuthorMetricsShortcode('author-metrics');
        $metrics->register();
    }
}