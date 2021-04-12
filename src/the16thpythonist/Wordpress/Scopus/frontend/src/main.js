/* eslint-disable */
import Vue from 'vue'
import AuthorMeta from "./components/author/AuthorMeta";

Vue.config.productionTip = true;

/*
new Vue({
  render: h => h(AuthorMeta),
}).$mount('#author-meta-input');
 */

var components = {
  'author-meta-component': AuthorMeta
}

// Mounting the components only if an element with the corresponding ID actually exists
for (let id in components) {
  let component = components[id];

  let element = document.getElementById(id);
  if (element) {
    new Vue({
      render: h => h(component)
    }).$mount('#' + id)
  }
}