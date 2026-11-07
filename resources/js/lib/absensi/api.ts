import axios from "axios"

axios.defaults.withCredentials = true
axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest"

export function assetUrl(path: string): string {
  return path
}

export class ApiError extends Error {
  status: number

  constructor(status: number, message: string) {
    super(message)
    this.status = status
  }
}

function errorFrom(err: unknown): ApiError {
  if (axios.isAxiosError(err)) {
    const status = err.response?.status ?? 0
    const message = err.response?.data?.message ?? `Gagal memuat data (${status})`
    return new ApiError(status, message)
  }
  return new ApiError(0, "Gagal memuat data")
}

async function request<T>(
  method: "GET" | "POST" | "PATCH" | "DELETE",
  path: string,
  data?: unknown,
  params?: Record<string, string | undefined>
): Promise<T> {
  try {
    const res = await axios.request<T>({ method, url: path, data, params })
    return res.data
  } catch (err) {
    throw errorFrom(err)
  }
}

export const api = {
  get: <T>(path: string, query?: Record<string, string | undefined>) =>
    request<T>("GET", path, undefined, query),
  post: <T>(path: string, body?: unknown) => request<T>("POST", path, body),
  patch: <T>(path: string, body?: unknown) => request<T>("PATCH", path, body),
  del: <T>(path: string) => request<T>("DELETE", path),
}

export async function postForm<T>(path: string, body: FormData): Promise<T> {
  try {
    const res = await axios.post<T>(path, body)
    return res.data
  } catch (err) {
    throw errorFrom(err)
  }
}

export async function uploadFile<T>(path: string, fieldName: string, file: File): Promise<T> {
  const body = new FormData()
  body.append(fieldName, file)
  return postForm<T>(path, body)
}

export async function downloadFile(
  path: string,
  query: Record<string, string | undefined>,
  filename: string
) {
  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined) params.set(key, value)
  }

  let res
  try {
    res = await axios.get(`${path}?${params.toString()}`, { responseType: "blob" })
  } catch (err) {
    throw errorFrom(err)
  }

  const objectUrl = URL.createObjectURL(res.data)
  const link = document.createElement("a")
  link.href = objectUrl
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(objectUrl)
}
