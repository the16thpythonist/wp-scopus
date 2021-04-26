<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 12.02.19
 * Time: 17:59
 */

namespace the16thpythonist\Wordpress\Scopus;

// include_once('wp-includes/option.php');

/**
 * Class ScopusOptions
 *
 * This is a static class which wraps the access to the worpdress option values for the package. It's methods can be
 * used to get and set the option values without having to directly invoke the wordpress methods for it.
 *
 * EXAMPLE
 *
 *    // The api key can be retrieved and updated with it's according methods
 *    $api_key = ScopusOptions::getScopusApiKey();
 *    ScopusOptions::setScopusApiKey("newsecretkey");
 *
 * TODO:
 *
 * - This class could also implement methods to check for the validity of the values...
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class ScopusOptions
{
    const OPTION_KEYS = [
        'api_key'           => 'scopuswp_api_key',
        'author_categories' => 'scopuswp_author_categories',
        'user_id'           => 'scopuswp_user_id'
    ];

    const DEFAULT_VALUES = [
        'api_key'           => '',
        'author_categories' => ['Physics', 'Mathematics', 'Computer Science'],
        'user_id'           => 1
    ];

    /**
     * Returns the string value which contains the Scopus API key.
     *
     * The scopus API Key is kind of like a password, which is required to be sent with every request to the scopus
     * database to authenticate that the requests are actually permitted to access this kind of data.
     * This method returns some kind of string but cannot guarantee, that the string which is returned is actually a
     * valid API key!
     *
     * @return string The API Key used
     */
    public static function getScopusApiKey() {
        // "get_option" will return the option value associated with the given key (first argument) and if there is no
        // such key, it will return the default value (second argument) instead.
        $api_key = get_option(
            self::OPTION_KEYS['api_key'],
            self::DEFAULT_VALUES['api_key']
        );
        return $api_key;
    }

    /**
     * Sets a new value for the scopus api key option.
     *
     * The string value of the scopus API key is saved as a wordpress option identified by "scopuswp_api_key".
     *
     * @param string $api_key The new value for the API key
     */
    public static function setScopusApiKey(string $api_key) {
        update_option(self::OPTION_KEYS['api_key'], $api_key);
    }

    /**
     * Returns the array of available author categories.
     *
     * This list of string category names is the list of available categories, which can be assigned to an Author post
     * The categories associated with an author determine the categories associated with a publication post, which was
     * derived from this author and automatically published.
     *
     * @return array
     */
    public static function getAuthorCategories() {
        $author_categories = get_option(
            self::OPTION_KEYS['author_categories'],
            self::DEFAULT_VALUES['author_categories']
        );
        return $author_categories;
    }

    /**
     * Sets a new value for the author categories.
     *
     * @param array $author_categories The array of string categories
     */
    public static function setAuthorCategories(array $author_categories) {
        update_option(self::OPTION_KEYS['author_categories'], $author_categories);
    }

    /**
     * Returns the user ID of the scopus user, which is the user that acts as author to the scopus publication posts
     * on the wordpress backend.
     *
     * ScopusWp fetches new publications from a web database and automatically published them as new posts to the
     * wordpress site. These posts have to be made by some user. The user ID of this user can be set by this Option.
     *
     * @return int
     */
    public static function getScopusUserID() {
        // If the options does not already exist, we will use the user id 0 as the default. This should (?) be the very
        // first admin user which was created during the wordpress setup.
        $scopus_user_id = get_option(
            self::OPTION_KEYS['user_id'],
            self::DEFAULT_VALUES['user_id']
        );
        return $scopus_user_id;
    }

    /**
     * Sets a new value for the scopus user ID option.
     *
     * @param int $user_id
     */
    public static function setScopusUserID(int $user_id) {
        update_option(self::OPTION_KEYS['user_id'], $user_id);
    }

    /**
     * Returns the WP_User object for the user, that acts as the author to the scopus publication posts on the
     * wordpress backend.
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

}