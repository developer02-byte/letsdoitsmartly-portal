/**
 * Deploy Complete Theme Changes
 * Uploads: style.css, sidebar.php, app.js
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

const filesToDeploy = [
  {
    local: '../portal/assets/css/style.css',
    remote: '/portal/assets/css/style.css',
    name: 'CSS (Theme Colors)'
  },
  {
    local: '../portal/templates/sidebar.php',
    remote: '/portal/templates/sidebar.php',
    name: 'Sidebar (Toggle Button HTML)'
  },
  {
    local: '../portal/assets/js/app.js',
    remote: '/portal/assets/js/app.js',
    name: 'JavaScript (Toggle Button)'
  }
];

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

async function uploadFile(localPath, remotePath, name) {
  const fullLocalPath = path.resolve(__dirname, localPath);

  if (!fs.existsSync(fullLocalPath)) {
    return { success: false, error: 'File not found locally' };
  }

  const content = fs.readFileSync(fullLocalPath, 'utf8');
  const dir = path.dirname(remotePath);
  const filename = path.basename(remotePath);

  // Add timestamp comment for cache busting
  const timestamp = new Date().toISOString();
  const contentWithTimestamp = `/* Updated: ${timestamp} */\n${content}`;

  const formData = new URLSearchParams({
    dir: config.basePath + dir,
    file: filename,
    content: filename.endsWith('.css') || filename.endsWith('.js') ? contentWithTimestamp : content
  }).toString();

  const result = await makeRequest('/execute/Fileman/save_file_content', formData);

  return {
    success: result.status === 1,
    error: result.errors || result.error,
    size: content.length
  };
}

async function main() {
  console.log('='.repeat(70));
  console.log('🚀 DEPLOYING COMPLETE THEME CHANGES');
  console.log('='.repeat(70));
  console.log('Server:', config.hostname);
  console.log('Files:', filesToDeploy.length);
  console.log('='.repeat(70) + '\n');

  let successCount = 0;
  let failCount = 0;

  for (const file of filesToDeploy) {
    process.stdout.write(`📤 ${file.name}... `);

    const result = await uploadFile(file.local, file.remote, file.name);

    if (result.success) {
      console.log(`✅ OK (${(result.size / 1024).toFixed(1)}KB)`);
      successCount++;
    } else {
      console.log(`❌ FAILED`);
      if (result.error) console.log(`   Error: ${result.error}`);
      failCount++;
    }
  }

  console.log('\n' + '='.repeat(70));
  console.log('📊 DEPLOYMENT SUMMARY');
  console.log('='.repeat(70));
  console.log(`✅ Success: ${successCount}/${filesToDeploy.length}`);
  console.log(`❌ Failed: ${failCount}/${filesToDeploy.length}`);
  console.log('='.repeat(70));

  if (successCount === filesToDeploy.length) {
    console.log('\n🎉 ALL FILES DEPLOYED SUCCESSFULLY!\n');
    console.log('🔗 Test it at: https://letsdoitsmartly.com/portal/\n');
    console.log('⚠️  IMPORTANT: Hard refresh your browser:');
    console.log('   • Chrome/Edge/Firefox: Ctrl + Shift + R');
    console.log('   • Mac: Cmd + Shift + R\n');
    console.log('✨ What to expect:');
    console.log('   • Light Mode: Soft blue sidebar (#00b4d8)');
    console.log('   • Dark Mode: Deep blue sidebar (#023e8a)');
    console.log('   • Modern toggle button: 🌙 Dark / ☀️ Light\n');
  } else {
    console.log('\n⚠️  SOME FILES FAILED TO DEPLOY');
    console.log('Check the errors above and try again.\n');
  }
}

main().catch(console.error);
