# GitHub Pages Deployment Guide

## Status

✅ **Pliki przygotowane i wypchnięte na GitHub**

- `docs/index.html` - prototyp aplikacji
- `docs/README.md` - dokumentacja live demo

## Kroki Konfiguracji GitHub Pages

### Opcja 1: Zmerguj branch do main i skonfiguruj GitHub Pages (Zalecane)

#### Krok 1: Utwórz Pull Request

1. Przejdź do: https://github.com/jakubciszak/family-plan
2. Kliknij **"Pull requests"** → **"New pull request"**
3. Ustaw:
   - **Base:** `main`
   - **Compare:** `claude/web-app-prototype-mocks-BGSE1`
4. Kliknij **"Create pull request"**
5. Dodaj tytuł: `Add web app prototype with GitHub Pages support`
6. Kliknij **"Create pull request"** i następnie **"Merge pull request"**

#### Krok 2: Skonfiguruj GitHub Pages

1. Przejdź do **Settings** → **Pages** w repozytorium
   - Link: https://github.com/jakubciszak/family-plan/settings/pages
2. W sekcji **"Source"**:
   - Wybierz branch: **`main`**
   - Wybierz folder: **`/docs`**
   - Kliknij **"Save"**
3. Poczekaj 1-2 minuty na deployment

#### Krok 3: Zweryfikuj Deployment

1. Po zakończeniu, GitHub pokaże link do Twojej strony:
   ```
   https://jakubciszak.github.io/family-plan/
   ```
2. Otwórz link w przeglądarce
3. Zaloguj się używając konta demo:
   - Email: `admin@family.com`
   - Hasło: `admin`

---

### Opcja 2: Skonfiguruj GitHub Pages dla feature branch (Szybkie demo)

Jeśli chcesz przetestować bez mergowania do main:

1. Przejdź do **Settings** → **Pages**
2. Wybierz:
   - Branch: `claude/web-app-prototype-mocks-BGSE1`
   - Folder: `/docs`
3. Kliknij **"Save"**

**Uwaga:** To nie jest zalecane dla produkcji, tylko do testowania.

---

## Troubleshooting

### Problem: "404 - File not found"

**Rozwiązanie:**
- Upewnij się, że wybrałeś folder `/docs` (nie root `/`)
- Sprawdź czy branch `main` zawiera folder `docs/index.html`
- Poczekaj 2-3 minuty - deployment może trwać

### Problem: "There isn't a GitHub Pages site here"

**Rozwiązanie:**
- Sprawdź czy GitHub Pages jest włączony w Settings → Pages
- Upewnij się, że wybrałeś poprawny branch i folder
- Sprawdź czy nie ma błędów w Actions (jeśli używasz GitHub Actions)

### Problem: Strona się nie aktualizuje

**Rozwiązanie:**
- Wyczyść cache przeglądarki (Ctrl+Shift+R lub Cmd+Shift+R)
- Poczekaj kilka minut na propagację zmian
- Sprawdź w GitHub Actions czy deployment zakończył się sukcesem

---

## Custom Domain (Opcjonalnie)

Jeśli chcesz użyć własnej domeny:

1. Dodaj plik `docs/CNAME` z Twoją domeną:
   ```
   demo.familyplan.com
   ```

2. W ustawieniach DNS domeny dodaj rekord:
   ```
   Type: CNAME
   Name: demo (lub subdomena)
   Value: jakubciszak.github.io
   ```

3. W GitHub Settings → Pages → Custom domain wpisz swoją domenę

---

## Automatyczne Deploymenty (Opcjonalnie)

Aby automatycznie deployować przy każdym push do main:

1. Utwórz `.github/workflows/deploy-pages.yml`:

```yaml
name: Deploy to GitHub Pages

on:
  push:
    branches: [main]
    paths:
      - 'docs/**'
      - 'prototype/**'

permissions:
  contents: read
  pages: write
  id-token: write

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup Pages
        uses: actions/configure-pages@v4
      - name: Upload artifact
        uses: actions/upload-pages-artifact@v3
        with:
          path: 'docs'
      - name: Deploy to GitHub Pages
        id: deployment
        uses: actions/deploy-pages@v4
```

2. Commit i push do main

---

## Weryfikacja

Po skonfigurowaniu GitHub Pages, aplikacja będzie dostępna pod adresem:

🚀 **https://jakubciszak.github.io/family-plan/**

### Test checklist:

- [ ] Strona się ładuje
- [ ] Logowanie działa (admin@family.com / admin)
- [ ] Lista zadań się wyświetla
- [ ] Filtry zadań działają
- [ ] Przełączanie języka PL/EN działa
- [ ] Responsywność (mobile menu) działa
- [ ] LocalStorage zapisuje dane

---

## Wsparcie

Jeśli napotkasz problemy:

1. Sprawdź [GitHub Pages Documentation](https://docs.github.com/en/pages)
2. Sprawdź status GitHub Pages w Settings → Pages
3. Sprawdź logi w Actions (jeśli używasz workflows)

---

**Data utworzenia:** 2026-01-17
**Branch:** claude/web-app-prototype-mocks-BGSE1
**Status:** Ready for deployment
