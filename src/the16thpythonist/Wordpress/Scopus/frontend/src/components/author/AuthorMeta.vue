<template>
    <div class="author-meta-input">
        <!-- Maybe displaying statistical information -->
        <h1>Statistical Information</h1>

        <p>Nothing here yet</p>
        <!-- The simple inputs for the authors name -->
        <h1>Edit Author Properties</h1>
        <p>
            <>
        </p>

        <DescribedTextInput
                class="text-input"
                v-model="author.firstName"
                id="author-first-name"
                placeholder="Maximilian"
                title="First Name">
        </DescribedTextInput>

        <DescribedTextInput
                class="text-input"
                v-model="author.lastName"
                id="author-last-name"
                placeholder="Mustermann"
                title="Last Name">
        </DescribedTextInput>
        <!-- The complex input for the ids and the categories -->

        <ArrayTextInput
                class="array-text-input"
                v-model="author.scopusIds"
                title="ScopusID's: ">
        </ArrayTextInput>

        <ArraySelectInput
                class="array-select-input"
                v-model="author.categories"
                :options="categories"
                title="Categories: "
                :default="categories[0]">
        </ArraySelectInput>

        <!-- The affiliation input -->
        <h1>Author Affiliations</h1>

        <p>
            <em>Author Affiliations:</em> Within each author's profile inside the scopus database, there is an
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
            The widget below presents a listing...
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
                :set-func="setAffiliationValue">
        </ObjectRadioSelect>

        <!-- Saving the changes -->
        <!-- Maybe have a red display that says changes have been made and they have to be saved -->
        <button
                type="button"
                class="save"
                @click.prevent="onSave">
            Save All Changes
        </button>
    </div>
</template>

<script>
    import DescribedTextInput from "../inputs/DescribedTextInput";
    import ArrayTextInput from "../inputs/ArrayTextInput";
    import ArraySelectInput from "../inputs/ArraySelectInput";
    import RadioGroup from "../inputs/RadioGroup";
    import ObjectRadioSelect from "../inputs/ObjectRadioSelect";

    import author from "../../lib/author";
    import backend from "../../lib/backend";

    export default {
        name: "AuthorMeta",
        components: {
            ObjectRadioSelect,
            RadioGroup,
            DescribedTextInput,
            ArrayTextInput,
            ArraySelectInput
        },
        data: function () {
            return {
                categories: [],
                affiliationOptions: ['blacklist', 'whitelist'],
                authorId: '1',
                author: author.emptyScopusAuthor(),
                backend: new backend.BackendWrapperMock()
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
                let authorPromise = this.backend.getAuthor(this.authorId);
                let self = this;
                authorPromise.then(function (author) {
                    self.author = author;
                })
            },
            onSave: function () {
                let savePromise = this.backend.saveAuthor(this.author);
                let self = this;
                savePromise.then(function (value) {
                    console.log('Sucessfully saved');
                }).catch(function (message) {
                    console.log('Saving failed');
                })
            },
            createAffiliationLabel: function (obj, key) {
                return `${obj[key].name} (${obj[key].id})`;
            },
            getAffiliationValue: function(vm, obj, key) {
                return (obj[key].whitelist ? 'whitelist' : 'blacklist');
            },
            setAffiliationValue: function(vm, obj, key, value) {
                obj[key].whitelist = (value === 'whitelist');
            }
        },
        created() {
            console.log("I was just created");
            this.fetchCategories();
            this.fetchAuthor();
        }
    }
</script>

<style scoped>

    h1 {
        margin-top: 20px;
    }

    .author-meta-input p {
        font-size: 1.3em;
    }

    .text-input, .array-text-input, .array-select-input, .object-radio-select{
        margin-top: 20px;
        margin-bottom: 20px;
        font-size: 1.2em;
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
        margin-top: 10px;
        font-size: 1.8em;
        align-self: center;
        justify-self: center;
    }
</style>