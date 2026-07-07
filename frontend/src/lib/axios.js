import axios from 'axios';

const apiBaseUrl = import.meta.env.VITE_API_URL;

const axiosInstance = axios.create({
    baseURL: apiBaseUrl,
    withCredentials: true,
    withXSRFToken: true,
});

export default axiosInstance;
