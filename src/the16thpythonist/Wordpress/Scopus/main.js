let Vue = require( 'vue/dist/vue.js' );
let BootstrapVue = require(  'bootstrap-vue' );

// The different components
let scopus_options = require( './components/scopus-options.vue' );
let author_input = require('./components/author-input.vue');

Vue.use(BootstrapVue);

new Vue( {
    el: '#scopus-options-main',
    components: {
        ScopusOptions: scopus_options
    }
});

new Vue({
   el: '#scopus-author-input',
   components: {
       AuthorInput: author_input
   }
});

