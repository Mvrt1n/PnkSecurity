'use strict';
// Healthcheck para Docker: consulta /health y sale 0 (ok) o 1 (falla).
const http = require('http');
const port = process.env.PORT || 3000;
const req = http.get({ host: '127.0.0.1', port, path: '/health', timeout: 3000 }, (res) => {
  if (res.statusCode === 200) process.exit(0);
  process.exit(1);
});
req.on('error', () => process.exit(1));
req.on('timeout', () => { req.destroy(); process.exit(1); });
