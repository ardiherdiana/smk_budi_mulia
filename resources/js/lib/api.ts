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

/** Galat dari unduhan: bodi respons berupa Blob, jadi pesan JSON-nya harus dibaca dulu supaya 403/404 tidak hanya "Gagal memuat data". */
async function errorFromBlob(err: unknown): Promise<ApiError> {
  if (axios.isAxiosError(err) && err.response?.data instanceof Blob) {
    try {
      const isi = JSON.parse(await err.response.data.text()) as { message?: string }
      return new ApiError(err.response.status, isi.message ?? `Gagal mengunduh (${err.response.status})`)
    } catch {
      // Bukan JSON: pakai pesan umum di bawah.
    }
  }
  return errorFrom(err)
}

/** Nama berkas dari header Content-Disposition (mendukung `filename*=UTF-8''...` dan `filename="..."`). */
function namaDariHeader(header: unknown): string | null {
  if (typeof header !== "string") return null
  const utf8 = /filename\*=(?:UTF-8|utf-8)''([^;]+)/.exec(header)
  if (utf8) {
    try {
      return decodeURIComponent(utf8[1])
    } catch {
      // jatuh ke bentuk biasa
    }
  }
  return /filename="?([^";]+)"?/.exec(header)?.[1] ?? null
}

/**
 * Unduh berkas lewat permintaan biasa (sesi dan CSRF ikut), dengan status yang bisa dipantau pemanggil. Nilai larik
 * dikirim berulang dengan kunci yang sama (mis. `kelas[]`). Mengembalikan nama berkas yang diunduh.
 */
export async function downloadFile(
  path: string,
  query: Record<string, string | string[] | undefined>,
  filename?: string
): Promise<string> {
  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(query)) {
    if (Array.isArray(value)) value.forEach((v) => params.append(key, v))
    else if (value !== undefined) params.set(key, value)
  }

  let res
  try {
    res = await axios.get(`${path}?${params.toString()}`, { responseType: "blob" })
  } catch (err) {
    throw await errorFromBlob(err)
  }

  const nama = namaDariHeader(res.headers["content-disposition"]) ?? filename ?? "unduhan"
  const objectUrl = URL.createObjectURL(res.data)
  const link = document.createElement("a")
  link.href = objectUrl
  link.download = nama
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(objectUrl)

  return nama
}
