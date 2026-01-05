const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

console.log('\n🚀 STARTING PIXEL WALL PRODUCTION RESET...\n');

const DATA_FILE = path.join(__dirname, '../data/wall_data.json');
const PRESET_FILE = path.join(__dirname, '../data/wall_preset.json');
const TRANS_FILE = path.join(__dirname, '../data/transactions.json');
const CONTRIB_FILE = path.join(__dirname, '../data/contributors.json');
const OWNERS_FILE = path.join(__dirname, '../data/pixel_owners.json');
const WINNERS_FILE = path.join(__dirname, '../data/winners.json');
const WINNERS_SILVER_FILE = path.join(__dirname, '../data/winners_silver.json');
const LOGS_FILE = path.join(__dirname, '../../access_logs.json');


// 1. Restore Wall Data
try {
    if (fs.existsSync(PRESET_FILE)) {
        const presetData = fs.readFileSync(PRESET_FILE, 'utf8');
        fs.writeFileSync(DATA_FILE, presetData);
        console.log('✅ [1/7] Wall data restored from wall_preset.json');
    } else {
        console.warn('⚠️  Preset file not found! Creating empty board.');
        fs.writeFileSync(DATA_FILE, '{}');
    }
} catch (e) { console.error('❌ Error restoring wall data:', e.message); }

// 2. Clear Transactions
try {
    fs.writeFileSync(TRANS_FILE, '[]');
    console.log('✅ [2/7] transactions.json cleared');
} catch (e) { console.error('❌ Error clearing transactions:', e.message); }

// 3. Clear Contributors
try {
    fs.writeFileSync(CONTRIB_FILE, '[]');
    console.log('✅ [3/7] contributors.json cleared');
} catch (e) { console.error('❌ Error clearing contributors:', e.message); }

// 4. Clear Pixel Owners
try {
    fs.writeFileSync(OWNERS_FILE, '{}');
    console.log('✅ [4/7] pixel_owners.json cleared');
} catch (e) { console.error('❌ Error clearing pixel owners:', e.message); }

// 5. Clear Winners (Gold)
try {
    fs.writeFileSync(WINNERS_FILE, '[]');
    console.log('✅ [5/7] winners.json cleared');
} catch (e) { console.error('❌ Error clearing winners:', e.message); }

// 6. Clear Winners (Silver)
try {
    fs.writeFileSync(WINNERS_SILVER_FILE, '[]');
    console.log('✅ [6/7] winners_silver.json cleared');
} catch (e) { console.error('❌ Error clearing silver winners:', e.message); }

// 7. Generate New Secret Pixels (Gold & Silver)
console.log('\n💎 [7/8] Generating new secret Gold & Silver Pixels...');
try {
    const output = execSync('php generate-gold-pixels.php', { cwd: __dirname });
    console.log('-----------------------------------------');
    console.log(output.toString().trim());
    console.log('-----------------------------------------');
    console.log('✅ Secret Pixels generation successful.');
} catch (err) {
    console.error('❌ Error generating secret pixels:', err.message);
}

// 8. Clear Access Logs
try {
    if (fs.existsSync(LOGS_FILE)) {
        fs.writeFileSync(LOGS_FILE, '[]');
        console.log('✅ [8/8] access_logs.json cleared');
    } else {
        console.log('ℹ️  [8/8] access_logs.json not found (skipping)');
    }
} catch (e) { console.error('❌ Error clearing logs:', e.message); }

console.log('\n✨ --- ALL SYSTEMS RESET & READY FOR PRODUCTION --- ✨\n');
