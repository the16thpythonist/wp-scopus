<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 12.02.19
 * Time: 17:59
 */

namespace the16thpythonist\Wordpress\Scopus;

/**
 * Class ScopusOptions
 *
 * This is just a static class, which wraps the access to the options of the package from the string names  of the
 * options to getter methods.
 * This way the functions can still be used even though the name or the method of saving these values changes
 *
 * CHANGELOG
 *
 * Added 12.02.2019
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class ScopusOptions
{
    /**
     * Returns the user ID of the scopus user, which is the user that acts as author to the scopus publication posts
     * on the wordpress backend.
     *
     * CHANGELOG
     *
     * Added 12.02.2019
     *
     * @return mixed
     */
    public static function getScopusUserID() {
        return get_option('scopus_user');
    }

    /**
     * Returns the WP_User object for the user, that acts as the author to the scopus publication posts on the
     * wordpress backend
     *
     * CHANGELOG
     *
     * Added 12.02.2019
     *
     * @return bool|\WP_User
     */
    public static function getScopusUser() {
        $scopus_user_id = self::getScopusUserID();
        $scopus_user = get_user_by('id', $scopus_user_id);
        return $scopus_user;
    }

    /**
     * Returns the array of string category names for the categories with which the authors can be associated
     *
     * CHANGELOG
     *
     * Added 24.02.2019
     *
     * @return mixed
     */
    public static function getAuthorCategories() {
        $author_categories = get_option('author_categories');
        return $author_categories;
    }


}