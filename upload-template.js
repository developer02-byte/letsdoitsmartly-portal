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

async function uploadFile(localPath, remotePath) {
  return new Promise((resolve, reject) => {
    const content = fs.readFileSync(localPath, 'utf8');
    const dir = path.dirname(remotePath);
    const filename = path.basename(remotePath);

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
            console.log('[OK] ' + remotePath);
            resolve({ success: true, path: remotePath });
          } else {
            console.log('[FAIL] ' + remotePath + ': ' + (result.errors?.[0] || 'Unknown error'));
            resolve({ success: false, path: remotePath, error: result.errors?.[0] || 'Unknown error' });
          }
        } catch (e) {
          console.log('[FAIL] ' + remotePath + ': Parse error');
          resolve({ success: false, path: remotePath, error: 'Parse error: ' + e.message });
        }
      });
    });

    req.on('error', (e) => {
      console.log('[FAIL] ' + remotePath + ': ' + e.message);
      resolve({ success: false, path: remotePath, error: e.message });
    });

    req.write(formData);
    req.end();
  });
}

async function main() {
  console.log('Uploading template files to cPanel...\n');

  const files = [
    {
      local: 'letsdoitsmartly-portal-main/portal/template/index.php',
      remote: '/portal/template/index.php'
    },
    {
      local: 'letsdoitsmartly-portal-main/portal/template/submit.php',
      remote: '/portal/template/submit.php'
    }
  ];

  for (const file of files) {
    await uploadFile(file.local, file.remote);
  }

  console.log('\n✓ Template files uploaded successfully!');
  console.log('Access the questionnaire at: https://letsdoitsmartly.com/portal/template/');
}

main();
