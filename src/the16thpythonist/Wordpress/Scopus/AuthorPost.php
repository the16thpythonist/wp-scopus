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
use Scopus\Response\Abstracts;
use Scopus\Response\AbstractAuthor;
use the16thpythonist\Wordpress\Base\PostPost;


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
class AuthorPost extends PostPost
{
    /**
     * @var string $POST_TYPE   The string name under which the author post type is registered in wordpress.
     *                          The name can be chosen when the static method "register" is called.
     */
    public static $POST_TYPE;

    /**
     * @var AuthorPostRegistration $REGISTRATION    The object used to register the author post type in wordpress
     */
    public static $REGISTRATION;

    /**
     * @var string $post_id The wordpress post ID of the post, around which this wrapper is built
     */
    public $post_id;

    /**
     * @var string $ID The wordpress post ID of the author post;
     */
    public $ID;

    /**
     * @var array|null|\WP_Post The actual WP_Post object of the post around which this wrapper revolves
     */
    public $post;

    /**
     * @var string  The first name of the author
     */
    public $first_name;

    /**
     * @var string  The last name of the author
     */
    public $last_name;

    /**
     * @var array   An array of all the scopus author IDs associated with the author.
     */
    public $author_ids;

    /**
     * @var array   An array of all the string category names for which this authors publications qualify
     */
    public $categories;

    /**
     * @var array   An array of the string scopus affiliation ids for all the whitelisted affiliations. Whitelisted
     *              affiliation means, that every publication of the author, that contains this affiliation will be
     *              posted in the wordpress system.
     */
    public $scopus_blacklist;

    /**
     * @var array   An array of the string scopus affiliation ids for all the blacklisted affiliations. Blacklisted
     *              affiliations: Every publication containing the author and this affiliation will not be posted on WP
     */
    public $scopus_whitelist;

    const DEFAULT_INSERT = array(
        'first_name'        => '',
        'last_name'         => '',
        'ids'               => array(),
        'categories'        => array(),
        'blacklist'         => array(),
        'whitelist'         => array()
    );

    /**
     * AuthorPost constructor.
     *
     * CHANGELOG
     *
     * Added 07.09.2018
     *
     * Changed 28.10.2018
     * Added the field ID and assigned it the wordpress post id in the constructor. This is to keep all the wrapper
     * objects sort the same
     *
     * @since 0.0.0.0
     *
     * @param $post_id
     */
    public function __construct($post_id)
    {
        $this->post_id = $post_id;
        $this->ID = $post_id;
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
     * For a given publication Abstract this method will return if that pub is black/whitelisted
     *
     * This function is especially designed to work with the ScopusApi, as the Abstracts object used as the
     * parameter is a return data structure of the Api object.
     * The function will check if this author is an author of the publication and then based on the affiliation ID
     * the author had back then, when the publication was written determine if the authors stance towards the paper
     * is supposed to be whitelisted (1), blacklisted (-1) or undefined (0) which is also the case if the author is not
     * an author of the publication.
     *
     * CHANGELOG
     *
     * Added 10.09.2018
     *
     * @since 0.0.0.0
     *
     * @param Abstracts $publication    The object returned by the API object as wrapper to a abstract retrieval
     *                                  response.
     * @return int
     */
    public function checkPublication(Abstracts $publication){
        $authors = $publication->getAuthors();
        foreach ($authors as $author) {
            $author_id = $author->getId();
            if (in_array($author_id, $this->author_ids)) {
                if( $affiliation_id = $this->publicationAuthorAffiliationID($author)) {
                    if (in_array($affiliation_id, $this->scopus_whitelist)) {
                        return 1;
                    } elseif (in_array($affiliation_id, $this->scopus_blacklist)) {
                        return -1;
                    } else {
                        return 0;
                    }
                } else {
                    return 0;
                }
            }
        }
        return 0;
    }

    /**
     * For a given AbstractAuthor object, this returns the affiliation ID
     *
     * CHANGELOG
     *
     * Added 10.09.2018
     *
     * @since 0.0.0.0
     *
     * @param AbstractAuthor $author
     * @return bool
     */
    private function publicationAuthorAffiliationID(AbstractAuthor $author) {
        /*
         * The affiliation ID of the author is not part of the values, that can be gotten from the public methods of the
         * AbstractAuthor class. Thus it has to be gotton from the data dict directly using a closure to access the
         * private data field.
         */
        $data = \Closure::bind(function (){return $this->data;}, $author, AbstractAuthor::class)();
        if (array_key_exists('affiliation', $data) && array_key_exists('@id', $data['affiliation'])) {
            return $data['affiliation']['@id'];
        } else {
            return false;
        }
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
            $search = $api->query($search_string)->start($index)->count($step)->viewStandard()->search();
            $entries = $search->getEntries();
            foreach ($entries as $entry) {
                $ids[] = $entry->getScopusId();
            }
            if (count($entries) < $step) {
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
     * Changed 20.10.2018
     * Added the additional argument "class", to make this class match the "PostWrapper" Interface.
     *
     * @param string $post_type The string name, the post type is supposed to have
     * @param string $class     The string class name of the Registration object to be executed to register this PT
     *
     * @since 0.0.0.0
     */
    public static function register(string $post_type, string $class=AuthorPostRegistration::class) {
        static::$POST_TYPE = $post_type;

        /** @var AuthorPostRegistration $registration */
        $registration = new $class($post_type);
        $registration->register();

        static::$REGISTRATION = $registration;
    }

    /**
     * Returns an array with AuthorPost wrapper objects for every author post currently on the website
     *
     * CHANGELOG
     *
     * Added 23.10.2018
     *
     * @since 0.0.0.2
     *
     * @return array
     */
    public static function getAll() {

        // The query will get all the author posts
        $args = array(
            'post_type'         => self::$POST_TYPE,
            'post_status'       => 'publish',
            'posts_per_page'    => -1
        );
        $query = new \WP_Query($args);
        $posts = $query->get_posts();

        // We will create a function, which takes a post and generates a new AuthorPost wrapper from it and then
        // map this function onto the whole list of posts just loaded by the query
        $cb = function($post) { return new AuthorPost($post->ID); };

        return array_map($cb, $posts);
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

    /**
     * Returns whether the "author" post type has been registered in wordpress already
     *
     * CHANGELOG
     *
     * Added 28.10.2018
     *
     * @since 0.0.0.2
     *
     * @return bool
     */
    public static function isRegistered() {
        return post_type_exists(self::$POST_TYPE);
    }

    /**
     * Inserts a new author post into wordpress
     *
     * CHANGELOG
     *
     * Added 28.10.2018
     *
     * @since 0.0.0.2
     *
     * @param array $args   The arguments
     *
     * @return string
     */
    public static function insert($args) {
        // Here we use the default argument array as a foundation, but replace all the fields, that are actually
        // specified by the given arguments, with the "real" values.
        $args = array_replace(self::DEFAULT_INSERT, $args);

        // The full name in the format "{last name}, {first name}" will be used as the title of the author post
        $full_name = sprintf('%s, %s', $args['last_name'], $args['first_name']);

        // Here the arguments array for the post insertion gets prepared. The multiple meta values are being inserted
        // as comma separated values due to a bad design choice, where they are saved as csv string within the meta
        // field and when loading it is expected they have that format.
        $postarr = array(
            'post_type'         => self::$POST_TYPE,
            'post_status'       => 'publish',
            'post_title'        => $full_name,
            'post_content'      => '',
            'meta_input'        => array(
                'first_name'        => $args['first_name'],
                'last_name'         => $args['last_name'],
                'scopus_author_id'  => implode(',', $args['ids']),
                'categories'        => implode(',', $args['categories']),
                'scopus_whitelist'  => implode(',', $args['whitelist']),
                'scopus_blacklist'  => implode(',', $args['blacklist'])
            )
        );
        $wp_id = wp_insert_post($postarr);
        return $wp_id;
    }

    /**
     * Deletes all the author posts, that are currently in the system
     *
     * CHANGELOG
     *
     * Added 28.10.2018
     *
     * @since 0.0.0.2
     */
    public static function removeAll() {

        $author_posts = self::getAll();
        /** @var AuthorPost $author_post */
        foreach ($author_posts as $author_post) {
            wp_delete_post($author_post->ID);
        }
    }
}