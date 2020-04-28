<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 13.01.19
 * Time: 10:11
 */

namespace the16thpythonist\Wordpress\Scopus\Publication;


/**
 * Class RecentPublicationsShortcode
 *
 * CHANGELOG
 *
 * Added 13.01.2019
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class RecentPublicationsShortcode
{

    const NAME = 'display-recent-publications';

    const DEFAULT_ARGS = array(
        'class'             => 'recent-publications',
        'type'              => 'div',
        'format'            => 'short',
        'count'             => 5
    );

    /**
     * Returns the name of the shortcode, with which it can be invoked from placing it in the post content
     *
     * CHANGELOG
     *
     * Added 13.01.2019
     *
     * @return string
     */
    public function getName() {
        return self::NAME;
    }

    // **************************************
    // REGISTERING THE SHORTCODE IN WORDPRESS
    // **************************************

    /**
     * Registers the shortcode in wordpress, so that wordpress knows which callback to invoke, when it encounters
     * the name of this shortcode
     *
     * CHANGELOG
     *
     * Added 13.01.2019
     */
    public function register() {
        add_shortcode(self::NAME, array($this, 'display'));
    }

    // **************************************
    // METHODS FOR GENERATING THE ACTUAL HTML
    // **************************************

    /**
     * This function creates and returns the complete HTML code to be displayed at the position of the shortcode as a
     * string.
     *
     * TODO: Using the getAll function and then sorting is really inefficient for a shortcode. Rather custom query.
     *
     * CHANGELOG
     *
     * Added 13.01.2019
     *
     * @param array $args
     * @return false|string
     */
    public function display(array $args) {

        $args = array_replace(self::DEFAULT_ARGS, $args);
        // Getting a list of all the PublicationPost objects currently on the wordpress site. The ones to be displayed
        // will be fetched from those.
        // To actually display the "recent" commands the list of the publications needs to be sorted by the publishing
        // date.
        $publication_posts = PublicationPost::getAll();
        usort($publication_posts, array($this, 'comparePublicationPublishingDates'));

        // We will be using output buffering to generate the html string to be returned for the shortcode here, because
        // writing the HTML with some php injections is easier than trying to create a html string purely from using
        // php code and string concat's.
        ob_start();
        ?>
            <div class="<?php echo $args['class']; ?>">
                <?php $this->displayListing($publication_posts, $args) ?>
            </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Echos the html code for the whole listing
     *
     * CHANGELOG
     *
     * Added 13.01.2019
     *
     * @param array $publication_posts
     * @param array $args
     */
    public function displayListing(array $publication_posts, array $args) {
        $index = 0;

        // The "type" argument will be either "li" or "div" representing the possibilities of displaying the listing
        // of publications either as just separate text blocks underneath each other or as bullet points list.
        // In case of the bullet points list however, the wrapper class needs to be "ul".
        $tag = ($args['type'] === 'li' ? 'ul' : 'div');

        ?>
            <<?php echo $tag; ?>>
                <?php
                    foreach ($publication_posts as $publication_post) {
                        // After the desired amount of publications has been display, the loop needs to end
                        if ($index >= $args['count']) { break; }

                        // This method will output the html code to display the info for the given publication post
                        $this->displayItem($publication_post, $args);
                        $index++;
                    }
                ?>
            </<?php echo $tag; ?>>
        <?php
    }

    /**
     * Echos the HTML code for an individual item of the listing, given the publication post object to base the content
     * on and the args to define which type of item (tag-wise) and the format of the item content (long, short)
     *
     * CHANGELOG
     *
     * Added 13.01.2019
     *
     * @param PublicationPost $publication_post
     * @param array $args
     */
    public function displayItem(PublicationPost $publication_post, array $args) {
        ?>
            <<?php echo $args['type']; ?>>
                <?php
                    if ($args['format'] == 'short') {
                        $this->displayItemContentShort($publication_post);
                    } elseif ($args['format'] == 'long') {
                        $this->displayItemContentLong($publication_post);
                    }
                ?>
            </<?php echo $args['type']; ?>>
        <?php
    }

    /**
     * Echos the html code for the content of an item in the listing for the "long" format option.
     *
     * CHANGELOG
     *
     * Added 13.01.2019
     *
     * @param PublicationPost $publication_post
     */
    public function displayItemContentLong(PublicationPost $publication_post) {
        // Creating a short excerpt of the content by just taking the first few characters of the post content
        $sanitized_description = sanitize_textarea_field($publication_post->abstract);
        $excerpt = substr($sanitized_description, 0, 300) . '...';
        ?>
            <a href="<?php echo get_the_permalink($publication_post->ID); ?>">
                <?php echo $publication_post->title; ?>
            </a>
            <span> - </span>
            <span>
                <?php echo $publication_post->getAuthors()[0]; ?> et al.
            </span>
            <span>
                in <em><?php echo $publication_post->getJournal(); ?></em>.
            </span>
            <span>
                <?php echo $excerpt; ?>
            </span>
        <?php
    }

    /**
     * Echos the html code for the content of an item in the listing for the "short" format option.
     *
     * CHANGELOG
     *
     * Added 13.01.2019
     *
     * @param PublicationPost $publication_post
     */
    public function displayItemContentShort(PublicationPost $publication_post) {
        ?>
            <a href="<?php echo get_the_permalink($publication_post->ID); ?>">
                <?php echo $publication_post->title; ?>
            </a>
        <?php
    }

    /**
     * Returns a positive or negative value, based on which given publication post object represents the publication,
     * that was published earlier.
     * This method will be used as a custom comparison function for sorting a list of PublicationPost objects
     *
     * CHANGELOG
     *
     * Added 13.01.2019
     *
     * @param PublicationPost $publication_post1
     * @param PublicationPost $publication_post2
     * @return false|int
     */
    public function comparePublicationPublishingDates(PublicationPost $publication_post1, PublicationPost $publication_post2) {
        $time_difference = strtotime($publication_post2->published) - strtotime($publication_post1->published);
        return $time_difference;
    }
}