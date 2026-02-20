import { fetcher } from 'itty-fetcher'

export interface FetchedData {
  installerVersion: string
  minPhpVersion: string
  phpVersion: string
  phpFullVersion: string
  phpIniPath: string
  path: string
  file: string
  htaccess: boolean
  webConfig: boolean
  requirements: Record<string, boolean>
  compatible: boolean
  extracted?: boolean
  windows?: boolean
}

const currentUrl = new URL(window.location.href)
const baseUrl = `${currentUrl.origin}${currentUrl.pathname}`

const client = fetcher({
  base: baseUrl,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
  },
  transformRequest(request) {
    const url = new URL(request.url, baseUrl)
    url.searchParams.set('execute', 'php')

    return { ...request, url: url.toString() }
  },
})

export function baseFetch(): Promise<FetchedData> {
  return client.get('')
}

export function download(): Promise<void> {
  return client.post('', {
    action: 'download',
  })
}
