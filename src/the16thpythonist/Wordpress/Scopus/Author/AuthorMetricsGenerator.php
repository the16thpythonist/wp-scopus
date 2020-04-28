<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 23.10.18
 * Time: 09:41
 */

namespace the16thpythonist\Wordpress\Scopus\Author;
use Log\LogPost;
use the16thpythonist\Wordpress\Data\DataPost;
use the16thpythonist\Wordpress\Data\Type\JSONFilePost;
use Log\VoidLog;

// 28.04.2020 After the namespace change
use the16thpythonist\Wordpress\Scopus\Publication\PublicationPost;

/**
 * Class AuthorMetricsGenerator
 *
 * CHANGELOG
 *
 * Added 23.10.2018
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class AuthorMetricsGenerator
{
    public $default;

    /** @var LogPost $log */
    public $log;

    public $authors;

    public $author_names;

    public $author_ids;

    public $author_assoc;

    public $author_pairs;

    public $author_counts;

    public $author_cooperations;

    public $author_colors;

    public $category_colors;

    public $publications;

    public function __construct($args, $log)
    {
        /** @var LogPost log */
        $this->log = $log;
    }

    /**
     * Calculates the author metrics for the observed authors from the publications currently on the website
     *
     * CHANGELOG
     *
     * Added 23.10.2018
     */
    public function run() {

        // Loading all the observed authors & all publications, currently on the website
        $this->authors = AuthorPost::getAll();
        $this->publications = PublicationPost::getAll(FALSE, FALSE);

        $this->log->info('Observed authors loaded: '. count($this->authors));
        $this->log->info('Publications loaded: ' . count($this->publications));

        // From all these post wrapper objects we will create a array, which contains only the string names of the
        // authors, by mapping the according method to the array
        $this->author_names = array_map(array($this, 'getAuthorPostName'), $this->authors);
        $this->author_ids = array();
        $this->author_colors = array();
        /** @var AuthorPost $author */
        foreach ($this->authors as $author) {
            $this->author_ids = array_merge($this->author_ids, $author->author_ids);

            // Getting the first category of an author
            $category = $author->categories[0];
            if (!array_key_exists($category, $this->category_colors)) {
                $color = sprintf("rgba(%s, %s, %s)", random_int(0, 255), random_int(0, 255), random_int(0, 255));
                $this->category_colors[$category] = $color;
            }
            $this->author_colors[$this->getAuthorPostName($author)] = $this->category_colors[$category];
        }

        $this->author_pairs = $this->createAuthorPairs($this->author_names);
        /*
         * The "author metrics" defines the following statistical data: The count of publications in the system for
         * each observed author and the amount of times each observed author has written a paper with any other
         * observed author.
         * This leads to two necessary arrays:
         * - author_counts: Whose keys are the n names of the authors and the values an integer
         * - author_cooperations:  The keys are pairs of authors as a string and the values are also int.
         * since the all the author PAIRS are required, the size of the array is O(n²)
         */

        // Initializing the arrays with 0 as count value
        $this->author_counts = array();
        foreach ($this->author_names as $author_name) {
            $this->author_counts[$author_name] = 0;
        }
        $this->author_cooperations = array();
        foreach ($this->author_pairs as $author_pair) {
            $this->author_cooperations[$author_pair] = 0;
        }

        /** @var PublicationPost $publication_post */
        foreach ($this->publications as $publication_post) {

            // Get all the author terms from the publication
            $author_terms = $publication_post->getAuthorTerms();

            // Filter these terms to get only those terms, that represent a observed author stay
            $observed_author_terms = array_filter($author_terms, array($this, 'filterAuthor'));

            $this->log->info(count($observed_author_terms) . ' found for publication: ' . $publication_post->title);

            $observed_author_names = array_map(function($term) {return explode('.', $term->name)[0] . '.'; }, $observed_author_terms);

            $this->incrementAuthorCount($observed_author_names);
            $this->incrementAuthorCooperation($observed_author_names);
        }

        arsort($this->author_cooperations);
        arsort($this->author_counts);

        return array($this->author_counts, $this->author_cooperations);

    }

    /**
     * Increments the author publication counter for all those authors within the given array of author names
     *
     * CHANGELOG
     *
     * Added 23.10.2018
     *
     * @param array $author_names
     */
    public function incrementAuthorCount($author_names) {
        foreach ($author_names as $author_name) {
            if (array_key_exists($author_name, $this->author_counts)){
                $this->author_counts[$author_name] += 1;
            }
        }
    }

    /**
     * Increments the author cooperation counter based on the author pairs within the given author list
     *
     * CHANGELOG
     *
     * Added 23.10.2018
     *
     * @param array $author_names
     */
    public function incrementAuthorCooperation($author_names) {
        if (count($author_names) >= 2) {
            $author_pairs = $this->createAuthorPairs($author_names);
            foreach ($author_pairs as $author_pair) {
                if (array_key_exists($author_pair, $this->author_cooperations)) {
                    $this->author_cooperations[$author_pair] += 1;
                }
            }
        }
    }

    /**
     * Whether or not the given author term describes a observed author
     *
     * CHANGELOG
     *
     * Added 23.10.2018
     *
     * @param \WP_Term $term    The term object for the 'author' taxonomy for publication posts
     * @return bool
     */
    public function filterAuthor(\WP_Term $term) {
        return in_array($term->name, $this->author_names) || in_array($term->slug, $this->author_ids);
    }

    /**
     * Given an AuthorPost wrapper object, this will return the full name string of the author, described by the wrapper
     *
     * The name format will be the following:
     * "{first name} {first character of last name}."
     * Because this is the format, the author terms (associated with the publications) are named
     *
     * CHANGELOG
     *
     * Added 23.10.2018
     *
     * @param AuthorPost $author_post
     * @return string
     */
    public function getAuthorPostName(AuthorPost $author_post) {

        /*
         * With this function we create a author name format, like the scopus system uses it, because in the end we
         * can only compare the string author names when iterating through the publications. The scopus system uses a
         * name format, which contains the first name and then the first letter of the last name followed by a period.
         */

        // The first letter of the last name
        $last_name_initial = substr($author_post->first_name, 0, 1);
        $name = sprintf('%s %s.', $author_post->last_name, $last_name_initial);
        $this->log->info(sprintf('Author "%s" with scopus ID "%s"', $name, $author_post->author_ids[0]));
        return $name;
    }

    /**
     * Creates an array which contains strings, that each contain two of the given author names. Overall every possible
     * pairing possibility between any two author names will be present in the returned array.
     *
     * The order of the names within one pair is dictated by which one is lexicographically bigger
     *
     * CHANGELOG
     *
     * Added 23.10.2018
     *
     * @param array $author_names   An array containing all the strings to be paired up.
     * @return array
     */
    public function createAuthorPairs($author_names) {

        // This is a safety meassure, because php has a weird mechanic, where array indices are sometimes not in linear
        // ascending order, because even numerically indexed arrays (normal ones) are treated as associative arrays
        $author_names = array_values($author_names);

        $author_pairs = array();
        for ($i = 0; $i < count($author_names) - 1; $i++) {
            for ($j = $i + 1; $j < count($author_names); $j++) {

                $authors = array($author_names[$i], $author_names[$j]);
                sort($authors);
                $pair_name = implode(';', $authors);

                $author_pairs[] = $pair_name;
            }
        }

        return $author_pairs;
    }

}