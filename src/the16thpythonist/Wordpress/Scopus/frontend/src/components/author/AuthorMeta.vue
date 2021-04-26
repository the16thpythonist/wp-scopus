<template>
    <div class="author-meta-input">
        <!-- Maybe displaying statistical information -->
        <h1>Statistical Information</h1>

        <p>Nothing here yet</p>

        <!-- The simple inputs for the authors name -->
        <h1>Edit Author Properties</h1>

        <p>
            <em>Personal Information.</em> The personal information regarding an author will be saved only for this
            very website. The information is not needed for the interaction with the scopus database. The entered
            first and last name will be used to represent the author on this website. Such is the case with the name
            of this very post: Updating the name here will update the title of this author post.
        </p>

        <p class="post-id">
            Post ID: {{ postId }}
        </p>

        <DescribedTextInput
                class="text-input"
                ref="first_name_input"
                v-model="author.firstName"
                id="author-first-name"
                placeholder="Maximilian"
                title="First Name"
                @input="onInput">
        </DescribedTextInput>

        <DescribedTextInput
                class="text-input"
                v-model="author.lastName"
                id="author-last-name"
                placeholder="Mustermann"
                title="Last Name"
                @input="onInput">
        </DescribedTextInput>

        <div class="spacing"></div>

        <!-- The complex input for the ids and the categories -->

        <p>
            <em>Scopus ID's.</em> Within the scopus database each author is represented by a profile. Amongst different
            information such as the name and country the profile also consists of the <strong>Scopus Author ID</strong>.
            This ID is actually used to represent an author in every request towards the database.<br>
            Once a paper has been published by an author, a reference of it will be linked to the author profile. This
            is actually how this plugin works: Based on an authors ID all the references to his/her publications are
            being retrieved from the scopus database and the papers are automatically imported as wordpress posts. <br>
            Here, an author can be represented by multiple author ID's though. The reason being, that sometimes scopus
            cannot correctly link a publication to an author (due to a spelling error for example) and then it creates
            a new profile, linking the publication to this profile instead. The real life author, which is a single
            person, is now represented by two profiles. For such a case it is possible to associate multiple author ID's
            with a single author.
        </p>

        <ArrayTextInput
                class="array-text-input"
                v-model="author.scopusIds"
                title="ScopusID's: "
                @input="onInput">
        </ArrayTextInput>

        <div class="spacing"></div>

        <p>
            <em>Categories.</em> Each author can be assigned one or multiple categories from an predefined set of
            options. This set of choosable categories can be edited on the options page of this plugin.<br>
            The idea is that these categories represent some sort of clustering of papers represented by this website.
            Assigning a category to an author will have every publication, that is retrieved from the scopus database
            in the future, also be assigned this very category.
        </p>

        <p>
            The widget below can be used to change to existing category assignments with a drop down selection element.
            Using the buttons additional categories can be added or existing ones can be removed.
        </p>

        <ArraySelectInput
                class="array-select-input"
                v-model="author.categories"
                :options="categories"
                title="Categories: "
                :default="categories[0]"
                @input="onInput">
        </ArraySelectInput>

        <div class="spacing"></div>

        <!-- The affiliation input -->

        <h1>Author Affiliations</h1>

        <p>
            <em>Author Affiliations.</em> Within each author's profile inside the scopus database, there is an
            affiliation associated with him. This affiliation refers to some sort of an institution for which the author
            currently produces papers and works for.<br>
            In fact with each individual publication which is retrieved from the scopus database there is a list of
            authors associated with it. And this list of authors contains small "Snapshots" of the authors profile at
            the time of publishing the document. This snapshot includes the affiliation of that time. <br>
            All this is important because in some cases an author might have worked at a different institution and on a
            different topic from what is to be displayed in this very website altogether. For such an author these kind
            of old publications would still end up on the website, even though they may not be desired. For this case
            it is possible to mark some past affiliations of an author as a "blacklisted". This means that when a
            publication with this author/affiliation combination is automatically retrieved from the scopus database,
            it is discarded without being imported as a post...
        </p>

        <p>
            The widget below presents a listing, where each of the known affiliations for this author is represented
            as a row in the table. The radio boxes in right hand columns can be used to mark an affiliation as either
            whitelisted or blacklisted (to be excluded).
        </p>

        <button
                type="button"
                @click.prevent="onAffiliationFetch">
            Fetch affiliations
        </button>

        <ObjectRadioSelect
                class="object-radio-select"
                v-model="author.affiliations"
                :options="affiliationOptions"
                :default="affiliationOptions[0]"
                title="Authors Affiliations Test"
                label="Affiliations:"
                :name-func="createAffiliationLabel"
                :get-func="getAffiliationValue"
                :set-func="setAffiliationValue"
                @input="onInput">
        </ObjectRadioSelect>

        <div class="spacing"></div>

        <!-- Saving the changes -->
        <!-- Maybe have a red display that says changes have been made and they have to be saved -->
        <StatusDiv class="status-modified"
                :value="status">
        </StatusDiv>

        <button
                type="button"
                class="save"
                @click.prevent="onSave">
            Save All Changes!
        </button>
    </div>
</template>

<script>
    /* eslint-disable */
    import DescribedTextInput from "../inputs/DescribedTextInput";
    import ArrayTextInput from "../inputs/ArrayTextInput";
    import ArraySelectInput from "../inputs/ArraySelectInput";
    import RadioGroup from "../inputs/RadioGroup";
    import ObjectRadioSelect from "../inputs/ObjectRadioSelect";
    import StatusDiv from "../outputs/StatusDiv";

    import author from "../../lib/author";
    import backend from "../../lib/backend";

    import axios from 'axios';

    export default {
        name: "AuthorMeta",
        components: {
            ObjectRadioSelect,
            RadioGroup,
            DescribedTextInput,
            ArrayTextInput,
            ArraySelectInput,
            StatusDiv
        },
        data: function () {
            return {
                categories: [],
                affiliationOptions: ['blacklist', 'whitelist'],
                postId: POST_ID,
                author: author.emptyScopusAuthor(),
                backend: new backend.BackendWrapper(),
                status: {},
                debug: false
            }
        },
        methods: {
            /**
             * This method sends a request to the backend to retrieve the list of available author categories.
             * This list is then assigned to the "categories" attribute of this component.
             */
            fetchCategories: function () {
                let optionsPromise = this.backend.getCategories();
                let self = this;
                optionsPromise.then(function (categories) {
                    self.categories = categories;
                })
            },
            /**
             * This method sends a request(s) to the backend to retrieve the information about the author post which is
             * represented by the current post id. This information is returned as a ScopusAuthor object and this
             * scopus author object is then assigned to the "author" attribute of this component.
             */
            fetchAuthor: function() {
                let authorPromise = this.backend.getAuthor(this.postId);
                let self = this;
                authorPromise.then(function (author) {
                    // So this section needs some explaining, because it is rather unintuitive.
                    // So basibally what we want to achieve here: As soon as we have received the new author
                    // information from the backend, we want to update the local author saved within the component.
                    // The obvious way to do this would be:
                    // self.author = author
                    // But this wont work! Or at least it will cause the component to crash. Because for the component
                    // we have defined several attributes of author (such as author.catgories) for example as
                    // v-bindings for child components. Internally these attributes now have observers attached to them
                    // If we just replace the entire author, then the new object does not have these observers! So by
                    // replacing only each attribute of the author object within the loop and with the special $set
                    // method we can swap the values without destroying the observers!
                    for (const [key, value] of Object.entries(author)) {
                        self.$set(self.author, key, value);
                    }
                    self.$forceUpdate();

                    self.log('-- fetched author --');
                    self.log(self.author);

                    // The whole affiliation thing probably also needs some explaining, because at the moment it is
                    // rather confusing.
                    // First of all, the reason why we actually make another seperate request here is because the
                    // information about which affiliation the author has is not actually saved as meta information of
                    // the author post itself. Instead it is saved as a seperata data record, hence requiring a seperate
                    // request.
                    // Now the thing is though, the information about which affiliation is whitelisted and blacklisted
                    // *is* a meta information of the author post. So what we need to do is to get the affiliation
                    // information via request and then update those affiliation objects with the information of wheter
                    // or not they are whitelisted from the author object and only then we can add them to the
                    // author object.
                    let authorAffiliationsPromise = self.backend.getAuthorAffiliations(self.author);
                    authorAffiliationsPromise.then(function (affiliations) {
                        for (const [affiliationId, affiliation] of Object.entries(affiliations)) {
                            affiliation.whitelist = self.author.isAffiliationWhitelisted(affiliationId);
                        }
                        return affiliations;
                    }).then(function (affiliations) {
                        self.$set(self.author, 'affiliations', affiliations);
                        self.$forceUpdate();
                    });
                })
            },
            /**
             * The callback function for whenever any input event is triggered within the component. This includes all
             * of the seperate input widgets. Regardless of what the input is, this method changes the status display
             * to inform the user of unsaved changes and urging to press the save button.
             */
            onInput: function () {
                this.setStatusModified();
            },
            /**
             * The callback function for the "save" button at the end of the component. This method will take the
             * current state of the "author" attribute and send a request to update the database record with this new
             * data. The callback also triggers the page to be reloaded.
             */
            onSave: function () {
                this.log('-- saving author -- ');
                this.log(this.author);

                // let savePromise = this.backend.saveAuthor(this.author);
                let savePromise = this.backend.putAuthor(this.author);
                let self = this;
                savePromise.then(function (value) {
                    self.log('(+) Sucessfully saved!');
                    self.setStatusSaved();
                }).catch(function (message) {
                    self.log('(-) Saving failed!');
                    self.setStatusError(message)
                });

                // Reloading the page after saving
                window.location.reload();
            },
            onAffiliationFetch: function () {
                this.log('-- starting affiliation gather --');
                for (let authorId of this.author.scopusIds) {
                    let requestPromise = this.backend.ajaxRequest('fetch_author_affiliations', {'author_id': authorId});
                    let self = this;

                    requestPromise.then(function (data) {
                        self.log(data);
                    }).catch(function (error) {
                        self.log(error);
                    })
                }
            },
            /**
             * This method is the callback for the creation of the row label for the ObjectRadioSelect component. It
             * is called for every entry of the object which is at the heart of the component. It is supposed to return
             * a string which is to be displayed as the title of the row corresponding to that entry.
             *
             * In this case it displays the affiliation name and the affiliation ID as the title.
             */
            createAffiliationLabel: function (obj, key) {
                return `${obj[key].name} (${obj[key].id})`;
            },
            /**
             * This method is the callback for the retrieving the value for the ObjectRadioSelect component. For every
             * entry of the object, this method is to return the value which that entry represents.
             *
             * In this case for a given entry (row) it returns of wheter or not the "whitelist" property is set for
             * the affiliation object in question.
             */
            getAffiliationValue: function(vm, obj, key) {
                return (obj[key].whitelist ? 'whitelist' : 'blacklist');
            },
            /**
             * This method is the callback for setting the value for the ObjectRadioSelect component. For every entry
             * of the object, it is supposed to take some action to save a new value assignment given through a UI
             * interaction.
             *
             * In this case, the string value of "whitelist" or "blacklist" is appropriately saved as the whitelist
             * boolean attribute of the affiliation object in question.
             */
            setAffiliationValue: function(vm, obj, key, value) {
                obj[key].whitelist = (value === 'whitelist');
            },
            /**
             * Sets the status display to "modified" informs the user of unsaved changes.
             */
            setStatusModified: function() {
                this.status = {
                    'type': 'warning',
                    'message': 'You have unsaved modification to the author data, please save with the button below!'
                }
            },
            /**
             * Sets the status display to "success" which indicates that the saving process has worked
             */
            setStatusSaved: function() {
                this.status = {
                    'type': 'success',
                    'message': 'Successfully saved the changes'
                }
            },
            /**
             * Sets the status display to "error" and displays the given error message.
             *
             * @param {String} message The message to be displayed in the status component
             */
            setStatusError: function(message) {
                this.status = {
                    'type': 'error',
                    'message': `There was an error while saving! "${message}"`
                }
            },
            log: function(msg) {
                if (this.debug) {
                    console.log(msg);
                }
            }
        },
        /**
         * This metod is called after the component has been created by the Vue runtime routine. It will start the
         * methods which will retrieve the data from the backend database using the REST requests.
         */
        created() {
            this.log("=== AuthorMeta.vue component created ===");
            this.fetchCategories();
            this.fetchAuthor();
        }
    }
</script>

<style scoped>

    h1 {
        margin-top: 20px;
    }

    .author-meta-input {
        display: flex;
        flex-direction: column;
    }

    .author-meta-input p {
        font-size: 1.3em;
        margin-top: 2px;
        margin-bottom: 2px;
    }

    .text-input, .array-text-input, .array-select-input, .object-radio-select{
        margin-top: 20px;
        margin-bottom: 20px;
        font-size: 1.2em;
    }

    .status-modified {
        margin-top: 0px;
        margin-bottom: 10px;
        font-size: 1.25em;
    }

    div.spacing {
        padding: 0;
        margin-top: 20px;
        margin-bottom: 20px;
    }

    button {
        padding: 3px 15px 3px 15px;
        border-style: none;
        background-color: #3ECF8E;
        color: white;
        border-radius: 2px;
        font-size: 0.9em;
        box-shadow: 0px 1px 10px 0px #c3c3c3;
    }

    button:hover {
        background-color: #3fdd9b;
    }

    button.save {
        margin-top: 20px;
        margin-bottom: 25px;
        font-size: 2em;
        align-self: center;
        justify-self: center;
        padding: 10px 25px 10px 25px;
    }
</style>