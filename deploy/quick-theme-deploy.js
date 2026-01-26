/**
 * Quick Theme Deployment Script
 * Uploads only the theme-related files (CSS, JS, Sidebar)
 */

const https = require('https');
const fs = require('fs');
const path = require('path');

// Configuration
const config = {
  hostname: process.env.CPANEL_HOSTNAME || 'bh-in-32.webhostbox.net',
  port: 2083,
  username: process.env.CPANEL_USERNAME || 'letsdoitadmin',
  token: process.env.CPANEL_TOKEN || 'P6WYJDG7L89HO9XN9QUDTXXP8TMKU0TV',
  basePath: process.env.CPANEL_BASE_PATH || '/home4/letsdoitadmin/public_html'
};

// Files to deploy (theme-related only)
const filesToDeploy = [
  { local: '../portal/assets/css/style.css', remote: '/portal/assets/css/style.css' },
  { local: '../portal/assets/js/app.js', remote: '/portal/assets/js/app.js' },
  { local: '../portal/templates/header.php', remote: '/portal/templates/header.php' },
  { local: '../portal/templates/footer.php', remote: '/portal/templates/footer.php' },
  { local: '../portal/emails.php', remote: '/portal/emails.php' }
];

async function uploadFile(localPath, remotePath) {
  return new Promise(async (resolve, reject) => {
    const fullLocalPath = path.resolve(__dirname, localPath);

    if (!fs.existsSync(fullLocalPath)) {
      resolve({ success: false, path: remotePath, error: 'File not found locally' });
      return;
    }

    const content = fs.readFileSync(fullLocalPath, 'utf8');
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
            resolve({ success: true, path: remotePath });
          } else {
            resolve({ success: false, path: remotePath, error: result.errors?.[0] || 'Unknown error' });
          }
        } catch (e) {
          resolve({ success: false, path: remotePath, error: 'Parse error: ' + e.message });
        }
      });
    });

    req.on('error', (e) => {
      resolve({ success: false, path: remotePath, error: e.message });
    });

    req.write(formData);
    req.end();
  });
}

async function deploy() {
  console.log('='.repeat(70));
  console.log('Quick Theme Deployment - UI Updates');
  console.log('='.repeat(70));
  console.log(`Host: ${config.hostname}`);
  console.log(`User: ${config.username}`);
  console.log(`Deploying: ${filesToDeploy.length} files`);
  console.log('='.repeat(70) + '\n');

  let successCount = 0;
  let failCount = 0;

  for (const file of filesToDeploy) {
    process.stdout.write(`Uploading ${path.basename(file.remote)}... `);

    const result = await uploadFile(file.local, file.remote);

    if (result.success) {
      console.log('[OK]');
      successCount++;
    } else {
      console.log(`[FAIL] ${result.error}`);
      failCount++;
    }
  }

  console.log('\n' + '='.repeat(70));
  console.log(`Deployment Complete`);
  console.log(`  Success: ${successCount} | Failed: ${failCount}`);
  console.log('='.repeat(70));

  if (successCount === filesToDeploy.length) {
    console.log('\n✓ All files deployed successfully!');
    console.log('\nUpdates deployed:');
    console.log('  - Modern light-colored action buttons');
    console.log('  - Dark mode flicker fix');
    console.log('\nTest it at: https://letsdoitsmartly.com/portal/');
  } else {
    console.log('\n⚠ Some files failed to deploy. Check the errors above.');
  }
}

// Run deployment
deploy().catch(console.error);
