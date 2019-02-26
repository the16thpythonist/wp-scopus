<template>
    <div class="author-input-component">
        <h2>Author Information</h2>
        <p>
            Use these following input fields to enter the necessary information about the author. <br>
            (Note that the first and last name will be used as the title of the author post, replacing any custom title
            entered)
        </p>
        <!--
        First we have two normal text inputs for the first and last name of the author. These are bound to two
        corresponding data properties of the component, which get live updated, when the input field is being modified
        -->
        <div class="scopus-input-container">
            <label>First name:</label>
            <input v-model="firstName" type="type-text" size="sm"></input>
        </div>
        <div class="scopus-input-container">
            <label>Last name:</label>
            <input v-model="lastName" type="type-text" size="sm"></input>
        </div>
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
        The "prevent" option on click is important, so the button doesnt reload the page!
        Pressing this button will either update the author post using ajax, if the author post exists already, if a new
        post is being created it will additionally redirect to the new page.
        -->
        <button @click.prevent="onSave">save</button>
    </div>
</template>

<script>
    jquery = require('jquery');
    ArrayTextInput = require('./array-text-input.vue');
    ArraySelectInput = require('./array-select-input.vue');

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
                    //console.log(response);
                },
                error:      function (response) {
                    //console.log(response);
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

        return {
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
                lastName: PARAMETERS['LAST_NAME']
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
             */
            onSave: function () {
                let data = {
                    'ID':           PARAMETERS.POST_ID,
                    'scopus_ids':   this.scopusIDs.join(','),
                    'categories':   this.categories.join(','),
                    'first_name':   this.firstName,
                    'last_name':    this.lastName
                };
                console.log(data);
                console.log(this.scopusIDs);

                ajax.update(data);
                // 26.02.2019
                // If we are currently on the new post page, than on save we need to redirect to the actual edit page
                // of the post that was just created
                if (window.location.pathname.includes('post-new.php')) {
                    this.redirectEdit();
                }

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
            'v-array-select-input': ArraySelectInput
        }
    }
</script>

<style scoped>
    div.scopus-input-container {
        margin-bottom: 10px;
    }
</style>