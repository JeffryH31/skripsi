export const PRIORITIES = [
    { level: 1, name: "Urgent", color: "#EF4444" },
    { level: 2, name: "High", color: "#F59E0B" },
    { level: 3, name: "Normal", color: "#3B82F6" },
    { level: 4, name: "Low", color: "#6B7280" },
];

export const PRIORITY_MAP = Object.fromEntries(
    PRIORITIES.map((p) => [p.level, p]),
);

export function getPriority(level) {
    return PRIORITY_MAP[level] || null;
}
