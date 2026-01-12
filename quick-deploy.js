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

async function uploadFile(content, remotePath) {
  return new Promise((resolve) => {
    const pathParts = remotePath.split('/');
    const filename = pathParts.pop();
    const dir = pathParts.join('/');

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

    console.log('Uploading ' + filename + '...');

    const req = https.request(options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          const result = JSON.parse(data);
          if (result.status === 1) {
            console.log('✓ ' + filename + ' uploaded successfully');
            resolve(true);
          } else {
            console.log('✗ Failed to upload ' + filename);
            console.log('Error:', result.errors?.[0] || 'Unknown error');
            resolve(false);
          }
        } catch (e) {
          console.log('✗ Failed to upload ' + filename);
          console.log('Error: Parse error');
          resolve(false);
        }
      });
    });

    req.on('error', (e) => {
      console.log('✗ Failed to upload ' + filename);
      console.log('Error:', e.message);
      resolve(false);
    });

    req.write(formData);
    req.end();
  });
}

async function main() {
  console.log('='.repeat(60));
  console.log('Questionnaire Template Deployment');
  console.log('='.repeat(60));
  console.log('Target: https://letsdoitsmartly.com/portal/template/\n');

  // Check if files exist
  const indexFile = 'index.php';
  const submitFile = 'submit.php';

  if (!fs.existsSync(indexFile)) {
    console.log('✗ Error: index.php not found in current directory');
    console.log('\nPlease ensure both index.php and submit.php are in:');
    console.log(process.cwd());
    return;
  }

  if (!fs.existsSync(submitFile)) {
    console.log('✗ Error: submit.php not found in current directory');
    console.log('\nPlease ensure both index.php and submit.php are in:');
    console.log(process.cwd());
    return;
  }

  console.log('Reading files...');
  const indexContent = fs.readFileSync(indexFile, 'utf8');
  const submitContent = fs.readFileSync(submitFile, 'utf8');

  console.log('✓ index.php (' + (indexContent.length / 1024).toFixed(1) + ' KB)');
  console.log('✓ submit.php (' + (submitContent.length / 1024).toFixed(1) + ' KB)\n');

  console.log('Uploading to cPanel...\n');

  const indexSuccess = await uploadFile(indexContent, '/portal/template/index.php');
  const submitSuccess = await uploadFile(submitContent, '/portal/template/submit.php');

  console.log('\n' + '='.repeat(60));
  if (indexSuccess && submitSuccess) {
    console.log('✓ Deployment completed successfully!\n');
    console.log('Access your questionnaire at:');
    console.log('→ https://letsdoitsmartly.com/portal/template/');
    console.log('→ http://43.225.55.146/portal/template/');
  } else {
    console.log('✗ Deployment completed with errors');
    console.log('Please check the errors above and try again.');
  }
  console.log('='.repeat(60));
}

main();
