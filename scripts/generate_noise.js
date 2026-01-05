const fs = require('fs');
const path = require('path');

const DATA_FILE = path.join(__dirname, '../data/wall_data.json');

// Colors
const COLORS = ['#ffe600', '#f09100', '#e63228', '#823c8c', '#ffffff', '#4dffbc'];

const wallData = {};

console.log("Generating noise...");

// Generate 20,000 random pixels
for (let i = 0; i < 200000; i++) {
    const x = Math.floor(Math.random() * 1000);
    const y = Math.floor(Math.random() * 400);
    const color = COLORS[Math.floor(Math.random() * COLORS.length)];

    wallData[`${x},${y}`] = color;
}

// Preserve existing "WE ARE MADNESS" if possible? 
// No, stress test usually overwrites or adds to it. Let's strictly overwrite to be sure of count.

fs.writeFileSync(DATA_FILE, JSON.stringify(wallData));
console.log(`Generated ${Object.keys(wallData).length} random pixels in wall_data.json`);
