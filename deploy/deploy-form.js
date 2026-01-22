/**
 * Deploy form.php and style.css to server
 * Quick deployment for questionnaire form changes
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
        console.log(file + ':', success ? '✓ OK' : '✗ FAIL');
        if (!success) console.log('Response:', data);
        resolve(success);
      });
    });

    req.on('error', (e) => {
      console.log(file + ': ✗ ERROR -', e.message);
      resolve(false);
    });
    req.write(formData);
    req.end();
  });
}

async function clearOPCache() {
  console.log('\nClearing OPcache...');
  return new Promise((resolve) => {
    const req = http.request({
      hostname: '43.225.55.146',
      port: 80,
      path: '/portal/clear-cache.php',
      method: 'GET',
      timeout: 10000,
      headers: { 'Host': 'letsdoitsmartly.com' }
    }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        console.log('Cache clear:', res.statusCode === 200 ? '✓ OK' : '✗ FAIL');
        resolve(true);
      });
    });
    req.on('error', () => {
      console.log('Cache clear: ✗ FAIL (non-critical)');
      resolve(true);
    });
    req.on('timeout', () => {
      req.destroy();
      console.log('Cache clear: ✗ TIMEOUT (non-critical)');
      resolve(true);
    });
    req.end();
  });
}

async function main() {
  console.log('╔════════════════════════════════════════════════════════╗');
  console.log('║     Deploying Responsive Form Updates                 ║');
  console.log('╚════════════════════════════════════════════════════════╝\n');

  let success = true;

  // Upload form.php
  console.log('📄 Uploading form.php...');
  const formResult = await uploadFile(
    'c:/Projects/letsdoitsmartly-portal-main/portal/form.php',
    '/portal/form.php'
  );
  success = success && formResult;

  // Upload style.css
  console.log('\n🎨 Uploading style.css...');
  const cssResult = await uploadFile(
    'c:/Projects/letsdoitsmartly-portal-main/portal/assets/css/style.css',
    '/portal/assets/css/style.css'
  );
  success = success && cssResult;

  // Clear cache
  await clearOPCache();

  console.log('\n╔════════════════════════════════════════════════════════╗');
  if (success) {
    console.log('║     ✓ Deployment Complete!                            ║');
    console.log('╚════════════════════════════════════════════════════════╝');
    console.log('\n🌐 Test your responsive form at:');
    console.log('   https://letsdoitsmartly.com/portal/form.php\n');
    console.log('📱 Test on different devices:');
    console.log('   • Mobile: < 576px');
    console.log('   • Tablet: 768px - 991px');
    console.log('   • Laptop: 992px - 1199px');
    console.log('   • Desktop: > 1200px\n');
  } else {
    console.log('║     ✗ Deployment had errors - check logs above        ║');
    console.log('╚════════════════════════════════════════════════════════╝');
  }
}

main();
