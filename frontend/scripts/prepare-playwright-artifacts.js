const fs = require('fs');
const path = require('path');

const SUPPORTED_EXTENSIONS = new Set(['.png', '.jpg', '.jpeg', '.webm', '.zip']);
const TYPE_DIRECTORIES = {
  '.png': 'screenshots',
  '.jpg': 'screenshots',
  '.jpeg': 'screenshots',
  '.webm': 'videos',
  '.zip': 'traces',
};

function walkFiles(directory) {
  const entries = fs.readdirSync(directory, { withFileTypes: true });

  return entries.flatMap(entry => {
    const entryPath = path.join(directory, entry.name);

    if (entry.isDirectory()) {
      return walkFiles(entryPath);
    }

    return [entryPath];
  });
}

function sanitizeFilename(relativeFilePath) {
  return relativeFilePath
    .replace(/[\\/]+/g, '__')
    .replace(/[^a-zA-Z0-9._-]/g, '-');
}

function humanizeGroupName(groupName) {
  return groupName
    .split(path.sep)
    .filter(Boolean)
    .map(part => part.replace(/[-_]+/g, ' ').trim())
    .join(' / ');
}

function escapeHtml(value) {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function toPosixPath(filePath) {
  return filePath.split(path.sep).join('/');
}

function formatBytes(bytes) {
  if (bytes < 1024) {
    return `${bytes} B`;
  }

  if (bytes < 1024 * 1024) {
    return `${(bytes / 1024).toFixed(1)} KB`;
  }

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function ensureDirectory(directory) {
  fs.mkdirSync(directory, { recursive: true });
}

function buildReadme(entries) {
  const lines = [
    '# Playwright Artifacts',
    '',
    `Wygenerowano ${new Date().toISOString()}.`,
    '',
  ];

  if (entries.length === 0) {
    lines.push('Brak screenshotów, wideo i trace ZIP do pokazania.');
    lines.push('');
    return `${lines.join('\n')}\n`;
  }

  lines.push('## Jak korzystać');
  lines.push('');
  lines.push('- Otwórz `index.html`, aby przejrzeć artefakty w przeglądarce.');
  lines.push('- Screenshoty są osadzone bezpośrednio w `index.html`.');
  lines.push('- Pliki `trace.zip` można otworzyć poleceniem `npx playwright show-trace <ścieżka-do-pliku.zip>`.');
  lines.push('');
  lines.push('## Zawartość');
  lines.push('');

  const groupedEntries = entries.reduce((groups, entry) => {
    const groupKey = entry.groupName;
    groups[groupKey] = groups[groupKey] || [];
    groups[groupKey].push(entry);
    return groups;
  }, {});

  Object.entries(groupedEntries)
    .sort(([left], [right]) => left.localeCompare(right))
    .forEach(([groupName, groupEntries]) => {
      lines.push(`### ${groupName}`);
      lines.push('');

      groupEntries.forEach(entry => {
        lines.push(`- **${entry.typeLabel}**: \`${entry.destinationRelativePath}\` _(źródło: \`${entry.sourceRelativePath}\`, ${entry.sizeLabel})_`);
      });

      lines.push('');
    });

  return `${lines.join('\n')}\n`;
}

function buildHtml(entries) {
  const groupedEntries = entries.reduce((groups, entry) => {
    const groupKey = entry.groupName;
    groups[groupKey] = groups[groupKey] || [];
    groups[groupKey].push(entry);
    return groups;
  }, {});

  const sections = Object.entries(groupedEntries)
    .sort(([left], [right]) => left.localeCompare(right))
    .map(([groupName, groupEntries]) => {
      const items = groupEntries
        .map(entry => {
          const preview = entry.type === 'screenshot'
            ? `<a href="${encodeURI(entry.destinationRelativePath)}" target="_blank" rel="noopener noreferrer"><img src="${encodeURI(entry.destinationRelativePath)}" alt="${escapeHtml(entry.displayName)}"></a>`
            : '';
          const traceHint = entry.type === 'trace'
            ? '<p class="hint">Otwórz lokalnie poleceniem <code>npx playwright show-trace &lt;plik.zip&gt;</code>.</p>'
            : '';

          return `
            <article class="artifact-card artifact-card--${entry.type}">
              <div class="artifact-card__content">
                <h3>${escapeHtml(entry.displayName)}</h3>
                <p><strong>Typ:</strong> ${escapeHtml(entry.typeLabel)}</p>
                <p><strong>Rozmiar:</strong> ${escapeHtml(entry.sizeLabel)}</p>
                <p><strong>Źródło:</strong> <code>${escapeHtml(entry.sourceRelativePath)}</code></p>
                <p><a href="${encodeURI(entry.destinationRelativePath)}" target="_blank" rel="noopener noreferrer">Otwórz / pobierz plik</a></p>
                ${traceHint}
              </div>
              ${preview}
            </article>
          `;
        })
        .join('\n');

      return `
        <section class="artifact-group">
          <h2>${escapeHtml(groupName)}</h2>
          <div class="artifact-grid">
            ${items}
          </div>
        </section>
      `;
    })
    .join('\n');

  const emptyState = entries.length === 0
    ? '<p class="empty-state">Brak screenshotów, wideo i trace ZIP do pokazania.</p>'
    : sections;

  return `<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Playwright Artifacts</title>
  <style>
    :root {
      color-scheme: light;
      font-family: Arial, sans-serif;
    }

    body {
      margin: 0;
      padding: 2rem;
      background: #f5f7fb;
      color: #1f2937;
    }

    main {
      max-width: 1200px;
      margin: 0 auto;
    }

    h1 {
      margin-top: 0;
    }

    .summary {
      margin-bottom: 2rem;
      padding: 1rem 1.25rem;
      border-radius: 12px;
      background: #ffffff;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
    }

    .artifact-group + .artifact-group {
      margin-top: 2rem;
    }

    .artifact-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 1rem;
    }

    .artifact-card {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      padding: 1rem;
      border-radius: 12px;
      background: #ffffff;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
    }

    .artifact-card img {
      width: 100%;
      border-radius: 8px;
      border: 1px solid #dbe3f0;
    }

    .artifact-card h3 {
      margin: 0 0 0.5rem;
      font-size: 1rem;
    }

    .artifact-card p {
      margin: 0.25rem 0;
      line-height: 1.4;
    }

    .artifact-card code {
      word-break: break-word;
    }

    .hint {
      color: #6b7280;
      font-size: 0.95rem;
    }

    .empty-state {
      padding: 1rem 1.25rem;
      border-radius: 12px;
      background: #ffffff;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
    }
  </style>
</head>
<body>
  <main>
    <section class="summary">
      <h1>Playwright Artifacts</h1>
      <p>Ten pakiet grupuje screenshoty, wideo i trace ZIP według testu, żeby łatwiej było je przeglądać po pobraniu artefaktu.</p>
      <p>Otwórz lokalnie plik <code>index.html</code>. Trace ZIP możesz uruchomić poleceniem <code>npx playwright show-trace &lt;plik.zip&gt;</code>.</p>
    </section>
    ${emptyState}
  </main>
</body>
</html>
`;
}

function main() {
  const inputDirectory = path.resolve(process.argv[2] || path.join(__dirname, '..', 'test-results'));
  const outputDirectory = path.resolve(process.argv[3] || path.join(__dirname, '..', 'playwright-artifacts-readable'));

  fs.rmSync(outputDirectory, { recursive: true, force: true });
  ensureDirectory(outputDirectory);

  if (!fs.existsSync(inputDirectory)) {
    fs.writeFileSync(path.join(outputDirectory, 'README.md'), buildReadme([]), 'utf8');
    fs.writeFileSync(path.join(outputDirectory, 'index.html'), buildHtml([]), 'utf8');
    return;
  }

  const entries = walkFiles(inputDirectory)
    .filter(filePath => SUPPORTED_EXTENSIONS.has(path.extname(filePath).toLowerCase()))
    .map(filePath => {
      const sourceRelativePath = path.relative(inputDirectory, filePath);
      const extension = path.extname(filePath).toLowerCase();
      const outputSubdirectory = TYPE_DIRECTORIES[extension];
      const destinationFileName = sanitizeFilename(sourceRelativePath);
      const destinationDirectory = path.join(outputDirectory, outputSubdirectory);
      const destinationPath = path.join(destinationDirectory, destinationFileName);
      const stats = fs.statSync(filePath);
      const groupName = humanizeGroupName(path.dirname(sourceRelativePath)) || 'root';
      const type = outputSubdirectory === 'screenshots'
        ? 'screenshot'
        : outputSubdirectory === 'videos'
          ? 'video'
          : 'trace';

      ensureDirectory(destinationDirectory);
      fs.copyFileSync(filePath, destinationPath);

      return {
        type,
        typeLabel: type === 'screenshot' ? 'Screenshot' : type === 'video' ? 'Wideo' : 'Trace ZIP',
        displayName: path.basename(sourceRelativePath),
        groupName,
        sourceRelativePath,
        destinationRelativePath: toPosixPath(path.relative(outputDirectory, destinationPath)),
        sizeLabel: formatBytes(stats.size),
      };
    })
    .sort((left, right) => {
      const groupCompare = left.groupName.localeCompare(right.groupName);

      if (groupCompare !== 0) {
        return groupCompare;
      }

      return left.destinationRelativePath.localeCompare(right.destinationRelativePath);
    });

  fs.writeFileSync(path.join(outputDirectory, 'README.md'), buildReadme(entries), 'utf8');
  fs.writeFileSync(path.join(outputDirectory, 'index.html'), buildHtml(entries), 'utf8');
}

main();
