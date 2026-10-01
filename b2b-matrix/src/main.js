import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import OrderListPage from './components/OrderListPage.vue';
import './styles/main.css';

function mountB2BApps() {
  // 1. Single Product Matrix App
  let matrixContainers = [...document.querySelectorAll('.b2b-vue-matrix-container, #b2b-vue-matrix-container')];

  // Fallback για τοπικό Vite development (#app)
  if (!matrixContainers.length && import.meta.env.DEV) {
    const fallback = document.getElementById('app');
    if (fallback) matrixContainers = [fallback];
  }

  for (const container of matrixContainers) {
    const app = createApp(App, { container });
    const pinia = createPinia();

    app.use(pinia);
    app.mount(container);
  }

  // 2. Custom Order List / Cart Page App
  const listContainers = [...document.querySelectorAll('.b2b-order-list-container, #b2b-order-list-app')];
  for (const container of listContainers) {
    const app = createApp(OrderListPage, { container });
    const pinia = createPinia();

    app.use(pinia);
    app.mount(container);
  }
}

// Ασφαλής εκκίνηση είτε το DOM έχει φορτώσει ήδη είτε περιμένουμε το DOMContentLoaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mountB2BApps);
} else {
  mountB2BApps();
}
