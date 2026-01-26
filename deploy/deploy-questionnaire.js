/**
 * Deploy Questionnaire Form to cPanel
 * Uploads form.php, form_handler.php, updated navigation, and CSS
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
  { local: 'portal/form.php', remote: '/portal/form.php' },
  { local: 'portal/form_handler.php', remote: '/portal/form_handler.php' },
  { local: 'portal/includes/navigation.php', remote: '/portal/includes/navigation.php' },
  { local: 'portal/assets/css/style.css', remote: '/portal/assets/css/style.css' },
  { local: 'portal/assets/js/app.js', remote: '/portal/assets/js/app.js' }
];

async function uploadFile(localPath, remotePath) {
  return new Promise((resolve) => {
    const fullLocalPath = path.join(__dirname, '..', localPath);

    if (!fs.existsSync(fullLocalPath)) {
      console.log('❌ File not found:', localPath);
      resolve(false);
      return;
    }

    const content = fs.readFileSync(fullLocalPath, 'utf8');
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

async function runMigration() {
  console.log('\n📦 Running database migration...');
  return new Promise((resolve) => {
    const req = http.request({
      hostname: '43.225.55.146',
      port: 80,
      path: '/portal/install/run_migration.php?migration=013_questionnaire_submissions',
      method: 'GET',
      timeout: 30000,
      headers: { 'Host': 'letsdoitsmartly.com' }
    }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        if (data.includes('success') || data.includes('already exists')) {
          console.log('✅ Database migration completed');
          resolve(true);
        } else {
          console.log('❌ Migration failed:', data);
          resolve(false);
        }
      });
    });

    req.on('error', (e) => {
      console.log('❌ Migration error:', e.message);
      resolve(false);
    });

    req.on('timeout', () => {
      req.destroy();
      console.log('❌ Migration timeout');
      resolve(false);
    });

    req.end();
  });
}

async function deploy() {
  console.log('🚀 Starting Questionnaire Form Deployment...\n');
  console.log('📁 Files to deploy:', filesToDeploy.length);
  console.log('');

  let successCount = 0;
  let failCount = 0;

  // Upload files
  for (const file of filesToDeploy) {
    const success = await uploadFile(file.local, file.remote);
    if (success) {
      successCount++;
    } else {
      failCount++;
    }
    // Small delay between uploads
    await new Promise(resolve => setTimeout(resolve, 200));
  }

  console.log('\n📊 Upload Summary:');
  console.log(`✅ Success: ${successCount}`);
  console.log(`❌ Failed: ${failCount}`);

  // Run migration
  if (successCount > 0) {
    await runMigration();
  }

  console.log('\n✨ Deployment complete!');
  console.log('📍 Visit: https://letsdoitsmartly.com/portal/form.php');
}

deploy().catch(console.error);
