<template>
    <form @submit.prevent="noop">
        <div class="array-select-input">
            <span class="title">
                {{ title }}
            </span>
            <div class="array-element" v-for="(value, index) in data" :key="index">
                <select
                        v-model="data[index]"
                        class="select"
                        :ref="index"
                        @change="onInput">
                    <option v-for="(option, j) in options" :selected="option === data[index]" :key="j">
                        {{ option }}
                    </option>
                </select>
                <button
                        type="button"
                        class="remove-button"
                        @click.prevent="onRemove(index)">
                    Remove
                </button>
            </div>
            <button
                    type="button"
                    class="add-button"
                    @click.prevent="onAdd">
                Add Element
            </button>
        </div>
    </form>
</template>

<script>
    export default {
        name: "ArraySelectInput",
        props: {
            // This is the property which contains the actual array, which is subject to this component. This array can
            // be edited by the several select elements of this component
            value: {
                type:       Array,
                required:   true
            },
            // This array contains all the options, which are available to select from the select elements
            options: {
                type:       Array,
                required:   true
            },
            // This is a string, which is supposed to be one element of the "options" array. This element will be set
            // as the default choice, when a new element is inserted to the array and thus a new select is created
            default: {
                type:       String,
                required:   false,
                default:    ''
            },
            // This is the string title of the widget, which is being displayed above the actual select elements
            title: {
                type:       String,
                required:   false,
                default:    'Array Select Input:'
            }
        },
        data: function () {
            return {
                data: this.value,
                index: this.value.length
            }
        },
        methods: {
            /**
             * Function which is called whenever a change occurs in one of the select elements
             *
             * CHANGELOG
             *
             * Added 03.05.2020
             */
            onInput: function() {
                this.$emit('input', this.data);
                console.log(this.data);
            },
            /**
             * Function which is called by pressing a "remove" button associated with the given index
             *
             * CHANGELOG
             *
             * Added 03.05.2020
             *
             * @param index
             */
            onRemove: function(index) {
                this.$delete(this.data, index);
                this.index  -= 1;
                this.onInput();
            },
            /**
             * Function which is called when pressing the "add" button
             *
             * CHANGELOG
             *
             * Added 03.05.2020
             */
            onAdd: function() {
                this.$set(this.data, this.index, this.default);
                this.index += 1;
                this.onInput();
            }
        },
        /**
         * Function which is invoked, whenever the "value" property of this component changes.
         *
         * CHANGELOG
         *
         * Added 03.05.2020
         *
         * Changed 08.05.2020
         * Now also updating the internal "index" field with each update of the value property as that was causing a
         * bug when using the "add" button after an external change.
         */
        watch: {
            value: function(newValue) {
                this.data = newValue;
                this.index = newValue.length;
            }
        }
    }
</script>

<style scoped>
    span.title {
        color: #737373;
        padding-left: 0px;
        font-size: 0.85em;
    }

    div.array-select-input {
        display: flex;
        flex-direction: column;
    }

    div.array-element {
        width: 100%;
        margin-top: 2px;
        margin-bottom: 2px;
        display: flex;
        flex-direction: row;
    }

    select {
        font-size: 1em;
        width: available;
        margin-right: 10px;
        flex-grow: 2;
    }

    select:focus {
        box-shadow: 0 0 1px #3ECF8E;
        border-color: #3ECF8E;
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

    button.remove-button {
        flex-grow: 0;
    }

    button.add-button {
        padding: 5px 18px 5px 18px;
        margin-top: 5px;
        justify-self: center;
        align-self: center;
        font-size: 1.15em;
    }
</style>