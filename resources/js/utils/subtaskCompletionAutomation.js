const STORAGE_KEY = "subtask_completion_target_by_space";

const isSubtaskStatus = (status) => {
    const appliesTo = status?.applies_to;
    return !appliesTo || appliesTo === "subtasks" || appliesTo === "both";
};

const parseStorage = () => {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : {};
    } catch {
        return {};
    }
};

const writeStorage = (data) => {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    } catch {
        // localStorage may be disabled (private mode, quota exceeded, etc.) — fail silently
    }
};

export const getSubtaskCompletionStatusOptions = (statuses = []) => {
    return statuses.filter(isSubtaskStatus);
};

export const getStoredSubtaskCompletionTarget = (spaceId, statuses = []) => {
    if (!spaceId) return null;

    const options = getSubtaskCompletionStatusOptions(statuses);
    const map = parseStorage();
    const storedId = Number(map[String(spaceId)] || 0);

    if (storedId && options.some((status) => status.id === storedId)) {
        return storedId;
    }

    return null;
};

export const setStoredSubtaskCompletionTarget = (spaceId, statusId) => {
    if (!spaceId) return;

    const map = parseStorage();
    if (statusId) {
        map[String(spaceId)] = Number(statusId);
    } else {
        delete map[String(spaceId)];
    }

    writeStorage(map);
};

export const getFallbackCompletionTarget = (statuses = []) => {
    return null;
};
