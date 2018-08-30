<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 30.08.18
 * Time: 16:07
 */

namespace the16thpythonist\Wordpress\Scopus;

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
    public $scopus_black_list;
    public $scopus_white_list;

    public function __construct($post_id)
    {
        $this->post_id = $post_id;
        $this->post_id = get_post($post_id);

        /*
         *
         */
        $this->first_name = get_post_meta($this->post_id, 'first_name', true);
        $this->last_name = get_post_meta($this->post_id, 'last_name', true);
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
}