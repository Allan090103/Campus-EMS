import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import './assets/main.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)

// Re-attach the Authorization header if a token survived (PERSIST mode).
useAuthStore().restore()

app.use(router)
app.mount('#app')
