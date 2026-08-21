import { copyFileSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const src = join(root, 'backend/public-php');
const dest = join(root, 'backend/public');

mkdirSync(dest, { recursive: true });

for (const file of ['index.php', '.htaccess', 'router.php']) {
  copyFileSync(join(src, file), join(dest, file));
}

console.log('Restored Slim public files after adapter-static build.');
