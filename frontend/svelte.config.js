import adapter from '@sveltejs/adapter-static';

/** @type {import('@sveltejs/kit').Config} */
const config = {
  kit: {
    adapter: adapter({
      pages: '../backend/public',
      assets: '../backend/public',
      fallback: 'index.html',
      precompress: false,
      strict: false
    }),
    paths: {
      relative: false
    }
  }
};

export default config;
