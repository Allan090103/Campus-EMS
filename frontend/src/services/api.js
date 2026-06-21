import axios from 'axios'

/**
 * Single Axios instance used for every API call.
 * baseURL points at the PHP Slim backend. The Authorization header
 * (Bearer token) is set by the auth store after login, and cleared
 * on logout — see stores/auth.js.
 */
const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: { 'Content-Type': 'application/json' },
})

export default api
