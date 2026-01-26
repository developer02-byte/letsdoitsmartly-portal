/**
 * Force deploy CSS - Delete then upload
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
          resolve({ status: 0, error: 'Parse error', raw: data.substring(0, 200) });
        }
      });
    });

    req.on('error', (e) => resolve({ status: 0, error: e.message }));
    req.write(formData);
    req.end();
  });
}

async function deleteFile(remotePath) {
  console.log(`Step 1: Deleting old file: ${remotePath}`);

  const formData = new URLSearchParams({
    path: config.basePath + remotePath
  }).toString();

  const result = await makeRequest('/execute/Fileman/delete_files', formData);
  console.log('Delete result:', result.status === 1 ? 'OK' : 'SKIP (file may not exist)');
  return true;  // Continue even if delete fails
}

async function uploadFile(localPath, remotePath) {
  console.log(`\nStep 2: Uploading new file: ${remotePath}`);

  const content = fs.readFileSync(localPath, 'utf8');
  const dir = path.dirname(remotePath);
  const filename = path.basename(remotePath);

  console.log(`  File size: ${content.length} bytes`);
  console.log(`  Contains new color #00b4d8: ${content.includes('#00b4d8') ? 'YES ✓' : 'NO ✗'}`);
  console.log(`  Contains new color #023e8a: ${content.includes('#023e8a') ? 'YES ✓' : 'NO ✗'}`);

  const formData = new URLSearchParams({
    dir: config.basePath + dir,
    file: filename,
    content: content
  }).toString();

  const result = await makeRequest('/execute/Fileman/save_file_content', formData);

  if (result.status === 1) {
    console.log('Upload result: SUCCESS ✓');
    return true;
  } else {
    console.log('Upload result: FAILED ✗');
    console.log('Error:', result.errors || result.error || 'Unknown error');
    return false;
  }
}

async function verifyUpload() {
  console.log(`\nStep 3: Verifying upload...`);

  // Give server a moment to process
  await new Promise(resolve => setTimeout(resolve, 2000));

  console.log('Please verify at: https://letsdoitsmartly.com/portal/assets/css/style.css');
  console.log('Clear browser cache (Ctrl+F5) and check for:');
  console.log('  - Light mode: --primary: #00b4d8');
  console.log('  - Dark mode: --primary: #023e8a');
}

async function main() {
  console.log('='.repeat(70));
  console.log('FORCE CSS DEPLOYMENT - Delete & Upload');
  console.log('='.repeat(70));

  const localPath = path.resolve(__dirname, '../portal/assets/css/style.css');
  const remotePath = '/portal/assets/css/style.css';

  // Step 1: Delete old file
  await deleteFile(remotePath);

  // Step 2: Upload new file
  const success = await uploadFile(localPath, remotePath);

  if (success) {
    // Step 3: Verify
    await verifyUpload();

    console.log('\n' + '='.repeat(70));
    console.log('DEPLOYMENT COMPLETE');
    console.log('='.repeat(70));
  } else {
    console.log('\n' + '='.repeat(70));
    console.log('DEPLOYMENT FAILED');
    console.log('='.repeat(70));
  }
}

main();
