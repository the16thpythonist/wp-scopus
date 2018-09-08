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

class AuthorObservatory
{

    public $authors;
    public $scopus_api;

    public function __construct()
    {
        // Loading all the author posts and creating AuthorPost wrapper objects from them
        $this->authors = $this->loadAuthors();

        // Creating a new API object
        $key = WpScopus::$API_KEY;
        $this->scopus_api = new ScopusApi($key);
    }

    /**
     * Returns an array of AuthorPost objects, each wrapping a author post from the wordpress db
     *
     * CHANGELOG
     *
     * Added 08.09.2018
     *
     * @since 0.0.0.0
     *
     * @return array
     */
    private function loadAuthors() {
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