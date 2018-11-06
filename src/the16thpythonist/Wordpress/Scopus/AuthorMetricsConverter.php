<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 28.10.18
 * Time: 09:05
 */

namespace the16thpythonist\Wordpress\Scopus;

/**
 * Class AuthorMetricsConverter
 *
 * This class will be used to convert the raw author metrics, which exist in the form of the publication counts for
 * each author and the collaboration counts for each author pair.
 *
 * CHANGELOG
 *
 * Added 28.10.2018
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class AuthorMetricsConverter
{

    public $log;

    public $author_counts;

    public $author_colors;

    public $collaboration_counts;

    public $indices;

    public $nodes;

    public $links;

    public $max_weight;

    /**
     * AuthorMetricsConverter constructor.
     * @param array $author_counts
     * @param array $collaboration_counts
     * @param array $author_colors
     * @param $log
     */
    public function __construct(array $author_counts, array $collaboration_counts, array $author_colors, $log) {
        $this->author_counts = $author_counts;
        $this->collaboration_counts = $collaboration_counts;
        $this->author_colors = $author_colors;

        $this->max_weight = max($collaboration_counts);

        $this->log = $log;
    }

    /**
     * This method runs all the calculations. After it is done the resulting arrays are saved to the object attributes.
     *
     * CHANGELOG
     *
     * Added 28.10.2018
     *
     * @since 0.0.0.2
     */
    public function run() {
        $this->createIndices();
        $this->createNodes();
        $this->createLinks();
    }

    /**
     * Creates the 'indices' associative array, which contains the author name strings as keys and integers as values
     * this array is needed, because the nodes and links only rely on numerical indexing, so this array assignes each
     * author a numerical node index
     *
     * CHANGELOG
     *
     * Added 28.10.2018
     *
     * @since 0.0.0.2
     */
    public function createIndices() {
        $counter = 0;
        foreach ($this->author_counts as $author => $count) {
            $this->indices[$author] = $counter;
            $counter++;
        }
    }

    /**
     * Creates the 'nodes' array, this array will be used to describe the nodes. The nodes will represent the
     * individual authors and the radius of the nodes will display the information of how many publications an author
     * has written (pure author counts)
     *
     * CHANGELOG
     *
     * Added 28.10.2018
     *
     * @since 0.0.0.2
     */
    public function createNodes() {
        $this->log->info('CREATING NODES');
        foreach ($this->author_counts as $author => $count) {
            // Getting the color of the category

            $radius = $this->nodeRadius($count);
            $color = $this->author_colors[$author];
            $this->nodes[] = array(
                'label'         => $author,
                'radius'        => $radius,
                'index'         => $this->indices[$author],
                'color'         => $color,
            );
            $this->log->info(sprintf('NODE "%s" radius "%s" color "%s"', $author, $radius, $color));
        }
    }

    /**
     * Creates the 'links' array, which will be an array of arrays, where each sub array contains the info about one
     * author pair and the weight of their connection. This weight will be calculated from the collaboration count of
     * this author pair.
     *
     * CHANGELOG
     *
     * Added 28.10.2018
     *
     * Changed 29.10.2018
     * Not including links with 0 weight.
     *
     * Changed 30.10.2018
     * Added a color property to links.
     * Now also adding invisible links to those authors, that are in the same category, but have never collaborated.
     *
     * @since 0.0.0.2
     */
    public function createLinks() {
        $this->log->info('CREATING LINKS');
        foreach ($this->collaboration_counts as $pair => $count) {
            list($author1, $author2) = explode(';', $pair);
            $weight = $this->linkWeight($count);

            // Having a link with 0 weight is redundant, because that is just about the same as having no link at all
            if ($weight != 0) {
                $this->links[] = array(
                    'source'        => $this->indices[$author1],
                    'target'        => $this->indices[$author2],
                    'weight'        => $weight,
                    'color'         => '#CCC'
                );
                $this->log->info(sprintf('LINK: "%s - %s" weight "%s"', $author1, $author2, $weight));
                continue;
            }

            // Now also adding a invisible Link if they are in the same category
            if ($this->author_colors[$author2] == $this->author_colors[$author1]) {
                $this->links[] = array(
                    'source'        => $this->indices[$author1],
                    'target'        => $this->indices[$author2],
                    'weight'        => 0.03,
                    'color'         => '#FFF'
                );
            }
        }
    }

    /**
     * Given the count of publications of an author, this calculates the radius of the authors node.
     *
     * The calculation is logarithmic to ensure, that even those authors with 0 publications will have a node with
     * radius bigger than 0 and for not having to big of a difference between the nodes, where one could be radius >200
     * and others may only be ~20.
     *
     * CHANGELOG
     *
     * Added 28.10.2018
     *
     * @since 0.0.0.2
     *
     * @param int $count    The amount of publications, the author has written
     * @return float
     */
    public function nodeRadius($count) {
        return ceil(4 * log($count + 3));
    }

    /**
     * Given the count of collaborations for one author pair, this will calculate the weight of their connection.
     *
     * The bigger the weight, the more they get pulled together in the force layout graph.
     *
     * The calculation is normalized, which means the given count is always divided by the biggest collaboration count
     * found in the whole array, as the javascript to display the graph only accepts weight values between 0 and 1.
     * Additionally the value is down scaled by a constant factor to make it look better and not so crammed together.
     *
     * CHANGELOG
     *
     * Added 28.10.2018
     *
     * @since 0.0.0.2
     *
     * @param $count
     * @return float|int
     */
    public function linkWeight($count) {
        return $count * (1 / ($this->max_weight * 4));
    }

}