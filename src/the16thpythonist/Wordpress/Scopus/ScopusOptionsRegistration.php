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
 * The registration class for the options page of the WpScopus. This class wraps all necessary steps which are required
 * to register the various functionality of the scopus options within wordpress. This mainly includes the registration
 * of an additional settings page in the wordpress admin backend and the adding of REST routes to fetch the option data.
 *
 * All registration steps can be executed by invoking the "register" method on a new instance of this class. This
 * process should be executed during the wordpress startup routine.
 *
 *    $options_registration = new ScopusOptionsRegistration();
 *    $options_registration->register();
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
        'author_categories'     => array('Physics', 'Mathematics', '')
    );

    const REST_BASE = 'wpscopus/v1';
    const REST_ROUTE = '/options/';

    // **************************************
    // FUNCTIONS FOR REGISTERING IN WORDPRESS
    // **************************************

    /**
     * Calls all the necessary functions to register the new page in wordpress.
     *
     * This is the only function, which should actually be invoked from an outside scope.
     *
     * @return void
     */
    public function register() {

        // Hooking in the call to create new option page into the wordpress init
        $this->registerOptionPage();

        // Creates all the REST routes, which can be used to manipulate the option values from the frontend JS.
        $this->registerRest();

        // Registering the ajax functions, for example the callback to save all the received options
        $this->registerAjax();
    }

    /**
     * Hooks in the method "addOptionPage" of the object to be called in the wordpress "admin_menu" action hook.
     *
     * @return void
     */
    public function registerOptionPage() {
        add_action('admin_menu', array($this, 'addOptionPage'));
    }

    /**
     * Calls the wordpress function to register a new option page bind the callback for the html content
     * to it.
     *
     * This function needs to be executed within the worpress "admin_menu" hook.
     *
     * @return void
     */
    public function addOptionPage() {
        add_options_page(
            self::PAGE_TITLE,
            self::MENU_TITLE,
            'manage_options',
            self::MENU_SLUG,
            // The "display" method of this class is the one, which echos the actual HTML code to be displayed within
            // this new options page.
            array($this, 'display')
        );
    }

    /**
     * Registers all ajax endpoints, which are important for the options page
     *
     * @return void
     */
    public function registerAjax() {
        //add_action('wp_ajax_save_scopus_options', array($this, 'ajaxSaveOptions'));
        add_action('wp_ajax_get_scopuswp_options', [$this, 'ajaxGetScopuswpOptions']);
        add_action('wp_ajax_update_scopuswp_options', [$this, 'ajaxUpdateScopuswpOptions']);

        add_action('wp_ajax_options_get_categories', [$this, 'ajaxOptionsGetCategories']);
        add_action('wp_ajax_options_update_categories', [$this, 'ajaxOptionsUpdateCategories']);

        add_action('wp_ajax_options_get_scopus_api_key', [$this, 'ajaxOptionsGetScopusApiKey']);
        add_action('wp_ajax_options_update_scopus_api_key', [$this, 'ajaxOptionsUpdateScopusApiKey']);
    }

    /**
     * Hooks the method "restInit" into the appropriate action, so that all the rest routes for the options work.
     *
     * @return void
     */
    public function registerRest() {
        add_action('rest_api_init', [$this, 'restInit']);
    }

    /**
     * Registers all the necessary REST routes for the wpscopus options.
     *
     * This method calls all the "register_rest_route" functions. This method should not be called directly, instead
     * it has to be hooked into the action hook for "rest_api_init"!
     *
     * @return void
     */
    public function restInit() {
        // Manipulating all the options at once. This is probably the most common action and is required by the options
        // page.
        register_rest_route(
            self::REST_BASE,
            self::REST_ROUTE,
            [
                [
                    'methods'       => 'GET',
                    'callback'      => [$this, 'restGetOptions'],
                    'args'          => []
                ],
                [
                    'methods'       => 'POST',
                    'callback'      => [$this, 'restPostOptions'],
                    'args'          => [
                        'scopus_api_key' => [
                            'required'          => true,
                            'type'              => 'string',
                            'description'       => 'The API key used to request the Scopus data'
                        ],
                        'scopus_user_id' => [
                            'required'          => true,
                            'type'              => 'integer',
                            'description'       => 'The user ID of the user to act as the autor of publication posts'
                        ],
                        'author_categories' => [
                            'required'          => true,
                            'type'              => 'array',
                            'description'       => 'The possible categories to be assigned to authors'
                        ]
                    ]
                ]
            ]
        );

        // Interfacig just the author categories
        register_rest_route(
            self::REST_BASE,
            self::REST_ROUTE . 'categories/',
            [
                [
                    'methods'       => 'GET',
                    'callback'      => [$this, 'restGetCategories'],
                    'args'          => []
                ]
            ]
        );
    }

    // == REST INTERFACE

    /**
     * The REST GET endpoint for retrieving all the options values.
     *
     * The request does not need to contain any parameters.
     *
     * The response has the following fields:
     * - scopus_user_id: The integer wordpress user ID for the user to act as the author of all the scopus publication
     *   posts
     * - scopus_api_key: The string value of the API key to be used to authenticate the scopus requests.
     * - author_categories: An array of string values for the available author categories.
     */
    public function restGetOptions( \WP_REST_Request $request ) {
        // So one part of the scopuswp options is that one can define which user is supposed to respresent the scopus
        // wp system (all automatically generated publication posts will be posted under this users name). A good input
        // method for this user would be a selection widget, where one can select from all the available users. But
        // the frontend as is does not know which users are available. So we need to send this information with the
        // the response.
        // "available_users" will be an associative array, whose keys are the int user ids and the values are the full
        // names of the users.
        $users = get_users();
        $available_users = [];
        foreach ($users as $user) {
            $user_id = $user->ID;
            $user_first_name = get_user_meta($user_id, 'first_name', true);
            $user_last_name = get_user_meta($user_id, 'last_name', true);
            $user_name = $user_first_name . ' ' . $user_last_name;
            $available_users[$user_id] = $user_name;
        }

        return [
            'available_users'           => $available_users,
            'scopus_user_id'            => ScopusOptions::getScopusUserID(),
            'scopus_api_key'            => ScopusOptions::getScopusApiKey(),
            'author_categories'         => ScopusOptions::getAuthorCategories()
        ];
    }

    /**
     * The REST POST endpoint for updating the option values.
     *
     * The request needs to contain the following parameters:
     * - scopus_user_id: The integer wordpress user ID for the user to act as the author of all the scopus publication
     *   posts
     * - scopus_api_key: The string value of the API key to be used to authenticate the scopus requests.
     * - author_categories: An array of string values for the available author categories.
     *
     * This method does not respond.
     *
     * @param \WP_REST_Request $request
     */
    public function restPostOptions( \WP_REST_Request $request ) {
        // error_log(var_export($request, true));
        ScopusOptions::setScopusUserID($request['scopus_user_id']);
        ScopusOptions::setScopusApiKey($request['scopus_api_key']);
        ScopusOptions::setAuthorCategories($request['author_categories']);
    }

    /**
     * The REST GET endpoint for retrieving the options value of the author categories array.
     *
     * The request does not have to contain any additional parameters.
     *
     * The response contains the following fields:
     * - author_categories: An array with all the string values for the available author categories
     *
     * @param \WP_REST_Request $request The request
     * @return array The response array
     */
    public function restGetCategories( \WP_REST_Request $request ) {
        return [
            'author_categories'         => ScopusOptions::getAuthorCategories()
        ];
    }

    // == DISPLAYIN THE ACTUAL HTML CONTENT

    /**
     * Echos the actual html code needed to display the options page.
     *
     * CHANGELOG
     *
     * Previously the html code of the options page was actually mostly static html code. The current option values had
     * been passed to this html code by creating a JS script, which held the values as constants. Now however the
     * options page has been reworked to be a Vue application (The widget for adding the categories was way easier this
     * way). Thus this method really only provided the necessary div for the Vue component to bind to. The current
     * option values will be retrieved from the REST endpoint.
     *
     * @return void
     */
    public function display() {
        ?>
        <div class="scopus-options-wrapper">
            <!-- Entry point for the Vue front end application code -->
            <div id="scopus-options-component">
                Seems like the Vue component could not be attached properly!
            </div>
        </div>
        <?php
    }

    // == UTILITY FUNCTIONS

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

    // == AJAX FUNCTIONALITY

    /**
     * Handler for the ajax endpoint "get_scopuswp_options". Returns a dict which contains all important option values
     * the way they are currently saved in the system.
     *
     * This endpoint does not expect any additional parameters.
     *
     * The response contains the following fields:
     * - available_users: An assoc array, where the keys are the wordpress user IDs of all the users which are known
     *   to the wordpress site and the values are strings of their according full names.
     * - scopus_user_id: The wordpress user ID of the user, which is currently chosen to represent the scopuswp system
     * - scopus_api_key: The string of the scopus API key, which is used to send the requests to the Scopus DB
     * - author_categories: An array of strings, where each string is a category name available for the authors.
     *
     * @return void
     */
    public function ajaxGetScopuswpOptions() {
        // So one part of the scopuswp options is that one can define which user is supposed to respresent the scopus
        // wp system (all automatically generated publication posts will be posted under this users name). A good input
        // method for this user would be a selection widget, where one can select from all the available users. But
        // the frontend as is does not know which users are available. So we need to send this information with the
        // the response.
        // "available_users" will be an associative array, whose keys are the int user ids and the values are the full
        // names of the users.
        $users = get_users();
        $available_users = [];
        foreach ($users as $user) {
            $user_id = $user->ID;
            $user_first_name = get_user_meta($user_id, 'first_name', true);
            $user_last_name = get_user_meta($user_id, 'last_name', true);
            $user_name = $user_first_name . ' ' . $user_last_name;
            $available_users[$user_id] = $user_name;
        }

        $response = [
            'available_users' => $available_users,
            'scopus_user_id' => ScopusOptions::getScopusUserID(),
            'scopus_api_key' => ScopusOptions::getScopusApiKey(),
            'author_categories' => ScopusOptions::getAuthorCategories()
        ];
        wp_send_json($response);
        wp_die();
    }

    /**
     * Handler for ajax endpoint "update_scopuswp_options". Sets new values for the options.
     *
     * The endpoint expects the following additional parameters:
     * - scopus_user_id: The int ID of the wordpress user to represent the scopuswp publication posts
     * - scopus_api_key: The string value of the scopus API key
     * - author_categories: An array with the string names of categories available for the authors
     *
     * This endoint does not respond with data.
     *
     * @return void
     */
    public function ajaxUpdateScopuswpOptions() {
        $expected_params = ['scopus_user_id', 'scopus_api_key', 'author_categories'];
        if (self::ajaxRequestContains($expected_params)) {
            $params = self::ajaxRequestParameters($expected_params);

            // The static class "ScopusOptions" can be used to set new values to the options as well as retrieve the
            // current values.
            ScopusOptions::setScopusUserID($params['scopus_user_id']);
            ScopusOptions::setScopusApiKey($params['scopus_api_key']);
            ScopusOptions::setAuthorCategories($params['author_categories']);

            wp_send_json(true);
        } else {
            wp_send_json_error();
        }
        wp_die();
    }

    /**
     * Handler for the ajax endpoint "options_get_author_categories". Returns the array of author categories.
     *
     * This ajax endpoint does not expect any additional parameters.
     *
     * The response contains the following fields:
     * - author_categories: An array of the string category names available for the authors.
     *
     * @return void
     */
    public function ajaxOptionsGetCategories() {
        $author_categories = ScopusOptions::getAuthorCategories();
        $response = [
            'author_categories' => $author_categories
        ];
        wp_send_json($response);
        wp_die();
    }

    /**
     * Handler for the ajax endpoint "options_update_author_categories". Updates the options value for the author
     * categories array.
     *
     * This ajax endoint expects the following additional parameters:
     * - author_categories: An array of the string category names
     *
     * The response does not contain data.
     *
     * @return void
     */
    public function ajaxOptionsUpdateCategories() {
        $expected_params = ['author_categories'];
        if (self::ajaxRequestContains($expected_params)) {
            $params = self::ajaxRequestParameters($expected_params);
            $author_categories = $params['author_categories'];
            ScopusOptions::setAuthorCategories($author_categories);
            wp_send_json(true);
        } else {
            wp_send_json_error();
        }
        wp_die();
    }

    /**
     * Ajax handler for the endoint "options_get_scopus_api_key". Returns the Scopus API key option value.
     *
     * This endpoint does not expect additional parameters.
     *
     * The response has the following fields:
     * - scopus_api_key: THe string scopus api key, which is used for the scopus requests
     *
     * @return void
     */
    public function ajaxOptionsGetScopusApiKey() {
        $api_key = ScopusOptions::getScopusApiKey();
        $response = [
            'scopus_api_key' => 'api_key'
        ];
        wp_send_json($response);
        wp_die();
    }

    /**
     * Ajax handler for the endpoint "options_update_scopus_api_key". Sets a new value to the scopus api key option
     *
     * This endpoint expects the following additional parameters:
     * - scopus_api_key: The new string value for the scopus api key
     *
     * This endpoint does not respond data.
     *
     * @return void
     */
    public function ajaxOptionsUpdateScopusApiKey() {
        $expected_params = ['scopus_api_key'];
        if (self::ajaxRequestContains($expected_params)) {
            $params = self::ajaxRequestParameters($expected_params);
            $api_key = $params['api_key'];
            ScopusOptions::setScopusApiKey($api_key);
            wp_send_json(true);
        } else {
            wp_send_json_error();
        }
        wp_die();
    }

    # == AJAX HELPER METHODS

    /**
     * Returns whether or not the current ajax request contains all parameters defines by the strings in $args.
     *
     * @param array $args A list of strings, where each string defines the name of one parameter which is expected to
     *      be found within the ajax request.
     * @return bool
     */
    public static function ajaxRequestContains(array $args) {
        foreach ($args as $arg) {
            if (!array_key_exists($arg, $_REQUEST)) {
                return FALSE;
            }
        }
        return TRUE;
    }

    /**
     * Returns an assoc array with the keys being the string names in $args and the values the corresponding values
     * extracted from the ajax request body.
     *
     * @param array $args An array of strings where each string defines the name of one parameter to be extracted
     *      from the current ajax request.
     * @return array
     */
    public static function ajaxRequestParameters(array $args) {
        $values = [];
        foreach ($args as $arg) {
            $values[$arg] = $_REQUEST[$arg];
        }

        return $values;
    }

    // === DEPRECATED ==============================================================================================

    /**
     * Assigns the options with default values, if they do not already have values.
     *
     * @deprecated The default options are handled differently now: The ScopusOptions class itself will return the
     *      default values in case they dont already exist.
     *
     * @return void
     */
    public function registerDefaultOptions() {
        foreach (self::DEFAULT_OPTIONS as $option_name => $default_value) {

            // This method checks if the option exists and if it doesnt if will create an option with that name
            // and set it to the default value.
            $this->setOptionDefault($option_name, $default_value);
        }
    }

    /**
     * Will assign the given "value" to the option with the given "option_name", but ONLY if the option does not
     * already exists.
     *
     * @deprecated The default options are handled differently now: The ScopusOptions class itself will return the
     *      default values in case they dont already exist.
     *
     * @param string $option_name The string identifier of the option
     * @param mixed $value The value to be assigned as the default
     *
     * @return void
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
     * @deprecated This is a concern of the ScopusOptions class
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
     * @deprecated This is a concern of the ScopusOptions class
     *
     * @return array
     */
    public function getAuthorCategories() {
        $categories = get_option('author_categories');
        return $categories;
    }

    /**
     * This function gets invoked, when a ajax call to the action "save_scopus_options" gets received.
     * It will take the values from the request and update the options with them.
     *
     * @deprecated This functionality has been rewritten
     *
     * @return void
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