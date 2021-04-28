<template>
    <div class="scopus-options">
        <h2>WpScopus Settings</h2>
        <div class="description">
            WpScopus is a plugin, which automatically imports scientific publication records from the Scopus web
            database as publication posts for a wordpress installation.
        </div>

        <h3>General Settings</h3>
        <div class="general-settings">
            <div class="user-id row">
                <div class="col1">
                    Scopus User
                </div>
                <div class="col2">
                    <select v-model="userId">
                        <option :value="id" :key="id" v-for="(name, id) in availableUsers">
                            {{ `${name} (${id})` }}
                        </option>
                    </select>
                    <div class="description">
                        The publication records which are imported from scopus are published as new posts. These posts
                        have to refer to some user as an author. This option lets you select which user to be listed
                        as the author of publication posts. (It is recommended to create a new user for this purpose,
                        which is called "Scopus Bot" or something similar)
                    </div>
                </div>
            </div>

            <div class="author-categories row">
                <div class="col1">
                    Author Categories
                </div>
                <div class="col2">
                    <ArrayTextInput
                            class="array-text-input"
                            v-model="categories"
                            title="">
                    </ArrayTextInput>
                    <div class="description">
                        When a new publication is imported as a post, it is automatically assigned a series of
                        categories, which is based on the categories assigned to all of it's observed authors (
                        observed authors are all those, which are registered within the wordpress site). This widget
                        lets you define the available categories for the authors.
                    </div>
                </div>
            </div>
        </div>

        <h3>Scopus Settings</h3>
        <div class="scopus-settings">
            <div class="api-key row">
                <div class="col1">
                    Scopus API Key
                </div>
                <div class="col2">
                    <input
                            class="text-input"
                            v-model="apiKey"
                            type="text"
                            placeholder="Enter your Scopus API Key"/>
                    <div class="description">
                        The scopus database is not an entirely public service. One has to be a registered user to
                        request data from the publication archive. A request is authenticated by using a personal API
                        key. You will need to create one of those to be able to use this service.
                    </div>
                </div>
            </div>
        </div>

        <button
                type="button"
                id="submit"
                class="button button-primary"
                @click="onSave">
            Save Changes
        </button>
    </div>
</template>

<script>
    /* eslint-disable */
    import ArrayTextInput from "../inputs/ArrayTextInput";
    import RowTextInput from "../inputs/RowTextInput";

    import backend from "../../lib/backend";

    export default {
        name: "ScopusOptions",
        components: {
            ArrayTextInput,
            RowTextInput,
        },
        data: function() {
            return {
                backend: new backend.BackendWrapper(),
                categories: [],
                apiKey: '',
                userId: 0,
                availableUsers: []
            }
        },
        methods: {
            /**
             * The callback function for when the "save" button is pressed. To save the options, an appropriate post
             * request is send to the backend. Additionally the page is reloaded.
             *
             * @return void
             */
            onSave: function() {
                let options = {
                    'scopus_api_key': this.apiKey,
                    'scopus_user_id': this.userId,
                    'author_categories': this.categories,
                }
                this.backend.updateOptions(options);

                // After pressing the save button we reload the page to show the user that the changes are actually
                // reflected on the server.
                window.location.reload();
            }
        },
        /**
         * The hook which is executed as soon as the component was created. After creation, we immediately send a
         * request to the backend retrieving the actual option values, which are then being assigned to the component
         * attributes. This in turn will display them on the page, since the component attributes are bound to the
         * various input widgets on the page.
         *
         * @return void
         */
        created: function() {
            let self = this;
            let optionsPromise = this.backend.getOptions().then(function (options){
                self.$set(self, 'categories', options['author_categories']);
                self.$set(self, 'apiKey', options['scopus_api_key']);
                self.$set(self, 'userId', options['scopus_user_id']);
                self.$set(self, 'availableUsers', options['available_users'])
            });
        }
    }
</script>

<style scoped>

    h2, h3 {
        margin-top: 30px;
    }

    .row {
        width: 100%;
        display: flex;
        flex-direction: row;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: nowrap;
        margin-left: 0px;
        margin-right: 20px;
        padding-top: 20px;
        font-size: 14px;
    }

    .col1 {
        font-weight: bold;
        width: 300px;
    }

    .col2 {
        width: 100%;
        align-self: flex-start;
        margin-right: 20px;
    }

    #submit {
        margin-top: 40px;
    }

    .description {
        margin-top: 10px;
        font-size: 12px;
        color: #646970;
    }

    .text-input {
        width: 50%;
    }

</style>