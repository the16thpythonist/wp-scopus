<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 30.08.18
 * Time: 16:07
 */

namespace the16thpythonist\Wordpress\Scopus;

use BrowscapPHP\Exception\FileNotFoundException;

/**
 * Class AuthorPost
 *
 * CHANGELOG
 *
 * Added 30.08.2018
 *
 * @since 0.0.0.0
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class AuthorPost
{
    public static $POST_TYPE;
    public static $REGISTRATION;

    public $post_id;
    public $post;

    public $first_name;
    public $last_name;
    public $author_ids;
    public $categories;
    public $scopus_blacklist;
    public $scopus_whitelist;

    public function __construct($post_id)
    {
        $this->post_id = $post_id;
        $this->post_id = get_post($post_id);

        /*
         *
         */
        $this->first_name = $this->loadMeta('first_name', true);
        $this->last_name = $this->loadMeta('last_name', true);
        $this->author_ids = $this->loadMeta('scopus_author_id', false);
        $this->categories = $this->loadMeta('categories', false);
        $this->scopus_blacklist = $this->loadMeta('scopus_blacklist', false);
        $this->scopus_whitelist = $this->loadMeta('scopus_whitelist', false);
    }

    public function is_whitelist(string $affiliation_id) {
        return in_array($affiliation_id, $this->scopus_whitelist);
    }

    public function is_blacklist(string $affiliation_id) {
        return in_array($affiliation_id, $this->scopus_blacklist);
    }

    private function loadMeta(string $key, bool $single) {
        if ($single === true) {
            return $this->singleMeta($key);
        } else {
            return $this->multipleMeta($key);
        }
    }

    private function multipleMeta(string $key) {
        return str_getcsv($this->singleMeta($key));
    }

    private function singleMeta(string $key) {
        return $this->first_name = get_post_meta($this->post_id, $key, true);
    }

    /**
     * Registers the post type with wordpress
     *
     * CHANGELOG
     *
     * Added 30.08.2018
     *
     * @param string $post_type
     *
     * @since 0.0.0.0
     */
    public static function register(string $post_type) {
        static::$POST_TYPE = $post_type;

        $registration = new AuthorPostRegistration($post_type);
        $registration->register();

        static::$REGISTRATION = $registration;
    }

    /**
     * Returns whether or not a author post with the given author id exists or not
     *
     * CHANGELOG
     *
     * Added 31.08.2018
     *
     * @since 0.0.0.0
     *
     * @param string $author_id
     * @return bool
     */
    public static function exists(string $author_id) {
        $query = static::authorIDQuery($author_id);
        return $query->post_count !== 0;
    }

    /**
     * Loads an AuthorPost object based on the author ID
     *
     * CHANGELOG
     *
     * Added 31.08.2018
     *
     * @since 0.0.0.0
     *
     * @throws FileNotFoundException    If there is no author with the given ID
     * @param string $author_id     The scopus author ID
     * @return AuthorPost
     */
    public static function load(string $author_id) {
        $query = static::authorIDQuery($author_id);
        if ($query->post_count !== 0) {
            $post = $query->get_posts()[0];
            $post_id = $post->ID;
            $author = new AuthorPost($post_id);
            return $author;
        } else {
            throw new FileNotFoundException(sprintf('There is not author with the ID "%s"', $author_id));
        }
    }

    /**
     * Creates a WP_Query, which will search for any AuthorPost posts, that are identified by the given author id
     *
     * CHANGELOG
     *
     * Added 31.08.2018
     *
     * @since 0.0.0.0
     *
     * @param string $author_id
     * @return \WP_Query
     */
    private static function authorIDQuery(string $author_id) {
        $args = array(
            'post_type'         => self::$POST_TYPE,
            'post_status'       => 'publish',
            'meta_query'        => array(
                array(
                    'key'       => 'scopus_author_id',
                    'value'     => $author_id,
                    'compare'   => 'LIKE'
                )
            )
        );
        $query = new \WP_Query($args);
        return $query;
    }
}