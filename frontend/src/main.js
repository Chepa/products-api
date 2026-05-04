import { createApp } from 'vue';
import App from './App.vue';
import './panel.css';

const root = document.getElementById('app');
if (!root) {
  throw new Error('#app not found');
}
const apiBase = root.dataset.apiBase || '/api/v1';
createApp(App, { apiBase }).mount('#app');
