import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import Register from '../views/auth/Register.vue'
import Login from '../views/auth/Login.vue'
import Dashboard from '../views/auth/Dashboard.vue'
import Accounts from '../views/Accounts.vue'
import Transactions from '../views/Transactions.vue'
import TransactionOptions from '../views/TransactionOptions.vue'
import SettingsProfile from '../views/SettingsProfile.vue'
import SettingsSemesters from '../views/SettingsSemesters.vue'
import Budgets from '../views/Budgets.vue'
import FinancialTrend from '../views/FinancialTrend.vue'
import FinancialSummary from '../views/FinancialSummary.vue'
import { useUserStore } from '../stores/userStore'

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        {
            path: '/',
            name: 'home',
            component: HomeView,
            meta: { requiresAuth: true },
        },
        {
            path: "/register",
            name: "register",
            component: Register,
            meta: { noSidebar: true, guestOnly: true },
        },
        {
            path: "/login",
            name: "login",
            component: Login,
            meta: { noSidebar: true, guestOnly: true },
        },
        {
            path: "/dashboard",
            name: "dashboard",
            component: Dashboard,
            meta: { requiresAuth: true },
        },
        {
            path: "/transactions",
            name: "transactions",
            component: Transactions,
            meta: { requiresAuth: true },
        },
        {
            path: "/accounts",
            name: "accounts",
            component: Accounts,
            meta: { requiresAuth: true },
        },
        {
            path: "/settings/profile",
            name: "settings-profile",
            component: SettingsProfile,
            meta: { requiresAuth: true },
        },
        {
            path: "/settings/semesters",
            name: "settings-semesters",
            component: SettingsSemesters,
            meta: { requiresAuth: true },
        },
        {
            path: "/settings/options",
            name: "settings-options",
            component: TransactionOptions,
            meta: { requiresAuth: true },
        },
        {
            path: "/transaction-options",
            redirect: "/settings/options",
        },
        {
            path: "/budgets",
            name: "budgets",
            component: Budgets,
            meta: { requiresAuth: true },
        },
        {
            path: "/financial-trend",
            name: "financial-trend",
            component: FinancialTrend,
            meta: { requiresAuth: true },
        },
        {
            path: "/financial-summary",
            name: "financial-summary",
            component: FinancialSummary,
            meta: { requiresAuth: true },
        },
    ],
})

router.beforeEach(async (to) => {
    const userStore = useUserStore()

    await userStore.ensureAuthChecked()

    if (to.meta.requiresAuth && !userStore.isAuthenticated) {
        return { name: 'login' }
    }

    if (to.meta.guestOnly && userStore.isAuthenticated) {
        return { name: 'dashboard' }
    }
})

export default router
