/**
 * Deploy CMS conditional logic changes
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

async function uploadFile(localPath, remotePath, retries = 3) {
  for (let attempt = 1; attempt <= retries; attempt++) {
    try {
      const success = await uploadFileAttempt(localPath, remotePath);
      if (success) return true;
      if (attempt < retries) {
        console.log(`  Retrying (${attempt}/${retries})...`);
        await sleep(2000);
      }
    } catch (e) {
      console.log(`  Attempt ${attempt} failed:`, e.message);
      if (attempt < retries) {
        await sleep(2000);
      }
    }
  }
  return false;
}

function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

async function uploadFileAttempt(localPath, remotePath) {
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
      rejectUnauthorized: false,
      timeout: 60000
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

    req.on('timeout', () => {
      req.destroy();
      console.log(file + ': TIMEOUT');
      resolve(false);
    });

    req.on('error', (e) => {
      console.log(file + ': ERROR -', e.message);
      resolve(false);
    });

    req.write(formData);
    req.end();
  });
}

async function main() {
  console.log('Deploying Section 7 conditional logic changes...\n');
  console.log('Changes:');
  console.log('- Domain name field: show only when "Domain owned?" = Yes');
  console.log('- Hosting provider field: show only when "Hosting account exists?" = Yes');
  console.log('- Webmail Other option: added with conditional text field');
  console.log('- Integrations & Analytics section: removed');
  console.log('- CMS conditional logic: preserved\n');

  // Deploy form.php
  await uploadFile(
    'c:/Projects/letsdoitsmartly-portal-main/portal/form.php',
    '/portal/form.php'
  );

  // Deploy app.js
  await uploadFile(
    'c:/Projects/letsdoitsmartly-portal-main/portal/assets/js/app.js',
    '/portal/assets/js/app.js'
  );

  console.log('\nDeployment complete!');
  console.log('View at: https://letsdoitsmartly.com/portal/form.php');
}

main();
