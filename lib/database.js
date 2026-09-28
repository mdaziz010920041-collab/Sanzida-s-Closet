const mysql = require('mysql2/promise');

let pool;

function databasePool() {
    if (!process.env.DB_USER) {
        throw new Error('Database credentials are not configured.');
    }

    if (!pool) {
        pool = mysql.createPool({
            host: process.env.DB_HOST || '127.0.0.1',
            port: Number(process.env.DB_PORT || 3306),
            database: process.env.DB_NAME || 'sanzidas_closet',
            user: process.env.DB_USER,
            password: process.env.DB_PASSWORD || '',
            charset: 'utf8mb4',
            waitForConnections: true,
            connectionLimit: 2,
            enableKeepAlive: true,
        });
    }

    return pool;
}

module.exports = { databasePool };