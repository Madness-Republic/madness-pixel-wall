const http = require('http');
const fs = require('fs');
const path = require('path');

const PORT = process.env.PORT || 3000;
const DATA_FILE = path.join(__dirname, 'data/wall_data.json');

// Ensure data file exists
if (!fs.existsSync(DATA_FILE)) {
    fs.writeFileSync(DATA_FILE, JSON.stringify({}));
}

const server = http.createServer((req, res) => {
    // CORS Headers (in case of local testing)
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

    if (req.method === 'OPTIONS') {
        res.writeHead(204);
        res.end();
        return;
    }

    // API: Load Wall Data
    if (req.url === '/api/wall' && req.method === 'GET') {
        fs.readFile(DATA_FILE, 'utf8', (err, data) => {
            if (err) {
                res.writeHead(500);
                res.end('Error reading data');
                return;
            }
            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(data);
        });
        return;
    }

    // API: Save Wall Data (Append/Merge)
    if (req.url === '/api/wall' && req.method === 'POST') {
        let body = '';
        req.on('data', chunk => { body += chunk.toString(); });
        req.on('end', () => {
            try {
                const newPixels = JSON.parse(body);
                fs.readFile(DATA_FILE, 'utf8', (err, data) => {
                    const wall = err ? {} : JSON.parse(data);
                    // Merge new pixels into the wall
                    Object.assign(wall, newPixels);
                    fs.writeFile(DATA_FILE, JSON.stringify(wall), err => {
                        if (err) {
                            res.writeHead(500);
                            res.end('Error saving data');
                            return;
                        }
                        res.writeHead(200, { 'Content-Type': 'application/json' });
                        res.end(JSON.stringify({ success: true }));
                    });
                });
            } catch (e) {
                res.writeHead(400);
                res.end('Invalid JSON');
            }
        });
        return;
    }

    // Static File Server
    let filePath = path.join(__dirname, req.url === '/' ? 'index.html' : req.url);

    // Handle files in parent directory (style.css, etc.)
    if (req.url.startsWith('/../')) {
        filePath = path.join(__dirname, '..', req.url.substring(4));
    } else if (req.url === '/style.css' || req.url === '/translations.js' || req.url.startsWith('/Icons/') || req.url.startsWith('/Images/')) {
        filePath = path.join(__dirname, '..', req.url);
    }

    const extname = path.extname(filePath);
    let contentType = 'text/html';
    switch (extname) {
        case '.js': contentType = 'text/javascript'; break;
        case '.css': contentType = 'text/css'; break;
        case '.json': contentType = 'application/json'; break;
        case '.png': contentType = 'image/png'; break;
        case '.jpg': contentType = 'image/jpg'; break;
    }

    fs.readFile(filePath, (err, content) => {
        if (err) {
            if (err.code === 'ENOENT') {
                res.writeHead(404);
                res.end('File not found');
            } else {
                res.writeHead(500);
                res.end(`Server Error: ${err.code}`);
            }
        } else {
            res.writeHead(200, { 'Content-Type': contentType });
            res.end(content, 'utf-8');
        }
    });
});

server.listen(PORT, () => {
    console.log(`Server running at http://localhost:${PORT}/`);
    console.log(`API available at http://localhost:${PORT}/api/wall`);
});
