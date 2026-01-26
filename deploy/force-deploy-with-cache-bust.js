/**
 * Force Deploy with Cache Busting
 * Uploads files and adds cache-busting version to CSS/JS links
 */
const https = require('https');
const http = require('http');
const fs = require('fs');
const path = require('path');

const config = {
  hostname: 'bh-in-32.webhostbox.net',
  port: 2083,
  username: 'letsdoitadmin',
  token: 'P6WYJDG7L89HO9XN9QUDTXXP8TMKU0TV',
  basePath: '/home4/letsdoitadmin/public_html'
};

// Files to deploy
const filesToDeploy = [
  { local: 'portal/assets/css/style.css', remote: '/portal/assets/css/style.css' },
  { local: 'portal/assets/js/app.js', remote: '/portal/assets/js/app.js' },
  { local: 'portal/form.php', remote: '/portal/form.php' },
  { local: 'portal/form_handler.php', remote: '/portal/form_handler.php' }
];

async function uploadFile(localPath, remotePath) {
  return new Promise((resolve) => {
    const fullLocalPath = path.join(__dirname, '..', localPath);

    if (!fs.existsSync(fullLocalPath)) {
      console.log('❌ File not found:', localPath);
      resolve(false);
      return;
    }

    let content = fs.readFileSync(fullLocalPath, 'utf8');

    // Add cache busting version to form.php for CSS/JS includes
    if (localPath.includes('form.php')) {
      const version = Date.now();
      content = content.replace(
        /assets\/css\/style\.css(\?v=\d+)?"/g,
        `assets/css/style.css?v=${version}"`
      );
      content = content.replace(
        /assets\/js\/app\.js(\?v=\d+)?"/g,
        `assets/js/app.js?v=${version}"`
      );
      console.log('  🔄 Added cache busting version:', version);
    }

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
        if (success) {
          console.log('✅', file);
        } else {
          console.log('❌', file, '- Upload failed');
        }
        resolve(success);
      });
    });

    req.on('error', (e) => {
      console.log('❌', file, '- ERROR:', e.message);
      resolve(false);
    });

    req.write(formData);
    req.end();
  });
}

async function deploy() {
  console.log('🚀 Force Deploying with Cache Busting...\n');
  console.log('📁 Files to deploy:', filesToDeploy.length);
  console.log('');

  let successCount = 0;
  let failCount = 0;

  // Upload files in order
  for (const file of filesToDeploy) {
    const success = await uploadFile(file.local, file.remote);
    if (success) {
      successCount++;
    } else {
      failCount++;
    }
    await new Promise(resolve => setTimeout(resolve, 300));
  }

  console.log('\n📊 Upload Summary:');
  console.log(`✅ Success: ${successCount}`);
  console.log(`❌ Failed: ${failCount}`);

  console.log('\n✨ Deployment complete with cache busting!');
  console.log('📍 Visit: https://letsdoitsmartly.com/portal/form.php?nocache=' + Date.now());
  console.log('\n💡 Hard refresh the page: Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac)');
}

deploy().catch(console.error);
