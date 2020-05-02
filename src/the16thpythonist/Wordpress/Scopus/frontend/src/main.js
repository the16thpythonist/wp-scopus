import Vue from 'vue'
import AuthorMeta from "./components/author/AuthorMeta";

Vue.config.productionTip = true;

new Vue({
  render: h => h(AuthorMeta),
}).$mount('#author-meta-input');