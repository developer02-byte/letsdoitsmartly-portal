const https = require('https');
const fs = require('fs');

const config = {
  hostname: 'bh-in-32.webhostbox.net',
  port: 2083,
  username: 'letsdoitadmin',
  token: 'P6WYJDG7L89HO9XN9QUDTXXP8TMKU0TV',
  basePath: '/home4/letsdoitadmin/public_html'
};

async function uploadFileContent(content, remotePath) {
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

    const req = https.request(options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          const result = JSON.parse(data);
          if (result.status === 1) {
            console.log('[OK] ' + remotePath);
            resolve(true);
          } else {
            console.log('[FAIL] ' + remotePath + ': ' + (result.errors?.[0] || 'Unknown error'));
            resolve(false);
          }
        } catch (e) {
          console.log('[FAIL] ' + remotePath + ': Parse error');
          resolve(false);
        }
      });
    });

    req.on('error', (e) => {
      console.log('[FAIL] ' + remotePath + ': ' + e.message);
      resolve(false);
    });

    req.write(formData);
    req.end();
  });
}

// Read the files from the user's documents
const indexPath = process.argv[2] || 'index.php';
const submitPath = process.argv[3] || 'submit.php';

async function main() {
  console.log('Uploading questionnaire template to cPanel...\n');

  try {
    const indexContent = fs.readFileSync(indexPath, 'utf8');
    const submitContent = fs.readFileSync(submitPath, 'utf8');

    console.log('Uploading index.php...');
    await uploadFileContent(indexContent, '/portal/template/index.php');

    console.log('Uploading submit.php...');
    await uploadFileContent(submitContent, '/portal/template/submit.php');

    console.log('\n✓ Template files uploaded successfully!');
    console.log('\nAccess the questionnaire at:');
    console.log('https://letsdoitsmartly.com/portal/template/');
    console.log('OR');
    console.log('http://43.225.55.146/portal/template/');
  } catch (error) {
    console.error('Error:', error.message);
    console.log('\nPlease provide the paths to index.php and submit.php as arguments.');
    console.log('Usage: node deploy-questionnaire.js <path-to-index.php> <path-to-submit.php>');
  }
}

main();
