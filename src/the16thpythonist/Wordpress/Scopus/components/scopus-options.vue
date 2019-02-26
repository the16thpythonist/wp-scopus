<template>
    <div class="scopus-options-component">

        <div id="scopus-options-user">
            <h3>Scopus User</h3>
            <p>
                Please select the user, which you want to be the author of the publication posts.<br>
                Note that this user is <em>not being used as the publication author</em>! It is only used as the
                author for the post, that <em>represents</em> the publication and does not necessarily have to be
                displayed on the actual post page!
-           </p>
            <!--
            24.02.2019
            Replaced the b-form-select with a normal select, because while bootstrap might be nice its bad practice to
            bloat the html with the styling, no clean separation of concern. Also the selected option didnt work.
            -->

            <select v-model="currentUser.ID" size="sm" class="mb-3">
                <template v-for="user in users">
                    <option v-if="user.ID === currentUser" :value="user.ID" selected >{{ user.name }}</option>
                    <option v-else :value="user.ID" >{{ user.name }}</option>
                </template>
            </select>
        </div>

        <!--
        24.02.2019
        Added an additional option to the scopus package: All the possible categories for the authors to be
        associated with. To have a predefined set to choose from is better than having a text input in every single
        author creation screen, because you wouldnt have to remember all of them/ dont have the opportunity to make
        spelling mistakes.
        -->
        <div id="scopus-options-categories">
            <h3>Publication Categories</h3>
            <p>
                The scopus publications are structured using categories. Publications get assigned categories based on
                which author has worked on them. Different authors can be associated with different categories. Think
                of it this way: Author1's speciality is nanotechnology, so every paper he works on will have some
                aspect of nanotechnology in it. Author2 mainly works with exotic plants. If the two authors work on
                a publication together, it will be assigned the categories 'microbes' and 'exotic plants'.<br>
                Use the following section to define all possible categories for your authors. When creating a new
                author profile you will be able to add categories to that author by choosing from all the categories
                defined here!.
            </p>
            <v-array-text-input @input="onCategoriesChange" :array="categories"></v-array-text-input>
        </div>

        <button @click="save">save changes</button>

    </div>
</template>

<style>

</style>

<script>

    jquery = require('jquery');
    ArrayTextInput = require('./array-text-input.vue');


    optionsAjax = (function() {

        /**
         * Given an object with all the parameters to be saved, this sends an ajax request to the server
         *
         * CHANGELOG
         *
         * Added 12.02.2019
         *
         * @param parameters
         */
        let save = function (parameters) {
            let data = {...{action:'save_scopus_options'}, ...parameters};
            jquery.ajax({
                url:        ajaxURL(),
                type:       'GET',
                timeout:    60000,
                dataType:   'html',
                async:      true,
                data:       data,
                success:    function (response) {
                    //console.log(response);
                },
                error:      function (response) {
                    //console.log(response);
                }
            });
        };

        return {
            save: save
        }
    })();

    module.exports = {
        // 24.02.2019
        // Moved all the attributes into the data field, because I found out doing this in props is bad practice.
        // Props is really only for passing values from the parent component
        data: function () {
            return {
                currentUser:    CURRENT_USER,
                users:          USERS,
                categories:     Object.values(CATEGORIES)
            }
        },
        // 24.02.2019
        // We are using the array text input for inputting all the possible categories of the publications/ with which
        // the authors can be associated with.
        components: {
            'v-array-text-input':   ArrayTextInput
        },
        methods: {

            /**
             * This callback will be invoked, when the "save" button has been clicked and it will create an object
             * which contains all the options to be changed and then send these options as an ajax request to the
             * server.
             *
             * CHANGELOG
             *
             * Added 12.02.2019
             *
             * Changed 24.02.2019
             * Removed the whole functionality with buffering the user in selectedUser, there is only the currentUser
             * acting as the model now, that means we do not longer need a if statement, we can just use the
             * currentUser value directly.
             */
            save: function () {

                let options = {};

                // 24.02.2019
                // Using the currentUser value, which is the user currently displayed in the select widget to be the
                // new value for scopus_user
                options['scopus_user'] = this.currentUser.ID;

                // 24.02.2019
                // Also passing the complete list of chosen categories as a CSV string to the response, so that they
                // can be updated as well.
                options['author_categories'] = this.categories.join(',');

                // Actually calling the ajax request, which will save the options
                optionsAjax.save(options);
            },
            /**
             * Returns the user object from the "users" list for the user with the given ID
             *
             * CHANGELOG
             *
             * Added 12.02.2019
             *
             * @param id
             * @return {*}
             */
            getUserByID(id) {
                let found_user = null;
                this.users.forEach(function (user) {
                    if (user.ID === id) {
                        found_user = user
                    }
                });
                return found_user;
            },
            /**
             * Updates the internal 'categories' attribute, when it changes
             *
             * CHANGELOG
             *
             * Added 24.02.2019
             *
             * @param value
             */
            onCategoriesChange: function (value) {
                this.categories = value;
            }
        }
    }
</script>