<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 30.08.18
 * Time: 16:07
 */

namespace the16thpythonist\Wordpress\Scopus;

use BrowscapPHP\Exception\FileNotFoundException;
use Scopus\ScopusApi;

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
        $this->post = get_post($post_id);

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

    /**
     * Returns array with scopus ids for all the publications of this author
     *
     * This method uses all the author ids, if there were multiple specified for this author
     *
     * CHANGELOG
     *
     * Added 09.08.2018
     *
     * @since 0.0.0.0
     *
     * @param ScopusApi $api
     * @return array
     */
    public function fetchScopusIDs(ScopusApi $api) {
        $ids = array();
        foreach ($this->author_ids as $author_id) {
            $_ids = $this->singleFetchScopusIDs($author_id, $api);
            $ids = array_merge($ids, $_ids);
        }
        return $ids;
    }

    /**
     * Returns array with scopus ids of all publications of the author by the given author id
     *
     * CHANGELOG
     *
     * Added 09.08.2018
     *
     * @since 0.0.0.0
     *
     * @param string $author_id the string id of the author for which to get all the publications
     * @param ScopusApi $api    the ScopusApi object to be used to contact the scopus website/database
     * @param int $step         the int amount of publications to be fetched with a single request to scopus. Example
     *                          if there are 340 pubs with 200 step size, two requests will be sent, with the first one
     *                          limited to 200 pubs for result and the second with 140
     * @return array
     */
    private function singleFetchScopusIDs(string $author_id, ScopusApi $api, int $step=200) {
        $ids = array();
        $search_string = sprintf('AU-ID(%s)', $author_id);
        $results_remaining = true;
        $index = 0;
        while ($results_remaining){
            $search = $api->query($search_string)->start($index)->count($index + $step)->viewStandard()->search();
            $entries = $search->getEntries();
            if (count($entries) < $step) {
                array_map(function ($e) {$ids[] = $e->getScopusId(); }, $entries);
                $results_remaining = false;
            } else {
                $index += $step;
            }
        }
        return $ids;
    }

    /**
     * Whether or not the given affiliation id is whitelisted for this author
     *
     * CHANGELOG
     *
     * Added 08.09.2018
     *
     * @since 0.0.0.0
     *
     * @param string $affiliation_id
     * @return bool
     */
    public function isWhitelist(string $affiliation_id) {
        return in_array($affiliation_id, $this->scopus_whitelist);
    }

    /**
     * Whether or not the given affiliation id is blacklisted for this author
     *
     * CHANGELOG
     *
     * Added 08.09.2018
     *
     * @since 0.0.0.0
     *
     * @param string $affiliation_id
     * @return bool
     */
    public function isBlacklist(string $affiliation_id) {
        return in_array($affiliation_id, $this->scopus_blacklist);
    }

    /**
     * Based on the single flag, either loads singular values or arrays from the given wordpress meta key string
     *
     * This is simply a wrapper, around the "singleMeta" and "multipleMeta" methods and calls the one or the other
     * based on the flag value.
     *
     * CHANGELOG
     *
     * Added 08.09.2018
     *
     * @since 0.0.0.0
     *
     * @param string $key
     * @param bool $single
     * @return array|mixed
     */
    private function loadMeta(string $key, bool $single) {
        if ($single === true) {
            return $this->singleMeta($key);
        } else {
            return $this->multipleMeta($key);
        }
    }

    /**
     * Returns the list of values of the meta field, given the string meta key name
     *
     * Some of the data associated with an author are just single values, such as the name, but other information is
     * list-like, which means there are multiple values associated to a single meta key. An author could have multiple
     * scopus profiles and thus multiple scopus ids, or an author can be structured in multiple categories.
     *
     * So meta value can be singular values or list like. This wrapper builds on saving the list like values as a
     * comma separated string, which is a sort of encoding. This in turn means that loading meta data from keys that
     * contain single or list-like values differ in the way, the given string is decoded.
     *
     * This method decodes the given string as a comma separated list and returns the resulting array of values.
     *
     * CHANGELOG
     *
     * Added 08.09.2018
     *
     * @since 0.0.0.0
     *
     * @param string $key
     * @return array
     */
    private function multipleMeta(string $key) {
        return str_getcsv($this->singleMeta($key));
    }

    /**
     * Returns the value of the wordpress meta field, given the string meta key name
     *
     * Some of the data associated with an author are just single values, such as the name, but other information is
     * list-like, which means there are multiple values associated to a single meta key. An author could have multiple
     * scopus profiles and thus multiple scopus ids, or an author can be structured in multiple categories.
     *
     * So meta value can be singular values or list like. This wrapper builds on saving the list like values as a
     * comma separated string, which is a sort of encoding. This in turn means that loading meta data from keys that
     * contain single or list-like values differ in the way, the given string is decoded.
     *
     * This method decodes the given string into a single value, given the meta key string.
     *
     * CHANGELOG
     *
     * Added 08.09.2018
     *
     * @since 0.0.0.0
     *
     * @param string $key
     * @return mixed
     */
    private function singleMeta(string $key) {
        return get_post_meta($this->post_id, $key, true);
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