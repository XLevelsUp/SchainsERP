// Starts Vitest from the upper-case spelling of the working directory.
//
// On Windows, a terminal opened on c:\… (VS Code opens this workspace that
// way) gives Node a lowercase drive letter. Vitest then loads itself twice —
// once per spelling of the path — and every suite fails with "Cannot read
// properties of undefined (reading 'config')". Run from C:\… it's fine.
//
// `npm test` and `npm run test:watch` go through here; arguments pass through.
import { spawnSync } from 'node:child_process'
import { join } from 'node:path'

const cwd = process.cwd()
const root = /^[a-z]:/.test(cwd) ? cwd.charAt(0).toUpperCase() + cwd.slice(1) : cwd
const vitest = join(root, 'node_modules', 'vitest', 'vitest.mjs')

const result = spawnSync(process.execPath, [vitest, ...process.argv.slice(2)], {
  cwd: root,
  stdio: 'inherit',
})

process.exit(result.status ?? 1)
