/* eslint-disable */
import axios from 'axios';
import author from './author';

function Ajax() {

}


function restURL() {
    return '/wp-json/wpscopus/v1/';
}

/*
    Planning for the ajax wrapper interface

    PUBLIC METHODS
    getAuthor:      Should return a ScopusAuthor object with all the affiliations already loaded
    saveAuthor:     Accepts a ScopusAuthor object and saves it into the server database
    getCategories:  Returns a list of strings, which are the categories possible for the site

    PRIVATE METHODS
    getAuthorAffiliations
 */

/**
 * This class is a wrapper which manages data exchange with the backend database/server.
 *
 * The class has special methods for requesting and updating data for the options and the author post type.
 *
 * @return {string}
 * @constructor
 */
function BackendWrapper() {

    const AJAX_URL = ajaxURL();
    const REST_URL = restURL();

    // -- Basic functions

    /**
     * Sends an ajax request to the wordpress backend, triggering the given "action" hook and using the given
     * parameters.
     *
     * The ajax request will be a POST request and the parameters will be encoded as form data. This is an important
     * detail because it means, that only primitive data types can be conveniently transmitted. Passing an array for
     * example as a parameter will convert it into a comma seperated string!
     *
     * @param {String} action The string identifier of the callback to invoke with the request
     * @param {Object} parameters An object defining the parameters to the callback as key -> value pairs
     * @return {Promise<AxiosResponse<any>>}
     */
    this.ajaxRequest = function(action, parameters) {
        let formData = new FormData();
        formData.append('action', action)
        for (const [key, value] of Object.entries(parameters)) {
            formData.append(key, value);
        }
        return axios.post(AJAX_URL, formData).then(function (response) {
            return response.data;
        });
    }

    this.assembleRestUrl(endpoint) {
        if (endpoint[0] !== '/') {
            return REST_URL + endpoint;
        } else {
            return '';
        }
    }

    /**
     * Sends a HTTP GET request to the given endpoint of the wpscopus REST API, where the given parameters object
     * specifies the get parameters.
     *
     * @param {String} endpoint The string partial url, which defines the specific endpoint to send to
     * @param {Object} parameters An object (key -> value) which defines the additional GET parameters
     * @return {Promise<AxiosResponse<Object>>}
     */
    this.getRequest = function(endpoint, parameters) {
        let url = this.assembleRestUrl(endpoint);
        return axios.get(url, {'params': parameters}).then(function (response) {
           return response.data;
        });
    }

    /**
     * Sends a HTTP POST request to the given endpoint of the wpscopus REST API, where the given payload object will
     * be converted to the JSON payload of the request.
     *
     * @param {String} endpoint The string partial url, which defines the specific endpoint to send to
     * @param {Object} payload The content of the request, transmitted as JSON
     * @return {Promise<AxiosResponse<Object>>}
     */
    this.postRequest = function(endpoint, payload) {
        let url = this.assembleRestUrl(endpoint);
        return axios.post(url, payload).then(function (response) {
           return response.data;
        });
    }

    /**
     * Sends a HTTP PUT Request to the given endpoint of the wpscopus REST API, where the given payload object will
     * be converted to the JSON payload of the request.
     *
     * @param {String} endpoint The string partial url, which defines the specific endpoint to send to
     * @param {Object} payload The content of the request, transmitted as JSON
     * @return {Promise<AxiosResponse<any>>}
     */
    this.putRequest = function(endpoint, payload) {
        let url = this.assembleRestUrl(endpoint);
        return axios.put(url, payload).then(function (response) {
            return response.data;
        })
    }

    // -- Authors
    // The following functions deal with the author post.

    /**
     * Updates the author post with the data given as the ScopusAuthor.
     *
     * @param {ScopusAuthor} _author The author object containing the new data, with which to overwrite the existing
     *      record in the wordpress database.
     * @return {Promise<AxiosResponse<Object>>}
     */
    this.putAuthor = function(_author) {
        // So this might be a little bit confusing, because the ScopusAuthor object actually has properties named
        // "whitelist" and "blacklist" and yet we are not using them here. This is because the actual input data for
        // the whitelist and blacklist assignment is saved as properties of the actual ScopusAffiliations objects
        // stored in the dict of affiliations. I know, this is confusing, but when dealing with the input method in
        // the vue component, this is just way more practical. So we actually have to assemble the whitelist and
        // blacklist here based on the properties of the individual affiliation objects associated with the author.
        let whitelist = [];
        let blacklist = [];
        for (let affiliation of Object.values(_author.affiliations)) {
            if (affiliation.whitelist) {
                whitelist.push(affiliation.id);
            } else {
                blacklist.push(affiliation.id);
            }
        }

        return this.putRequest(`author/${_author.id}`, {
            'first_name':           _author.firstName,
            'last_name':            _author.lastName,
            'categories':           _author.categories,
            'scopus_ids':           _author.scopusIds,
            'scopus_whitelist':     whitelist,
            'scopus_blacklist':     blacklist,
        })
    }

    /**
     * Retrieves the information of the author post identified by the given wordpress post ID and returns the data
     * as a ScopusAuthor object.
     *
     * @param {String} postID The wordpress post ID of the author post, whose information to retrieve.
     * @return {Promise<AxiosResponse<ScopusAuthor>>}
     */
    this.getAuthor = function (postID) {
        // You will notice one oddity, being that we pass an empty object to the "affiliations" argument of the
        // constructor instead of any value from the response data. That is because at the moment, the information
        // about the affiliations of the author are not actually saved as meta data of the author post itself, but in
        // a seperata record. That means we need another request which fetches those...
        return this.getRequest(`author/${postID}`, {}).then(function (data) {
            return new author.ScopusAuthor(
                data['post_id'],
                data['first_name'],
                data['last_name'],
                data['scopus_ids'],
                data['categories'],
                {},
                data['scopus_whitelist'],
                data['scopus_blacklist']
            );
        });
    }

    /**
     * Given a ScopusAuthor object, this method will retrieve an object containing all the affiliations of this author.
     * The keys of the returned object will be the affiliations ids and the value ScopusAffiliation objects.
     *
     * @param {ScopusAuthor} _author The author object to identify the author
     * @return {Promise<Object>}
     */
    this.getAuthorAffiliations = function(_author) {
        let promises = [];
        for (let authorId of _author.scopusIds) {
            let fileName = `affiliations_author_${authorId}.json`;
            let promise = this.getFile(fileName);
            promises.push(promise);
        }

        return Promise.all(promises).then(function (files) {
            let affiliations = {};
            for (let file of files) {
                for (const [affiliationId, affiliationData] of Object.entries(file)) {
                    let affiliation = new author.ScopusAuthorAffiliation(
                        affiliationId,
                        affiliationData.name,
                        affiliationData.whitelist
                    )
                    affiliations[affiliationId] = affiliation;
                }
            }

            return affiliations;
        });
    }


    // -- Options
    // The following methods deal with the wpscopus options page.


    this.getCategories = function () {
        return this.getRequest('options/categories/', {}).then(function (data) {
            return data['author_categories'];
        })
    };

    /**
     * Returns the options/settings data for the wpscopus package.
     * The returned object will have the following keys:
     * -
     *
     * @return {Promise<unknown> | Promise<AxiosResponse<Object>>}
     */
    this.getOptions = function () {
        return this.getRequest('options/', {});
    }

    /**
     * Updated the options/settings data for the wpscopus package.
     *
     * @param options
     * @return {Promise<AxiosResponse<Object>>}
     */
    this.updateOptions = function (options) {
        return this.postRequest('options/', options);
    }

    // -- WpFile related

    /**
     * Retrieves a "file" file post
     *
     * @param {String} fileName The name of the file post to retrieve.
     * @return {Promise<*>}
     */
    this.getFile = function(fileName) {
        return this.ajaxRequest('read_data_file', {'filename': fileName}).then(function (data){
            // console.log(`${fileName} content:`);
            // console.log(data);
            return data;
        });
    }

    // -- DEPRECATED ----------------------------------------------------------------------------------------------
    
    this.saveAuthor = function(_author) {
        this.ajaxRequest('update_author_post', {
            'ID': _author.id,
            'first_name': _author.firstName,
            'last_name': _author.lastName,
            'categories': _author.categories,
            'scopus_ids': _author.scopusIds
        });

        let whitelist = [];
        let blacklist = [];
        for (let affiliation of Object.values(_author.affiliations)) {
            if (affiliation.whitelist) {
                whitelist.push(affiliation.id);
            } else {
                blacklist.push(affiliation.id);
            }
        }

        return this.ajaxRequest('update_author_affiliations', {
            'ID': _author.id,
            'whitelist': whitelist,
            'blacklist': blacklist
        });
    }
}


function BackendWrapperMock() {

    const mockCategories = [
        'Science',
        'Biology',
        'Nanotechnology',
        'Physics',
        'Chemistry',
        'Astronomy',
        'Computer Science'
    ];

    const mockMiriam = new author.ScopusAuthor(
        '1',
        'Miriam',
        'Musterfrau',
        ['66867579', '675451799', '6626514132'],
        ['Computer Science', 'Biology'],
        [
            new author.ScopusAuthorAffiliation('1000298', 'Karlsruher Institut für Technologie', true),
            new author.ScopusAuthorAffiliation('1000345', 'Institut für Prozessdatenverarbeitung', true),
            new author.ScopusAuthorAffiliation('1002871', 'Institut für Regelungssysteme', true),
            new author.ScopusAuthorAffiliation('1003901', 'Fraunhofer Institut', true),
            new author.ScopusAuthorAffiliation('1200342', 'Forschungszentrum Informatik (FZI)', true),
            new author.ScopusAuthorAffiliation('3400029', 'Technische Universität München', false),
            new author.ScopusAuthorAffiliation('4500023', 'TU Darmstadt', false)
        ]
    );

    const mockAuthors = {
        '1':    mockMiriam
    };

    this.getAuthor = function (authorID) {
        return new Promise(function (resolve, reject) {
            if (Object.keys(mockAuthors).includes(authorID)) {
                resolve(mockAuthors[authorID]);
            } else {
                reject('No other with the given ID exists');
            }
        })
    };

    this.getCategories = function () {
        return new Promise(function (resolve, reject) {
            resolve(mockCategories);
        })
    };

    this.saveAuthor = function(author) {
        return new Promise(function (resolve, reject) {
            resolve(true);
        })
    }
}


export default {
    BackendWrapper: BackendWrapper,
    BackendWrapperMock: BackendWrapperMock,
}