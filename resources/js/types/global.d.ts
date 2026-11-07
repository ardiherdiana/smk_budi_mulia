// Modul SPPD: `route()` is injected as a global by Ziggy's `@routes` Blade directive
// (see resources/views/app.blade.php) — this only declares its type for tsc.
declare function route(name?: string, params?: unknown, absolute?: boolean): string;
