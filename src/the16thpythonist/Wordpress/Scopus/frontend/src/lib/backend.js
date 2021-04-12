/* eslint-disable */
import axios from 'axios';
import author from './author';

function Ajax() {

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

    const mockCategories = [
        'Science',
        'Biology',
        'Nanotechnology',
        'Physics',
        'Chemistry',
        'Astronomy',
        'Computer Science'
    ];

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


    this.getAuthor = function (postID) {
        return this.ajaxRequest('get_author_post', {'ID': postID});
    }

    // Still mock!

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