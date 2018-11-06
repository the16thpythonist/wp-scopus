# Wordpress Scopus database 

## CHANGELOG

### 11.09.2018 - 0.0.0.0

- Initial version

### 17.10.2018 - 0.0.0.1

- Added the package [wp-cpt-lib](https://github.com/the16thpythonist/wp-cpt-lib.git) To the composer requirements. It is 
essentially a base package for introducing new custom post types in a very object oriented way.

## 06.11.2018 - 0.0.0.2

Major update
- Added the "PublicationPost" post type, which is a wrapper to describe scopus publications themselves
- Added the publication fetch mechanism with the "PublicationFetcher" and "PublicationMetaCache" classes, that 
implements a background command, that will automatically post new publications to the site, based on the observed 
authors
- Added the author metrics, which introduces a new shortcode, that will show an animated force graph about the 
publication frequency and the collaborations of the observed authors

