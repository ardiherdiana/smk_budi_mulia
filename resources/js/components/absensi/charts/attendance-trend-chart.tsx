import * as React from "react"

export interface TrendPoint {
  tanggal: string
  rate: number
  hadir: number
  telat: number
  alpa: number
}

const DEFAULT_WIDTH = 600
const HEIGHT = 220
const PAD_LEFT = 36
const PAD_RIGHT = 16
const PAD_TOP = 16
const PAD_BOTTOM = 24
const PLOT_H = HEIGHT - PAD_TOP - PAD_BOTTOM

export function AttendanceTrendChart({ data }: { data: TrendPoint[] }) {
  const [hoverIndex, setHoverIndex] = React.useState<number | null>(null)
  const containerRef = React.useRef<HTMLDivElement>(null)
  const svgRef = React.useRef<SVGSVGElement>(null)

  const [width, setWidth] = React.useState(DEFAULT_WIDTH)

  React.useEffect(() => {
    const el = containerRef.current
    if (!el) return
    const observer = new ResizeObserver((entries) => {
      const entry = entries[0]
      if (entry) setWidth(entry.contentRect.width)
    })
    observer.observe(el)
    return () => observer.disconnect()
  }, [])

  if (data.length === 0) {
    return <p className="text-sm text-muted-foreground">Belum ada data</p>
  }

  const plotW = width - PAD_LEFT - PAD_RIGHT

  const xAt = (i: number) =>
    PAD_LEFT + (data.length === 1 ? plotW / 2 : (i / (data.length - 1)) * plotW)
  const yAt = (rate: number) => PAD_TOP + PLOT_H - (rate / 100) * PLOT_H

  const linePath = data.map((d, i) => `${i === 0 ? "M" : "L"}${xAt(i)},${yAt(d.rate)}`).join(" ")
  const areaPath = `${linePath} L${xAt(data.length - 1)},${PAD_TOP + PLOT_H} L${xAt(0)},${PAD_TOP + PLOT_H} Z`

  function handlePointerMove(event: React.PointerEvent<SVGSVGElement>) {
    const svg = svgRef.current
    if (!svg) return
    const rect = svg.getBoundingClientRect()
    const x = ((event.clientX - rect.left) / rect.width) * width
    const ratio = (x - PAD_LEFT) / plotW
    const index = Math.round(ratio * (data.length - 1))
    setHoverIndex(Math.min(Math.max(index, 0), data.length - 1))
  }

  const hovered = hoverIndex !== null ? data[hoverIndex] : undefined
  const last = data[data.length - 1]!
  const tooltipLeftPct = hoverIndex !== null ? (xAt(hoverIndex) / width) * 100 : 0
  const flipTooltip = hoverIndex !== null && xAt(hoverIndex) > width * 0.65

  return (
    <div ref={containerRef} className="relative">
      <svg
        ref={svgRef}
        viewBox={`0 0 ${width} ${HEIGHT}`}
        className="w-full touch-none"
        onPointerMove={handlePointerMove}
        onPointerLeave={() => setHoverIndex(null)}
      >
        {[0, 25, 50, 75, 100].map((tick) => (
          <g key={tick}>
            <line
              x1={PAD_LEFT}
              x2={width - PAD_RIGHT}
              y1={yAt(tick)}
              y2={yAt(tick)}
              stroke="var(--border)"
              strokeWidth={1}
            />
            <text
              x={PAD_LEFT - 8}
              y={yAt(tick)}
              textAnchor="end"
              dominantBaseline="middle"
              className="fill-muted-foreground text-[10px]"
            >
              {tick}%
            </text>
          </g>
        ))}

        <path d={areaPath} fill="var(--chart-1)" opacity={0.1} stroke="none" />
        <path
          d={linePath}
          fill="none"
          stroke="var(--chart-1)"
          strokeWidth={2}
          strokeLinejoin="round"
          strokeLinecap="round"
        />

        <circle
          cx={xAt(data.length - 1)}
          cy={yAt(last.rate)}
          r={4}
          fill="var(--chart-1)"
          stroke="var(--card)"
          strokeWidth={2}
        />
        <text
          x={xAt(data.length - 1)}
          y={yAt(last.rate) - 10}
          textAnchor="end"
          className="fill-foreground text-[11px] font-medium"
        >
          {Math.round(last.rate)}%
        </text>

        {hoverIndex !== null && hovered && (
          <>
            <line
              x1={xAt(hoverIndex)}
              x2={xAt(hoverIndex)}
              y1={PAD_TOP}
              y2={PAD_TOP + PLOT_H}
              stroke="var(--muted-foreground)"
              strokeWidth={1}
              opacity={0.4}
            />
            <circle
              cx={xAt(hoverIndex)}
              cy={yAt(hovered.rate)}
              r={4}
              fill="var(--chart-1)"
              stroke="var(--card)"
              strokeWidth={2}
            />
          </>
        )}

        {data.map((d, i) => {
          const isEdgeOrMid =
            i === 0 || i === data.length - 1 || i === Math.floor((data.length - 1) / 2)
          if (!isEdgeOrMid) return null
          return (
            <text
              key={d.tanggal}
              x={xAt(i)}
              y={HEIGHT - 6}
              textAnchor={i === 0 ? "start" : i === data.length - 1 ? "end" : "middle"}
              className="fill-muted-foreground text-[10px]"
            >
              {new Date(d.tanggal).toLocaleDateString("id-ID", { day: "2-digit", month: "short" })}
            </text>
          )
        })}
      </svg>

      {hoverIndex !== null && hovered && (
        <div
          className="pointer-events-none absolute top-0 z-10 rounded-md border bg-popover px-2.5 py-1.5 text-xs whitespace-nowrap shadow-md"
          style={{
            left: `${tooltipLeftPct}%`,
            transform: flipTooltip ? "translate(-100%, 0)" : "translate(0, 0)",
          }}
        >
          <div className="font-medium text-popover-foreground">
            {new Date(hovered.tanggal).toLocaleDateString("id-ID", {
              weekday: "short",
              day: "2-digit",
              month: "short",
            })}
          </div>
          <div className="mt-1 flex items-center gap-1.5">
            <span className="inline-block h-0.5 w-3 rounded-full bg-chart-1" />
            <span className="font-semibold tabular-nums text-popover-foreground">
              {Math.round(hovered.rate)}%
            </span>
            <span className="text-muted-foreground">hadir tepat waktu</span>
          </div>
          <div className="mt-1 text-muted-foreground">
            {hovered.hadir} hadir &middot; {hovered.telat} telat &middot; {hovered.alpa} alpa
          </div>
        </div>
      )}
    </div>
  )
}
