<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 23.10.18
 * Time: 14:43
 */

namespace the16thpythonist\Wordpress\Scopus;

use the16thpythonist\Command\Command;
use the16thpythonist\Wordpress\Data\Type\JSONFilePost;
use the16thpythonist\Wordpress\Data\DataPost;

// 28.04.2020 After namespace change
use the16thpythonist\Wordpress\Scopus\Author\AuthorMetricsGenerator;
use the16thpythonist\Wordpress\Scopus\Author\AuthorMetricsConverter;


class GenerateAuthorMetricsCommand extends Command
{
    protected function run(array $args)
    {
        $this->log->info('STARTING TO GENERATE AUTHOR METRICS FROM ALL PUBLICATIONS');

        $args = array();
        $generator = new AuthorMetricsGenerator($args, $this->log);
        $generator->run();

        $this->log->info('SAVING THE AUTHOR METRICS INTO JSON FILES');

        $counts_file = DataPost::create('author-publication-counts.json');
        $counts_file->save($generator->author_counts);

        $cooperations_file = DataPost::create('author-cooperation-counts.json');
        $cooperations_file->save($generator->author_cooperations);

        $colors_file = DataPost::create('category-colors.json');
        $colors_file->save($generator->category_colors);

        $this->log->info('STARTING TO CONVERT THE AUTHOR METRICS TO NODES AND LINKS');

        $converter = new AuthorMetricsConverter(
            $generator->author_counts,
            $generator->author_cooperations,
            $generator->author_colors,
            $this->log
        );
        $converter->run();

        $this->log->info('SAVING THE AUTHOR NODES AND LINKS INTO JSON FILES');

        $nodes_file = DataPost::create('author-nodes.json');
        $nodes_file->save($converter->nodes);

        $links_file = DataPost::create('author-links.json');
        $links_file->save($converter->links);

        $this->log->info('Successfully generated metrics');
    }
}