const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..');
const polyfills = [...fs.readFileSync(path.join(root, 'src/intl-polyfills.ts'), 'utf8').matchAll(/^import '([^']+)';$/gm)].map((match) => match[1]);

const withoutHermesGaps = (loadPolyfills, probe) => JSON.parse(execFileSync(process.execPath, ['--input-type=module', '-e', `
  import fs from 'fs';
  delete Intl.PluralRules;
  delete Intl.RelativeTimeFormat;
  delete Intl.Locale;
  ${loadPolyfills ? `for (const name of ${JSON.stringify(polyfills)}) await import(name);` : ''}
  const { default: i18next } = await import('i18next');
  const i18n = i18next.createInstance();
  await i18n.init({ lng: 'pl', resources: { pl: { translation: JSON.parse(fs.readFileSync('src/i18n/locales/pl.json', 'utf8')) } } });
  process.stdout.write(JSON.stringify(await (async () => { ${probe} })()));
`], { cwd: root, encoding: 'utf8' }));

test('the polyfills load the Intl APIs Hermes lacks, with Polish and English data', () => {
  expect(polyfills.length).toBeGreaterThan(0);
  const result = withoutHermesGaps(true, `
    const polish = new Intl.RelativeTimeFormat('pl', { numeric: 'auto' });
    const plural = new Intl.PluralRules('pl');
    return {
      minutes: polish.format(-5, 'minute'),
      yesterday: polish.format(-1, 'day'),
      english: new Intl.RelativeTimeFormat('en', { numeric: 'auto' }).format(-3, 'hour'),
      forms: [1, 2, 5, 12, 22].map((count) => plural.select(count)),
      events: [1, 3, 5].map((count) => i18n.t('dayPlanning.eventsCount', { count })),
    };
  `);
  expect(result).toEqual({
    minutes: '5 minut temu',
    yesterday: 'wczoraj',
    english: '3 hours ago',
    forms: ['one', 'few', 'many', 'many', 'few'],
    events: ['1 wydarzenie', '3 wydarzenia', '5 wydarzeń'],
  });
});

test('without the polyfills relative time is missing and Polish plurals fall back to one and other', () => {
  expect(withoutHermesGaps(false, `
    return { relativeTime: typeof Intl.RelativeTimeFormat, events: i18n.t('dayPlanning.eventsCount', { count: 5 }) };
  `)).toEqual({ relativeTime: 'undefined', events: '5 wydarzenia' });
});
