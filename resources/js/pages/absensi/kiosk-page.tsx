import * as React from "react"
import { Link } from "@inertiajs/react"
import { Html5Qrcode, Html5QrcodeScannerState } from "html5-qrcode"
import { ArrowLeft, Camera, CheckCircle2, Keyboard, RotateCw, ScanLine, SwitchCamera, XCircle } from "lucide-react"

import { api, ApiError } from "@/lib/absensi/api"
import type { CheckinResult, Settings } from "@/lib/absensi/types"

type Status = "idle" | "processing"
type CameraStatus = "starting" | "scanning" | "error"
type Feedback = { result: CheckinResult | null; message: string | null }

const RESULT_RESET_MS = 3000
const SAME_TOKEN_COOLDOWN_MS = RESULT_RESET_MS
const READER_ID = "kiosk-qr-reader"
const MODE_STORAGE_KEY = "kiosk-input-mode"

type InputMode = "kamera" | "alat"

const DESKTOP_QUERY = "(hover: hover) and (pointer: fine)"

function loadMode(): InputMode {
  try {
    return localStorage.getItem(MODE_STORAGE_KEY) === "kamera" ? "kamera" : "alat"
  } catch {
    return "alat"
  }
}

const RESULT_LABEL: Record<CheckinResult["type"], string> = {
  MASUK: "Absen masuk berhasil",
  PULANG: "Absen pulang berhasil",
  BRIEFING: "Absen briefing berhasil",
}

/** Scan station: the device's own camera, decoded in-browser via html5-qrcode
 * and shown full-bleed behind the UI - lets a plain tablet/phone act as the
 * scan station with no external hardware. Opened on a PC/tablet at school,
 * logged in as ADMIN; a guru presents their own static QR (phone or printed
 * card) to the device instead of scanning anything themselves. */
export function KioskPage({ settings }: { settings?: Settings }) {
  const [now, setNow] = React.useState(new Date())
  React.useEffect(() => {
    const t = setInterval(() => setNow(new Date()), 1000)
    return () => clearInterval(t)
  }, [])
  const [status, setStatus] = React.useState<Status>("idle")
  const [cameraStatus, setCameraStatus] = React.useState<CameraStatus>("starting")
  const [cameraError, setCameraError] = React.useState<string | null>(null)
  const [retryKey, setRetryKey] = React.useState(0)
  const [chosenMode, setMode] = React.useState<InputMode>(loadMode)
  // Desktop/laptop (mouse + hover) hanya memakai alat scan; pilihan kamera hanya untuk perangkat layar sentuh.
  const [isDesktop, setIsDesktop] = React.useState(() => window.matchMedia(DESKTOP_QUERY).matches)
  React.useEffect(() => {
    const mql = window.matchMedia(DESKTOP_QUERY)
    const onChange = () => setIsDesktop(mql.matches)
    mql.addEventListener("change", onChange)
    return () => mql.removeEventListener("change", onChange)
  }, [])
  const mode: InputMode = isDesktop ? "alat" : chosenMode
  const [inputValue, setInputValue] = React.useState("")
  const inputRef = React.useRef<HTMLInputElement>(null)
  const [facing, setFacing] = React.useState<"user" | "environment">("user")
  const scannerRef = React.useRef<Html5Qrcode | null>(null)
  const successAudioRef = React.useRef<HTMLAudioElement | null>(null)
  const errorAudioRef = React.useRef<HTMLAudioElement | null>(null)
  if (successAudioRef.current === null) {
    successAudioRef.current = new Audio("/sounds/success.mp3")
  }
  if (errorAudioRef.current === null) {
    errorAudioRef.current = new Audio("/sounds/error.mp3")
  }
  const [feedback, setFeedback] = React.useState<Feedback | null>(null)
  const feedbackTimerRef = React.useRef<ReturnType<typeof setTimeout> | null>(null)
  const busyRef = React.useRef(false)
  const lastTokenRef = React.useRef<{ token: string; at: number } | null>(null)

  function showFeedback(next: Feedback) {
    if (feedbackTimerRef.current) clearTimeout(feedbackTimerRef.current)
    setFeedback(next)
    feedbackTimerRef.current = setTimeout(() => setFeedback(null), RESULT_RESET_MS)
  }

  React.useEffect(() => {
    return () => {
      if (feedbackTimerRef.current) clearTimeout(feedbackTimerRef.current)
    }
  }, [])

  async function handleScan(qrToken: string) {
    const token = qrToken.trim()
    if (!token) return
    const nowMs = Date.now()
    if (lastTokenRef.current?.token === token && nowMs - lastTokenRef.current.at < SAME_TOKEN_COOLDOWN_MS) {
      return
    }
    if (busyRef.current) return
    busyRef.current = true
    lastTokenRef.current = { token, at: nowMs }
    setStatus("processing")
    try {
      const res = await api.post<CheckinResult>("/kiosk/scan-qr", { qrToken: token })
      showFeedback({ result: res, message: null })
      successAudioRef.current?.play().catch(() => {})
    } catch (err) {
      showFeedback({
        result: null,
        message: err instanceof ApiError ? err.message : "Gagal memproses scan",
      })
      errorAudioRef.current?.play().catch(() => {})
    } finally {
      busyRef.current = false
      setStatus("idle")
    }
  }

  function changeMode(next: InputMode) {
    setMode(next)
    try {
      localStorage.setItem(MODE_STORAGE_KEY, next)
    } catch {
      // penyimpanan tidak tersedia, mode hanya berlaku untuk sesi ini
    }
  }

  // Alat scan USB bertindak sebagai keyboard: mengetik isi QR lalu Enter ke elemen yang fokus,
  // jadi input harus selalu fokus di mode "alat".
  React.useEffect(() => {
    if (mode !== "alat") return
    const focusInput = () => inputRef.current?.focus()
    focusInput()
    document.addEventListener("click", focusInput)
    return () => document.removeEventListener("click", focusInput)
  }, [mode])

  function handleKeyDown(event: React.KeyboardEvent<HTMLInputElement>) {
    if (event.key === "Enter") {
      event.preventDefault()
      const value = inputValue
      setInputValue("")
      void handleScan(value)
    }
  }

  React.useEffect(() => {
    if (mode !== "kamera") return
    let cancelled = false
    const scanner = new Html5Qrcode(READER_ID)
    scannerRef.current = scanner

    function stopIfRunning() {
      const state = scanner.getState()
      if (state === Html5QrcodeScannerState.SCANNING || state === Html5QrcodeScannerState.PAUSED) {
        return scanner.stop().catch(() => {}).finally(() => scanner.clear())
      }
      return Promise.resolve()
    }

    async function start() {
      setCameraStatus("starting")
      setCameraError(null)
      try {
        await scanner.start(
          { facingMode: facing },
          {
            fps: 10,
            qrbox: (viewfinderWidth, viewfinderHeight) => {
              const edge = Math.floor(Math.min(viewfinderWidth, viewfinderHeight) * 0.7)
              return { width: edge, height: edge }
            },
          },
          (decodedText) => {
            void handleScan(decodedText)
          },
          () => {
            // per-frame decode failure, ignore
          }
        )
        if (cancelled) {
          void stopIfRunning()
          return
        }
        setCameraStatus("scanning")
      } catch {
        if (!cancelled) {
          setCameraStatus("error")
          setCameraError(
            "Tidak bisa mengakses kamera. Pastikan Anda mengizinkan akses kamera di browser."
          )
        }
      }
    }

    start()

    return () => {
      cancelled = true
      scannerRef.current = null
      void stopIfRunning()
    }
  // handleScan sengaja tidak jadi dependensi: scanner hanya dimulai ulang saat kamera berganti
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [retryKey, facing, mode])

  if (mode === "alat") {
    return (
      <div className="relative flex min-h-svh flex-col items-center justify-center gap-6 bg-neutral-950 p-6 text-white">
        <Link
          href="/absen/dashboard"
          className="absolute top-2 left-2 flex min-h-11 items-center gap-1.5 px-2 text-sm text-white/70 hover:text-white"
        >
          <ArrowLeft className="size-4" />
          Kembali ke dashboard
        </Link>
        {!isDesktop && (
          <button
            type="button"
            onClick={() => changeMode("kamera")}
            className="absolute top-2 right-2 flex min-h-11 items-center gap-1.5 px-2 text-sm text-white/70 hover:text-white"
          >
            <Camera className="size-4" />
            Pakai kamera
          </button>
        )}

        <div className="text-center">
          <p className="text-sm tracking-wide text-white/60 uppercase">{settings?.namaSekolah ?? "Sekolah"}</p>
          <h1 className="text-2xl font-semibold">Absensi Guru</h1>
          <p className="mt-1 font-mono text-4xl tabular-nums">{now.toLocaleTimeString("id-ID", { hour12: false })}</p>
          <p className="text-sm text-white/60">
            {now.toLocaleDateString("id-ID", { weekday: "long", day: "numeric", month: "long", year: "numeric" })}
          </p>
        </div>

        <div className="flex w-full max-w-sm flex-col items-center gap-4 rounded-2xl bg-white/5 p-8 text-center ring-1 ring-white/10">
          {status === "processing" ? (
            <>
              <ScanLine className="size-16 animate-pulse text-white/60" />
              <p className="text-white/70">Memproses...</p>
            </>
          ) : feedback?.result ? (
            <>
              <CheckCircle2 className="size-16 text-emerald-400" />
              <p className="text-lg font-medium">{feedback.result.nama}</p>
              <p className="text-white/70">
                {RESULT_LABEL[feedback.result.type]}
                {feedback.result.statusMasuk ? ` (${feedback.result.statusMasuk === "TELAT" ? "Telat" : "Hadir"})` : ""}
              </p>
              {feedback.result.type === "MASUK" && feedback.result.briefing && (
                <p className="text-sm text-emerald-400">Absen briefing juga tercatat</p>
              )}
              <p className="font-mono text-sm text-white/50">
                {new Date(feedback.result.jam).toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit" })}
              </p>
            </>
          ) : feedback?.message ? (
            <>
              <XCircle className="size-16 text-red-400" />
              <p className="text-white/80">{feedback.message}</p>
            </>
          ) : (
            <>
              <ScanLine className="size-16 text-white/40" />
              <p className="text-white/70">Arahkan / tunjukkan QR guru ke alat scan</p>
            </>
          )}

          <input
            ref={inputRef}
            value={inputValue}
            onChange={(e) => setInputValue(e.target.value)}
            onKeyDown={handleKeyDown}
            onBlur={() => inputRef.current?.focus()}
            autoFocus
            autoComplete="off"
            placeholder="Menunggu scan..."
            className="w-full rounded-md border border-white/10 bg-white/5 px-3 py-1.5 text-center text-sm text-white/60 outline-none placeholder:text-white/30"
          />
        </div>

        <p className="max-w-sm text-center text-xs text-white/40">
          Alat scan mengetik otomatis ke kotak di atas - bisa juga diketik manual buat tes.
        </p>
      </div>
    )
  }

  return (
    <div className="fixed inset-0 flex items-center justify-center bg-black">
      <Link
        href="/absen/dashboard"
        className="absolute top-[max(1rem,env(safe-area-inset-top))] left-4 z-30 flex items-center gap-1.5 rounded-full bg-black/40 px-3 py-1.5 text-sm text-white/90 backdrop-blur-sm transition hover:bg-black/60 hover:text-white"
      >
        <ArrowLeft className="size-4" />
        Kembali
      </Link>
      <div className="absolute top-[max(1rem,env(safe-area-inset-top))] right-4 z-30 flex items-center gap-2">
        <button
          type="button"
          onClick={() => changeMode(mode === "kamera" ? "alat" : "kamera")}
          aria-label="Ganti mode input"
          className="flex items-center gap-1.5 rounded-full bg-black/40 px-3 py-1.5 text-sm text-white/90 backdrop-blur-sm transition hover:bg-black/60 hover:text-white"
        >
          {mode === "kamera" ? <Keyboard className="size-4" /> : <Camera className="size-4" />}
          {mode === "kamera" ? "Pakai alat scan" : "Pakai kamera"}
        </button>
        {mode === "kamera" && (
          <button
            type="button"
            onClick={() => setFacing((f) => (f === "user" ? "environment" : "user"))}
            aria-label="Ganti kamera depan/belakang"
            className="flex items-center gap-1.5 rounded-full bg-black/40 px-3 py-1.5 text-sm text-white/90 backdrop-blur-sm transition hover:bg-black/60 hover:text-white"
          >
            <SwitchCamera className="size-4" />
            {facing === "user" ? "Depan" : "Belakang"}
          </button>
        )}
      </div>
    <div className="relative flex h-full w-full flex-col md:h-[min(100svh,100vw)] md:w-[min(100svh,100vw)] overflow-hidden bg-black text-white">
      <div
        id={READER_ID}
        className={`absolute inset-0 overflow-hidden border-0! [&>div]:h-full! [&>div]:w-full! [&_video]:h-full! [&_video]:w-full! [&_video]:max-w-none! [&_video]:object-cover!${facing === "user" ? " [&_video]:-scale-x-100" : ""}`}
      />

      <div className="relative z-10 flex flex-1 items-center justify-center px-6">
        {cameraStatus === "starting" && (
          <div className="flex flex-col items-center gap-3 text-white/80">
            <ScanLine className="size-10 animate-pulse" />
            <p className="text-sm">Memulai kamera...</p>
          </div>
        )}
        {cameraStatus === "error" && (
          <div className="flex max-w-xs flex-col items-center gap-3 rounded-2xl bg-black/60 p-6 text-center backdrop-blur-sm ring-1 ring-white/10">
            <XCircle className="size-10 text-destructive" />
            <p className="text-sm text-white/90">{cameraError}</p>
            <button
              type="button"
              onClick={() => setRetryKey((k) => k + 1)}
              className="mt-1 flex items-center gap-1.5 rounded-full bg-white/10 px-4 py-1.5 text-sm font-medium text-white transition hover:bg-white/20"
            >
              <RotateCw className="size-3.5" />
              Coba lagi
            </button>
          </div>
        )}
      </div>

      <div className="relative z-10 flex flex-col items-center gap-3 px-4 pb-[max(2rem,env(safe-area-inset-bottom))] text-center">
        {status === "processing" && (
          <div className="flex items-center gap-2 rounded-full bg-black/60 px-5 py-2.5 backdrop-blur-sm">
            <ScanLine className="size-4 animate-pulse text-white" />
            <p className="text-sm text-white">Memproses...</p>
          </div>
        )}
        {status !== "processing" && feedback?.result && (
          <div className="flex w-full max-w-xs flex-col items-center gap-1.5 rounded-2xl bg-black/70 p-5 backdrop-blur-sm ring-1 ring-white/10">
            <CheckCircle2 className="size-9 text-success" />
            <p className="text-base font-medium text-white">{feedback.result.nama}</p>
            <p className="text-sm text-white/80">
              {RESULT_LABEL[feedback.result.type]}
              {feedback.result.statusMasuk ? ` (${feedback.result.statusMasuk === "TELAT" ? "Telat" : "Hadir"})` : ""}
            </p>
            {feedback.result.type === "MASUK" && feedback.result.briefing && (
              <p className="text-sm text-success">Absen briefing juga tercatat</p>
            )}
            <p className="font-mono text-xs text-white/60">
              {new Date(feedback.result.jam).toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit" })}
            </p>
          </div>
        )}
        {status !== "processing" && feedback?.message && (
          <div className="flex w-full max-w-xs flex-col items-center gap-1.5 rounded-2xl bg-black/70 p-5 backdrop-blur-sm ring-1 ring-white/10">
            <XCircle className="size-9 text-destructive" />
            <p className="text-sm text-white">{feedback.message}</p>
          </div>
        )}
      </div>
    </div>
    </div>
  )
}
