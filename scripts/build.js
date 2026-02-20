#!/usr/bin/env node
/* eslint-env node */

/**
 * Build script for CentralCorp Panel Installer
 * 
 * This script:
 * 1. Runs the Vite build
 * 2. Copies assets to the correct location
 * 3. Updates index.php with the correct asset filenames
 * 4. Creates a deployable ZIP file
 */

import fs from 'fs';
import path from 'path';
import { execSync } from 'child_process';
import { fileURLToPath } from 'url';
import archiver from 'archiver';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT_DIR = path.join(__dirname, '..');

const DIST_DIR = path.join(ROOT_DIR, 'dist');
const BACKEND_DIR = path.join(ROOT_DIR, 'backend');
const PUBLIC_ASSETS_DIR = path.join(BACKEND_DIR, 'public', 'assets');
const OUTPUT_ZIP = path.join(ROOT_DIR, 'installer.zip');

// CDN Configuration
// Set CDN_BASE_URL environment variable to load assets from CDN
// Example: CDN_BASE_URL=https://cdn.jsdelivr.net/gh/CentralCorp/Installer@1.2.0/backend/public npm run build:prod
const CDN_BASE_URL = process.env.CDN_BASE_URL || '';
const USE_CDN = CDN_BASE_URL.length > 0;

console.log('🔨 Building CentralCorp Panel Installer...\n');
if (USE_CDN) {
    console.log(`🌐 CDN Mode: Assets will be loaded from ${CDN_BASE_URL}\n`);
}

// Step 1: Run Vite build
console.log('📦 Running Vite build...');
try {
    execSync('npm run build-only', { stdio: 'inherit', cwd: ROOT_DIR });
} catch (error) {
    console.error('❌ Vite build failed');
    process.exit(1);
}

// Step 2: Find the generated asset files
console.log('\n📁 Finding generated assets...');
const distAssetsDir = path.join(DIST_DIR, 'assets');
const assetFiles = fs.readdirSync(distAssetsDir);

const jsFile = assetFiles.find(f => f.endsWith('.js'));
const cssFile = assetFiles.find(f => f.endsWith('.css'));

if (!jsFile || !cssFile) {
    console.error('❌ Could not find JS or CSS files in dist/assets/');
    process.exit(1);
}

console.log(`   Found: ${jsFile}`);
console.log(`   Found: ${cssFile}`);

// Step 3: Create public/assets directory and copy files
console.log('\n📂 Copying assets to backend/public/assets/...');
if (!fs.existsSync(PUBLIC_ASSETS_DIR)) {
    fs.mkdirSync(PUBLIC_ASSETS_DIR, { recursive: true });
}

// Clean old assets
const existingAssets = fs.readdirSync(PUBLIC_ASSETS_DIR);
for (const file of existingAssets) {
    fs.unlinkSync(path.join(PUBLIC_ASSETS_DIR, file));
}

// Copy new assets
for (const file of assetFiles) {
    fs.copyFileSync(
        path.join(distAssetsDir, file),
        path.join(PUBLIC_ASSETS_DIR, file)
    );
    console.log(`   Copied: ${file}`);
}

// Step 4: Update backend/index.php with correct asset filenames
console.log('\n✏️  Updating backend/index.php with new asset filenames...');
const indexPhpPath = path.join(BACKEND_DIR, 'index.php');
let indexPhpContent = fs.readFileSync(indexPhpPath, 'utf8');

// Determine asset base path (CDN or local)
const assetBasePath = USE_CDN ? `${CDN_BASE_URL}/assets` : '/assets';

// Replace JS file reference (supports both local /assets/ and CDN URLs)
indexPhpContent = indexPhpContent.replace(
    /src="[^"]*\/assets\/index-[^"]+\.js"/,
    `src="${assetBasePath}/${jsFile}"`
);

// Replace CSS file reference (supports both local /assets/ and CDN URLs)
indexPhpContent = indexPhpContent.replace(
    /href="[^"]*\/assets\/index-[^"]+\.css"/,
    `href="${assetBasePath}/${cssFile}"`
);

fs.writeFileSync(indexPhpPath, indexPhpContent);
console.log(`   Updated asset references in index.php`);
if (USE_CDN) {
    console.log(`   JS:  ${assetBasePath}/${jsFile}`);
    console.log(`   CSS: ${assetBasePath}/${cssFile}`);
}

// Step 5: Create ZIP file
console.log('\n📦 Creating installer.zip...');

// Remove old zip if exists
if (fs.existsSync(OUTPUT_ZIP)) {
    fs.unlinkSync(OUTPUT_ZIP);
}

const output = fs.createWriteStream(OUTPUT_ZIP);
const archive = archiver('zip', { zlib: { level: 9 } });

output.on('close', () => {
    const sizeMB = (archive.pointer() / 1024 / 1024).toFixed(2);
    console.log(`\n✅ Build complete!`);
    console.log(`   Created: installer.zip (${sizeMB} MB)`);
    console.log(`\n📋 To deploy:`);
    console.log(`   1. Upload installer.zip to your web server`);
    console.log(`   2. Extract the contents to your web root`);
    console.log(`   3. Navigate to your domain in a browser`);
});

archive.on('error', (err) => {
    console.error('❌ Error creating ZIP:', err);
    process.exit(1);
});

archive.pipe(output);

// Add backend files to ZIP (at root level)
archive.directory(path.join(BACKEND_DIR, 'public'), 'public');
archive.file(path.join(BACKEND_DIR, 'index.php'), { name: 'index.php' });
archive.file(path.join(BACKEND_DIR, '.htaccess'), { name: '.htaccess' });

archive.finalize();
