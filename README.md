# WpScopus - Automated Scientific Papers on Wordpress

Are you involved in science and proud of all the high class papers you and your colleagues have published? Do you want 
to show the whole community your work publicly? Then obviously having a personal website with all your precious 
publications would be awesome! But, well, obviously you dont have the time to make a new post, copy and paste all the 
contents, add tags, assign categories etc for *each and every* new publication. You dont have time for that, you 
have to do *actual research*.

If that all applies to you, than this project should be your choice. It will offer you the possibility to have a 
wordpress page with all your publications and it will *automatically get & post* new publications, once they are listed 
within the [scopus scientific database](https://www.elsevier.com/solutions/scopus). 
All you have to do is to create profiles for every author, whose publications 
you want to see on your site *once*. Then *3 clicks* on the admin dashboard is enough to set the update process going. 
The result will be fresh posts about all the new publications with category, title, authors & tags correctly assigned!

## Getting Started

Before you get frustrated along the way, I want to warn you now. The installation won't be all "plug & play". Since the 
plugin is not *yet* on the official Wordpress repository, you will have to install it by doing a little bit of coding 
yourself. But if you dont want that, keep checking on the project, as I am busy working on creating an *official plugin* 
to provide the best experience!

### Prerequisites

Well, the most important condition is to *already have a wordpress site up and running*, as this is just a modification 
to an existing site. If you want to know how to set up a new wordpress installation manually, go check out 
[this tutorial](https://www.wpbeginner.com/how-to-install-wordpress/#installftp).

If you are running it via a hosting service is also important that you *have filesystem access*, as we will need to 
create a file or two along the way.

Also you will have to have [composer](https://getcomposer.org/) installed and ready to be used. If you dont usually like 
to download, unpack and import thousands of files yourself, then I think you'll be glad to use composer, because it does 
all just that *for you*. If you dont know how to install composer, go and check out 
[the instructions](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-macos)!

#### Scopus API Key

Since this plugin just builds on top of the *amazing* scopus scientific database, you will need to acquire an *API key* 
to access this database. This API key will then be sent with every request made to the scopus DB to identify, that 
you are a registered member and thus allowed to access the data.

If you want to acquire an API key, go register a scopus account [here](https://www.scopus.com/home.uri)!

### Installing independently

First, navigate to the root folder of your wordpress installation and then go to wp-content \> plugins.
There you will have to create a new folder by the name "wpscopus" and *within that new folder* create two files: 
"wpscopus.php" and "composer.json".

Here is an example series of terminal commands to achieve this: (assuming the standard path for an apache2 server on ubuntu)

```shell
cd /var/www/html/wordpress/wp-content/plugins
mkdir wpscopus
cd wpscopus
touch composer.json
touch wpscopus.php
```

Next, we will have to write the actual code to those files. So open them up in your favorite text editor and 
paste this into your *composer.json*:

```json
{
  "name": "WpScopus Plugin",
  "description": "A wordpress app for scientific publications",
  "version": "0.0.1",
  "type": "project",
  "repositories":[
    {
      "type": "vcs",
      "url": "https://github.com/the16thpythonist/wp-pi-logging.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/the16thpythonist/wp-scopus-collaboration-checker.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/the16thpythonist/kitopen-api.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/the16thpythonist/indico-api.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/the16thpythonist/wp_commands.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/the16thpythonist/wp-data-safe.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/the16thpythonist/wp-scopus.git"
    },
    {
      "type": "vcs",
      "url": "https://github.com/the16thpythonist/wp-cpt-lib.git"
    }
  ],
  "require":
  {
    "the16thpythonist/wp-cpt-lib": "@dev",
    "the16thpythonist/wp-pi-logging": "@dev",
    "the16thpythonist/scopus-collaboration-checker": "@dev",
    "the16thpythonist/kitopen-api": "@dev",
    "the16thpythonist/indico-api": "@dev",
    "the16thpythonist/wp-commands": "@dev",
    "the16thpythonist/wp-data-safe": "@dev",
    "the16thpythonist/wp-scopus": "@dev",
    "kasparsj/scopus-search-api":"9999999-dev"
  },
  "minimum-stability": "dev"
}
```

And the following code into the *wpscopus.php* file:

```php
<?php
/**
 * Plugin Name: WpScopus
 * Plugin URI: https://github.com/the16thpythonist/wp-scopus.git
 * Description: A wordpress app for scientific publications
 * Author: Jonas Teufel
 * Version: 0.0.1.16
 * Author URI: google.com
 * License: GPLv2 or later
 */

require_once 'vendor/autoload.php';
use the16thpythonist\Wordpress\Scopus\WpScopus;

// YOU WILL HAVE TO PUT IN THE KEY YOU RECEIVED FROM SCOPUS INTO THIS STRING!
WpScopus::register(array(), "YOUR API KEY");
```

Now, the last thing we need to do is to actually let the package be installed by composer. For that navigate into the 
*wpscopus* folder and run the composer *update* command

Here is an example:

```shell
cd /var/www/home/html/wordpress/wp-content/plugins/wpscopus
composer update
```

And that's it! Now it should be up and ready. The only thing you have to to now is activate it in the *Plugins* menu 
of the wordpress admin backend.


## CHANGELOG

### 0.0.0.0 - 11.09.2018 

- Initial version

### 0.0.0.1 - 17.10.2018

- Added the package [wp-cpt-lib](https://github.com/the16thpythonist/wp-cpt-lib.git) To the composer requirements. It is 
essentially a base package for introducing new custom post types in a very object oriented way.

### 0.0.0.2 - 06.11.2018

Major update
- Added the "PublicationPost" post type, which is a wrapper to describe scopus publications themselves
- Added the publication fetch mechanism with the "PublicationFetcher" and "PublicationMetaCache" classes, that 
implements a background command, that will automatically post new publications to the site, based on the observed 
authors
- Added the author metrics, which introduces a new shortcode, that will show an animated force graph about the 
publication frequency and the collaborations of the observed authors

### 0.0.0.3 - 20.11.2018 

- Added the UpdateKITOpenCommand, which will add a reference to the KITOpen page to all the posts possible
- Extended the PublicationPost with methods for accessing the KITOpen ID and URL
- Added Parameter support for the FetchPublicationsCommand

### 0.0.0.4 - 06.12.2018

- Wrote the Readme with install instructions
- Fixed a display bug with the flex layout of the author affiliation list
- Fixed an issue with the title for author profiles not being set correctly

### 0.0.0.5 - 02.01.2019

- Added an additional meta field for the PublicationPosts: The author_affiliations are now also being saved with the 
publication. It will be a list of the affiliation IDs of all the observed authors, so it can be tracked to which 
institution the publications where affiliated, when they were written (at least the interesting ones being of the 
observed authors)
- During the fetch process the author affiliations are now also saved to the PublicationPosts
- Added new parameters to the fetch command:
    - count: The amount of publications to be added to the website
    - author_count: The amount of authors to be added to each publication post
    - collaboration_threshold: The amount of authors a publication has to have to declare it as a collaboration paper
- Fixed an issue where the whole command would freeze, when there was a problem with fetching the publications for one 
of the observed authors.
- Added a "save" button in the AuthorPost edit screen, which can be used to save a new blacklist/whitelist configuration
- Changed the DeletePublicationsCommand to UpdatePublicationsCommand, which now updates all the publications based on 
the new affiliation white/blacklist values and a given Time period. (the delete was not necessary anyways)

### 0.0.0.6 - 13.01.2019

- Added a shortcode class for a shortcode "display-recent-commands", which will display a listing with the most 
recent publications posted to the wordpress system with customizable format (long/short) container html class


### 0.0.0.7 - 26.02.2019

- Added an options page for the scopus plugin: To be input there is the user which should act as the wordpress author 
of all the scopus publication posts. Also a list input of all the category names with which an author can ve associated 
with.
- Changed the way author posts are created and edited. The scopus Ids can now be input as a array text input. The 
categories can be added by a dynamic list of select inputs, where the options are defined in the options page of the 
scopus plugins, to prevent wrong names/duplicates/spelling mistakes
ls
- Added some tests (which can only be run on a specific machine and setup, but better than nothing)
- Using VueJS now for the frontend application code

### 0.0.1 - 15.10.2019

- Fixed the scopus author page
    - Added the functionality for blacklisting/whitelisting author affiliations as a Vue component.
    - Removed the author affiliation functionality as plain Javascript
