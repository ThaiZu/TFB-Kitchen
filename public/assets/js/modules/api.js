export async function api(endpoint, { method = 'GET', data = null, headers = {} } = {}) {
    let url = '/supplier' + endpoint;
    const opts = { method, headers: { ...headers } };

    if (method === 'GET' && data) {
        url += '?' + new URLSearchParams(data);
    } else if (data instanceof FormData) {
        // NIE ustawiaj Content-Type, przeglądarka zrobi to sama
        opts.body = data;
    } else if (data) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(data);
    }

    const res = await fetch(url, opts);

    let payload = null;
    try {
        payload = await res.json();
    } catch (e) {}

    return {
        ok:      res.ok,
        success:      res.success,
        status:  res.status,
        data:    payload,
        ...payload
    };
}

export async function downloadPdf(endpoint, payload = {}, defaultFilename = "plik.pdf", method = "GET") {
    try {

        let fetchOptions = {
            method: method.toUpperCase(),
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/pdf"
            },
            credentials: "same-origin"
        };

        let url = `/supplier${endpoint}`;

        if (fetchOptions.method === "GET") {
            const query = new URLSearchParams(payload).toString();
            url += "?" + query;
        } else {
            fetchOptions.body = JSON.stringify(payload);
        }

        const response = await fetch(url, fetchOptions);

        if (!response.ok) {
            let errorMessage = "";

            switch (response.status) {
                case 404:
                    errorMessage = "ENDPOINT_NOT_FOUND " + endpoint;
                    break;
                case 403:
                    errorMessage = "FORBIDDEN";
                    break;
                case 500:
                    errorMessage = "INTERNAL_SERVER_ERROR";
                    break;
                default:
                    errorMessage = `ERROR: ${response.status} ${response.statusText}`;
                    break;
            }

            const contentType = response.headers.get("Content-Type") || "";
            if (contentType.includes("application/json")) {
                const errorData = await response.json();
                if (errorData.message) {
                    // Jeśli `message` to tablica, to złącz
                    errorMessage = Array.isArray(errorData.message)
                        ? errorData.message.join(", ")
                        : errorData.message;
                }
            }

            throw new Error(errorMessage);
        }

        const blob = await response.blob();

        const disposition = response.headers.get("Content-Disposition") || "";
        const match = /filename="?(.+?)"?(;|$)/.exec(disposition);
        const filename = match ? match[1] : defaultFilename;

        const downloadUrl = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = downloadUrl;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(downloadUrl);

    } catch (err) {
        Swal.fire({
            icon: "error",
            title: "Błąd",
            text: err.message || "PDF_DOWNLOAD_ERROR"
        });
    }
}