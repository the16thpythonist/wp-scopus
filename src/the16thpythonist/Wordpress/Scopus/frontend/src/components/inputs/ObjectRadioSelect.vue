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
                <span v-for="option in options" :key="option">
                    {{ option }}
                </span>
            </div>
        </div>
        <div
                class="row"
                v-for="(key, index) in Object.keys(data)"
                :key="key">
            <span class="row-label">
                {{ nameFunc(value, key) }}
            </span>
            <RadioGroup
                    class="row-options"
                    :value="get(key)"
                    @input="set(key, $event)"
                    :options="options"
                    :ref="index"
                    @keyup.up.prevent="moveUp(index)"
                    @keyup.down.prevent="moveDown(index)">
            </RadioGroup>
        </div>
    </div>
</template>

<script>
    /* eslint-disable */
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
            },
            // This seems to work
            nameFunc: {
                type:       Function,
                required:   false,
                default:    function (obj, key) {
                    return key;
                }
            },
            setFunc: {
                type:       Function,
                required:   false,
                default:    function (vm, obj, key, value) {
                    vm.$set(obj, key, value);
                }
            },
            getFunc: {
                type:       Function,
                required:   false,
                default:    function (vm, obj, key) {
                    return obj[key];
                }
            }
        },
        data: function () {
            return {
                data: {},
                length: Object.keys(this.value).length
            }
        },
        methods: {
            /**
             * Returns the current selection choice for the row identified by the given key.
             *
             * CHANGELOG
             *
             * Added 07.05.2020
             *
             * @param    {String}   key     The key, which identifies the row and which is also the key for the object
             *                              "data", which stores the internal state of the component
             */
            get: function (key) {
                return this.getFunc(this, this.data, key);
            },
            /**
             * Sets a new selection value to the row, which is identified by the given key
             *
             * CHANGELOG
             *
             * Added 07.05.2020
             *
             * @param   {String}    key     The key, which identifies the row that is to be modified
             * @param   {String}    value   The new value to be set as the selection value of the row in question
             *                              This has to be one of the values defined in the "options" array
             */
            set: function(key, value) {
                this.setFunc(this, this.data, key, value);
                this.emitInput();
            },
            /**
             * Gets called, whenever one of the radio group input elements changes
             *
             * CHANGELOG
             *
             * Added 05.05.2020
             *
             * Changed 07.05.2020
             * Moved the actual input emitting to its own function "emitInput", as that very same functionality was
             * now also needed at some other place in the code.
             */
            onInput: function () {
                this.emitInput();

            },
            /**
             * Emits an "input" event to the parent component, which contains the current value of the internal data
             * object as the parameter.
             *
             * CHANGELOG
             *
             * Added 07.05.2020
             */
            emitInput: function() {
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
             * @param   index
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
            },
            /**
             * Fills the internal "data" variable with the information provided within the given "obj"
             *
             * This method also supports more complex object structures, as it uses the functions "getFunc" and
             * "setFunc" given as properties to the component to access both the given object and the internal "data"
             * object.
             * In case the "obj" value to be set to the data object is a value, which is not contained within the
             * internal "options" array, the default value (as given by the "default" property) will be set instead.
             *
             * CHANGELOG
             *
             * Added 07.05.2020
             *
             * Changed 08.05.2020
             * At the end I added a new assignment for the internal "length" parameter. If this was not there it would
             * be the bug, that the arrow key navigation would still "see" the total valid length of the object from
             * before thus considering new elements invalid indices to move to
             *
             * @param   {Object}  obj   The object, whose values will be used to overwrite the current values of the
             *                          internal state object "data"
             */
            fillData: function(obj) {
                this.data = {...obj};
                for (let key in obj) {
                    if (obj.hasOwnProperty(key)) {
                        let value = this.getFunc(this, obj, key);
                        if (this.options.includes(value)) {
                            this.setFunc(this, this.data, key, value);
                        } else {
                            this.setFunc(this, this.data, key, this.default);
                        }
                    }
                }
                this.length = Object.keys(this.data).length;
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
             * Changed 07.05.2020
             * Moved the logic of the process to the method "fillData" and now just calling this
             * method here
             *
             * Changed 08.05.2020
             * Had to add the additional
             *
             * @param obj
             */
            value: function (obj) {
                this.fillData(obj);
            }
        },
        /**
         * This method gets called once, after the component has been created
         *
         * CHANGELOG
         *
         * Added 07.05.2020
         */
        created: function () {
            this.fillData(this.value);
            this.emitInput();
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