/**
 * cPanel API Deployment Script
 * Uploads files directly to server via cPanel UAPI
 */

const https = require('https');
const fs = require('fs');
const path = require('path');

// Configuration - uses environment variables for CI/CD, falls back to defaults for local
const config = {
  hostname: process.env.CPANEL_HOSTNAME || 'bh-in-32.webhostbox.net',
  port: 2083,
  username: process.env.CPANEL_USERNAME || 'letsdoitadmin',
  token: process.env.CPANEL_TOKEN || 'P6WYJDG7L89HO9XN9QUDTXXP8TMKU0TV',
  basePath: process.env.CPANEL_BASE_PATH || '/home4/letsdoitadmin/public_html'
};

// Directories to deploy
const deployDirs = [
  { local: '../portal', remote: '/portal' }
  // { local: '../google-api', remote: '/google-api' }  // Uncomment when needed
];

// Files to skip
const skipPatterns = [
  /node_modules/,
  /\.git/,
  /\.env$/,
  /\.DS_Store/,
  /Thumbs\.db/
];

function shouldSkip(filePath) {
  return skipPatterns.some(pattern => pattern.test(filePath));
}

// Track created directories to avoid duplicate calls
const createdDirs = new Set();

async function createDirectory(remotePath) {
  if (createdDirs.has(remotePath) || remotePath === '' || remotePath === '/') {
    return true;
  }

  // First ensure parent directory exists
  const parentDir = path.dirname(remotePath);
  if (parentDir !== remotePath && parentDir !== '/' && parentDir !== '.') {
    await createDirectory(parentDir);
  }

  return new Promise((resolve) => {
    const parentPath = path.dirname(remotePath);
    const dirName = path.basename(remotePath);

    const formData = new URLSearchParams({
      path: config.basePath + parentPath,
      name: dirName
    }).toString();

    const options = {
      hostname: config.hostname,
      port: config.port,
      path: '/execute/Fileman/mkdir',
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
        createdDirs.add(remotePath);
        resolve(true);  // Ignore errors - directory might already exist
      });
    });

    req.on('error', () => resolve(true));
    req.write(formData);
    req.end();
  });
}

async function uploadFile(localPath, remotePath) {
  return new Promise(async (resolve, reject) => {
    const content = fs.readFileSync(localPath, 'utf8');
    const dir = path.dirname(remotePath);
    const filename = path.basename(remotePath);

    // Ensure directory exists
    await createDirectory(dir);

    // Use POST with form data for proper content handling
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
      rejectUnauthorized: false // Allow self-signed certs
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

function getAllFiles(dirPath, basePath = '') {
  const files = [];
  const items = fs.readdirSync(dirPath);

  for (const item of items) {
    const fullPath = path.join(dirPath, item);
    const relativePath = path.join(basePath, item);

    if (shouldSkip(fullPath)) continue;

    const stat = fs.statSync(fullPath);
    if (stat.isDirectory()) {
      files.push(...getAllFiles(fullPath, relativePath));
    } else {
      files.push({ local: fullPath, relative: relativePath });
    }
  }

  return files;
}

async function deploy() {
  console.log('='.repeat(60));
  console.log('cPanel Deployment');
  console.log('='.repeat(60));
  console.log(`Host: ${config.hostname}`);
  console.log(`User: ${config.username}`);
  console.log(`Base: ${config.basePath}`);
  console.log('='.repeat(60) + '\n');

  let totalFiles = 0;
  let successCount = 0;
  let failCount = 0;

  for (const dir of deployDirs) {
    const localDir = path.resolve(__dirname, dir.local);

    if (!fs.existsSync(localDir)) {
      console.log(`[SKIP] Directory not found: ${localDir}`);
      continue;
    }

    console.log(`\nDeploying ${dir.local} -> ${dir.remote}`);
    console.log('-'.repeat(40));

    const files = getAllFiles(localDir);
    totalFiles += files.length;

    for (const file of files) {
      const remotePath = dir.remote + '/' + file.relative.replace(/\\/g, '/');
      process.stdout.write(`  ${remotePath}... `);

      const result = await uploadFile(file.local, remotePath);

      if (result.success) {
        console.log('[OK]');
        successCount++;
      } else {
        console.log(`[FAIL] ${result.error}`);
        failCount++;
      }
    }
  }

  console.log('\n' + '='.repeat(60));
  console.log(`Deployment Complete`);
  console.log(`  Total: ${totalFiles} | Success: ${successCount} | Failed: ${failCount}`);
  console.log('='.repeat(60));
}

// Run deployment
deploy().catch(console.error);
