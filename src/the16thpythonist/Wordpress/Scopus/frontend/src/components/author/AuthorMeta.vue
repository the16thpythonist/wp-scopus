<template>
    <div class="author-meta-input">
        <!-- Maybe displaying statistical information -->
        <h1>Statistical Information</h1>

        <p>Nothing here yet</p>
        <!-- The simple inputs for the authors name -->
        <h1>Edit Author Properties</h1>
        <p>
            Edit the authors personal information, the ScopusID's and the categories associated
            with him:
        </p>

        <DescribedTextInput
                class="text-input"
                v-model="firstName"
                id="author-first-name"
                placeholder="Maximilian"
                title="First Name">
        </DescribedTextInput>

        <DescribedTextInput
                class="text-input"
                v-model="lastName"
                id="author-last-name"
                placeholder="Mustermann"
                title="Last Name">
        </DescribedTextInput>
        <!-- The complex input for the ids and the categories -->

        <ArrayTextInput
                class="array-text-input"
                v-model="scopusIDs"
                title="ScopusID's: ">
        </ArrayTextInput>

        <ArraySelectInput
                class="array-select-input"
                v-model="categories"
                :options="options"
                title="Categories: "
                :default="defaultCategory">
        </ArraySelectInput>

        <!-- The affiliation input -->
        <h1>Author Affiliations</h1>

        <ObjectRadioSelect
                class="object-radio-select"
                v-model="affiliations"
                :options="affiliationOptions"
                :default="affiliationOptions[0]"
                title="Authors Affiliations"
                label="Affiliations:">
        </ObjectRadioSelect>
        <button type="button" @click.prevent="test"></button>
        <!-- Saving the changes -->
        <!-- Maybe have a red display that says changes have been made and they have to be saved -->
    </div>
</template>

<script>
    import DescribedTextInput from "../inputs/DescribedTextInput";
    import ArrayTextInput from "../inputs/ArrayTextInput";
    import ArraySelectInput from "../inputs/ArraySelectInput";
    import RadioGroup from "../inputs/RadioGroup";
    import ObjectRadioSelect from "../inputs/ObjectRadioSelect";

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
                firstName: '',
                lastName: '',
                scopusIDs: ["12", "10"],
                options: ["cells", "microbes", "plants", "birds"],
                categories: ["cells", "microbes", ""],
                defaultCategory: "cells",
                affiliations: {
                    KIT:    '',
                    IMP:    '',
                    HSOG:   ''
                },
                affiliationOptions: ['unset', 'blacklist', 'whitelist']
            }
        },
        methods: {
            test: function () {
                console.log('hello');
                this.$set(this.affiliations, 'IPE', 'unset');
            }
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
</style>