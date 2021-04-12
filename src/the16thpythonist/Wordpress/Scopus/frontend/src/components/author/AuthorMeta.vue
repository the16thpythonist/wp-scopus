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

    console.log(PARAMETERS);

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
                postId: PARAMETERS['POST_ID'],
                author: author.emptyScopusAuthor(),
                backend: new backend.BackendWrapper(),
                status: {}
            }
        },
        methods: {
            fetchCategories: function () {
                let optionsPromise = this.backend.getCategories();
                let self = this;
                optionsPromise.then(function (categories) {
                    self.categories = categories;
                })
            },
            fetchAuthor: function() {
                let authorPromise = this.backend.getAuthor(this.postId);
                let self = this;
                authorPromise.then(function (author) {
                    self.author = author;
                    console.log(self.author);
                    self.$forceUpdate();
                })
            },
            onInput: function () {
                this.setStatusModified();
            },
            onSave: function () {
                let savePromise = this.backend.saveAuthor(this.author);
                let self = this;
                savePromise.then(function (value) {
                    console.log('Sucessfully saved');
                    self.setStatusSaved();
                }).catch(function (message) {
                    console.log('Saving failed');
                    self.setStatusError(message)
                });
            },
            createAffiliationLabel: function (obj, key) {
                return `${obj[key].name} (${obj[key].id})`;
            },
            getAffiliationValue: function(vm, obj, key) {
                return (obj[key].whitelist ? 'whitelist' : 'blacklist');
            },
            setAffiliationValue: function(vm, obj, key, value) {
                obj[key].whitelist = (value === 'whitelist');
            },
            setStatusModified: function() {
                this.status = {
                    'type': 'warning',
                    'message': 'You have unsaved modification to the author data, please save with the button below!'
                }
            },
            setStatusSaved: function() {
                this.status = {
                    'type': 'success',
                    'message': 'Successfully saved the changes'
                }
            },
            setStatusError: function(message) {
                this.status = {
                    'type': 'error',
                    'message': `There was an error while saving! "${message}"`
                }
            }
        },
        created() {
            console.log("=== AuthorMeta.vue component created ===");
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