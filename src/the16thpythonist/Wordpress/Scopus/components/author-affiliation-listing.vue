<template>
    <div class="scopus-author-affiliation-container">
        <h2>Author Affiliations</h2>
        <p>
            The following listing contains the information about all the institutes, with which the author has been
            affiliated with in the past. Here you have the chance to select those institutes either to be whitelisted
            or blacklisted. Publications, which have been written by the author while he was affiliated with a
            blacklisted institute will not be imported as a post!
        </p>
        <p>
            Why might this feature be interesting? Some publications an author has written while he was affiliated with
            a different institute/work group might not be interesting for your current project. Those old publications
            of all your authors might flood the website, "drowing" all the interesting, new publications.
        </p>
        <div class="scopus-author-affiliation-list">
            <div class="scopus-author-affiliation scopus-author-affiliation-header">
                <strong class="first">Affiliation name </strong>
                <strong class="other">whitelisted </strong>
                <strong class="other">blacklisted </strong>
            </div>
            <div class="scopus-author-affiliation" v-for="(affiliation, index) in affiliations" :key="affiliation.name">
                <p class="first">{{index}}: {{affiliation.name}}</p>
                <div class="other">
                    <input type="radio" value="white" :name="index" v-model="listing[index]">
                </div>
                <div class="other">
                    <input type="radio" value="black" :name="index" v-model="listing[index]">
                </div>
                <!--
                <fieldset :id="index">
                    <input type="radio" value="white" :name="index" v-model="listing[index]">
                    <input type="radio" value="black" :name="index" v-model="listing[index]">
                </fieldset>
                -->
            </div>
        </div>
        <div class="scopus-author-affiliations-buttons">
            <button @click.prevent="fetchAffiliations" class="material-button">Fetch Affiliations</button>
        </div>
    </div>
</template>

<script>
    // NOTE: Saving the affiliations is not a concern of this component. This component merely acts as a black box,
    // which fetches the affiliation info from the server, gets the whitelist/blacklist config via user input and
    // exposes the result to the parent component.
    // Saving is a higher function of the parent component. It is the parents concern to save the affiliations once the
    // save button is pressed.

    // TODO: Make the logBus actually be used
    // TODO: Button to initiate a new fetch
    let Vue = require( 'vue/dist/vue.js' );
    jquery = require('jquery');


    /**
     * CLASS
     * This class wraps the ajax functionality to interface with the wordpress server.
     *
     * CHANGELOG
     *
     * Added 10.10.2019
     *
     */
    let ajax = function(logBus) {

        /**
         * This function executes the given action on the wordpress server, passing along the given parameters from the
         * 'parameters' object.
         *
         * CHANGELOG
         *
         * Added 10.02.2019
         *
         * @param action_name
         * @param parameters
         */
        let doAction = function (action_name, parameters) {
            let data = {...{action: action_name}, ...parameters};
            jquery.ajax({
                url:        ajaxURL(),
                type:       'GET',
                timeout:    60000,
                dataType:   'html',
                async:      true,
                data:       data,
                success:    function (response) {
                    console.log(response);
                    return response;
                },
                error:      function (response) {
                    console.log(response);
                    return false;
                }
            });
        };

        /**
         * Sends an ajax request to the wordpress server, invoking the ajax callback given by "action_name" using the
         * "parameters". The call is blocking, so that it delays until the communication is finished and the response
         * has been received. This string response of the server is then returned
         *
         * CHANGELOG
         *
         * Added 10.10.2019
         *
         * @param action_name
         * @param parameters
         */
        let receiveData = function(action_name, parameters) {
            let result;
            let data = {...{action: action_name}, ...parameters};

            // Here we are explicitly setting the "async" property to false. This will make the call to the ajax()
            // function blocking. This is important because at some point a result has to be returned.
            jquery.ajax({
                url:        ajaxURL(),
                type:       'GET',
                timeout:    5000,
                dataType:   'html',
                async:      false,
                data:       data,
                success:    function (response) {
                    console.log(response);
                    result = response;
                },
                error:      function (response) {
                    console.log(response);
                    return false;
                }
            });
            return result;
        };

        /**
         * Given a scopus author id, this will tell the wordpress server to fetch the new affiliation info from the
         * scopus database and then save the resulst into the corresponding DataPost.
         *
         * CHANGELOG
         *
         * Added 10.10.2019
         *
         * @param author_id
         */
        let fetchAffiliations = function (author_id) {
            let action = 'scopus_author_fetch_affiliations';
            let parameters = {
                'author_id':    author_id
            };
            doAction(action, parameters);
        };

        /**
         * Given the "filename" of a wordpress DataPost, this method will query the wordpress server to return the
         * string contents of this DataPost.
         *
         * CHANGELOG
         *
         * Added 10.10.2019
         *
         * @param filename
         */
        let readDataPost = function(filename) {
            let action = 'read_data_file';
            let parameters = {
                'filename':     filename,
            };
            return receiveData(action, parameters);
        };

        return {
            doAction: doAction,
            receiveData: receiveData,
            fetchAffiliations: fetchAffiliations,
            readDataPost: readDataPost
        }
    };

    /**
     * CLASS
     * This class wraps the functionality to interface with the affiliation info on the wordpress server. It provides
     * methods to read and save affiliation configuration for an author (which is described by an array of author ids)
     *
     * It uses the basic, general functionality provided by the "ajax" class.
     *
     * CHANGELOG
     *
     * Added 10.10.2019
     *
     */
    let affiliationManager = function(logBus) {

        let ajax_connection = ajax(logBus);

        /**
         * Takes the array of all the author ids associated with the current author. Returns an array of strings file
         * names, where each file name is the name of a DataPost within wordpress, which will contain the affiliation
         * config for one of the given author ids.
         *
         * The files are named with a pattern, that incorporates the author id. Thus the filenames can be directly
         * derived from the author ids.
         *
         * CHANGELOG
         *
         * Added 10.10.2019
         *
         * @param author_ids
         * @return {Array}
         */
        let getFilenames = function(author_ids) {
            let names = [];
            author_ids.forEach(function (id) {
                let name = `affiliations_author_${id}.json`;
                names.push(name);
            });
            return names;
        };

        /**
         * Takes the array of all the scopus author ids, which describe the current author. For each author id the
         * DataPost with the affiliation info will be read and all the affiliation info will be compiled into one
         * object which is returned. The keys will be the scopus affiliation ids, the values objects with the string
         * key 'name' and the two boolean keys 'whitelist' and 'blacklist'.
         *
         * CHANGELOG
         *
         * Added 10.10.2019
         *
         * @param author_ids
         * @return {Array}
         */
        let getAffiliations = function(author_ids) {
            let filenames = getFilenames(author_ids);
            console.log(filenames);
            let responses = [];
            filenames.forEach(function (filename) {
                let response = ajax_connection.readDataPost(filename);
                if (response) {
                    responses.push(response);
                }
            });
            // The responses in the array are just strings for now. JSON strings to be exact. They contain the
            // affiliation whitelisting / blacklisting as objects.
            // The json structure is an object, whose keys are the affiliation ids and the values are objects
            // with the string field "name" and the two boolean fields "whitelist" and "blacklist"
            let affiliations = {};
            responses.forEach(function (response) {
                let object = JSON.parse(response);
                affiliations = {...affiliations, ...object}
            });
            return affiliations;
        };

        /**
         * Given the array with scopus author ids, which describe the current author. This method will trigger the
         * wordpress server to re-fetch the affiliation information for each one of these author ids and then write
         * the new data into the corresponding DataPosts.
         *
         * CHANGELOG
         *
         * Added 10.10.2019
         *
         * @param author_ids
         */
        let fetchAffiliations = function (author_ids) {
            // We are going to tell the server to re-fetch all the affiliations from the scopus database, for every
            // author id, with which this author is associated
            for (let author_id of author_ids) {
                ajax_connection.fetchAffiliations(author_id);
            }
        };

        return {
            getFilenames: getFilenames,
            getAffiliations: getAffiliations,
            fetchAffiliations: fetchAffiliations
        }
    };

    module.exports = {
        name: "AuthorAffiliationListing",
        data: function () {
            return {
                // This assoc array will contain the affiliation ids as keys and either the string "white" or "black"
                // to denote if the affiliation in question has been white- or blacklisted by the user. The individual
                // values of this object are two-way bound to the radio button form displayed on screen.
                listing: {},
                // This object wraps the functionality of interfacing with the wordpress server side to acquire the
                // affiliation info or save it back.
                affiliation_manager: affiliationManager(this.logBus)
            }
        },
        props: {
            // This assoc array will contain thw affiliation ids as keys and objects describing the affiliation
            // state as values. This object is used to iterate over and create the html list on the screen.
            affiliations: {
                type:       Object,
                required:   true
            },
            // It is the parent component's responsibility to pass in the array of author ids, as they are put into an
            // entirely different input component and that is none of the concern of this component.
            authors: {
                type:       Array,
                required:   true,
            },
            // On this vue object the event "logActivity" can be emitted with a string message. These events are then
            // optionally caught by a log component, which displays the log messaages on the site.
            logBus: {
                type:       Vue,
                required:   false
            }
        },
        methods: {
            /**
             * Updates the list of affiliations displayed with the information from the server.
             *
             * This method will query the server for an object containing all the affiliation information. This object
             * is then used to update the internal "affiliations" and "listing" object with the according new values.
             * It is important to note, that this function only fetches information from the server it does not trigger
             * a new fetch process for affiliation data.
             *
             * CHANGELOG
             *
             * Added 10.10.2019
             *
             * Changed 17.10.2019
             * Instead of assigning the new values of the assoc. affiliations dict directly, the function Vue.set() is
             * now being used. Because when working with an object the automatic change detection does not work!
             * Setting the new value with the Vue.set() function tells Vue that it has changed directly so that the
             * display can be updated easily.
             * Also added log messages.
             *
             * Changed 03.12.2019
             * On defau1lt everything will be blacklisted until it is whitelisted explicitly
             */
            updateAffiliations: function () {
                let affiliations = this.affiliation_manager.getAffiliations(this.authors);
                for (let id in affiliations) {
                    if (affiliations.hasOwnProperty(id)) {

                        // Logging, that this affiliation has been changed.
                        if (!this.affiliations.hasOwnProperty(id)) {
                            this.log(`affiliation with ID '${id}' has been updated`);
                        }

                        // Changed 17.10.2019
                        // using the Vue.set() method now to add a new value to the affiliations object, because that
                        // issues Vue to redraw the v-for, which is using the affiliations object!
                        // Changed 03.12.2019
                        // If the entry is not specifically whitelisted, it will be blacklisted on default. This
                        // ensures that even an unedited author has a valid listing in the beginning
                        Vue.set(this.affiliations, id, affiliations[id]);
                        if (affiliations[id].whitelist) {
                            Vue.set(this.listing, id, 'white');
                        } else {
                            Vue.set(this.listing, id, 'black');
                        }
                    }
                }
            },
            /**
             * Clears the assoc. array, which contains the affiliations as well as the array, which contains, whether
             * they have been white or blacklisted
             *
             * CHANGELOG
             *
             * Added 17.10.2019
             */
            clearAffiliations: function() {
                this.affiliations = {};
                this.listing = {};
            },
            /**
             * Triggers the server to fetch new affil. info from scopus and updates the display on site.
             *
             * CHANGELOG
             *
             * Added 11.10.2019
             *
             * Changed 17.10.2019
             * Added a call to "clearAffiliations" before actually updating the affiliations.
             * Also added log messages
             */
            fetchAffiliations: function () {
                // Changed 17.10.2019
                // The fetch process is intended to get a fresh set of affiliations, which means that the old ones
                // have to be cleared first
                this.clearAffiliations();
                this.log('Current affiliations have been cleared');

                // Telling the server to fetch the new affiliation info from the scopus database
                this.affiliation_manager.fetchAffiliations(this.authors);
                this.log('Server has been instructed to query new affiliations');
                // Now, while the server is fetching the new affiliations and writing them to the DataPost file, we are
                // going to periodically update the display of the

                let update_function = this.updateAffiliations;

                let loop = function() {
                    update_function();
                    setTimeout(loop, 2000);
                };

                setTimeout(loop, 2000);
            },
            /**
             * Returns a list with all the affiliation IDS of the author affiliations, that were whitelisted
             *
             * CHANGELOG
             *
             * Added 11.10.2019
             *
             * @return {Array}
             */
            getWhitelist: function() {
                let whitelist = [];
                for (let id in this.listing) {
                    if (this.listing[id] === 'white') {
                        whitelist.push(id);
                    }
                }
                return whitelist;
            },
            /**
             * Returns a list with all the affiliation IDS of the authors affiliations, that were blacklisted
             *
             * CHANGELOG
             *
             * Added 11.10.2019
             *
             * @return {Array}
             */
            getBlacklist: function() {
                let blacklist = [];
                for (let id in this.listing) {
                    if (this.listing[id] === 'black') {
                        blacklist.push(id);
                    }
                }
                return blacklist;
            },
            /**
             * Takes a message and pushes it as an event into the logBus, so that a logging component also connected
             * to the log bus could display the message
             *
             * CHANGELOG
             *
             * Added 17.10.2019
             *
             * @param message
             */
            log: function(message) {
                this.logBus.$emit('logActivity', message);
            }
        },
        created: function () {
            // In the beginning, when the page is being loaded, we directly query the server for the information about
            // the affiliations, so they can be displayed.
            this.updateAffiliations();
        }
    }
</script>

<style scoped>

</style>