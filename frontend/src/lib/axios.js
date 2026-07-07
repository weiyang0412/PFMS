import axios from 'axios';

const apiBaseUrl = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
const apiRootUrl = import.meta.env.VITE_API_ROOT_URL || 'http://localhost:8000';

const axiosInstance = axios.create({
    baseURL: apiBaseUrl,
    withCredentials: true,
    withXSRFToken: true,
});

export { apiBaseUrl, apiRootUrl };
export default axiosInstance;
