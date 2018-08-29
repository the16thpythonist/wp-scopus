<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 29.08.18
 * Time: 15:14
 */

/**
 * Class AuthorPostRegistration
 *
 * CHANGELOG
 *
 * Added 29.08.2018
 *
 * @since 0.0.0.0
 */
class AuthorPostRegistration
{

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
     * CHANGELOG
     *
     * Added 29.08.2018
     *
     * @since 0.0.0.0
     */
    public function register() {
        add_action('init', array($this, 'registerPostType'));
        add_action('add_meta_boxes', array($this, 'registerMetabox'));
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
     *
     * CHANGELOG
     *
     * Added 29.08.2018
     *
     * @since 0.0.0.0
     *
     * @param WP_Post $post
     */
    public function callbackMetabox(WP_Post $post) {
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
                $value = get_post_meta($post_id, true);
            }

            $meta[$key] = array(
                'value'     => $value,
                'label'     => $label
            );
        }

        ?>
        <div class="scopus-meta-box-wrapper">
            <?php foreach ($meta as $key => $data): ?>
                <div class="scopus-input-wrapper">
                    <p>
                        <?php echo $data['label'] . ': '; ?>
                    </p>
                    <input type="text" id="<?php echo $key; ?>" name="<?php echo $key; ?>" title="<?php echo $key; ?>" value="<?php echo $data['value']; ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <div id="affiliation-wrapper">
            <div class="affiliation-caption-row">
                <p class="first">
                    Affiliation name
                </p>
                <p>
                    whitelisted
                </p>
                <p>
                    blacklisted
                </p>
            </div>
        </div>
        <?php
    }

}