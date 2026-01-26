/**
 * Force upload style.css to server
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

async function uploadFile() {
  const localPath = path.resolve(__dirname, '../portal/assets/css/style.css');
  const content = fs.readFileSync(localPath, 'utf8');

  console.log(`Reading local file: ${localPath}`);
  console.log(`File size: ${content.length} bytes`);
  console.log(`File lines: ${content.split('\n').length}`);

  const remotePath = '/portal/assets/css/style.css';
  const dir = '/portal/assets/css';
  const filename = 'style.css';

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

  return new Promise((resolve) => {
    console.log(`\nUploading to: ${remotePath}`);

    const req = https.request(options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          const result = JSON.parse(data);
          console.log('Response:', JSON.stringify(result, null, 2));
          if (result.status === 1) {
            console.log('✓ Upload successful!');
            resolve(true);
          } else {
            console.log('✗ Upload failed:', result.errors || result);
            resolve(false);
          }
        } catch (e) {
          console.log('✗ Parse error:', e.message);
          console.log('Raw response:', data.substring(0, 500));
          resolve(false);
        }
      });
    });

    req.on('error', (e) => {
      console.log('✗ Request error:', e.message);
      resolve(false);
    });

    req.write(formData);
    req.end();
  });
}

uploadFile().then(success => {
  console.log('\nDone!');
  process.exit(success ? 0 : 1);
});
