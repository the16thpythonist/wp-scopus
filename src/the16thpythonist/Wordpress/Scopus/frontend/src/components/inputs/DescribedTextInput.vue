<template>
    <div class="described-text-input">
        <span class="title">
            {{ title }}:
        </span>
        <input
                class="input"
                type="text"
                :id="id"
                :name="id"
                :placeholder="placeholder">
    </div>
</template>

<script>
    export default {
        name: "DescribedTextInput",
        props: {
            value: String,
            title: String,
            id: String,
            placeholder: {
                default: '',
                type: String
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