<template>
    <div class="described-text-input">
        <label :for="id" class="title">
            {{ title }}:
        </label>
        <input
                :value="value"
                class="input"
                type="text"
                ref="inputField"
                @input="onInput"
                :id="id"
                :name="id"
                :placeholder="placeholder">
    </div>
</template>

<script>
    export default {
        name: "DescribedTextInput",
        props: {
            value: {
                type:       String,
                required:   true
            },
            title: {
                type:       String,
                required:   true,
            },
            id: {
                type:       String,
                required:   true
            },
            placeholder: {
                type:       String,
                required:   false,
                default:    ''
            }
        },
        methods: {
            /**
             * Emits the current value of the inputField as the "input" event to the parent component
             *
             * CHANGELOG
             *
             * Added 02.05.2020
             */
            onInput: function() {
                this.$emit('input', this.$refs.inputField.value);
            }
        }
    }
</script>

<style scoped>
    .described-text-input {
        display: flex;
        flex-direction: column;
    }

    .title {
        color: #737373;
        padding-left: 0px;
        font-size: 0.85em;
    }

    .input {
        width: 100%;
        padding-left: 0px;
        padding-top: 1px;
        margin: 0px;
        border-style: none;
        border-bottom-style: solid;
        border-bottom-width: 2px;
        box-shadow: none;
        font-size: 1.05em;
    }

    .input::placeholder {
        font-size: 1.05em;
        color: #696969;
    }

    .input:focus {
        box-shadow: none;
        border-color: #3ECF8E;
        transition: 0.5s border-color ease-in-out;
    }
</style>