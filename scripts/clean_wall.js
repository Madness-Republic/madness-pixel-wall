const fs = require('fs');
const path = require('path');

const dataFile = path.join(__dirname, '../data/wall_data.json');

try {
    const raw = fs.readFileSync(dataFile, 'utf8');
    const data = JSON.parse(raw);
    const cleanData = {};

    // Limits for the WE ARE MADNESS text based on observation
    // Y range: 190 to 209
    // Colors: #ffe600, #f09100, #e63228, #823c8c
    // Doodles seem to be #ff4d4d

    Object.keys(data).forEach(key => {
        const [x, y] = key.split(',').map(Number);
        const color = data[key];

        // Keep only if within the text band
        if (y >= 190 && y <= 210) {
            // Filter out default red doodles
            if (color !== '#ff4d4d') {
                cleanData[key] = color;
            }
        }
    });

    fs.writeFileSync(dataFile, JSON.stringify(cleanData));
    console.log(`Cleaned wall data. Kept ${Object.keys(cleanData).length} pixels.`);
} catch (e) {
    console.error("Error cleaning:", e);
}
