/**
 * Duration formatting helpers.
 *
 * Different parts of the app track time in different units. Use the helper
 * whose name matches the input unit so you can be sure what is being
 * formatted.
 */

/** Format minutes -> "1h 30m" / "45m" / "0h 0m". */
export function formatMinutes(minutes) {
    if (!minutes || minutes <= 0) return '0h 0m';
    const h = Math.floor(minutes / 60);
    const m = Math.round(minutes % 60);
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

/** Format seconds -> "1h 30m" / "45m" / "0m". */
export function formatSeconds(seconds) {
    if (!seconds || seconds <= 0) return '0m';
    const totalMinutes = Math.floor(seconds / 60);
    return formatMinutes(totalMinutes);
}

/** Format seconds -> "00:01:30" stopwatch style. */
export function formatHMS(seconds) {
    if (!seconds || seconds <= 0) return '00:00:00';
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = Math.floor(seconds % 60);
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

/** Format hours (decimal) -> "1h 30m" / "45m". */
export function formatHours(hours) {
    if (hours === null || hours === undefined || Number.isNaN(hours)) return 'N/A';
    if (hours < 1) return `${Math.round(hours * 60)}m`;
    const h = Math.floor(hours);
    const m = Math.round((hours - h) * 60);
    return m > 0 ? `${h}h ${m}m` : `${h}h`;
}
