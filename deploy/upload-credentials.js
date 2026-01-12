/**
 * Upload Google Credentials to secure location
 */

const https = require('https');
const fs = require('fs');

const config = {
  hostname: 'bh-in-32.webhostbox.net',
  port: 2083,
  username: 'letsdoitadmin',
  token: 'P6WYJDG7L89HO9XN9QUDTXXP8TMKU0TV',
  basePath: '/home4/letsdoitadmin'
};

async function createDirectory(dirPath) {
  return new Promise((resolve) => {
    const formData = new URLSearchParams({
      path: config.basePath,
      name: 'credentials'
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
      res.on('end', () => {
        console.log('Directory creation:', data);
        resolve(true);
      });
    });

    req.on('error', (e) => {
      console.log('Directory error:', e.message);
      resolve(true);
    });

    req.write(formData);
    req.end();
  });
}

async function uploadFile(localPath, remotePath) {
  return new Promise((resolve) => {
    const content = fs.readFileSync(localPath, 'utf8');
    const dir = '/credentials';
    const filename = 'google-credentials.json';

    const formData = new URLSearchParams({
      dir: config.basePath + dir,
      file: filename,
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
          if (result.status === 1) {
            console.log('[OK] Uploaded google-credentials.json');
            resolve(true);
          } else {
            console.log('[FAIL]', result.errors?.[0] || 'Unknown error');
            resolve(false);
          }
        } catch (e) {
          console.log('[FAIL] Parse error:', e.message);
          resolve(false);
        }
      });
    });

    req.on('error', (e) => {
      console.log('[FAIL]', e.message);
      resolve(false);
    });

    req.write(formData);
    req.end();
  });
}

async function main() {
  console.log('Uploading Google Credentials...\n');

  // Create credentials directory
  await createDirectory();

  // Upload the file
  const localFile = '../email-management-483417-adeaa29b2a6b.json';
  await uploadFile(localFile);

  console.log('\nDone!');
}

main().catch(console.error);
