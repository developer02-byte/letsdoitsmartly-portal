/**
 * Verify Deployment - Check what's actually on the server
 */
const https = require('https');
const http = require('http');

// Check CSS file for disabled-option class
function checkCSS() {
  return new Promise((resolve) => {
    console.log('📝 Checking CSS file on server...');

    const req = http.request({
      hostname: '43.225.55.146',
      port: 80,
      path: '/portal/assets/css/style.css?v=' + Date.now(),
      method: 'GET',
      headers: { 'Host': 'letsdoitsmartly.com' }
    }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        const hasDisabledOption = data.includes('.disabled-option');
        const hasNewColor = data.includes('#0D6EFD');

        console.log('  ✅ CSS file loaded');
        console.log('  ' + (hasDisabledOption ? '✅' : '❌') + ' .disabled-option class found');
        console.log('  ' + (hasNewColor ? '✅' : '❌') + ' New color #0D6EFD found');
        resolve(hasDisabledOption && hasNewColor);
      });
    });

    req.on('error', (e) => {
      console.log('  ❌ Error loading CSS:', e.message);
      resolve(false);
    });

    req.end();
  });
}

// Check JS file for disabled-option class
function checkJS() {
  return new Promise((resolve) => {
    console.log('\n📝 Checking JS file on server...');

    const req = http.request({
      hostname: '43.225.55.146',
      port: 80,
      path: '/portal/assets/js/app.js?v=' + Date.now(),
      method: 'GET',
      headers: { 'Host': 'letsdoitsmartly.com' }
    }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        const hasDisabledOption = data.includes('disabled-option');
        const hasPrimarySecondary = data.includes('initPrimarySecondaryPurpose');

        console.log('  ✅ JS file loaded');
        console.log('  ' + (hasDisabledOption ? '✅' : '❌') + ' disabled-option class usage found');
        console.log('  ' + (hasPrimarySecondary ? '✅' : '❌') + ' initPrimarySecondaryPurpose function found');
        resolve(hasDisabledOption && hasPrimarySecondary);
      });
    });

    req.on('error', (e) => {
      console.log('  ❌ Error loading JS:', e.message);
      resolve(false);
    });

    req.end();
  });
}

// Check form.php for contact persons and scope
function checkForm() {
  return new Promise((resolve) => {
    console.log('\n📝 Checking form.php on server...');

    const req = http.request({
      hostname: '43.225.55.146',
      port: 80,
      path: '/portal/form.php?v=' + Date.now(),
      method: 'GET',
      headers: { 'Host': 'letsdoitsmartly.com' }
    }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        const hasContactPersonsTable = data.includes('contactPersonsTable');
        const hasScope = data.includes('Scope');

        console.log('  ✅ form.php loaded');
        console.log('  ' + (hasContactPersonsTable ? '✅' : '❌') + ' Contact persons table found');
        console.log('  ' + (hasScope ? '✅' : '❌') + ' Scope field found');
        resolve(hasContactPersonsTable && hasScope);
      });
    });

    req.on('error', (e) => {
      console.log('  ❌ Error loading form.php:', e.message);
      resolve(false);
    });

    req.end();
  });
}

async function verify() {
  console.log('🔍 Verifying Deployment on Live Server\n');
  console.log('═══════════════════════════════════════\n');

  const cssOk = await checkCSS();
  const jsOk = await checkJS();
  const formOk = await checkForm();

  console.log('\n═══════════════════════════════════════');
  console.log('\n📊 Verification Summary:');
  console.log('  CSS: ' + (cssOk ? '✅ VERIFIED' : '❌ FAILED'));
  console.log('  JavaScript: ' + (jsOk ? '✅ VERIFIED' : '❌ FAILED'));
  console.log('  Form HTML: ' + (formOk ? '✅ VERIFIED' : '❌ FAILED'));

  if (cssOk && jsOk && formOk) {
    console.log('\n✅ All changes verified on server!');
    console.log('\n💡 If changes not visible:');
    console.log('   1. Hard refresh: Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac)');
    console.log('   2. Clear browser cache');
    console.log('   3. Try incognito/private browsing mode');
  } else {
    console.log('\n⚠️  Some changes not found on server. Re-deploying may be needed.');
  }

  console.log('\n🌐 URL: https://letsdoitsmartly.com/portal/form.php?v=' + Date.now());
}

verify().catch(console.error);
