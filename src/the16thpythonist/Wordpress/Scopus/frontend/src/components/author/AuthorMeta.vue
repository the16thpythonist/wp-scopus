<template>
    <div class="author-meta-input">
        <!-- Maybe displaying statistical information -->
        <h1>Statistical Information</h1>

        <p>Nothing here yet</p>
        <!-- The simple inputs for the authors name -->
        <h1>Edit Author Properties</h1>
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

        <!-- The affiliation input -->
        <h1>Author Affiliations</h1>

        <!-- Saving the changes -->
        <!-- Maybe have a red display that says changes have been made and they have to be saved -->
    </div>
</template>

<script>
    import DescribedTextInput from "../inputs/DescribedTextInput";

    export default {
        name: "AuthorMeta",
        components: {
            DescribedTextInput
        },
        data: function () {
            return {
                firstName: '',
                lastName: ''
            }
        }
    }
</script>

<style scoped>
    .author-meta-input p {
        font-size: 1.5em;
    }

    .text-input {
        margin-top: 15px;
        font-size: 1.2em;
    }
</style>