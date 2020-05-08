<template>
    <form @submit.prevent="noop">
        <div class="array-text-input">
            <span class="title">
                {{ title }}
            </span>
            <div class="array-element" v-for="(value, index) in data" :key="index">
                <input
                        v-model="data[index]"
                        class="text-input"
                        type="text"
                        :ref="index"
                        :placeholder="placeholder"
                        @keyup.enter="onEnter(index)"
                        @keyup.up="focusPreviousElement(index)"
                        @keyup.down="focusNextElement(index)"
                        @input="onInput">
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
        name: "ArrayTextInput",
        props: {
            // This is the property which contains the actual array, which subject to this component. The
            // array which can be edited by the several input fields.
            value: {
                type:       Array,
                required:   true
            },
            // The title will be displayed in a span above the actual inputs
            title: {
                type:       String,
                required:   false,
                default:    'Array text input:',
            },
            // This is the string, which will be used as the placeholder within the input fields, when they
            // have been added and are still empty
            placeholder: {
                type:       String,
                required:   false,
                default:    "enter data"
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
             * Gets called whenever any one of the input fields of this component are being edited
             *
             * CHANGELOG
             *
             * Added 02.05.2020
             */
            onInput: function () {
                this.$emit('input', this.data);
            },
            /**
             * Gets called whenever any one of the "remove" buttons is pressed
             *
             * CHANGELOG
             *
             * Added 02.05.2020
             *
             * @param index
             */
            onRemove: function(index) {
                this.$delete(this.data, index);
                this.index -= 1;
                this.onInput();
            },
            /**
             * Gets called when the "add" button of the components is pressed.
             *
             * CHANGELOG
             *
             * Added 02.05.2020
             */
            onAdd: function () {
                this.$set(this.data, this.index, '');
                this.index += 1;
                this.onInput();
            },
            /**
             * Gets called whenever the enter key is pressed within one of the input fields
             *
             * CHANGELOG
             *
             * Added 02.05.2020
             */
            onEnter: function (index) {
                if (index === this.index - 1) {
                    this.onAdd();
                }
                this.focusNextElement(index);
            },
            /**
             * Changes the focus of the page to the index after the given one
             *
             * CHANGELOG
             *
             * Added 02.05.2020
             */
            focusNextElement: function (index) {
                if (index !== this.index - 1) {
                    this.$nextTick(function () {
                        this.$refs['' + (index + 1)][0].focus();
                    })
                }
            },
            /**
             * Changes the focus of the page to the index before the given one
             *
             * CHANGELOG
             *
             * Added 02.05.2020
             */
            focusPreviousElement: function (index) {
                if (index !== 0) {
                    this.$nextTick(function () {
                        this.$refs['' + (index - 1)][0].focus();
                    })
                }
            }
        },
        watch: {
            /**
             * This method gets called every time there is a change to the "value" property of this component.
             *
             * The value property is the property which is used by the parent component to feed the actual array
             * structure into this component. This methods updates the internal "data" attribute with the new value
             * externally set to the value property
             *
             * CHANGELOG
             *
             * Added 02.05.2020
             *
             * Changed 08.05.2020
             * Now also updating the internal "index" field with each update of the value property as that was causing a
             * bug when using the "add" button after an external change.
             */
            value: function (newValue) {
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

    div.array-text-input {
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

    input.text-input {
        font-size: 1em;
        width: available;
        flex-grow: 2;
        margin-right: 10px;
    }

    input.text-input:focus {
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