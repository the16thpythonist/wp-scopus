<template>
    <div class="object-radio-select">
        <span class="title" v-if="title !== ''">
            {{ title }}:
        </span>
        <div class="heading">
            <span class="heading-label">
                {{ label }}
            </span>
            <div class="heading-options">
                <span v-for="option in options">
                    {{ option }}
                </span>
            </div>
        </div>
        <div
                class="row"
                v-for="(key, index) in Object.keys(data)">
            <span class="row-label">
                {{ key }}
            </span>
            <RadioGroup
                    class="row-options"
                    v-model="data[key]"
                    :options="options"
                    :ref="index"
                    @input="onInput"
                    @keyup.up.prevent="moveUp(index)"
                    @keyup.down.prevent="moveDown(index)">
            </RadioGroup>
        </div>
    </div>
</template>

<script>
    import RadioGroup from "./RadioGroup";

    

    export default {
        name: "ObjectRadioSelect",
        components: {RadioGroup},
        props: {
            value: {
                type:       Object,
                required:   true
            },
            options: {
                type:       Array,
                required:   true
            },
            // The label to be displayed in the heading in the column, where all the keys of the object
            // will be listed, thus this label will explain, which kind of data the selection is to be made for
            label: {
                type:       String,
                required:   false,
                default:    'Elements:'
            },
            title: {
                type:       String,
                required:   false,
                default:    ''
            },
            default: {
                type:       String,
                required:   false,
                default:    ''
            }
        },
        data: function () {

            let result = {};
            for (let prop in this.value) {
                if(this.value.hasOwnProperty(prop)) {
                    if (this.options.includes(this.value[prop])) {
                        result[prop] = this.value[prop];
                    } else {
                        result[prop] = this.default;
                    }
                }
            }

            return {
                data: result,
                length: Object.keys(result).length
            }
        },
        methods: {
            /**
             * Gets called, whenever one of the radio group input elements changes
             *
             * CHANGELOG
             *
             * Added 05.05.2020
             */
            onInput: function () {
                this.$emit('input', this.data);
            },
            /**
             * Moves the focus onto the radio group above relative to the given index, if possible
             *
             * CHANGELOG
             *
             * Added 05.05.2020
             *
             * @param index
             */
            moveUp: function (index) {
                let newIndex = index - 1;
                if (this.isIndexValid(newIndex)) {
                    let input = this.getInputByIndex(newIndex);
                    input.focus();
                }
            },
            /**
             * Moves the focus onto the radio group below relative to the given index, if possible
             *
             * CHANGELOG
             *
             * Added 05.05.2020
             *
             * @param index
             */
            moveDown: function (index) {
                let newIndex = index + 1;
                if (this.isIndexValid(newIndex)) {
                    let input = this.getInputByIndex(newIndex);
                    input.focus();
                }
            },
            /**
             * Returns whether or not the given index is a valid index of an input element.
             *
             * CHANGELOG
             *
             * Added 05.05.2020
             *
             * @param index
             * @return {boolean|boolean}
             */
            isIndexValid: function (index) {
                return (index >= 0 && index < this.length);
            },
            /**
             * Returns the value of the current selection for the radio group with the given index
             *
             * CHANGELOG
             *
             * Added 05.05.2020
             *
             * @param index
             * @return {unknown}
             */
            getValueByIndex: function (index) {
                return Object.values(this.data)[index];
            },
            /**
             * Returns the key within the "data" object for the given index
             *
             * CHANGELOG
             *
             * Added 05.05.2020
             *
             * @param index
             * @return {string}
             */
            getKeyByIndex: function (index) {
                return Object.keys(this.data)[index];
            },
            /**
             * Returns the radio group vue component with the given index
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
            }
        },
        watch: {
            /**
             * Gets called every time the "value" property changes.
             *
             * CHANGELOG
             *
             * Added 05.05.2020
             *
             * @param obj
             */
            value: function (obj) {
                for (let prop in obj) {
                    if (obj.hasOwnProperty(prop)){
                        if (this.options.includes(obj[prop])) {
                            this.data[prop] = obj[prop];
                        } else {
                            this.data[prop] = this.default;
                        }
                    }
                }
            }
        }
    }
</script>

<style scoped>

    span.title {
        color: #737373;
        padding-left: 0px;
        font-size: 0.85em;
        padding-bottom: 5px;
    }

    .object-radio-select {
        font-size: 1em;
        display: flex;
        flex-direction: column;
    }

    .row, .heading{
        display: flex;
        flex-direction: row;
    }

    .row-label, .heading-label {
        padding-left: 10px;
        width: 30%;
    }

    .row-options, .heading-options {
        width: 70%;
    }

    .heading {
        font-size: 1.13em;
        background-color: #e6e6e6;
    }

    .heading-options {
        display: flex;
        flex-direction: row;
    }

    .heading-options>span {
        flex-basis: 0;
        flex-grow: 1;
    }

    .row-label, .row-options, .heading-options, .heading-label {
        padding-top: 5px;
        padding-bottom: 5px;

        border-style: solid;
        border-width: 0px;
        border-bottom-width: 1px;
        border-bottom-color: #e6e6e6;
    }
</style>