/**
 * Upload and run migration script
 */
const https = require('https');
const http = require('http');
const fs = require('fs');

const config = {
  hostname: 'bh-in-32.webhostbox.net',
  port: 2083,
  username: 'letsdoitadmin',
  token: 'P6WYJDG7L89HO9XN9QUDTXXP8TMKU0TV',
  basePath: '/home4/letsdoitadmin/public_html'
};

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
          console.log('Response:', data.substring(0, 200));
          resolve(false);
        }
      });
    });

    req.on('error', (e) => {
      console.log('Upload error:', e.message);
      resolve(false);
    });
    req.write(formData);
    req.end();
  });
}

async function runScript(path) {
  return new Promise((resolve) => {
    const req = http.request({
      hostname: '43.225.55.146',
      port: 80,
      path: path,
      method: 'GET',
      timeout: 30000,
      headers: { 'Host': 'letsdoitsmartly.com' }
    }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve(data));
    });
    req.on('error', (e) => resolve('Error: ' + e.message));
    req.on('timeout', () => { req.destroy(); resolve('Timeout'); });
    req.end();
  });
}

async function main() {
  // Read the migration script
  const content = fs.readFileSync(
    'd:/Projects/Letdoitsmartly/portal/install/run_sync_lock_migration.php',
    'utf8'
  );

  console.log('1. Uploading migration script...');
  const uploaded = await uploadFile(content, '/portal/install/run_sync_lock_migration.php');

  if (!uploaded) {
    console.log('Failed to upload script');
    return;
  }
  console.log('   Uploaded!\n');

  console.log('2. Running migration...');
  const result = await runScript('/portal/install/run_sync_lock_migration.php');
  console.log(result);
}

main();
