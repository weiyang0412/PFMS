import axios from 'axios';

const apiBaseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
const apiRootUrl = import.meta.env.VITE_API_ROOT_URL || 'http://localhost:8000';

const axiosInstance = axios.create({
    baseURL: apiBaseUrl,
    withCredentials: true,
    withXSRFToken: true,
    xsrfCookieName: 'XSRF-TOKEN',
    xsrfHeaderName: 'X-XSRF-TOKEN',
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

function getCookie(name) {
    if (typeof document === 'undefined') {
        return '';
    }

    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);

    if (parts.length !== 2) {
        return '';
    }

    return decodeURIComponent(parts.pop().split(';').shift());
}

axiosInstance.interceptors.request.use((config) => {
    const xsrfToken = getCookie('XSRF-TOKEN');

    if (xsrfToken) {
        config.headers = config.headers ?? {};
        config.headers['X-XSRF-TOKEN'] = xsrfToken;
    }

    return config;
});

export async function refreshCsrfCookie() {
    await axiosInstance.get('/sanctum/csrf-cookie', {
        baseURL: apiRootUrl,
        withCredentials: true,
    });
}

export { apiBaseUrl, apiRootUrl };
export default axiosInstance;
