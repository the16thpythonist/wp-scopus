<template>
    <div class="array-input">
        <!--
        Changed 11.10.2019
        Added the class "array-input-button" to both the remove and add button, so it easier to style both of
        them in the same way.
        -->
        <template v-for="index in Object.keys(associatedData)">
            <div class="array-text-input-element">
                <input v-on:input="onInput" class="array-text-input" type="text" :placeholder="associatedData[index]" v-model="associatedData[index]">
                <button v-on:click.prevent="onRemove(index)" class="array-input-button array-input-remove-button">-</button>
            </div>
        </template>
        <button v-on:click.prevent="onAdd" class="array-input-button array-input-add-button">+</button>
    </div>
</template>

<script>
    module.exports = {
        data: function () {
            // Computing an associative object with indices from the given array
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
            };
        },
        props:{
            array: {
                type:       Array,
                required:   true,
            }
        },
        methods: {
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
             */
            onAdd: function () {
                this.associatedData[this.currentIndex] = 'enter your value here';
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
             * @param index
             */
            onRemove: function (index) {
                delete this.associatedData[index];
                this.emitChange();
            },
            /**
             * Wrapper method to first force an update of all values and then emit the values array of the internal
             * associative array representation of all the input values to the "input" event to the parent component.
             *
             * CHANGELOG
             *
             * Added 24.02.2019
             */
            emitChange: function () {
                this.$forceUpdate();
                this.$emit('input', Object.values(this.associatedData));
            }
        }
    };
</script>

<style scoped>
    div.array-input {
        display: flex;
        flex-direction: column;
    }

    div.array-text-input-element {
        margin-bottom: 10px;
    }

    button.array-input-add-button {
        width: 20px;
    }
</style>