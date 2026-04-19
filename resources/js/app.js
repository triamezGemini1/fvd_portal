import '../css/app.css';
import { createApp } from 'vue';
import MasterPanel from './Components/Admin/MasterPanel.vue';

const el = document.getElementById('fvd-master-app');
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
  createApp(MasterPanel, { initialState: initial }).mount(el);
}
