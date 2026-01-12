const fs = require('fs');

console.log('='.repeat(60));
console.log('Preparing Template Files for cPanel Deployment');
console.log('='.repeat(60));
console.log();

// Check if files exist
if (!fs.existsSync('index.php')) {
    console.log('❌ Error: index.php not found');
    console.log();
    console.log('Please copy your files from XAMPP first:');
    console.log('1. Edit copy-from-xampp.bat');
    console.log('2. Set the correct XAMPP path');
    console.log('3. Run copy-from-xampp.bat');
    process.exit(1);
}

if (!fs.existsSync('submit.php')) {
    console.log('❌ Error: submit.php not found');
    console.log();
    console.log('Please copy your files from XAMPP first:');
    console.log('1. Edit copy-from-xampp.bat');
    console.log('2. Set the correct XAMPP path');
    console.log('3. Run copy-from-xampp.bat');
    process.exit(1);
}

console.log('✓ Found index.php');
console.log('✓ Found submit.php');
console.log();

// Read files
const indexContent = fs.readFileSync('index.php', 'utf8');
const submitContent = fs.readFileSync('submit.php', 'utf8');

// Check for XAMPP-specific paths that need to be changed
console.log('Checking for XAMPP-specific configurations...');
console.log();

let issues = [];
let warnings = [];

// Check for localhost references
if (indexContent.includes('localhost') || submitContent.includes('localhost')) {
    warnings.push('⚠️  Found "localhost" references - may need updating for production');
}

// Check for database connections
if (indexContent.includes('mysqli_connect') || submitContent.includes('mysqli_connect')) {
    issues.push('Database connection found - you may need to update:');
    issues.push('  - Database host (usually "localhost" on cPanel)');
    issues.push('  - Database name');
    issues.push('  - Database username');
    issues.push('  - Database password');
}

if (indexContent.includes('$_SERVER[\'DOCUMENT_ROOT\']') || submitContent.includes('$_SERVER[\'DOCUMENT_ROOT\']')) {
    warnings.push('⚠️  Uses DOCUMENT_ROOT - should work fine on cPanel');
}

// Check for file paths
if (indexContent.includes('C:\\') || submitContent.includes('C:\\') ||
    indexContent.includes('C:/') || submitContent.includes('C:/')) {
    issues.push('❌ CRITICAL: Found Windows absolute paths (C:\\) - must be changed!');
    issues.push('   Use relative paths or __DIR__ constant instead');
}

// Check for XAMPP-specific folders
if (indexContent.includes('xampp') || submitContent.includes('xampp')) {
    issues.push('❌ CRITICAL: Found "xampp" in code - must be removed!');
}

// Display results
if (issues.length === 0 && warnings.length === 0) {
    console.log('✅ No issues found! Files appear ready for cPanel deployment.');
} else {
    if (issues.length > 0) {
        console.log('ISSUES THAT MUST BE FIXED:');
        console.log('─'.repeat(60));
        issues.forEach(issue => console.log(issue));
        console.log();
    }

    if (warnings.length > 0) {
        console.log('WARNINGS (review recommended):');
        console.log('─'.repeat(60));
        warnings.forEach(warning => console.log(warning));
        console.log();
    }
}

// File sizes
console.log('─'.repeat(60));
console.log('File Information:');
console.log('  index.php:  ' + (indexContent.length / 1024).toFixed(2) + ' KB');
console.log('  submit.php: ' + (submitContent.length / 1024).toFixed(2) + ' KB');
console.log();

// Next steps
console.log('='.repeat(60));
console.log('NEXT STEPS:');
console.log('='.repeat(60));

if (issues.length > 0) {
    console.log('1. Fix the critical issues listed above');
    console.log('2. Run this script again to verify');
    console.log('3. Then deploy using one of these methods:');
} else {
    console.log('Files are ready! Deploy using one of these methods:');
}

console.log();
console.log('METHOD 1 - Automated (if API works):');
console.log('  node quick-deploy.js');
console.log();
console.log('METHOD 2 - Manual (always works):');
console.log('  Follow instructions in UPLOAD-INSTRUCTIONS.txt');
console.log('  Upload to: https://bh-in-32.webhostbox.net:2083');
console.log();
console.log('='.repeat(60));
