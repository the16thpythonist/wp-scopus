<template>
    <div :class="getClass(value.type)">
        <div class="text">
            {{ value.message }}
        </div>
    </div>
</template>

<script>
    export default {
        name: "StatusDiv",
        props: {
            value: {
                type:       Object,
                required:   false,
                default:    {
                    type:       '',
                    message:    ''
                }
            }
        },
        methods: {
            /**
             * Returns the CSS classes to be assigned to the status field based on the given "type" string
             *
             * CHANGELOG
             *
             * Added 14.05.2020
             *
             * @param type
             * @return {{success: boolean, warning: boolean, error: boolean, base: boolean, info: boolean}}
             */
            getClass: function (type) {
                return {
                    'base':     true,
                    'error':    type === 'error',
                    'warning':  type === 'warning',
                    'info':     type === 'info',
                    'success':  type === 'success'
                }
            }
        }
    }
</script>

<style scoped>

    .base {
        font-size: 1em;
        display: flex;
        padding: 10px;
        border-style: solid;
        border-width: 1px;
    }

    .text {
        text-align: center;
        padding: 0;
        margin-right: auto;
        margin-left: auto;
    }

    .warning {
        border-color: rgba(225,221,0,0.45);
        background-color: rgba(225,221,0,0.03);
    }

    .error {
        border-color: rgba(225,0,3,0.4);
        background-color: rgba(225,0,3,0.04);
    }

    .success {
        border-color: rgba(63,221,155,0.4);
        background-color: rgba(63,221,155,0.03);
    }
</style>