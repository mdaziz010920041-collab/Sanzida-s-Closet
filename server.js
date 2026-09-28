const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const handler = require('./api/index');

const staticTypes = {
    '.css': 'text/css; charset=utf-8',
    '.gif': 'image/gif',
    '.jpg': 'image/jpeg',
    '.jpeg': 'image/jpeg',
    '.js': 'text/javascript; charset=utf-8',
    '.png': 'image/png',
    '.svg': 'image/svg+xml',
    '.webp': 'image/webp',
};

function serveStatic(request, response) {
    let pathname;
    try {
        pathname = decodeURIComponent(new URL(request.url || '/', 'http://localhost').pathname);
    } catch {
        return false;
    }
    const prefix = pathname.startsWith('/assets/') ? '/assets/' : pathname.startsWith('/uploads/') ? '/uploads/' : '';
    if (!prefix || request.method !== 'GET' && request.method !== 'HEAD') return false;
    const root = path.resolve(__dirname, prefix.slice(1, -1));
    const filePath = path.resolve(root, pathname.slice(prefix.length));
    if (!filePath.startsWith(`${root}${path.sep}`)) {
        response.writeHead(403);
        response.end();
        return true;
    }
    fs.stat(filePath, (error, stat) => {
        if (error || !stat.isFile()) {
            response.writeHead(404);
            response.end();
            return;
        }
        response.writeHead(200, {
            'content-type': staticTypes[path.extname(filePath).toLowerCase()] || 'application/octet-stream',
            'content-length': stat.size,
            'x-content-type-options': 'nosniff',
        });
        if (request.method === 'HEAD') response.end();
        else fs.createReadStream(filePath).pipe(response);
    });
    return true;
}

function loadLocalEnvironment() {
    const envPath = path.join(__dirname, '.env');
    if (!fs.existsSync(envPath)) return;

    for (const line of fs.readFileSync(envPath, 'utf8').split(/\r?\n/)) {
        const match = line.match(/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?)\s*$/);
        if (!match || match[1] in process.env) continue;
        process.env[match[1]] = match[2].replace(/^(['"])(.*)\1$/, '$2');
    }
}

loadLocalEnvironment();

const port = Number(process.env.PORT || 3000);
process.env.APP_URL = `http://localhost:${port}`;
http.createServer((request, response) => {
    if (serveStatic(request, response)) return;
    Promise.resolve(handler(request, response)).catch((error) => {
        console.error(error);
        if (!response.headersSent) response.writeHead(500, { 'content-type': 'text/plain; charset=utf-8' });
        response.end('Internal server error');
    });
}).listen(port, () => console.log(`Sanzida's Closet listening on http://localhost:${port}`));