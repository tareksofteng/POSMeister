// Axios global defaults (legacy support — real instance lives in services/api.js)
import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
