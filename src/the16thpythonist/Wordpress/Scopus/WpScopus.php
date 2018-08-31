<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 31.08.18
 * Time: 09:25
 */

namespace the16thpythonist\Wordpress\Scopus;


/**
 * Class WpScopus
 *
 * This is a static class used as a facade to access the 'wp-scopus' package
 *
 * CHANGELOG
 *
 * Added 31.08.2018
 *
 * @since 0.0.0.0
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class WpScopus
{
    const STD_POST_TYPES = array(
        'author'
    );

    public static $AUTHOR_POST_TYPE;
    public static $API_KEY;

    /**
     *
     * The parameter post type can either be a string or a array.
     * - If it is a string, that string will be interpreted as prefix to be used for all the standard post type names,
     * for example the standard post type name for the Author CPT is 'author', giving the string 'hr', will make the
     * author post type be named 'hr_author'
     * - If it is an array, it has to be an associative array, which assigns each post type created by the wp-scopus
     * package a new custom name for the post type. All post types that are not being assigned custom names, will be
     * given the standard post type names
     *
     * CHANGELOG
     *
     * Added 31.08.2018
     *
     * @since 0.0.0.0
     *
     * @param $post_type string|array
     * @param string $api_key
     */
    public static function register($post_type, string $api_key) {
        static::$API_KEY = $api_key;
    }
}