<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 29.08.18
 * Time: 15:14
 */

namespace the16thpythonist\Wordpress\Scopus;

use Exception;
use the16thpythonist\Wordpress\Base\PostRegistration;
use the16thpythonist\Wordpress\Functions\PostUtil;


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
     * @since 0.0.0.0
     */
    public function register() {
        add_action('init', array($this, 'registerPostType'));
        add_action('add_meta_boxes', array($this, 'registerMetabox'));

        // A custom save method is needed to save all the data from the custom meta box to the correct post meta values
        add_action('save_post', array($this, 'savePost'));

        /*
         * This AJAX method is used to trigger the process of fetching an authors affiliations. The results of this
         * fetch will be saved in a temp. DataPost, from where they can be accessed by the frontend.
         */
        add_action('wp_ajax_scopus_author_fetch_affiliations', array($this, 'ajaxFetchAffiliations'));

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
            } else {
                /*
                 * If the value is not single, which means it is multiple and represented by a textarea. Multiple values
                 * are being displayed and entered as being in new lines. For saving the list as a meta value, it is
                 * being converted into a csv string.
                 */

                $value = str_replace(self::PHP_NEWLINE, ',', $_POST[$key]);
            }
            update_post_meta($post_id, $key, $value);

        }

        /*
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
            $wpdb->update($wpdb->posts, array('post_title' => $title), $where);
        }
    }

    /**
     * CHANGELOG
     *
     * Added 29.08.2018
     *
     * @since 0.0.0.0
     */
    public function registerMetabox() {
        add_meta_box(
            $this->post_type . '-meta',
            'Author Meta Information',
            array($this, 'callbackMetabox'),
            $this->post_type,
            'normal',
            'high'
        );
    }

    /**
     * Echos the all the necessary HTML code to appear inside the custom metabox
     *
     * CHANGELOG
     *
     * Added 29.08.2018
     *
     * Changed 30.08.2018
     * Added text paragraphs to the metabox, which describe, what has to be done/what happens in the specific sections
     *
     * @since 0.0.0.0
     *
     * @param \WP_Post $post
     */
    public function callbackMetabox(\WP_Post $post) {
        $post_id = $post->ID;
        /*
         * Within the author post there is metabox being used to input the meta data about the author, such as the
         * name, affiliation etc.
         *
         */
        $meta = array();
        foreach (self::$META_FIELDS as $key => $label) {
            $value = '';
            if (metadata_exists('post', $post_id, $key)) {
                $value = get_post_meta($post_id, $key, true);
            }

            if (self::$META_SINGLE[$key] === true) {
                $type = 'text';
            } else {
                $type = 'textarea';
            }

            $meta[$key] = array(
                'value'     => $value,
                'label'     => $label,
                'type'      => $type
            );
        }
        $post = new AuthorPost($post_id);
        ?>
        <p>
            Use these following input fields to enter the necessary information about the author.<br>
            The first and last name will be used as the posts title (which means any custom title entered will be
            deleted). The scopus author id is used to fetch all the associated publications from the scopus database.
            The categories will define to which category any given post of that author will be added.
        </p>
        <div class="scopus-meta-box-wrapper">
            <?php foreach ($meta as $key => $data): ?>
                <div class="scopus-input-wrapper" style="display: flex; flex-direction: row; margin-bottom: 5px;">
                    <?php if($data['type'] === 'text'): ?>
                        <p style="width: 10%; height: 15px;">
                            <?php echo $data['label'] . ': '; ?>
                        </p>
                        <input type="<?php echo $data['type'];?>" id="<?php echo $key; ?>" name="<?php echo $key; ?>" value="<?php echo $data['value']; ?>" style="flex-grow: 2;">
                    <?php elseif ($data['type'] === 'textarea'): ?>
                        <p style="width: 10%; align-self: flex-start;">
                            <?php echo $data['label'] . ': '; ?>
                        </p>
                        <textarea id="<?php echo $key; ?>" name="<?php echo $key; ?>" rows="3" style="flex-grow: 2;"><?php echo str_replace(',', self::HTML_NEWLINE, $data['value'])?></textarea>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <h3>Author affiliations</h3>
        <p>
            After entering the scopus author id, the sever performs a search about all the institutes the author has
            been affiliated with over time, according to the scopus database. <br>
            In some cases it might not be desired to keep getting papers from an unrelated institute loaded onto the
            website, for such a case the corresponding affiliations can be ticked as blacklist. All the related
            institutes <em>have to be manually ticked</em> as whitelisted!
        </p>

        <button id="update-affiliations">Update Affiliations</button>

        <div id="affiliation-fetch-log">
            <strong>Affiliation retrieval Log</strong>
        </div>

        <div id="affiliation-wrapper">
            <div class="affiliation-caption-row">
                <p class="first">
                    <em>Affiliation Name</em>
                </p>
                <p>
                    whitelisted
                </p>
                <p>
                    blacklisted
                </p>
            </div>
        </div>

        <script>
            // First we need to load the script, which contains all the necessary functions.
            // The actual code for the affiliation display only gets executed once the script is loaded
            jQuery.getScript("<?php echo plugin_dir_url(__FILE__); ?>scopus-author.js", function () {

                // Running if the script is loaded properly
                if (id_input.attr('value') !== '') {
                    //deleteData();
                    let ids = getIDs();
                    console.log(ids);
                    ids.forEach(function (id) {
                        // fetchAffiliations(id);
                    });

                    // Loading all the affiliation IDs from the server
                    updateAffiliations(true);
                }
            })

        </script>
        <?php
    }

    /**
     * The ajax method, which is called to start the process of fetching the author affiliations.
     *
     * CHANGELOG
     *
     * Added 31.08.2018
     *
     * @since 0.0.0.0
     */
    public function ajaxFetchAffiliations() {
        if (array_key_exists('author_id', $_GET)) {
            $author_id = $_GET['author_id'];
            echo $author_id;
            try {
                $fetcher = new AuthorAffiliationFetcher();
                $fetcher->set($author_id);
                var_export($fetcher->fetchAffiliations());
            } catch (Exception $e) {
                echo $e->getMessage();
            }
        }
        wp_die();
    }

}