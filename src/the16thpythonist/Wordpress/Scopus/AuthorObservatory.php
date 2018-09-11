<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 08.09.18
 * Time: 18:10
 */

namespace the16thpythonist\Wordpress\Scopus;

use WP_Query;
use Scopus\ScopusApi;
use Scopus\Response\Abstracts;


/**
 * Class AuthorObservatory
 *
 * CHANGELOG
 *
 * Added 07.09.2018
 *
 * @since 0.0.0.0
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class AuthorObservatory
{
    /**
     * @var array $authors Contains the AuthorPost wrapper objects for all the authors saved in the wordpress database
     */
    public $authors;

    /**
     * @var array $authors_map  Associative array, whose keys are the author ids of the authors saved in the wordpress
     *                          database and the values being the AuthorPost wrapper objects
     */
    public $authors_map;

    /**
     * @var ScopusApi $scopus_api   The Api wrapper used to make requests to the scopus database/website
     */
    public $scopus_api;

    /**
     * AuthorObservatory constructor.
     *
     * CHANGELOG
     *
     * Added 08.09.2018
     *
     * @since 0.0.0.0
     */
    public function __construct()
    {
        // Loading all the author posts and creating AuthorPost wrapper objects from them
        $this->authors = $this->loadAuthors();

        // Creating a new API object
        $key = WpScopus::$API_KEY;
        $this->scopus_api = new ScopusApi($key);

        /*
         * The author map is supposed to be an associative array, which contains the author ids of the authors as keys
         * and the actual wrapper objects as values.
         */
        /** @var AuthorPost $author */
        foreach ($this->authors as $author) {
            foreach ($author->author_ids as $id) {
                $this->authors_map[$id] = $author;
            }
        }
    }

    /**
     * Fetches all scopus ids for all publications any of the authors has worked on from the authors scopus profile
     *
     * CHANGELOG
     *
     * Added 10.09.2018
     *
     * @since 0.0.0.0
     *
     * @return array
     */
    public function fetchScopusIDs() {
        $ids = array();
        /** @var AuthorPost $author */
        foreach ($this->authors as $author) {
            $_ids = $author->fetchScopusIDs($this->scopus_api);
            $ids = array_merge($_ids, $ids);
        }
        /*
         * Since the possibility of a few of the authors having worked on a publication together at times is very high
         * there might be duplicates in the list, which have to be removed.
         */
        $ids = array_unique($ids);
        return $ids;
    }

    /**
     * For a given publication Abstract this method will return if that pub is black/whitelisted
     *
     * This function is especially designed to work with the ScopusApi, as the Abstracts object used as the
     * parameter is a return data structure of the Api object.
     * Based on the authors of the given publication object, this function will first evaluate which of the
     * authors observed by this observatory were also authors of the publication and then based on these authors
     * black and whitelists it is evaluated if the status of the publication should be blacklisted (returns -1),
     * whitelisted (1) or unknown (0).
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
    public function checkPublication(Abstracts $publication) {
        $checks = array();
        /** @var AuthorPost $author */
        foreach ($this->authors as $author) {
            $checks[] = $author->checkPublication($publication);
        }

        /*
         * If even one of the authors was explicitly whitelisted for this publication, then the publication will be
         * valid overall, if that is not the case and one of them was blacklisted, the publication is not valid.
         * In any other case the status is unknown.
         */
        if (in_array(1, $checks)) {
            return 1;
        } elseif (in_array(-1, $checks)) {
            return -1;
        } else {
            return 0;
        }
    }

    /**
     * Returns the list of all category names of all observed authors, which are authors of the given abstract
     *
     * CHANGELOG
     *
     * Added 09.09.2018
     *
     * @since 0.0.0.0
     *
     * @param Abstracts $publication
     * @return array
     */
    public function getCategoriesPublication(Abstracts $publication) {
        // Creating a list of all the author IDS of the authors of the publication
        $authors = $publication->getAuthors();
        $author_ids = array_map(function ($a){return $a->getId(); }, $authors);

        return $this->getCategories($author_ids);
    }

    /**
     * Returns the list of all the category names of all observed authors, occuring within the given list of author ids
     *
     * CHANGELOG
     *
     * Added 09.09.2018
     *
     * @since 0.0.0.0
     *
     * @param array $author_ids
     * @return array
     */
    public function getCategories(array $author_ids) {
        // A list of all the author ids of all the authors observed by this observatory
        $observed_authors = array_keys($this->authors_map);

        /*
         * A list of all the author ids within the given list of ids, that are also the ids of authors, observed by this
         * observatory.
         */
        $relevant_authors = array_intersect($author_ids, $observed_authors);

        $categories = array();
        foreach ($relevant_authors as $author_id) {
            /*
             * The actual post wrapper to the ID can be accessed through the assoc array 'authors_map', which stores the
             * wrapper objects as values to the ID keys. The categories will then be extended by the categories of the
             * current author, but only if they are not already in there.
             */
            /** @var AuthorPost $author */
            $author = $this->authors_map[$author_id];
            $categories = array_unique(array_merge($categories, $author->categories));
        }

        return $categories;
    }

    /**
     * Returns an array of AuthorPost objects, each wrapping a author post from the wordpress db
     *
     * CHANGELOG
     *
     * Added 08.09.2018
     *
     * Changed 09.09.2018
     *
     * @since 0.0.0.0
     *
     * @return array
     */
    public function loadAuthors() {
        $authors = array();
        $posts = $this->loadPosts();
        foreach ($posts as $post) {
            $post_id = $post->ID;
            $author = new AuthorPost($post_id);
            $authors[] = $author;
        }
        return $authors;
    }

    /**
     * Returns an array of WP_Post objects, each post representing an author from the wordpress db
     *
     * CHANGELOG
     *
     * Added 08.09.2018
     *
     * @since 0.0.0.0
     *
     * @return array
     */
    private function loadPosts() {
        $args = array(
            'post_type'         => AuthorPost::$POST_TYPE,
            'posts_per_page'    => -1,
        );
        $query = new WP_Query($args);
        $posts = $query->get_posts();
        return $posts;
    }
}