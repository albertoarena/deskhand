// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

// Project GitHub Pages site: https://albertoarena.github.io/deskhand
export default defineConfig({
  site: 'https://albertoarena.github.io',
  base: '/deskhand',
  integrations: [
    starlight({
      title: 'deskhand',
      description:
        'Isolated, test-passing Laravel environments per worktree, for running parallel AI coding agents.',
      logo: {
        light: './src/assets/logo.svg',
        dark: './src/assets/logo-dark.svg',
        replacesTitle: true,
      },
      favicon: '/favicon.svg',
      customCss: ['./src/styles/custom.css'],
      social: [
        {
          icon: 'github',
          label: 'GitHub',
          href: 'https://github.com/albertoarena/deskhand',
        },
      ],
      editLink: {
        baseUrl: 'https://github.com/albertoarena/deskhand/edit/main/website/',
      },
      sidebar: [
        {
          label: 'Getting Started',
          items: [
            { label: 'Installation', slug: 'getting-started/installation' },
            { label: 'Quickstart', slug: 'getting-started/quickstart' },
            { label: 'Configuration', slug: 'getting-started/configuration' },
          ],
        },
        {
          label: 'Commands',
          items: [
            { label: 'up', slug: 'commands/up' },
            { label: 'down', slug: 'commands/down' },
            { label: 'list', slug: 'commands/list' },
            { label: 'status', slug: 'commands/status' },
          ],
        },
        {
          label: 'Concepts',
          items: [
            { label: 'How isolation works', slug: 'concepts/isolation' },
            { label: 'Safety model', slug: 'concepts/safety' },
          ],
        },
      ],
    }),
  ],
});
