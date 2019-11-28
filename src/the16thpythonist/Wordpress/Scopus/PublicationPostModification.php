<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 20.10.18
 * Time: 16:07
 */

namespace the16thpythonist\Wordpress\Scopus;

use the16thpythonist\KITOpen\Publication;
use the16thpythonist\Wordpress\Base\PostRegistration;
use the16thpythonist\Wordpress\Functions\PostUtil;

/**
 * Class PublicationPostModification
 *
 * CHANGELOG
 *
 * Added 20.10.2018
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class PublicationPostModification implements PostRegistration
{
    /**
     * @var string $post_type  Contains "post", the string post type name of the wordpress standard post type
     */
    public $post_type = 'post';

    const LABEL = 'Publication';
    const ICON = 'dashicons-format-aside';
    const TAXONOMIES = array(
        'journal'       => array(
            'label'         => 'Journal',
            'public'        => true,
        ),
        'collaboration' => array(
            'label'         => 'Collaboration',
            'public'        => true,
        ),
        'author'        => array(
            'label'         => 'Author',
            'public'        => true,
        ),
        'selection'     => array(
            'label'         => 'Selection',
            'public'        => true,
        )
    );

    public function __construct(string $post_type='')
    {
    }

    /**
     * Returns "post", the string name of the standard wordpress post type
     *
     * CHANGELOG
     *
     * Added 20.10.2018
     *
     * @since 0.0.0.2
     *
     * @return string
     */
    public function getPostType()
    {
        return $this->post_type;
    }

    // *************************
    // REGISTERING THE POST TYPE
    // *************************

    /**
     * Hooking in all the methods needed to modify the "post" type to represent publication type posts
     *
     * CHANGELOG
     *
     * Added 20.10.2018
     *
     * Changed 12.02.2019
     * Added the method, that registers the comments to be disabled for the post type.
     *
     * Changed 28.11.2019
     * Applying the static method "trashPost" to the "wp_trash_post" hook now. Whenever a publication post is being
     * trashed, a new boolean flag is added to the meta cache, which will prevent the publication from being added
     * again.
     *
     * @return void
     */
    public function register()
    {
        // The method "modifyPostTypeArguments" changes the label of the post type to "Publications" and changes the
        // Icon. With this, we apply this method to a filter, which filters the arguments for a post type before
        // actually applying the arguments
        add_filter('register_post_type_args', array($this, 'modifyPostTypeArguments'), 20, 2);

        add_action('init', array($this, 'registerTaxonomies'));

        add_filter('save_post', array($this, 'savePost'), 20, 2);
        // 28.11.2019
        // This filter will add a new flag to the publications meta cache entry, which will prevent the publication to
        // be fetched again after it has been trashed once.
        add_filter('wp_trash_post', array($this, 'trashPost'), 20, 2);
        //add_filter('wp_insert_post_data', array($this, 'insertPostData'), 20, 2);

        // 12.02.2019
        // Disabling the comments under a scopus publication
        $this->registerDisabledComments();
    }

    /**
     * Hooks in the method, that actuall disables the comments into wordpress "init"
     *
     * CHANGELOG
     *
     * Added 12.02.2019
     */
    public function registerDisabledComments() {
        add_action('init', array($this, 'disableComments'));
    }

    /**
     * Disables the comments for this post type.
     * Has to be called in wordpress "init"
     *
     * CHANGELOG
     *
     * Added 12.02.2019
     */
    public function disableComments() {
        remove_post_type_support($this->post_type, 'comments');
    }

    public function remove() {

        remove_filter('register_post_type_args', array($this, 'modifyPostTypeArguments'));
        remove_action('init', array($this, 'registerTaxonomies'));

        remove_filter('save_post', array($this, 'savePost'));
    }

    /**
     * Changes the arguments of the post type registration for the standard post type "post" before they are applied.
     *
     * Changes the label and the menu icon.
     *
     * CHANGELOG
     *
     * Added 20.10.2018
     *
     * @since 0.0.0.2
     *
     * @param array $args       The array of arguments passed to the "register_post_type" function
     * @param string $post_type The string name of the post type, whose arguments are to be filtered
     * @return mixed
     */
    public function modifyPostTypeArguments($args, $post_type) {
        // This filter function gets applied to each and every post type registration, which means we need an if
        // statement to only execute our code only for the post type we want.
        if ($post_type == $this->post_type) {
            $args['label'] = self::LABEL;
            $args['menu_icon'] = self::ICON;
        }
        // A filter obviously needs to pass on the data again, after it was modified.
        return $args;
    }

    /**
     * Registers all the taxonomies lists in the "TAXONOMIES" array with the specified args arrays (values of the array)
     *
     * CHANGELOG
     *
     * Added 20.10.2018
     *
     * @since 0.0.0.2
     *
     * @return void
     */
    public function registerTaxonomies() {
        // The "TAXONOMIES" is an associative array, whose keys are the string taxonomy names and the values are the
        // argument arrays to be used for every specific post type
        foreach (self::TAXONOMIES as $name => $args) {
            register_taxonomy($name, $this->post_type, $args);
        }
    }

    /**
     *
     * THIS FUNCTION IS COMPLETE BULLSHIT.
     * It does what it is supposed to do, but its like super hack-y.
     *
     * CHANGELOG
     *
     * Added 20.10.2018
     *
     * @param $post_id
     * @param \WP_Post $post
     * @return mixed
     */
    public function savePost($post_id, $post) {

        //wp_die();
        if (PostUtil::isSavingPostType($this->post_type, $post_id)) {

            $modified_key = '__post_data_modified';
            // The Publication posts are supposed to have the same publishing date as the publication within their
            // journal. The publishing date within the journal is saved in the meta key "published". We will read
            // this date and set the post date to this value as well.
            $publishing_date_exists = metadata_exists($this->post_type, $post_id, 'published');
            $publishing_date_modified = metadata_exists($this->post_type, $post_id, $modified_key);
            if ($publishing_date_exists && !$publishing_date_modified) {
                $publishing_date = get_post_meta($post_id, 'published', true) . " 06:00:00";

                // Holy shit. I just had one hour of figuring out, the you ABSOLUTELY NEED the "edit_date"=>true
                // parameter, if it is not there the date just will not change at all!

                update_post_meta($post_id, $modified_key, 'yes');
                $args = array(
                    'ID'            => $post_id,
                    'edit_date'     => true,
                    'post_date'     => $publishing_date,
                    'post_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime($publishing_date) )
                );
                wp_update_post($args);
            }
        }

        return $post_id;
    }

    /**
     * Filter function for "wp_trash_post" event. With the trashing adds a boolean flag "exclude" to the publication
     * meta cache, which will prevent the post from being fetched in the future.
     *
     * CHANGELOG
     *
     * Added 28.11.209
     *
     * @param $post_id
     */
    public static function trashPost($post_id) {
       /*
        * This function will be hooked as a filter into the "wp_trash_post" hook. It is thus being called when a
        * publication post is being deleted.
        * What does it have to do? The scopus system potentially loads way to many publications onto the site, which
        * are not all being needed. These excess publications can then obviously be deleted by trashing them, but the
        * problem is, that the next fetch process would just load them into the page again!
        * Now the trash button should create a new entry within the publication cache, which states, that this
        * publication is not to be loaded onto the page again.
        */

        // The function will obviously only be executed for publication posts
        if (get_post_type($post_id) !== PublicationPost::$POST_TYPE) {
            return;
        }


        $publication_cache = new PublicationMetaCache();

        // First we need the relevant information from the publication
        $publication_post = new PublicationPost($post_id);

        // If there is no entry for the publication yet, we will create one.
        if (!$publication_cache->contains($publication_post->scopus_id)) {
            $publication_cache->write(
                $publication_post->scopus_id,
                $publication_post->title,
                $publication_post->published
            );
        }

        // Then we add an additional boolean field "exclude", which will signal whether the publication is to be
        // fetched in the future
        $publication_cache->writeMeta(
            $publication_post->scopus_id,
            'exclude',
            true
        );

        $publication_cache->save();
    }
}