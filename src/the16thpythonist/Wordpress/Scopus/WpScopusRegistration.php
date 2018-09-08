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
    }

    public function registerStyle() {
        wp_enqueue_style('wp-scopus-style', plugin_dir_url(__FILE__) . 'scopus.css');
    }
}