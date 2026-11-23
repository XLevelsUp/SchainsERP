import { defineConfig, mergeConfig } from 'vitest/config'
import viteConfig from './vite.config.ts'

// Unit tests for frontend logic (form helpers, permissions, stores).
// Run with `npm test` (or `npm run test:watch`), which goes through
// scripts/vitest.mjs: on Windows a lowercase c:\ working directory makes
// Vitest load itself twice, and the script starts it from C:\ instead.
// See .claude/rules/schainserp-product.md, "Testing". Browser tests of whole
// pages are Playwright's (playwright.config.ts).
export default mergeConfig(
  viteConfig,
  defineConfig({
    test: {
      environment: 'jsdom',
      include: ['tests/unit/**/*.spec.ts'],
      restoreMocks: true,
    },
  }),
)
