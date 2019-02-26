<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 11.02.19
 * Time: 13:25
 */

namespace the16thpythonist\Wordpress\Scopus;

use the16thpythonist\Wordpress\Functions\PostUtil;

/**
 * Class ScopusOptionsRegistration
 *
 * CHANGELOG
 *
 * Created 30.01.2019
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class ScopusOptionsRegistration
{
    const PAGE_TITLE = 'ScopusWp Settings';
    const MENU_TITLE = 'ScopusWp';
    const MENU_SLUG = 'scopuswp';

    const DEFAULT_OPTIONS = array(
        'scopus_user'           => '1',
        'author_categories'     => array('microbes', 'exotic plants')
    );

    // **************************************
    // FUNCTIONS FOR REGISTERING IN WORDPRESS
    // **************************************

    /**
     * Calls all the necessary functions to register the new page in wordpress
     *
     * CHANGELOG
     *
     * Added 11.02.2019
     *
     * Changed 12.02.2019
     * Added the method to register the ajax functions
     */
    public function register() {

        // Hooking in the call to create new option page into the wordpress init
        $this->registerOptionPage();

        // Setting the options to default values for the first time.
        $this->registerDefaultOptions();

        // 12.02.2019
        // Registering the ajax functions, for example the callback to save all the received options
        $this->registerAjax();
    }

    /**
     * Hooks in the method "addOptionPage" of the object to be called in the wordpress "admin_menu" action hook
     *
     * CHANGELOG
     *
     * Added 11.02.2019
     */
    public function registerOptionPage() {
        add_action('admin_menu', array($this, 'addOptionPage'));
    }

    /**
     * Calls the wordpress function to register a new option page bind the callback for the html content
     * to it.
     * This function needs to be executed within the worpress "admin_menu" hook.
     *
     * CHANGELOG
     *
     * Added 11.02.2019
     */
    public function addOptionPage() {
        add_options_page(
            self::PAGE_TITLE,
            self::MENU_TITLE,
            'manage_options',
            self::MENU_SLUG,
            array($this, 'display')
        );
    }

    /**
     * Assigns the options with default values, if they do not already have values.
     *
     * CHANGELOG
     *
     * Added 11.02.2019
     */
    public function registerDefaultOptions() {
        foreach (self::DEFAULT_OPTIONS as $option_name => $default_value) {

            // This method checks if the option exists and if it doesnt if will create an option with that name
            // and set it to the default value.
            $this->setOptionDefault($option_name, $default_value);
        }
    }

    /**
     * Registers all the ajax functions with wordpress
     *
     * CHANGELOG
     *
     * Added 12.02.2019
     */
    public function registerAjax() {
        add_action('wp_ajax_save_scopus_options', array($this, 'ajaxSaveOptions'));
    }

    // ************************
    // DEALING WITH THE OPTIONS
    // ************************

    /**
     * Will assign the given "value" to the option with the given "option_name", but ONLY if the option does not
     * already exists.
     *
     * CHANGELOG
     *
     * Added 11.02.2019
     *
     * @param string $option_name
     * @param $value
     */
    public function setOptionDefault(string $option_name, $value) {
        // First we check if the option already exists, if it doesnt it will be set to a default value
        // The call to "get_option" will evaluate as FALSE, if no option by that name exists or if the option
        // field is empty!
        if (!get_option($option_name)) {
            update_option($option_name, $value);
        }
    }

    /**
     * Returns the WP_User object for the set current scopus user.
     *
     * CHANGELOG
     *
     * Added 11.02.2019
     *
     * @return bool|\WP_User
     */
    public function getCurrentScopusUser() {
        $user_id = get_option('scopus_user');
        $user = get_user_by('id', $user_id);
        return $user;
    }

    /**
     * Returns the array with the string category names, with which an author can be associated
     *
     * CHANGELOG
     *
     * Added 24.02.2019
     *
     * @return mixed
     */
    public function getAuthorCategories() {
        $categories = get_option('author_categories');
        return $categories;
    }

    // **************************************
    // FOR DISPLAYING THE ACTUAL HTML CONTENT
    // **************************************

    /**
     * Echos the actual html code needed to display the options page.
     *
     * CHANGELOG
     *
     * Added 11.02.2019
     *
     * Changed 24.02.2019
     * Added the author categories to also be passed to the front end
     */
    public function display() {
        // Ok apparently here I need to check for the _POST array to contain the new options in case the
        // page gets reloaded due to clicking the save changes button.


        // Creating the Javascript code for ṕassing the currently selected user and the list of all available users to
        // the front end application
        $user_arrays = self::allUserArrays();
        $user_arrays_code = PostUtil::javascriptExposeObjectArray('USERS', $user_arrays);

        $current_scopus_user = $this->getCurrentScopusUser();
        $current_scopus_user_array = self::createUserArray($current_scopus_user);
        $current_scopus_user_code = PostUtil::javascriptExposeObject('CURRENT_USER', $current_scopus_user_array);

        // 24.02.2019
        // Passing the array with all the author categories to the front end
        $author_categories = $this->getAuthorCategories();
        $author_categories_code = PostUtil::javascriptExposeObject('CATEGORIES', $author_categories);
        ?>
        <h2>ScopusWp Settings</h2>
        <div class="wrap">
            <!-- This script contains dynamically created JS code, that passes values to the VUE application -->
            <script>
                <?php
                // 24.02.2019
                // Added the code for the author categories object
                echo $user_arrays_code;
                echo $current_scopus_user_code;
                echo $author_categories_code;
                ?>
            </script>

            <!-- Entry point for the Vue front end application code -->
            <div id="scopus-options-main">
                <scopus-options></scopus-options>
            </div>
        </div>
        <?php
    }

    // *****************
    // UTILITY FUNCTIONS
    // *****************

    /**
     * Returns an array, which contains an associative array for each user currently in the wordpress system.
     * The associative arrays are made up of the following key value pairs:
     * - name:  The string display name of the user
     * - ID:    The wordpress user ID of that user
     *
     * CHANGELOG
     *
     * Added 11.02.2019
     *
     * @return array
     */
    public static function allUserArrays() {
        $users = self::allUsers();
        $user_arrays = array();
        foreach ($users as $user) {
            $user_array = self::createUserArray($user);
            $user_arrays[] = $user_array;
        }
        return $user_arrays;
    }

    /**
     * Creates an associative array with the infos about a user, given the wordpress user object
     *
     * CHANGELOG
     *
     * Added 11.02.2019
     *
     * @param \WP_User $user
     * @return array
     */
    public static function createUserArray($user) {
        $user_array = array(
            'name'          => $user->display_name,
            'ID'            => $user->ID
        );
        return $user_array;
    }

    /**
     * Returns an array with all user objects, one for each user with the role "author".
     *
     * CHANGELOG
     *
     * Added 11.02.2019
     *
     * @return array
     */
    public static function allUsers() {
        $args = array(
            'role__in'  => array('author', 'administrator'),
            'fields'    => array('display_name', 'ID'),
            'orderby'   => 'user_nicename',
            'order'     => 'ASC'
        );
        $users = get_users($args);
        return $users;
    }

    // ******************
    // AJAX FUNCTIONALITY
    // ******************

    /**
     * This function gets invoked, when a ajax call to the action "save_scopus_options" gets received.
     * It will take the values from the request and update the options with them.
     *
     * CHANGELOG
     *
     * Added 12.02.2019
     *
     * Changed 24.02.2019
     * The author categories list will now also be updated
     */
    public function ajaxSaveOptions() {
        $expected_parameters = array('scopus_user');
        if (PostUtil::containsGETParameters($expected_parameters)) {
            // Updating the actual options
            update_option('scopus_user', $_GET['scopus_user']);

            // 24.02.2019
            // This list of categories dictates, which of the categories can be added to a user, when a new user is
            // being created.
            $categories = str_getcsv($_GET['author_categories']);
            update_option('author_categories', $categories);
        }
        wp_die();
    }
}