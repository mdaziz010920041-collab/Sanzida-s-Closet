async function rawRequestBody(request, limit = 32768) {
    const chunks = [];
    let size = 0;
    for await (const chunk of request) {
        size += chunk.length;
        if (size > limit) throw new Error('Request body is too large.');
        chunks.push(chunk);
    }
    return Buffer.concat(chunks);
}

async function requestBody(request, limit = 32768) {
    const raw = (await rawRequestBody(request, limit)).toString('utf8');
    const type = String(request.headers['content-type'] || '').split(';', 1)[0].trim().toLowerCase();
    if (type === 'application/json') return JSON.parse(raw || '{}');
    if (type === 'application/x-www-form-urlencoded') return Object.fromEntries(new URLSearchParams(raw));
    if (raw === '') return {};
    throw new Error('Unsupported request content type.');
}

function jsonResponse(response, status, data) {
    response.writeHead(status, {
        'content-type': 'application/json; charset=utf-8',
        'cache-control': 'no-store',
        'x-content-type-options': 'nosniff',
    });
    response.end(JSON.stringify(data));
}

module.exports = { jsonResponse, rawRequestBody, requestBody };