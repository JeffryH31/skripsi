export function safeFetch(url, options = {}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;

    return fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
            ...options.headers,
        },
    });
}
