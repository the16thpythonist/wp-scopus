<template>
    <div class="array-input">
        <template v-for="index in Object.keys(associatedData)">
            <div class="array-select-input-element">
                <select v-on:change="onInput" v-model="associatedData[index]">
                    <template v-for="option in options">
                        <option v-if="option === associatedData[index]" selected>{{ option }}</option>
                        <option v-else>{{ option }}</option>
                    </template>
                </select>
                <button v-on:click.prevent="onRemove(index)">-</button>
            </div>
        </template>
        <button v-on:click.prevent="onAdd">add element</button>
    </div>
</template>

<script>
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
            emitChange: function () {
                this.$forceUpdate();
                this.$emit('input', Object.values(this.associatedData));
            },
            onInput: function () {
                this.emitChange();
            },
            onAdd: function () {
                this.associatedData[this.currentIndex] = this.options[0];
                this.currentIndex += 1;
                this.emitChange();
            },
            onRemove: function (index) {
                delete this.associatedData[index];
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