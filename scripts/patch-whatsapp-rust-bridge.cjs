const fs = require('node:fs');
const path = require('node:path');

const manifestPath = path.join(
  __dirname,
  '..',
  'node_modules',
  'whatsapp-rust-bridge',
  'package.json',
);

if (!fs.existsSync(manifestPath)) {
  process.exit(0);
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const packageExport = manifest.exports?.['.'];

if (packageExport?.import && !packageExport.require) {
  packageExport.require = packageExport.import;
  fs.writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`);
}
