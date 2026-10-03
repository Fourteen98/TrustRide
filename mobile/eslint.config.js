// https://docs.expo.dev/guides/using-eslint/
const { defineConfig } = require('eslint/config');
const expoConfig = require('eslint-config-expo/flat');

module.exports = defineConfig([
  expoConfig,
  {
    ignores: ['dist/*', 'src/api/schema.d.ts'],
  },
  {
    rules: {
      // This rule is about HTML. In React Native an apostrophe in text is just an apostrophe.
      'react/no-unescaped-entities': 'off',
    },
  },
]);
