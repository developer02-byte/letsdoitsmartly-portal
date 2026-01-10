/**
 * Run migration on remote server
 */

const https = require('https');
const http = require('http');

const config = {
  hostname: 'bh-in-32.webhostbox.net',
  port: 2083,
  username: 'letsdoitadmin',
  token: 'P6WYJDG7L89HO9XN9QUDTXXP8TMKU0TV',
  basePath: '/home4/letsdoitadmin/public_html'
};

const migrationScript = `<?php
define('PORTAL_ACCESS', true);
require_once __DIR__ . '/includes/config.php';
header('Content-Type: text/plain');
$p = $google_config['api_path'] . '/vendor/autoload.php';
require_once $p;
$c = new Google_Client();
$c->setAuthConfig($google_config['credentials_file']);
$c->setScopes($google_config['scopes']);
$c->setSubject($google_config['admin_email']);
echo "Scopes: " . count($google_config['scopes']) . "\\n";
foreach($google_config['scopes'] as $s) echo "- ".basename($s)."\\n";
$svc = new Google_Service_Directory($c);
$u = $svc->users->listUsers(['customer'=>'my_customer','maxResults'=>1]);
echo "\\nTest: " . (count($u->getUsers())>0 ? "OK" : "FAIL") . "\\n";
`;

async function uploadFile(content, remotePath) {
  return new Promise((resolve) => {
    const fullPath = config.basePath + remotePath;
    const dir = fullPath.substring(0, fullPath.lastIndexOf('/'));
    const file = fullPath.substring(fullPath.lastIndexOf('/') + 1);

    const formData = new URLSearchParams({
      dir: dir,
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
        console.log('   Response:', data.substring(0, 200));
        try {
          const result = JSON.parse(data);
          resolve(result.status === 1);
        } catch (e) {
          console.log('   Parse error:', e.message);
          resolve(false);
        }
      });
    });

    req.on('error', (e) => {
      console.log('   Request error:', e.message);
      resolve(false);
    });
    req.write(formData);
    req.end();
  });
}

async function runScript() {
  return new Promise((resolve) => {
    const req = http.request({
      hostname: '43.225.55.146',
      port: 80,
      path: '/portal/run_migration_temp.php',
      method: 'GET',
      timeout: 60000,
      headers: { 'Host': 'letsdoitsmartly.com' }
    }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => resolve(data));
    });
    req.on('error', (e) => resolve('Error: ' + e.message));
    req.on('timeout', () => { req.destroy(); resolve('Timeout'); });
    req.end();
  });
}

async function secureFile() {
  const blockContent = '<?php http_response_code(403); die("Access Denied");';
  return uploadFile(blockContent, '/portal/run_migration_temp.php');
}

async function main() {
  console.log('1. Uploading migration script...');
  const uploaded = await uploadFile(migrationScript, '/portal/run_migration_temp.php');
  if (!uploaded) {
    console.log('Failed to upload migration script');
    return;
  }
  console.log('   Done\n');

  console.log('2. Running migration...\n');
  const result = await runScript();
  console.log(result);

  console.log('\n3. Securing temporary file...');
  await secureFile();
  console.log('   Done');
}

main().catch(console.error);
