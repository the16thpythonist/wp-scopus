<template>
    <div class="radio-group">
        <!-- We need the wrapper here, because if it was the radio buttons directly in the
        parent flex container, then the little circles would be stretched and distorted to fit into the
        available space... -->
        <span
                class="wrapper"
                v-for="(value, index) in options"
                :key="index">
            <input
                    v-model="choice"
                    type="radio"
                    :value="value"
                    :checked="value === choice"
                    :id="`${name}-${index}`"
                    :ref="index"
                    @keyup.right="moveRight(index)"
                    @keyup.left="moveLeft(index)"
                    @keyup.up.prevent="forwardEvent"
                    @keyup.down.prevent="forwardEvent"
                    @keydown.up.prevent="noop"
                    @keydown.down.prevent="noop">
        </span>
    </div>
</template>

<script>
    export default {
        name: "RadioGroup",
        props: {
            value: {
                type:       String,
                required:   true,
            },
            options: {
                type:       Array,
                required:   true
            },
            name: {
                type:       String,
                required:   false,
                default:    'radio-group'
            }
        },
        data: function () {
            return {
                choice: this.value,
                length: this.options.length
            }
        },
        methods: {
            /**
             * Moves the selection and the focus of the radio boxes one to the right relative to the given index
             *
             * CHANGELOG
             *
             * Added 05.05.2020
             *
             * @param index
             */
            moveRight: function (index) {
                let newIndex = index + 1;
                if (this.isIndexValid(newIndex)) {
                    let input = this.getInputByIndex(newIndex);
                    input.focus();
                    this.setValueByIndex(newIndex);
                }
            },
            /**
             * Moves the selection and the focus of the radio boxes one to the left relative to the given index
             *
             * CHANGELOG
             *
             * Added 05.05.2020
             *
             * @param index
             */
            moveLeft: function (index) {
                let newIndex = index - 1;
                if (this.isIndexValid(newIndex)) {
                    let input = this.getInputByIndex(newIndex);
                    input.focus();
                    this.setValueByIndex(newIndex);
                }
            },
            /**
             * Returns whether or not the given index is a valid index for the radio elements
             *
             * CHANGELOG
             *
             * Added 05.05.2020
             *
             * @param index
             * @return {boolean|boolean}
             */
            isIndexValid: function (index) {
                return (index >= 0 && index < this.length)
            },
            /**
             * Returns the radio input html element given its index within the array.
             *
             * CHANGELOG
             *
             * Added 05.05.2020
             *
             * @param index
             * @return {*|Vue|Element}
             */
            getInputByIndex: function (index) {
                return this.$refs[index][0];
            },
            /**
             * Sets a new value to the current choice based on the index of the radio input within the array
             *
             * CHANGELOG
             *
             * Added 05.05.2020
             *
             * @param index
             */
            setValueByIndex: function (index) {
                this.choice = this.options[index];
            },
            /**
             * Given an event, this method will emit the event, so that the parent component can handle it
             *
             * CHANGELOG
             *
             * Added 05.05.2020
             *
             * @param event
             */
            forwardEvent: function (event) {
                this.$emit(event.type, event);
            },
            /**
             * Returns the index of the current choice based on the state variable "choice"
             *
             * CHANGELOG
             *
             * Added 05.05.2020
             */
            getCurrentIndex: function () {
                let index = this.options.indexOf(this.choice);
                return (index === -1 ? 0 : index);
            },
            /**
             * This function sets the focus onto the radio input, which is currently selected
             *
             * CHANGELOG
             *
             * Added 05.05.2020
             */
            focus: function () {
                let index = this.getCurrentIndex();
                let input = this.getInputByIndex(index);
                input.focus();
            }
        },
        watch: {
            /**
             * Gets called every time the "value" property changes.
             *
             * This method will set the internal data attribute "choice" to the new value given to the
             * property.
             *
             * CHANGELOG
             *
             * Added 05.05.2020
             *
             * @param newValue
             */
            value: function (newValue) {
                this.choice = newValue;
            },
            /**
             * Gets called every time the data field "choice" changes
             *
             * This method will emit an "input" event to the parent component, whenever the internal state
             * "choice" changes
             *
             * CHANGELOG
             *
             * Added 05.05.2020
             *
             * @param value
             */
            choice: function (value) {
                this.$emit('input', value);
            }
        }
    }
</script>

<style scoped>

    .radio-group {
        display: flex;
        flex-direction: row;
    }

    /**
    Setting flex-basis to 0 and flex-grow to 1 for all the elements within a flex container will cause them to split
    the available space absolutely equally
     */
    .wrapper {
        flex-basis: 0;
        flex-grow: 1;
    }

    input[type="radoi"]:after {
        color: #3ECF8E;
        background-color: #3ECF8E;
    }

    input[type="radio"]:focus {
        box-shadow: 0 0 1px #3ECF8E;
        border-color: #3ECF8E;
    }

</style>