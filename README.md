# Dziennik Praktyk

Prosta aplikacja w PHP + SQLite do prowadzenia dziennika praktyk: jeden dzień na ekranie, nawigacja między dniami, gotowa do wydruku.

## Wymagania

- PHP 8.x z rozszerzeniem `pdo_sqlite`
- (Laragon: wystarczy wrzucić projekt do `www/`)

## Uruchomienie

1. Skopiuj projekt do katalogu serwera (np. `C:\laragon\www\DziennikPraktyk`).
2. Otwórz w przeglądarce: `http://dziennikpraktyk/` (lub `http://localhost/DziennikPraktyk/`).
3. Baza utworzy się automatycznie przy pierwszym uruchomieniu (patrz: „Dane i bezpieczeństwo").

## Funkcje

- Dodawanie / edycja / usuwanie wpisów (data, godziny od–do, opis czynności)
- Jeden dzień na ekranie, przełączanie przyciskami ← / → oraz strzałkami na klawiaturze
- Lista dni do szybkiego wyboru + przycisk „Dzisiaj"
- Wydruk pojedynczego dnia (przycisk „Drukuj", style `@media print`)

## Struktura

```
index.php        — strona główna (widok dnia + obsługa formularza)
src/db.php       — połączenie SQLite + schemat + lokalizacja bazy
src/functions.php— operacje CRUD i pomocnicze
assets/style.css — style (z wersją do wydruku)
assets/app.js    — skróty klawiszowe
data/            — pusty katalog (baza leży poza docroot); .htaccess blokuje dostęp
.htaccess        — ochrona zasobów Apache (baza, .git, src/)
plan.md          — plan realizacji / postęp prac
```

## Dane i bezpieczeństwo

Baza **nie leży w katalogu serwera** (docroot), więc nie da się jej pobrać przez
HTTP (np. `http://.../data/dziennik.db` zwróci 403). Kolejność wyboru lokalizacji:

1. zmienna środowiskowa `DZIENNIK_DATA_DIR` (pełna ścieżka katalogu),
2. katalog danych Laragona: `<laragon>/data/DziennikPraktyk`,
3. katalog danych użytkownika (`%LOCALAPPDATA%\DziennikPraktyk` lub `~/.local/share/DziennikPraktyk`).

Stara baza z `data/dziennik.db` jest przenoszona automatycznie przy pierwszym uruchomieniu.

Dodatkowo plik `.htaccess` blokuje zdalny dostęp do plików bazy (`*.db`, `*.db-wal`,
`*.db-shm`, `*.db-journal`), katalogu `.git` oraz kodu źródłowego w `src/`, a także
wyłącza listowanie katalogów.
