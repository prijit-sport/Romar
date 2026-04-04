const fs = require('fs');
const path = 'admin\documents.php';
let content = fs.readFileSync(path, 'utf8');
function replaceFirst(pattern, replacement) {
    const regex = new RegExp(pattern, 's');
    const match = regex.exec(content);
    if (!match) { return; }
    content = content.slice(0, match.index) + replacement + content.slice(match.index + match[0].length);
}
