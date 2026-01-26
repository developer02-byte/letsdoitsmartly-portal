/**
 * Clear cache and deploy CSS
 */
const https = require('https');
const fs = require('fs');
const path = require('path');

const config = {
  hostname: 'bh-in-32.webhostbox.net',
  port: 2083,
  username: 'letsdoitadmin',
  token: 'P6WYJDG7L89HO9XN9QUDTXXP8TMKU0TV',
  basePath: '/home4/letsdoitadmin/public_html'
};

function makeRequest(endpoint, formData) {
  return new Promise((resolve) => {
    const options = {
      hostname: config.hostname,
      port: config.port,
      path: endpoint,
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
          resolve(result);
        } catch (e) {
          resolve({ status: 0, error: 'Parse error' });
        }
      });
    });

    req.on('error', (e) => resolve({ status: 0, error: e.message }));
    req.write(formData);
    req.end();
  });
}

async function uploadWithTimestamp(localPath, remotePath) {
  console.log(`Uploading: ${remotePath}`);

  const content = fs.readFileSync(localPath, 'utf8');

  // Add timestamp comment at the top to force cache bust
  const timestamp = new Date().toISOString();
  const contentWithTimestamp = `/* Deployed: ${timestamp} */\n${content}`;

  const dir = path.dirname(remotePath);
  const filename = path.basename(remotePath);

  const formData = new URLSearchParams({
    dir: config.basePath + dir,
    file: filename,
    content: contentWithTimestamp
  }).toString();

  const result = await makeRequest('/execute/Fileman/save_file_content', formData);

  console.log(`Result: ${result.status === 1 ? 'SUCCESS' : 'FAILED'}`);
  return result.status === 1;
}

async function touchFile(remotePath) {
  console.log(`Touching file to update timestamp: ${remotePath}`);

  const formData = new URLSearchParams({
    file: config.basePath + remotePath
  }).toString();

  await makeRequest('/execute/Fileman/autoindex', formData);
}

async function main() {
  console.log('='.repeat(70));
  console.log('DEPLOY CSS WITH CACHE BUSTING');
  console.log('='.repeat(70));

  const localPath = path.resolve(__dirname, '../portal/assets/css/style.css');
  const remotePath = '/portal/assets/css/style.css';

  const success = await uploadWithTimestamp(localPath, remotePath);

  if (success) {
    await touchFile(remotePath);

    console.log('\n' + '='.repeat(70));
    console.log('DEPLOYMENT COMPLETE');
    console.log('='.repeat(70));
    console.log('\nIMPORTANT: Hard refresh your browser:');
    console.log('  - Chrome/Edge: Ctrl + Shift + R');
    console.log('  - Firefox: Ctrl + F5');
    console.log('  - Mac: Cmd + Shift + R');
    console.log('\nURL: https://letsdoitsmartly.com/portal/');
  }
}

main();
