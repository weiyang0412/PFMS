/**
 * @typedef {{ name: string; email: string }} User
 */
import { defineStore } from 'pinia'
import axiosInstance from '@/lib/axios'

export const useUserStore = defineStore('user', {
    state: () => ({
        /** @type {User | null} */
        user: null,
        isAuthenticated: false,
    }),

    actions: {
        async fetchUser() {
            try {
                const res = await axiosInstance.get('/user')
                this.user = res.data
                this.isAuthenticated = true
            } catch (error) {
                console.error(error)
                this.user = null
                this.isAuthenticated = false
            }
        },

        async logout() {
            try {
                await axiosInstance.post('/logout')
                this.user = null
                this.isAuthenticated = false
            } catch (error) {
                console.error(error)
            }
        },
    },
})
