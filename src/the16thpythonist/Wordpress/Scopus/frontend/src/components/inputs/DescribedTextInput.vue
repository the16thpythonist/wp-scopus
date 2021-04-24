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
    /* eslint-disable */

    // V-MODEL COMPATIBILITY
    // Generally a nice feature for a input component is if the higher level component can use the "v-model" directive
    // with it. The v-model directive binds the the "value" property of a component to a variable. Specifically it is
    // a two way binding. In the case of an input compnent: If the input components value changes through modification
    // due to user interaction, this changes value will also be reflected in the bound variable. But it goes the other
    // way around as well: If the variables values is changed by the code, the visible value of the component (input)
    // changes as well.
    // This feature can also be supported by custom components like this one: The first direction of the binding is
    // rather easy. At first you have to define a property named "value" and this value has to be bound to the value of
    // the input component using the v-bind directive. But this way it will only support changes coming from the code.
    // To also pass changes made to the input by the user, you have to bind the "on input" event of the input field to
    // make the custom component also emit "input" with the corresponding changed value.
    // That is how "v-model" is actuall implemented. It is more or less just a short hand for a seperate v-bind and
    // on-input reaction.

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
             * @return void
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