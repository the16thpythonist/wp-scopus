<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 20.10.18
 * Time: 16:06
 */

namespace the16thpythonist\Wordpress\Scopus;

use the16thpythonist\Wordpress\Base\PostPost;
use the16thpythonist\Wordpress\Functions\PostUtil;

/**
 * Class PublicationPost
 *
 * CHANGELOG
 *
 * Added 21.10.2018
 *
 * Changed 23.10.2018
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class PublicationPost extends PostPost
{
    public static $POST_TYPE;

    public static $REGISTRATION;

    public $ID;

    public $post;

    public $title;

    public $abstract;

    public $published;

    public $scopus_id;

    public $doi;

    public $eid;

    public $volume;

    const DEFAULT_INSERT = array(
        'title'             => '',
        'abstract'          => '',
        'published'         => '2012-01-01',
        'scopus_id'         => '',
        'doi'               => '',
        'eid'               => '',
        'issn'              => '',
        'journal'           => '',
        'volume'            => '',
        'collaboration'     => 'NONE',
        'author_count'      => '0',
        'status'            => 'publish',
        'categories'        => array(),
        'tags'              => array(),
        'authors'           => array()
    );

    /**
     * PublicationPost constructor.
     *
     * CHANGELOG
     *
     * Added 23.10.2018
     *
     * @param $post_id
     */
    public function __construct($post_id)
    {
        // Loading the actual post object from the post id
        $this->ID = $post_id;
        $this->post = get_post($post_id);

        // The title of the publication is the normal post title and the abstract of the paper is mapped to the
        // post content
        $this->title = $this->post->post_title;
        $this->abstract = $this->post->post_content;

        /*
         * Ok, so here is the problem, this is a wrapper right? So this constructor gets called in nearly every
         * situation, in which we would want to handle publication posts. And there are a lot of these posts. So my
         * concern is, that if I loaded each and every taxonomy term for every post right away, it would get pretty
         * slow. So what we are going to do is we will only load the meta values for every constructor and put the
         * loading of the taxonomy terms into getter methods, so they really only get loaded, when they are needed!
         */

        // Loading all the meta values
        $this->doi = PostUtil::loadSinglePostMeta($this->ID, 'doi');
        $this->eid = PostUtil::loadSinglePostMeta($this->ID, 'eid');
        $this->scopus_id = PostUtil::loadSinglePostMeta($this->ID, 'scopus_id');
        $this->published = PostUtil::loadSinglePostMeta($this->ID, 'published');
        $this->volume = PostUtil::loadSinglePostMeta($this->ID, 'volume');
    }

    /**
     * Returns an array, that contains the (indexed) names of SOME of the authors involved in the paper
     *
     * NOTE: For collaboration publications it is possible to have above ~100 authors, fetching all that data from
     * the publication data base scopus would take far too long, thus the authors are limited to around ~30 per paper
     *
     * CHANGELOG
     *
     * Added 23.10.2018
     *
     * @return array
     */
    public function getAuthors() {

        // Loading the all the term names of the custom 'author' taxonomy
        return PostUtil::loadTaxonomyStrings($this->ID, 'author');
    }

    /**
     * Returns an array, containing the WP_Term objects for the publication authors
     *
     * CHANGELOG
     *
     * Added 23.10.2018
     *
     * @return array|\WP_Error
     */
    public function getAuthorTerms() {
        return wp_get_post_terms($this->ID, 'author');
    }

    /**
     * Returns an array, that contains the tags for the publication
     *
     * CHANGELOG
     *
     * Added 23.10.2018
     *
     * @return array
     */
    public function getTags() {

        // Loading all the term names of the wordpress standard 'post_tag' taxonomy
        return PostUtil::loadTaxonomyStrings($this->ID, 'post_tag');
    }

    /**
     * Returns an array, that contains the category names under which the publication is listed
     *
     * CHANGELOG
     *
     * Added 23.10.2018
     *
     * @return array
     */
    public function getCategories() {

        // Loading all the term names of the standard 'category' taxonomy
        return PostUtil::loadTaxonomyStrings($this->ID, 'category');
    }

    /**
     * returns the name of the collaboration, the journal is a part of
     *
     * In case the publication is not part of a collaboration, returns "NONE", if it is part of a collaboration,
     * but it is not clear, which returns "ANY"
     *
     * CHANGELOG
     *
     * Added 23.10.2018
     *
     * @return string
     */
    public function getCollaboration() {

        return PostUtil::loadSingleTaxonomyString($this->ID, 'collaboration');
    }

    /**
     * returns the name of the journal in which the publication was published
     *
     * CHANGELOG
     *
     * Added 23.10.2018
     *
     * @return string
     */
    public function getJournal() {

        return PostUtil::loadSingleTaxonomyString($this->ID, 'journal');
    }

    /**
     * Adds a new author term to the publication post
     *
     * CHANGELOG
     *
     * Added 28.10.2018
     *
     * @since 0.0.0.2
     *
     * @param string $author_name
     * @param string $author_id
     */
    public function addAuthor(string $author_name, string $author_id) {

        // This method is a generalized solution to the problem, it adds the author given by its name and id to any
        // post, identified by its wordpress post id.
        // So here we just call this utility function with the wordpress post id, currently described by wrapper object
        self::addAuthorToPublication($this->ID, $author_name, $author_id);
    }

    /**
     * Registers the publication post type in wordpress
     *
     * This static function has to be called right at the startup of wordpress
     *
     * CHANGELOG
     *
     * Added 21.10.2018
     *
     * Changed 28.10.2018
     * Added the line, which saved the string post type in the static field POST_TYPE, so it can be used later.
     *
     * @since 0.0.0.2
     *
     * @param string $post_type
     * @param string $class
     */
    public static function register(string $post_type, string $class=PublicationPostModification::class)
    {
        /** @var PublicationPostModification $registration */
        $registration = new $class($post_type);
        $registration->register();

        self::$POST_TYPE = $registration->post_type;
        self::$REGISTRATION = $registration;
    }

    /**
     * Returns an array of publication post wrappers for each post currently on the website
     *
     * CHANGELOG
     *
     * Added 23.10.2018
     *
     * @since 0.0.0.2
     *
     * @param bool $include_drafts          Whether to also include drafted publication posts
     * @param bool $include_collaborations  Whether to include publications that are part of a collaboration
     * @return array
     */
    public static function getAll(bool $include_drafts=FALSE, bool $include_collaborations=FALSE) {

        // If we want to include the drafts, then the 'post_status' option will be set to any (will also include
        // future posts and auto drafts)
        $post_status = ($include_drafts ? 'any' : 'publish');

        // preparing the parameter array for the query
        $args = array(
            'post_type'         => self::$POST_TYPE,
            'post_status'       => $post_status,
            'posts_per_page'    => -1,
        );

        // If collaborations are NOT supposed to be included, we need to add an additional taxonomy query, which will
        // tell the query "Only take posts, that include the term 'none' as collaboration"
        if (!$include_collaborations) {
            $collaborations_query = array(
                'taxonomy'      => 'collaboration',
                'field'         => 'slug',
                'terms'         => array('none'),
                'operator'      => 'IN'
            );

            $args['tax_query'] = array($collaborations_query);
        }

        $query = new \WP_Query($args);
        $posts = $query->get_posts();

        // Now we only have the posts, but we want to use the publication post wrappers, we get an array of these, by
        // mapping a function, that takes a post as input and outputs a wrapper, to the array of all the posts
        $cb = function ($post) {return new PublicationPost($post->ID); };
        return array_map($cb, $posts);

    }

    /**
     * Whether or not the "publication" post type already exists in wordpress
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
     * Inserts a new publication post into the database with the given arguments.
     *
     * CHANGELOG
     *
     * Added 28.10.2018
     *
     * @param $args
     * @return int|\WP_Error
     */
    public static function insert($args) {
        $args = array_replace(self::DEFAULT_INSERT, $args);

        $postarr = array(
            'post_type'         => self::$POST_TYPE,
            'post_title'        => $args['title'],
            'post_content'      => $args['abstract'],
            'post_status'       => $args['status'],
            'meta_input'        => array(
                'scopus_id'         => $args['scopus_id'],
                'published'         => $args['published'],
                'volume'            => $args['volume'],
                'doi'               => $args['doi'],
                'eid'               => $args['eid'],
                'author_count'      => $args['author_count'],
                'issn'              => $args['issn']
            ),
        );
        $wpid = wp_insert_post($postarr);

        // Here we insert the taxonomy values into the post. We need to do this with separate functions, because
        // sadly the 'tax_input' option in the postarr does not work very well.
        wp_set_object_terms($wpid, $args['journal'], 'journal', true);
        wp_set_object_terms($wpid, $args['collaboration'], 'collaboration', true);
        wp_set_object_terms($wpid, $args['tags'], 'post_tag', true);
        wp_set_object_terms($wpid, $args['categories'], 'category', true);

        // We need to insert the author taxonomy like this, because the author terms need to have a custom slug.
        // The slug of an author term is supposed to be the scopus author id associated with that author
        foreach ($args['authors'] as $author_name => $author_id) {
            self::addAuthorToPublication($wpid, $author_name, $author_id);
        }

        return $wpid;
    }

    /**
     * Adds the author described by its string name and the scopus author id to the post of the given wordpress post id
     *
     * This is a utility function, which adds a new author taxonomy term to the post, identified by the given post ID
     *
     * CHANGELOG
     *
     * Added 28.10.2018
     *
     * @since 0.0.0.2
     *
     * @param string $post_id       The ID of the post, to which to add the author to
     * @param string $author_name   The name of the author to add. This string will later be displayed on the frontend
     * @param string $author_id     The scopus author ID. This is used as the slug of the Term object, as a unique
     *                              identifier
     */
    public static function addAuthorToPublication(string $post_id, string $author_name, string $author_id) {

        // Before actually creating a new Term object, we test if a term for that author id already exists.
        // In case it does not exists, we first create a new Term object, where the term name will be set to the given
        // author name and the slug will be set to the scopus author id (which is a unique identifier already anyways)
        if (empty(term_exists($author_id, 'author'))) {

            wp_insert_term(
                $author_name,
                'author',
                array(
                    'slug'      => $author_id
                )
            );
        }

        // In any case the term object now exists within wordpress, but it is not yet linked to any post.
        // That is what we are doing now, we assigning the author to the post, described by this wrapper
        wp_set_object_terms($post_id, $author_name, 'author', true);
    }

    /**
     * Deletes all publication posts, that are currently in the wordpress system
     *
     * ! USE WITH CAUTION
     *
     * CHANGELOG
     *
     * Added 28.10.2018
     */
    public static function removeAll() {

        // Here we just use the already implemented method, which will make a query to the database to return ALL
        // the PublicationPost wrappers of all the publications currently saved in wordpress.
        $publication_posts = self::getAll(TRUE, TRUE);
        /** @var PublicationPost $publication_post */
        foreach ($publication_posts as $publication_post) {
            wp_delete_post($publication_post->ID, true);
        }
    }
}