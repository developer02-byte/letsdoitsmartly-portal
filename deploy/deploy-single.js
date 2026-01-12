/**
 * Deploy single file to server
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

async function uploadFile(localPath, remotePath) {
  return new Promise((resolve) => {
    const content = fs.readFileSync(localPath, 'utf8');
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
        const success = data.includes('"status":1');
        console.log(file + ':', success ? 'OK' : 'FAIL');
        resolve(success);
      });
    });

    req.on('error', (e) => {
      console.log(file + ': ERROR -', e.message);
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
      timeout: 120000,
      headers: { 'Host': 'letsdoitsmartly.com' }
    }, (res) => {
      console.log('Status:', res.statusCode);
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
  console.log('Deploying emails.php...\n');

  await uploadFile(
    'c:/Projects/letsdoitsmartly-portal-main/letsdoitsmartly-portal-main/portal/emails.php',
    '/portal/emails.php'
  );

  console.log('\nDone!');
}

main();
