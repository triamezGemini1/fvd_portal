import '../css/app.css';
import { createApp } from 'vue';
import DelegadoDashboard from './Components/Delegado/DelegadoDashboard.vue';

const el = document.getElementById('fvd-delegado-app');
if (el) {
  let initial = {};
  const raw = el.getAttribute('data-initial-state');
  if (raw) {
    try {
      initial = JSON.parse(raw);
    } catch {
      initial = {};
    }
  }
  createApp(DelegadoDashboard, { initialState: initial }).mount(el);
}
