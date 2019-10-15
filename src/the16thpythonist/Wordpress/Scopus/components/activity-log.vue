<template>
    <div class="activity-log-container">
        <strong>Activity Log</strong>
        <div class="activity-log">
            <template v-for="message in messages">
                <p>{{ message }}</p>
            </template>
        </div>
    </div>
</template>

<script>
    let Vue = require( 'vue/dist/vue.js' );

    module.exports = {
        name: "ActivityLog",
        data: function () {
            return {

            }
        },
        props: {
            messages: {
                type:       Array,
                required:   true
            },
            logBus: {
                type:       Vue,
                required:   true
            }
        },
        methods: {
            onLogActivity: function(message) {
                this.messages.push(message);
                this.$forceUpdate();
            }
        },
        created: function () {
            this.logBus.$on('logActivity', this.onLogActivity);
        }
    }
</script>

<style scoped>

</style>