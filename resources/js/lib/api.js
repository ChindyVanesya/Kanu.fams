export async function api(url, { method = 'GET', body } = {}) {
    const isFormData = body instanceof FormData;
    const headers = {
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
    };

    if (body !== undefined && !isFormData) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        body: body === undefined || isFormData ? body : JSON.stringify(body),
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const validationMessage = Object.values(data.errors ?? {}).flat()[0];
        throw new Error(data.message ?? validationMessage ?? 'Permintaan tidak dapat diproses.');
    }

    return data;
}
