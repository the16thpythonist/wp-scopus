<template>
    <div class="array-input">
        <!--
        Changed 11.10.2019
        Added the class "array-input-button" to both the remove and add button, so it easier to style both of
        them in the same way.
        -->
        <template v-for="index in Object.keys(associatedData)">
            <div class="array-select-input-element">
                <select v-on:change="onInput" v-model="associatedData[index]">
                    <template v-for="option in options">
                        <option v-if="option === associatedData[index]" selected>{{ option }}</option>
                        <option v-else>{{ option }}</option>
                    </template>
                </select>
                <button v-on:click.prevent="onRemove(index)" class="array-input-button array-input-remove-button">-</button>
            </div>
        </template>
        <button v-on:click.prevent="onAdd" class="array-input-button array-input-add-button">+</button>
    </div>
</template>

<script>
    // Changed 17.10.2019
    // Imported Vue due to the need for calling the Vue.set() and Vue.delete() methods.
    let Vue = require('vue/dist/vue.js');

    module.exports = {
        name: 'array-select-input',
        props: {
            array: {
                type:       Array,
                required:   true
            },
            options: {
                type:       Array,
                required:   true
            }
        },
        data: function () {
            let associatedData = {};
            let currentIndex = 1;
            this.array.forEach(function (value, index) {
                associatedData[index] = value;
                currentIndex += 1;
            });
            this.$emit('input', Object.values(associatedData));

            return {
                associatedData: associatedData,
                currentIndex: currentIndex
            }
        },
        methods: {
            /**
             * Wrapper method to first force an update of all values and then emit the values array of the internal
             * associative array representation of all the input values to the "input" event to the parent component.
             *
             * CHANGELOG
             *
             * Added 24.02.2019
             *
             * Changed 17.10.2019
             * Removed the call to $forceUpdate(), because the v-for display updates with the use of Vue.set() and
             * Vue.delete() just fine.
             */
            emitChange: function () {
                // this.$forceUpdate();
                this.$emit('input', Object.values(this.associatedData));
            },
            /**
             * This is the event handler for the "input" event for all the text inputs, it will simply emit the "input"
             * event to the parent component containing the current state of the array with all the input texts.
             *
             * CHANGELOG
             *
             * Added 24.02.2019
             */
            onInput: function () {
                this.emitChange();
            },
            /**
             * This is the "click" event handler for the button at the bottom. When clicking it  a new text intput
             * will be added to the array.
             *
             * CHANGELOG
             *
             * Added 24.02.2019
             *
             * Changed 17.10.2019
             * Instead of relying on the $forceUpdate function, now using the Vue.set() function to make the v-for
             * display update.
             * Also the input does no longer contain a value at the start, as it is quite annoying to delete that every
             * time before entering a new one.
             */
            onAdd: function () {
                Vue.set(this.associatedData, this.currentIndex, this.options[0]);
                this.currentIndex += 1;
                this.emitChange();
            },
            /**
             * The "click" event handler for the - buttons at the end of each text input. The index of the how many-th
             * input it is has to be passed, so that this element can be removed from the internal array, which will
             * then of course update the view as well.
             *
             * CHANGELOG
             *
             * Added 24.02.2019
             *
             * Changed 17.10.2019
             * Instead relying on the $forceUpdate function, using the Vue.delete() function to update the v-for
             * display
             *
             * @param index
             */
            onRemove: function (index) {
                //delete this.associatedData[index];
                Vue.delete(this.associatedData, index);
                this.emitChange();
            }
        }
    }
</script>

<style scoped>
    div.array-input {
        display: flex;
        flex-direction: column;
    }

    div.array-select-input-element {
        margin-bottom: 10px;
    }
</style>