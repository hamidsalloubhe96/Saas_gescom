// Compile le CSS de la console (pages Blade) vers backend/public/console.css et copie les polices utilisées.
import { execFileSync } from 'node:child_process'
import { copyFileSync, mkdirSync, readdirSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const out = resolve(root, '../backend/public')
const watch = process.argv.includes('--watch')

mkdirSync(join(out, 'files'), { recursive: true })
for (const pkg of ['rubik', 'nunito-sans']) {
  const dir = join(root, 'node_modules/@fontsource-variable', pkg, 'files')
  // Seuls les alphabets latins sont copiés : le navigateur ne charge de toute façon que ceux dont il a besoin.
  for (const f of readdirSync(dir)) if (/-latin(-ext)?-wght-normal\.woff2$/.test(f)) copyFileSync(join(dir, f), join(out, 'files', f))
}

const cli = join(root, 'node_modules/@tailwindcss/cli/dist/index.mjs')
execFileSync(process.execPath, [cli, '-i', 'src/console.css', '-o', join(out, 'console.css'), ...(watch ? ['--watch'] : ['--minify'])], { cwd: root, stdio: 'inherit' })
