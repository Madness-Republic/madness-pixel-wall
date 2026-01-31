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
const UPDATES_FILE = path.join(__dirname, '../data/updates.json');
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

// 7. Reset Updates (Madness Log) with Hello Message
try {
    const today = new Date();
    const dd = String(today.getDate()).padStart(2, '0');
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const yyyy = today.getFullYear();
    const hh = String(today.getHours()).padStart(2, '0');
    const min = String(today.getMinutes()).padStart(2, '0');
    const dateStr = `${dd}/${mm}/${yyyy} ${hh}:${min}`;

    const initialLog = [{
        date: dateStr,
        title: "Hello Wall!",
        content: "Madness Pixel Wall è finalmente online!"
    }];

    fs.writeFileSync(UPDATES_FILE, JSON.stringify(initialLog, null, 4));
    console.log('✅ [7/9] updates.json reset with welcome message');
} catch (e) {
    console.warn('⚠️  Could not reset updates.json', e.message);
}

// 8. Generate New Secret Pixels (Gold & Silver)
console.log('\n💎 [8/9] Generating new secret Gold & Silver Pixels...');

// Cleanup old secret files first
try {
    const privDir = path.join(__dirname, '../private');
    if (fs.existsSync(privDir)) {
        const files = fs.readdirSync(privDir);
        files.forEach(file => {
            if (file.match(/^secret_.*_pixels_.*\.php$/)) {
                fs.unlinkSync(path.join(privDir, file));
                console.log(`🗑️  Deleted old secret file: ${file}`);
            }
        });
    }
} catch (e) {
    console.warn('⚠️  Could not cleanup old secret files:', e.message);
}

try {
    const output = execSync('php generate-gold-pixels.php', { cwd: __dirname });
    console.log('-----------------------------------------');
    console.log(output.toString().trim());
    console.log('-----------------------------------------');
    console.log('✅ Secret Pixels generation successful.');
} catch (err) {
    console.error('❌ Error generating secret pixels:', err.message);
}

// 9. Clear Access Logs (DISABLED)
console.log('ℹ️  [9/9] Skipping access_logs.json clear (Preserving main site stats)');

console.log('\n✨ --- ALL SYSTEMS RESET & READY FOR PRODUCTION --- ✨\n');
