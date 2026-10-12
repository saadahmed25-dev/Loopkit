/**
 * Where puppeteer and Chrome live.
 *
 * Every suite used to hardcode the same two paths, so moving either meant
 * editing nine files. PUPPETEER_PATH and CHROME override, for a machine
 * that keeps them somewhere else.
 */
const fs = require('fs');

const CANDIDATES = [
  process.env.PUPPETEER_PATH,
  '/home/claude/.npm-global/lib/node_modules/@mermaid-js/mermaid-cli/node_modules/puppeteer',
  'puppeteer',
].filter(Boolean);

let puppeteer = null, tried = [];
for (const p of CANDIDATES) {
  try { puppeteer = require(p); break; } catch (e) { tried.push(p); }
}
if (!puppeteer) {
  console.error('Could not load puppeteer. Tried:\n  ' + tried.join('\n  ') +
    '\nSet PUPPETEER_PATH to where it is installed.');
  process.exit(2);
}

function chromePath() {
  if (process.env.CHROME && fs.existsSync(process.env.CHROME)) return process.env.CHROME;
  const roots = ['/root/.cache/puppeteer/chrome', process.env.HOME + '/.cache/puppeteer/chrome'];
  for (const root of roots) {
    try {
      for (const dir of fs.readdirSync(root)) {
        const bin = root + '/' + dir + '/chrome-linux64/chrome';
        if (fs.existsSync(bin)) return bin;
      }
    } catch (e) { /* not there */ }
  }
  for (const bin of ['/usr/bin/chromium-browser', '/usr/bin/google-chrome']) {
    if (fs.existsSync(bin)) return bin;
  }
  return null;
}

module.exports = { puppeteer, chromePath };
