/**
 * Normalize a user-input value into a valid 6-digit hex color string.
 * Returns the fallback when the input is empty or malformed.
 */
export function normalizeHexColor(value, fallback = '#6366F1') {
    const raw = (value || '').trim();
    if (!raw) return fallback;
    const hex = raw.startsWith('#') ? raw : `#${raw}`;
    return /^#[0-9A-Fa-f]{6}$/.test(hex) ? hex.toUpperCase() : fallback;
}
