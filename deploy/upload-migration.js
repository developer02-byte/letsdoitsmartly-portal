/**
 * Upload migration file to server
 */

const https = require('https');
const fs = require('fs');

const config = {
  hostname: 'bh-in-32.webhostbox.net',
  port: 2083,
  username: 'letsdoitadmin',
  token: 'P6WYJDG7L89HO9XN9QUDTXXP8TMKU0TV',
  basePath: '/home4/letsdoitadmin/public_html'
};

async function createDir(remotePath) {
  return new Promise((resolve) => {
    const parts = remotePath.split('/').filter(p => p);
    let currentPath = '';

    const createNext = (index) => {
      if (index >= parts.length) {
        resolve(true);
        return;
      }

      currentPath += '/' + parts[index];
      const parentPath = currentPath.substring(0, currentPath.lastIndexOf('/')) || '/';
      const dirName = parts[index];

      const formData = new URLSearchParams({
        path: config.basePath + parentPath,
        name: dirName
      }).toString();

      const options = {
        hostname: config.hostname,
        port: config.port,
        path: '/execute/Fileman/mkdir',
        method: 'POST',
        headers: {
          'Authorization': 'cpanel ' + config.username + ':' + config.token,
          'Content-Type': 'application/x-www-form-urlencoded',
          'Content-Length': Buffer.byteLength(formData)
        },
        rejectUnauthorized: false
      };

      const req = https.request(options, (res) => {
        let data = '';
        res.on('data', chunk => data += chunk);
        res.on('end', () => createNext(index + 1));
      });

      req.on('error', () => createNext(index + 1));
      req.write(formData);
      req.end();
    };

    createNext(0);
  });
}

async function uploadFile(content, remotePath) {
  return new Promise((resolve) => {
    const dir = remotePath.substring(0, remotePath.lastIndexOf('/'));
    const file = remotePath.substring(remotePath.lastIndexOf('/') + 1);

    const formData = new URLSearchParams({
      dir: config.basePath + dir,
      file: file,
      content: content
    }).toString();

    const options = {
      hostname: config.hostname,
      port: config.port,
      path: '/execute/Fileman/save_file_content',
      method: 'POST',
      headers: {
        'Authorization': 'cpanel ' + config.username + ':' + config.token,
        'Content-Type': 'application/x-www-form-urlencoded',
        'Content-Length': Buffer.byteLength(formData)
      },
      rejectUnauthorized: false
    };

    const req = https.request(options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          const result = JSON.parse(data);
          resolve(result.status === 1);
        } catch (e) {
          resolve(false);
        }
      });
    });

    req.on('error', () => resolve(false));
    req.write(formData);
    req.end();
  });
}

async function main() {
  console.log('Creating migrations directory...');
  await createDir('/portal/install/migrations');
  console.log('Done\n');

  // Read migration file
  const migrationContent = fs.readFileSync(
    'd:/Projects/Letdoitsmartly/portal/install/migrations/005_sync_lock.sql',
    'utf8'
  );

  console.log('Uploading migration file...');
  const success = await uploadFile(
    migrationContent,
    '/portal/install/migrations/005_sync_lock.sql'
  );

  if (success) {
    console.log('Migration file uploaded successfully!');
  } else {
    console.log('Failed to upload migration file');
  }
}

main().catch(console.error);
