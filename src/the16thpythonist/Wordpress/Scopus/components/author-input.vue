<template>
    <div class="author-input-component">
        <h2>Author Information</h2>
        <p>
            Use these following input fields to enter the necessary information about the author. <br>
            (Note that the first and last name will be used as the title of the author post, replacing any custom title
            entered), <br>
            One author might be associated with multiple scopus author ids. This can be due to a mistake within the
            scopus database. For such a case multiple ids can be added to the author. All of them will be considered
            when fetching affiliation info and while requesting new publications from the scopus database. <br>
            One author usually works in a specific scientific field or a work group. For this case a category can be
            chosen. It is assumed, that if the author has these "specialties", every publication he has co-authored
            will be (at least partially) connected to that topic. Thus every publication fetched, which contains this
            author will be classified with his categories.
        </p>
        <!--
        First we have two normal text inputs for the first and last name of the author. These are bound to two
        corresponding data properties of the component, which get live updated, when the input field is being modified
        -->
        <div class="scopus-input-container">
            <label>First name:</label>
            <input v-model="firstName" type="text" size="sm">
        </div>
        <div class="scopus-input-container">
            <label>Last name:</label>
            <input v-model="lastName" type="text" size="sm">
        </div>test
        <div class="scopus-input-container">
            <label>Scopus ID:</label>
            <!--
            With an array text input it is possible to add an remove new text input fields in a list like display. The
            values of all these input fields are live updated into an array , that is being passed as the parameter to
            the "input" event, that is being emitted from the component.
            -->
            <v-array-text-input v-on:input="onIDChange" :array="scopusIDs"></v-array-text-input>
        </div>
        <div class="scopus-input-container">
            <label>Categories</label>
            <!--
            Array select input works the same way as the array text input, by dynamically adding and removing new
            select widgets. Here the possible options of the select have to be passed as an array to the options
            parameter of the component.
            The array parameter of the array input components are used to make up the initial list.
            -->
            <v-array-select-input v-on:input="onCategoryChange" :array="categories" :options="options"></v-array-select-input>
        </div>

        <!--
        This section is for the functionality to display the affiliations for an author
        -->
        <v-author-affiliation-listing ref="affiliations" :affiliations="affiliations" :logBus="logBus" :authors="scopusIDs"></v-author-affiliation-listing>
        <v-activity-log :messages="[]" :logBus="logBus"></v-activity-log>

        <!--
        The "prevent" option on click is important, so the button doesnt reload the page!
        Pressing this button will either update the author post using ajax, if the author post exists already, if a new
        post is being created it will additionally redirect to the new page.

        Changed 11.10.2019
        Moved the button to the bottom of thee page, so it is clear, that it will also save the affiliations

        Changed 03.12.2019
        Added an additional header and a text box to make it more clear, that the save button HAS TO BE USED to save
        the changes for ALL of this section
        -->
        <h2>Save Changes</h2>
        <p>
            Use the button below to save all the changes made in this section about the author meta information!
        </p>
        <button @click.prevent="onSave" class="material-button">save</button>
    </div>
</template>

<script>
    jquery = require('jquery');
    ArrayTextInput = require('./array-text-input.vue');
    ArraySelectInput = require('./array-select-input.vue');
    AuthorAffiliationListing = require('./author-affiliation-listing.vue');
    ActivityLog = require('./activity-log.vue');

    let Vue = require( 'vue/dist/vue.js' );

    let ajax = function() {

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
                },
                error:      function (response) {
                    console.log(response);
                }
            });
        };

        /**
         * This method sends an ajax request with the given data to the server, which will cause the current author
         * post to be updated with the new info defined in data. Because of the way wordpress works when creating new
         * posts, the update method is the only one needed. There needs to be no insert.
         *
         * CHANGELOG
         *
         * Added 26.02.2019
         *
         * @param data
         */
        let update = function (data) {
            doAction('update_author_post', data)
        };

        // Changed 10.10.2019
        // Also exposing the "doAction" directly, becuase it is needed for the saving process of the affiliations.
        return {
            doAction: doAction,
            update: update
        }
    }();

    module.exports = {
        data: function () {
            // The php code, that creates this component will prepend a javascript code string, that was automatically
            // created from the necessary parameters to be passed to the front end. All these parameters will be
            // saved in the global "PARAMETERS" object.
            return {
                options: Object.values(PARAMETERS['CATEGORY_OPTIONS']),
                categories: Object.values(PARAMETERS['CATEGORIES']),
                scopusIDs: Object.values(PARAMETERS['AUTHOR_IDS']),
                firstName: PARAMETERS['FIRST_NAME'],
                lastName: PARAMETERS['LAST_NAME'],
                // Added 11.10.2019
                // We are also saving the wordpress post id as a property now, because it is needed in several places
                // and it is cleaner to have it as a property instead of having the methods directly access the
                // PARAMETERS object...
                postID: PARAMETERS['POST_ID'],
                // Added 10.10.2019
                logBus: new Vue(),
                // Added 10.10.2019
                // This object will be bound to the author-affiliation-listing component. This component will populate
                // the object with all the information about the affiliations of the author and also whether or  not
                // the user has whitelisted or blacklisted them.
                affiliations: {}
            }
        },
        props: {

        },
        methods: {
            /**
             * Bound on the "input" event of the array text input for the author ids. The value passed to it is the
             * complete updated array with all the scopus ids. This method simply updates the attribute value of the
             * component.
             *
             * CHANGELOG
             *
             * Added 26.02.2019
             *
             * @param value
             */
            onIDChange: function (value) {
                this.scopusIDs = value;
            },
            /**
             * Callback for the "input" event of the array select input for the author categories, simply updates the
             * local component attribute with the new value emitted from the widget.
             *
             * CHANGELOG
             *
             * Added 26.02.2019
             *
             * @param value
             */
            onCategoryChange: function (value) {
                this.categories = value;
            },
            /**
             * Callback for pressing the "save" button.
             * This will assemble a object with all the values from the inputs and send it to the server using ajax.
             * If the current page is the "create new post" page, it will also redirect to the edit page of the newly
             * created author post
             *
             * CHANGELOG
             *
             * Added 26.02.2019
             *
             * Changed 11.10.2019
             * Added a call to the 'saveAffiliations' method, so that after pressing the save button, the affiliation
             * config is also saved to the server
             */
            onSave: function () {
                let data = {
                    'ID':           PARAMETERS.POST_ID,
                    'scopus_ids':   this.scopusIDs.join(','),
                    'categories':   this.categories.join(','),
                    'first_name':   this.firstName,
                    'last_name':    this.lastName
                };
                //console.log(data);
                //console.log(this.scopusIDs);

                ajax.update(data);

                // 11.10.2019
                // This method will save the affiliation config, which the user has put in via the whitelist(blacklist
                // radio selects, to the server.
                this.saveAffiliations();

                // 26.02.2019
                // If we are currently on the new post page, than on save we need to redirect to the actual edit page
                // of the post that was just created
                if (window.location.pathname.includes('post-new.php')) {
                    this.redirectEdit();
                }

                // 03.11.2019
                // Displaying a message in the activity log, after the info has been saved to increase responsiveness
                this.logBus.$emit('logActivity', 'Changes have been saved!');

            },
            /**
             * Saves the affiliation whitelist/blacklist config from the author-affiliation-listing to the server
             *
             * The whitelist and blacklist are generated by functions of the affiliations component. The saving process
             * Is initiated by sending the lists with the affiliation ids as the parameters to an AJAX call.
             *
             * CHANGELOG
             *
             * Added 11.10.2019
             */
            saveAffiliations: function () {
                // The "$refs.affiliations" is a reference to the author affiliation listing component object, which is
                // used as part of the author input.
                // This component manages the author affiliations and exposes methods to get the whitelist and
                // blacklist from it.
                let whitelist = this.$refs.affiliations.getWhitelist();
                let blacklist = this.$refs.affiliations.getBlacklist();

                // This will save the blacklist/whitelist config into the persistent DataPost file for that author.
                let action = 'scopus_author_store_affiliations';
                let parameters = {
                    'post_id':      this.postID,
                    'whitelist':    whitelist,
                    'blacklist':    blacklist
                };
                ajax.doAction(action, parameters);
            },
            /**
             * Redirects the current page to the edit page of the post, whose POST_ID was passed in the
             * PARAMETERS object
             *
             * CHANGELOG
             *
             * Added 26.02.2019
             */
            redirectEdit: function () {
                window.location.href = PARAMETERS.ADMIN_URL + 'post.php?post=' + PARAMETERS.POST_ID + '&action=edit';
            }
        },
        name: "author-input",
        components: {
            'v-array-text-input': ArrayTextInput,
            'v-array-select-input': ArraySelectInput,
            'v-author-affiliation-listing': AuthorAffiliationListing,
            'v-activity-log': ActivityLog,
        }
    }
</script>

<style scoped>
    div.scopus-input-container {
        margin-bottom: 10px;
    }
</style>