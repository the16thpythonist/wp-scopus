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

function BackendWrapper() {

    const AJAX_URL = ajaxURL();
    const REST_URL = restURL();

    // -- Basic functions

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

    this.getRequest = function(endpoint, parameters) {
        // TODO: Better wrap this in a function
        let url = REST_URL + endpoint;
        return axios.get(url, {'params': parameters}).then(function (response) {
           return response.data;
        });
    }

    this.postRequest = function(endpoint, payload) {
        let url = REST_URL + endpoint;
        return axios.post(url, payload).then(function (response) {
           return response.data;
        });
    }

    // -- Specific functions

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

    this.getAuthor = function (postID) {
        return this.ajaxRequest('get_author_post', {'ID': postID}).then(function (data) {
            return new author.ScopusAuthor(
                data['post_id'],
                data['first_name'],
                data['last_name'],
                data['scopus_ids'],
                data['categories'],
                {}
            );
        });
    }

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

    this.getCategories = function () {
        return this.getRequest('options/categories/', {}).then(function (data) {
            return data['author_categories'];
        })
    };

    this.getOptions = function () {
        return this.getRequest('options/', {});
    }

    this.updateOptions = function (options) {
        return this.postRequest('options/', options);
    }

    // -- WpFile related

    this.getFile = function(fileName) {
        return this.ajaxRequest('read_data_file', {'filename': fileName}).then(function (data){
            // console.log(`${fileName} content:`);
            // console.log(data);
            return data;
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