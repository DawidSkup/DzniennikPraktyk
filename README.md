# Dziennik Praktyk

Prosta aplikacja w PHP + SQLite do prowadzenia dziennika praktyk: jeden dzień na ekranie, nawigacja między dniami, gotowa do wydruku.

## Wymagania

- PHP 8.x z rozszerzeniem `pdo_sqlite`
- (Laragon: wystarczy wrzucić projekt do `www/`)

## Uruchomienie

1. Skopiuj projekt do katalogu serwera (np. `C:\laragon\www\DziennikPraktyk`).
2. Otwórz w przeglądarce: `http://dziennikpraktyk/` (lub `http://localhost/DziennikPraktyk/`).
3. Baza `data/dziennik.db` utworzy się automatycznie przy pierwszym uruchomieniu.

## Funkcje

- Dodawanie / edycja / usuwanie wpisów (data, godziny od–do, opis czynności)
- Jeden dzień na ekranie, przełączanie przyciskami ← / → oraz strzałkami na klawiaturze
- Lista dni do szybkiego wyboru + przycisk „Dzisiaj"
- Wydruk pojedynczego dnia (przycisk „Drukuj", style `@media print`)

## Struktura

```
index.php        — strona główna (widok dnia + obsługa formularza)
src/db.php       — połączenie SQLite + schemat
src/functions.php— operacje CRUD i pomocnicze
assets/style.css — style (z wersją do wydruku)
assets/app.js    — skróty klawiszowe
data/            — baza danych (ignorowana przez git)
plan.md          — plan realizacji / postęp prac
```
