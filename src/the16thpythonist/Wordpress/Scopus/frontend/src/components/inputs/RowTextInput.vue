<template>
    <div class="row-text-input">
        <label :for="id" class="title">
            {{ title }}
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
        name: "RowTextInput",
        props: {
            value: {
                type: String,
                required: true
            },
            title: {
                type: String,
                required: true,
            },
            id: {
                type: String,
                required: true
            },
            placeholder: {
                type: String,
                required: true,
                default: false
            },
        },
        methods: {
            /**
             * Emits the current value of the input field to the parent component as soon as the value of the text
             * input field has been modified.
             *
             * @return void
             */
            onInput: function() {
                this.$emit('input', this.$refs.inputField.value);
            }
        },
        computed: {
            classString: function() {

            }
        }
    }
</script>

<style scoped>

</style>