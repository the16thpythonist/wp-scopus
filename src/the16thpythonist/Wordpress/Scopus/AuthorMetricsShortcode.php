<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 20.10.18
 * Time: 15:29
 */

namespace the16thpythonist\Wordpress\Scopus;

use the16thpythonist\Wordpress\Base\Shortcode;


class AuthorMetricsShortcode implements Shortcode
{
    public $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function register()
    {
        add_shortcode(
            $this->name,
            array($this, 'display')
        );
    }

    public function display()
    {
        ob_start();
        ?>
        <div class="author-metrics">
            <!-- Here, the script will add a SVG object, which will contain the actual graph -->
        </div>
        <script>
            // Late loading the scripts, that are needed to display the graph
            var d3_url = "https://d3js.org/d3.v2.js";
            console.log("LOADING SCRIPT " + d3_url);
            jQuery.getScript(d3_url, function () {
                var author_metrics_url = "<?php echo plugin_dir_url(__FILE__) ?>author-metrics.js";
                console.log("LOADING SCRIPT " + author_metrics_url);
                jQuery.getScript(author_metrics_url, function () {
                    authorMetrics(700, 600);
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }

}