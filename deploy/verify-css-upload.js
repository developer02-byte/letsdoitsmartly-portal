/**
 * Verify style.css on server
 */
const https = require('https');

const config = {
  hostname: 'bh-in-32.webhostbox.net',
  port: 2083,
  username: 'letsdoitadmin',
  token: 'P6WYJDG7L89HO9XN9QUDTXXP8TMKU0TV',
  basePath: '/home4/letsdoitadmin/public_html'
};

async function readFile() {
  return new Promise((resolve) => {
    const dir = config.basePath + '/portal/assets/css';
    const file = 'style.css';

    const formData = new URLSearchParams({
      dir: dir,
      file: file
    }).toString();

    const options = {
      hostname: config.hostname,
      port: config.port,
      path: '/execute/Fileman/get_file_content',
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
          if (result.status === 1 && result.data) {
            const content = result.data.content || '';
            const lines = content.split('\n').length;
            const size = content.length;
            const darkModeCount = (content.match(/data-theme="dark"/g) || []).length;

            console.log('Server file statistics:');
            console.log('  Size:', size, 'bytes');
            console.log('  Lines:', lines);
            console.log('  Dark mode selectors:', darkModeCount);

            // Check for specific comments
            if (content.includes('Password Requirements in Dark Mode')) {
              console.log('  ✓ Contains "Password Requirements in Dark Mode"');
            } else {
              console.log('  ✗ Missing "Password Requirements in Dark Mode"');
            }

            if (content.includes('Input Group Buttons in Dark Mode')) {
              console.log('  ✓ Contains "Input Group Buttons in Dark Mode"');
            } else {
              console.log('  ✗ Missing "Input Group Buttons in Dark Mode"');
            }

            resolve(true);
          } else {
            console.log('Failed to read file:', result.errors || 'Unknown error');
            resolve(false);
          }
        } catch (e) {
          console.log('Parse error:', e.message);
          resolve(false);
        }
      });
    });

    req.on('error', (e) => {
      console.log('Request error:', e.message);
      resolve(false);
    });

    req.write(formData);
    req.end();
  });
}

readFile();
