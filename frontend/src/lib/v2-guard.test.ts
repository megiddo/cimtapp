import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const forbidden = ['/api/v1/compounds', '/api/v1/uses', '/api/v1/bac-bottles', '/api/v1/profiles', '/api/v1/peptide-types', '/api/v1/syringes'];

function walk(dir: string, out: string[]): void {
  for (const entry of readdirSync(dir)) {
    const path = join(dir, entry);
    if (statSync(path).isDirectory()) {
      if (entry === 'node_modules' || entry === '.svelte-kit') {
        continue;
      }
      walk(path, out);
      continue;
    }
    if (path.endsWith('.ts') || path.endsWith('.svelte')) {
      if (path.endsWith('.test.ts') && path.includes('v2-guard')) {
        continue;
      }
      out.push(path);
    }
  }
}

describe('v2 client guard', () => {
  it('does not call removed stock API paths', () => {
    const root = join(process.cwd(), 'src');
    const files: string[] = [];
    walk(root, files);
    const hits: string[] = [];
    for (const file of files) {
      const text = readFileSync(file, 'utf8');
      for (const path of forbidden) {
        if (text.includes(path)) {
          hits.push(`${file}: ${path}`);
        }
      }
    }
    expect(hits).toEqual([]);
  });
});