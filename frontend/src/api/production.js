import { request } from './client'

export function quoteConfiguration(payload) {
  return request('/production/quote', { method: 'POST', body: payload })
}
