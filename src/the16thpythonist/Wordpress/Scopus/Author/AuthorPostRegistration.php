<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 29.08.18
 * Time: 15:14
 */

namespace the16thpythonist\Wordpress\Scopus\Author;

use Exception;
use the16thpythonist\KITOpen\Author;
use the16thpythonist\Wordpress\Base\PostRegistration;
use the16thpythonist\Wordpress\Functions\PostUtil;

// 28.04.2020 After the namespace change
use the16thpythonist\Wordpress\Scopus\ScopusOptions;

/**
 * Class AuthorPostRegistration
 *
 * CHANGELOG
 *
 * Added 29.08.2018
 *
 * @since 0.0.0.0
 */
class AuthorPostRegistration implements PostRegistration
{
    const PHP_NEWLINE = "\r\n";
    const HTML_NEWLINE = '&#13;&#10;';

    public $label;
    public $post_type;

    /**
     * This is an associative array. The keys are the names of the meta values, that are supposed to be stored with
     * an author post (The sames ones are being requested to be entered in the custom metabox). The values are the
     * labels to be used when displaying these values to the user.
     * @var array
     */
    public static $META_FIELDS = array(
        'first_name'        => 'First Name',
        'last_name'         => 'Last Name',
        'scopus_author_id'  => 'Scopus ID',
        'categories'        => 'Categories'
    );

    public static $META_SINGLE = array(
        'first_name'        => true,
        'last_name'         => true,
        'scopus_author_id'  => false,
        'categories'        => false
    );

    /**
     * AuthorPostRegistration constructor.
     *
     * CHANGELOG
     *
     * Added 29.08.2018
     *
     * @since 0.0.0.0
     *
     * @param string $post_type
     * @param string $label
     */
    public function __construct(string $post_type, string $label='Author')
    {
        $this->label = $label;
        $this->post_type = $post_type;
    }

    /**
     * Returns the string post type name that is used to register the post type
     *
     * CHANGELOG
     *
     * Added 20.10.2018
     *
     * @return string
     */
    public function getPostType()
    {
        return $this->post_type;
    }

    /**
     * CHANGELOG
     *
     * Added 29.08.2018
     *
     * Changed 02.01.2019
     * Added an additional AJAX function, which saves the current setting of whitelist and blacklist in the edit page
     * of the AuthorPost.
     *
     * Changed 24.02.2019
     * Moved the ajax registrations into an own method and calling it here.
     *
     * Changed 10.12.2019
     * Added the call to the function "registerAdminListViewModifications", which will register all the callbacks, that
     * are neceassary for the custom behaviour of the post types list view in the admin area of the site.
     *
     * @since 0.0.0.0
     */
    public function register() {
        add_action('init', array($this, 'registerPostType'));
        add_action('add_meta_boxes', array($this, 'registerMetabox'));

        // 24.02.2019
        // Moved the actual ajax registration into its own method
        $this->registerAJAX();

        // A custom save method is needed to save all the data from the custom meta box to the correct post meta values
        add_action('save_post', array($this, 'savePost'));

        // 10.12.2019
        // Creates a custom list view of the posts in the admin area
        $this->registerAdminListViewModification();
    }

    /**
     * Wraps the callback registrations for all the hooks, that are involved in modifying the list view of this post
     * type  within the admin area of the site
     *
     * CHANGELOG
     *
     * Added 10.12.2019
     */
    public function registerAdminListViewModification(){

        // This filter will be used to define, which columns the list view is supposed to have
        add_filter(
            $this->insertPostType('manage_%s_posts_columns'),
            array($this, 'manageAuthorColumns'),
            10, 1
        );

        // This action will be used to generate the actual contents for the columns
        add_action(
            $this->insertPostType('manage_%s_posts_custom_column'),
            array($this, 'contentAuthorColumns'),
            10, 2
        );
    }

    /**
     * This function takes a string, which has to contain exactly one string position for inserting a
     * string with the "sprintf" function.
     * This position will be inserted with the post type string of this class.
     * This function will be needed in situations, where the name of a hook is dynamically dependant on the post type
     * for example.
     *
     * EXAMPLE
     *
     * $this->post_type = "author"
     * $this->insertPostType("manage_%s_posts")
     * >> "manage_author_posts"
     *
     * CHANGELOG
     *
     * Added 10.12.2019
     *
     * @param string $template
     * @return string
     */
    public function insertPostType(string $template) {
        return sprintf($template, $this->post_type);
    }

    /**
     * CHANGELOG
     *
     * Added 29.08.2018
     *
     * @since 0.0.0.0
     */
    public function registerPostType() {
        $args = array(
            'label'                 => $this->label,
            'description'           => 'Describes an Author of scientific publications',
            'public'                => true,
            'publicly_queryable'    => false,
            'show_ui'               => true,
            'menu_position'         => 5,
            'map_meta_cap'          => true,
            'supports'              => array(),
            'menu_icon'             => 'dashicons-businessman'
        );
        register_post_type($this->post_type, $args);
    }

    /**
     * Registers all the methods of this instance, which are ajax callbacks with wordpress
     *
     * @return void
     */
    function registerAJAX() {
        // IMPORTANT INFORMATION FOR THE NEXT TIME I AM READING THIS WITHOUT REMEMBERING WORDPRESS:
        // - Ajax hooks are registered as "normal" wordpress action hooks using the "add_action" function. What makes
        //   them into actual ajax endpoints is the naming of the hook! You can create an ajax hook by adding
        //   "wp_ajax" as a prefix to the action name.
        // - To then actually call the endpoint, the request needs to contain a parameter called "action" whose
        //   string content is the action name of defined for the hook. But (and this is important!) this should be
        //   the name without the "wp_ajax" prefix !!
        // - On default registering an ajax endpoint with "wp_ajax" only makes the endpoints available for logged in
        //   users with the according privileges. So usually these are used for functionality in the admin backend
        //   where only logged in users can enter anyways. For just a regular visitor to the site to be able to use the
        //   endpoint one has to use the "wp_ajax_nopriv" prefix instead.

        // This AJAX method is used to trigger the process of fetching an authors affiliations. The results of this
        // fetch will be saved in a temp. DataPost, from where they can be accessed by the frontend.
        add_action('wp_ajax_fetch_author_affiliations', array($this, 'ajaxFetchAuthorAffiliations'));

        // 02.01.2019
        // This AJAX method will save the blacklist/whitelist configuration to the Post meta
        add_action('wp_ajax_update_author_affiliations', array($this, 'ajaxUpdateAuthorAffiliations'));

        // 24.02.2019
        // THis ajax method is for saving the other information about the author, such as the first name etc.
        add_action('wp_ajax_update_author_post', array($this, 'ajaxUpdateAuthorPost'));
        add_action('wp_ajax_insert_author_post', array($this, 'ajaxInsertAuthorPost'));

        // This ajax method returns an author post object as JSON.
        // - ID: It only expects the post ID (! not the author ID) to be passed as a parameter.
        add_action('wp_ajax_get_author_post', array($this, 'ajaxGetAuthorPost'));
    }

    /**
     * callback for the wordpress 'save_post' hook. Makes sure all the data from the custom metabox gets saved to post
     *
     * CHANGELOG
     *
     * Added 30.08.2018
     *
     * Changed 20.10.2018
     * Changed the if statement, that checks for the post type to use a function, which also checks if the saving process
     * is actually happening over the wordpress backend, as there was an error caused when the 'wp_insert_post' function
     * was called.
     *
     * Changed 28.10.2018
     * Made the function quit in case the post was not saved via the editor, but by the wp_insert_post function from
     * within the code
     *
     * @since 0.0.0.0
     *
     * @param $post_id
     * @return mixed
     */
    public function savePost($post_id) {
        /*
         * This method will be hooked into the wordpress hook 'save_post', which means that it will get called for
         * every post, regardless of the post type. So it is the callbacks responsibility to filter out the correct
         * post type to address.
         *
         * The function for 'save_post' will be called as the response to the html POST, that is being triggered, once
         * the button has been pressed on the post edit page, which means the $_POST array contains all the relevant
         * information of the post.
         */
        if (!PostUtil::isSavingPostType($this->post_type, $post_id)) {
            return $post_id;
        }

        // So this on save hook gets also invoked, when a new post is being inserted using the 'wp_insert_post'
        // function from the code and in this case there obviously is no information in the POST array.
        // In such a case however the values are being saved correctly as an array already.
        if (!array_key_exists('first_name', $_POST)) {
            return $post_id;
        }

        /*
         * All the values in the input fields within the metabox will automatically be appended to the $_POST array by
         * wordpress, using their html id as the key in the array.
         * In the case of the blacklist and whitelist checkboxes this means, that only the checkboxes, that have been
         * checked will be appended to the array in the form 'id' => 'checked'. This means the value doesnt say
         * anything about the actual id, that has been white/black-listed. The id is also part of the html id of the
         * elements like such: "whitelist-1527384" => "checked". This way the information can be retrieved from the key.
         */
        $whitelist = array();
        $blacklist = array();
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'whitelist') !== false) {
                $affiliation_id = explode('-', $key)[1];
                $whitelist[] = $affiliation_id;
            }
            if (strpos($key, 'blacklist') !== false) {
                $affiliation_id = explode('-', $key)[1];
                $blacklist[] = $affiliation_id;
            }
        }
        update_post_meta($post_id, 'scopus_whitelist', implode(',', $whitelist));
        update_post_meta($post_id, 'scopus_blacklist', implode(',', $blacklist));

        /*
         * All the "normal" text input fields, just directly contain, whatever was written into them as the value to
         * their array entry. The key being the key also used in $META_FIELDS
         */
        foreach (self::$META_FIELDS as $key => $label) {

            if (self::$META_SINGLE[$key] === true) {
                $value = $_POST[$key];
                update_post_meta($post_id, $key, $value);
            } else {
                /*
                 * If the value is not single, which means it is multiple and represented by a textarea. Multiple values
                 * are being displayed and entered as being in new lines. For saving the list as a meta value, it is
                 * being converted into a csv string.
                 */

                // $value = str_replace(self::PHP_NEWLINE, ',', $_POST[$key]);
                $value = $_POST[$key];
                foreach ($_POST[$key] as $value) {
                    update_post_meta($post_id, $key, $value);
                }
            }
        }

        /*
         * ~ CREATING CUSTOM TITLE
         * The save hook callback is also the correct place to possibly overwrite standard wordpress attributes,
         * written during a save, such as the body, time or title.
         * The Author post type doesnt support a custom title. The title is supposed to be a combination of the strings
         * that were entered for the first and last name of the author.
         */
        if (metadata_exists('post', $post_id, 'first_name') && metadata_exists('post', $post_id, 'last_name')) {
            global $wpdb;
            $first_name = get_post_meta($post_id, 'first_name', true);
            $last_name = get_post_meta($post_id, 'last_name', true);
            // The title will be the last name first and then the given name, separated by a comma
            $title = $last_name . ', ' . $first_name;
            $where = array('ID' => $post_id);
            // At this point in the pipeline, the title can sadly only be modified in this slightly "hacky" way by
            // actually making a database query with the global wpdb reference.
            $wpdb->update($wpdb->posts, array('post_title' => $title), $where);
        }
    }

    /**
     * Registers the additional metabox for the author post type by using the wp function "add_meta_box"
     *
     * This function should be called together with all other registration functions!
     *
     * @return void
     */
    public function registerMetabox() {
        add_meta_box(
            $this->post_type . '-meta',
            // This string will be the title of the metabox displayed within the browser:
            'Author Meta Information',
            // This is the callback which defines the contents of the meta box. This function accpets a WP_Post object
            // and is supposed to render the according HTML content in turn.
            array($this, 'callbackMetabox'),
            $this->post_type,
            'normal',
            'high'
        );
    }

    /**
     * Echos the HTML code for the additional MetaBox within the edit page for the author post type.
     *
     * This function is not intended to be actually called somewhere. It will be registered as a callback function
     * for the wordpress command "add_meta_box". These kind of callbacks do not have to return anything, instead they
     * have to echo the necessary html.
     *
     * The frontend code of this metabox actually consits of a VueJS component. That's why this method is so short.
     * It only provided the anchor for the component. When the frontend code is loaded Vue will latch onto this
     * special anchor element and render the component dynamically from the JS.
     *
     * @param \WP_Post $post
     *
     * @return  void
     */
    public function callbackMetabox(\WP_Post $post) {
        $post_id = $post->ID;
        ?>
        <script> var POST_ID = <?php echo $post_id; ?>; </script>
        <div id="author-meta-component">
            This section should contain the Vue frontend application...
        </div>
        <?php
    }

    // *********************************
    // AJAX OPERATIONS FOR THE POST TYPE
    // *********************************

    /**
     * The ajax method, which is called to start the process of fetching the author affiliations.
     *
     * @return void
     */
    public function ajaxFetchAuthorAffiliations() {

        $expected_params = ['author_id'];
        if (self::ajaxRequestContains($expected_params)) {
            $params = self::ajaxRequestParameters($expected_params);
            $author_id = $params['author_id'];

            try {
                $fetcher = new AuthorAffiliationFetcher();
                $fetcher->set($author_id);
                $fetcher->fetchAffiliations();

                wp_send_json(true);

            } catch (Exception $e) {

                wp_send_json_error($e->getMessage, 501);
            }
        }
        wp_die();
    }

    /**
     * The ajax method, which will save the blacklist and the whitelist (passed as URL parameters) to the AuthorPost
     *
     * @return void
     */
    public function ajaxUpdateAuthorAffiliations() {

        $expected_params = ['whitelist', 'blacklist', 'ID'];
        if (self::ajaxRequestContains($expected_params)) {
            $params = self::ajaxRequestParameters($expected_params);
            try {
                $whitelist = str_getcsv($params['whitelist']);
                $blacklist = str_getcsv($params['blacklist']);
                $post_id = $params['ID'];

                // Here we actually insert the date as meta values to the corresponding author post.
                // 11.10.2019
                // The lists are now being saved as arrays instead of comma separated lists.
                update_post_meta($post_id, 'scopus_whitelist', $whitelist);
                update_post_meta($post_id, 'scopus_blacklist', $blacklist);

                $author_post = new AuthorPost($post_id);

                // Why this is necessary is a little bit more complicated. The front end display of the affiliations and
                // the checkboxes is based on a temporary DataPost. With this we issue the affiliation fetcher to update
                // the blacklist and whitelist values within this DataPost according to how they are in the actual
                // AuthorPost. If we didnt do this, the changes would indeed be saved, but not visible in the front end.
                $fetcher = new AuthorAffiliationFetcher();
                foreach ($author_post->author_ids as $author_id) {
                    $fetcher->set($author_id);
                    $fetcher->updateAffiliations();
                }

                wp_send_json(true);

            } catch (Exception $e) {
                wp_send_json_error($e->getMessage(), 501);
            }
        }
        wp_die();
    }

    /**
     * Handler for the ajax action "update_author_post". Will update the author post specified by the ajax parameters
     * with the values which also have to be contained within the ajax params.
     *
     * This ajax endpoint expects the following parameters:
     * - ID: The string post ID of the post, which is to be updated
     * - first_name: The string first name of the author
     * - last_name: The string last name of the author
     * - categories: An array with the string names for the categories to associate with the author
     * - scopus_ids: An array with the string(s) of one or multiple scopus ids associated with this author
     *
     * @return void
     */
    public function ajaxUpdateAuthorPost() {
        $expected_params = ['ID', 'first_name', 'last_name', 'categories', 'scopus_ids'];
        if (self::ajaxRequestContains($expected_params)) {
            try {
                $params = self::ajaxRequestParameters($expected_params);

                $post_id = $params['ID'];
                // "createInsertArgs" will use the values from the ajax request ($_REQUEST) to create an array which can
                // be directly passed to the "insert" method of the author post class.
                // -> The "update" and "insert" method expect the same values for the "args" array.
                $args = self::createInsertArgs();
                AuthorPost::update($post_id, $args);

                wp_send_json(true);
            } catch (Exception $e) {

                wp_send_json_error('There was an error updating the author post', 500);
            }
        }
        wp_die();
    }

    /**
     * Handler for the ajax action "insert_author_post". Will insert a new author post based on the values passed as
     * arguments to the ajax request.
     *
     * This ajax endpoint expects the following parameters:
     * - first_name: The string first name of the author
     * - last_name: The string last name of the author
     * - categories: An array with the string names for the categories to associate with the author
     * - scopus_ids: An array with the string(s) of one or multiple scopus ids associated with this author
     *
     * @return void
     */
    public function ajaxInsertAuthorPost () {
        $expected_params = array('first_name', 'last_name', 'categories', 'scopus_ids');
        if (self::ajaxRequestContains($expected_params)) {
            // "createInsertArgs" will use the values from the ajax request ($_REQUEST) to create an array which can
            // be directly passed to the "insert" method of the author post class.
            $args = self::createInsertArgs();
            AuthorPost::insert($args);

            echo json_encode(true);
        }
    }

    /**
     * Handler for ajax action "get_author_post". Will return the author information, if given the post ID.
     *
     * This ajax endpoint expects the following parameters:
     * - ID: The post ID for which to return the detailed author informations
     *
     * @return void
     */
    public function ajaxGetAuthorPost() {
        // This is a function which is expected to exist by the frontend. It is supposed to return the fundamental JSON
        // data which describes the author post meta that is introduced by the wp-scopus package. This data includes:
        // - wordpress post ID
        // - first name
        // - last name
        // - list of all author scopus ID's (as strings)
        // - list of the tags associated with the author
        // - list of affiliations associated with the author (although, I am not sure if that should happen here or
        //   in a separate method ?)

        $expected_params = array('ID');
        if (self::ajaxRequestContains($expected_params)) {
            $params = self::ajaxRequestParameters($expected_params);
            $post_id = $params['ID'];

            try{
                // ~ loading the actual author post object and formatting the JSON return
                $author_post = new AuthorPost($post_id);
                $response_data = [
                    'post_id'           => $author_post->post_id,
                    'first_name'        => $author_post->first_name,
                    'last_name'         => $author_post->last_name,
                    'scopus_ids'        => $author_post->author_ids,
                    'categories'        => $author_post->categories,
                ];

                wp_send_json($response_data);

            } catch (Exception $e){

                wp_send_json_error('There was an error with getting the AuthorPost', 500);
            }

        } else {
            wp_send_json_error('The AJAX request does not contain the ID parameter for the post ID', 500);
        }

        wp_die();
    }

    // INSERT THE NECESSARY AJAX

    // **********************
    // MODIFY ADMIN LIST VIEW
    // **********************

    /*
     * MODIFYING THE ADMIN COLUMNS
     * What are the admin columns? When being in the admin dashboard and looking at a post type, the first thing that
     * is being displayed is a sort of list view with all the posts. This list view has certain columns, that display
     * certain information about the post. These columns can be modified to display custom data, which better suits the
     * custom post type.
     * This modification is being done by manipulating various filters.
     *
     * The first thing to be done is to register an additional filter to the hook "manage_[posttype]_posts_column"
     * This function gets passed an array of columns to be registered and can be modified with additional ones.
     *
     * Then the hook action hook "manage_[posttype]_posts_custom_column" can be used to echo the content for one
     * specific column.
     *
     * At last, we can implement sorting of the posts by a custom column. For that we first have to implement a filter
     * to the hook "manage_edit-[posttype]-sortable-columns" and add the column keys to that.
     * After that we have to implement custom wordpress query for this column in the "pre_get_posts" hook.
     */

    /**
     * Filter function for the hook "manage_author_posts_column", which will register additional, custom columns to the
     * list view of this post type within the admin dashboard.
     *
     * CHANGELOG
     *
     * Added 10.12.2019
     *
     * @param $columns
     * @return array
     */
    public function manageAuthorColumns($columns) {
        /*
         * $columns is an associative array, which contains a list of all the columns to be registered for the  admin
         * list view of this post type. Simply adding or removing entries from this array should do the trick.
         * The keys of the array are slugs to identify the columns and the values are the headers, which describe the
         * content of the columns within the dashboard
         *
         * The standard array contains the keys "cb", "title", "author", "categories", "tags", "comments", "date"
         */
        $columns = array(
            'cb'                => $columns['cb'],
            'title'             => $columns['title'],
            'authorID'          => __('Author ID'),
            'topic'             => __('Topic'),
            'date'              => $columns['date']
        );

        return $columns;
    }

    /**
     * Callback function for the action hook "manage_author_posts_custom_column". Depending on the passed column key and
     * the post ID, this function will echo the content to be displayed in the corresponding row of the admin list view
     *
     * CHANGELOG
     *
     * Added 10.12.2019
     *
     * @param $column
     * @param $post_id
     */
    public function contentAuthorColumns($column, $post_id) {
        /*
         * Right now the content of the fields are being generated by simply putting the strings of the items separated
         * by commas.
         */

        $author_post = new AuthorPost($post_id);
        if ($column === 'authorID') {
            echo implode(', ', $author_post->author_ids);
        }

        if ($column === 'topic') {
            echo implode(', ', $author_post->categories);
        }

    }

    // **************
    // STATIC METHODS
    // **************

    /**
     * This method computes an array with the format needed to call the AuthorPost::insert method from the values in
     * the _REQUEST array. This method should only be called in the corresponding ajax callbacks and after validating
     * that the response contains all these values.
     *
     * The following example is assumed to be during the handling of an ajax request (which means that $_REQUEST
     * actually contains values). We will assume that the objective is to create a new author post within the database
     * based on the values passed with the ajax request:
     *
     *    // Within an ajax handler which is a method of AuthorPostRegistration...
     *    $insert_args = self::createInsertArgs();
     *    $author_post = AuthorPost::insert($insert_args);
     *
     * @return array
     */
    public static function createInsertArgs() {
        $args = array(
            'first_name'        => $_REQUEST['first_name'],
            'last_name'         => $_REQUEST['last_name'],
            //'scopus_ids'        => $_REQUEST['scopus_ids'],
            //'categories'        => $_REQUEST['categories']
            'scopus_ids'        => str_getcsv($_REQUEST['scopus_ids']),
            'categories'        => str_getcsv($_REQUEST['categories'])
        );
        return $args;
    }

    // -- Ajax helper methods --
    // Interesting insight: You can send wordpress ajax requests as GET requests or as POST requests. In the case of
    // get requests the parameters have to be URL parameters and for post requests the params have to be form data.
    // I thought that for get requests you would have to get the params from the $_GET global array and for post
    // requests consequently from the $_POST array. And that is the case, in a sense, because for get requests the
    // $_POST array is empty and vice versa. But interestingly for both cases you can just get it from the global
    // $_REQUEST array. There the same parameters will be regardless of whether it is post or get.
    // That is exactly what those two helper methods are doing.

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
 }