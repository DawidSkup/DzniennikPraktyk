# Plan realizacji — Dziennik Praktyk

## Etap 1 — Przygotowanie

- [x] Spisać wymagania w `AGENTS.md`
- [x] Zainicjować repozytorium Git
- [x] Wrzucić `AGENTS.md` na GitHuba
- [x] Utworzyć strukturę projektu (`index.php` w rootcie + `src/`, `assets/`, `data/`)

## Etap 2 — Baza danych (SQLite)

- [x] Plik bazy `data/dziennik.db` (tworzony automatycznie)
- [x] Tabela `wpisy`: `id`, `data` (unikalna), `godzina_od`, `godzina_do`, `opis`
- [x] Skrypt inicjalizujący bazę (`src/db.php` — auto-tworzenie schematu)

## Etap 3 — Backend (PHP)

- [x] Połączenie z bazą (PDO + SQLite)
- [x] Zapisywanie nowego wpisu
- [x] Edycja istniejącego wpisu (UPSERT po dacie)
- [x] Usuwanie wpisu
- [x] Pobieranie wpisu po dacie
- [x] Lista wszystkich dat + poprzednia/następna data (do nawigacji)

## Etap 4 — Widok (HTML/CSS/JS)

- [x] Formularz wpisu: data, godziny (od–do), opis czynności
- [x] Wyświetlanie jednego dnia na ekranie
- [x] Guziki przełączania: ← poprzedni dzień / następny dzień →
- [x] Lista dni (select) do szybkiego wyboru + przycisk „Dzisiaj"
- [x] Obsługa pustego dnia (komunikat + formularz dodania)
- [x] Stylowanie CSS + skróty klawiszowe ← / → (`assets/app.js`)

## Etap 5 — Wydruk

- [x] Przycisk „Drukuj" dla aktualnego dnia
- [x] Style `@media print` — chowanie nawigacji i formularza
- [x] Układ dokumentowy: nagłówek, data, godziny, opis, miejsce na podpis

## Etap 6 — Testy i finalizacja

- [x] Test dodawania / edycji / usuwania wpisów
- [x] Test nawigacji między dniami
- [x] Test zapisu w bazie SQLite
- [ ] Test wydruku (podgląd wydruku w przeglądarce — do sprawdzenia przez użytkownika)
- [x] Przegląd zgodności z `AGENTS.md`
- [ ] Commit i push na GitHuba
