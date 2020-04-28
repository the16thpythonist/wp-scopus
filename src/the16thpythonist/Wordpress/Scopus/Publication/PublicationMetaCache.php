<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 30.10.18
 * Time: 15:44
 */

namespace the16thpythonist\Wordpress\Scopus\Publication;

use the16thpythonist\Wordpress\Data\DataPost;
use the16thpythonist\Wordpress\Data\Type\JSONFilePost;

/**
 * Class PublicationMetaCache
 *
 * The initial problem is the following:
 * At this point, when the fetch process is running (This is the script which gets new scopus publications from the
 * database) most of these publications are too old, but they have to be requested from the database anyways, because
 * only in the response their publication date is specified. This means about 2/3 of all publications cause
 * unnecessary network traffic.
 *
 * That is what this object is supposed to solve:
 * Once all publications have been fetched, their publishing date will be persistently saved in this object and then
 * during the next fetch process, the date will be evaluated before actually sending a request to the scopus database
 *
 * CHANGELOG
 *
 * Added 30.10.2018
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class PublicationMetaCache
{

    public $data;

    /** @var JSONFilePost $file */
    public $file;

    public $exists;

    const FILENAME = 'publication-meta-cache.json';

    /**
     * PublicationMetaCache constructor.
     *
     * CHANGELOG
     *
     * Added 30.10.2018
     *
     * @since 0.0.0.2
     */
    public function __construct()
    {
        // First we will attempt to load the data from the file and if that is not possible, we will create the main
        // data array as empty.
        /** @var JSONFilePost $file */
        $this->exists = DataPost::exists(self::FILENAME);
        if ($this->exists) {
            $this->file = DataPost::load(self::FILENAME);
            $this->data = $this->file->load();
        } else {
            $this->file = DataPost::create(self::FILENAME);
            $this->data = array();
        }
    }

    /**
     * Whether or not this object contains meta data for the given scopus id at all
     *
     * CHANGELOG
     *
     * Added 30.10.2018
     *
     * @since 0.0.0.2
     *
     * @param string $scopus_id
     * @return bool
     */
    public function contains(string $scopus_id) {
        return array_key_exists($scopus_id, $this->data);
    }

    /**
     * Writes new meta data for a given scopus id
     *
     * CHANGELOG
     *
     * Added 30.10.2018
     *
     * Changed 31.10.2018
     * Added the title as a parameter and meta value to save in the cache
     *
     * @since 0.0.0.2
     *
     * @param string $scopus_id
     * @param string $title
     * @param string $published
     */
    public function write(string $scopus_id, string $title, string $published) {
        // Here we make sure, that in the following function we can assume, that a key for the given scopus id exists
        // in the main data structure and that the value to this key is an array
        $this->prepareEntry($scopus_id);

        // Creating the array that contains the new meta key value pairs to be assigned to this scopus id
        // 31.10.2018
        // Saving the title and the publishing date for a publication in the cache.
        $meta = array(
            'published'         => $published,
            'title'             => $this->sanitizeString($title),
        );
        // Overwriting the old meta array for this scopus id
        $this->data[$scopus_id] = array_replace($this->data[$scopus_id], $meta);
    }

    private function sanitizeString(string $string) {
        $string = utf8_encode($string);
        $string = stripslashes($string);
        $string = str_replace('{', '', $string);
        $string = str_replace('}', '', $string);
        $string = str_replace("'", '', $string);
        $string = str_replace('"', '', $string);
        if (0 === strpos(bin2hex($string), 'efbbbf')) {
            $string = substr($string, 3);
        }
        return $string;
    }

    /**
     * Returns the string publishing date for the publication with the given scopus id.
     *
     * The date has the format "{year}-{month}-{day}"
     *
     * CHANGELOG
     *
     * Added 30.10.2018
     *
     * @since 0.0.0.2
     *
     * @param string $scopus_id
     *
     * @return string
     */
    public function getPublishingDate(string $scopus_id) {
        return $this->data[$scopus_id]['published'];
    }

    /**
     * Returns the title of the publication with the given scopus id
     *
     * CHANGELOG
     *
     * Added 31.10.2018
     *
     * @since 0.0.0.2
     *
     * @param string $scopus_id
     * @return mixed
     */
    public function getTitle(string $scopus_id) {
        return $this->data[$scopus_id]['title'];
    }

    /**
     * This function actually saves the current data structure into a persistent post format
     *
     * CHANGELOG
     *
     * Added 30.10.2018
     *
     * @since 0.0.0.2
     */
    public function save() {
        $this->file->save($this->data);
    }

    /**
     * Adds the given key value combination as meta information for the given scopus id
     *
     * CHANGELOG
     *
     * Added 30.10.2018
     *
     * @since 0.0.0.2
     *
     * @param string $scopus_id
     * @param string $key
     * @param $value
     */
    public function writeMeta(string $scopus_id, string $key, $value) {
        // Here we make sure, that in the following function we can assume, that a key for the given scopus id exists
        // in the main data structure and that the value to this key is an array
        $this->prepareEntry($scopus_id);

        // Writing the value for the given key into the meta array for the given scopus id.
        $this->data[$scopus_id][$key] = $value;
    }

    /**
     * Given the scopus id for the entry and a string key name, this method will check if the cache entry array
     * associated with the given scopus id contains an entry with with the given key name.
     *
     * CHANGELOG
     *
     * Added 28.11.2019
     *
     * @param string $scopus_id
     * @param string $key
     * @return bool
     */
    public function keyExists(string $scopus_id, string $key) {
        $publication_exists = array_key_exists($scopus_id, $this->data);
        $exists = $publication_exists && array_key_exists($key, $this->data[$scopus_id]);
        return $exists;
    }

    /**
     * Given the scopus id and the string key name, this function will return the value saved under the given key
     * within the entry of the publication identified by the given scopus id
     *
     * It should be noted, that this function does not check, whether the key exists for the given scopus id or not.
     * This check for key existence should be done before calling this function!
     *
     * CHANGELOG
     *
     * Added 28.11.2019
     *
     * @param string $scopus_id
     * @param string $key
     * @return mixed
     */
    public function readMeta(string $scopus_id, string $key) {
        return $this->data[$scopus_id][$key];
    }

    /**
     * Initializes the value for the given scopus id as an empty array, if the key doesn't exist yet
     *
     * The main data structure, that is supposed to be represented by this object is supposed to be an array whose
     * keys are the string scopus ids and the values are arrays containing different key value pairs themselves.
     * So when we now want to add a new key value pair, we want to assume, that for any given scopus id, the value
     * at least already is an array.
     *
     * That's what this function does. If the given scopus id does not exist in the main data structure already it
     * creates a new entry and sets an empty array as the key.
     *
     * CHANGELOG
     *
     * Added 30.10.2018
     *
     * @param string $scopus_id
     */
    private function prepareEntry(string $scopus_id) {
        if (!array_key_exists($scopus_id, $this->data)) {
            $this->data[$scopus_id] = array();
        }
    }

}