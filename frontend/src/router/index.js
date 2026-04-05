import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import Register from '../views/auth/Register.vue'
import Login from '../views/auth/Login.vue'
import Dashboard from '../views/auth/Dashboard.vue'
import Transactions from '../views/Transactions.vue'

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        {
            path: '/',
            name: 'home',
            component: HomeView,
        },
        {
            path: "/register",
            name: "register",
            component: Register,
            meta: { noSidebar: true },
        },
        {
            path: "/login",
            name: "login",
            component: Login,
            meta: { noSidebar: true },
        },
        {
            path: "/dashboard",
            name: "dashboard",
            component: Dashboard,
        },
        {
            path: "/transactions",
            name: "transactions",
            component: Transactions,
        },
    ],
})

export default router
