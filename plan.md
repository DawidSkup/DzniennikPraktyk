# Plan realizacji — Dziennik Praktyk

## Etap 1 — Przygotowanie

- [x] Spisać wymagania w `AGENTS.md`
- [x] Zainicjować repozytorium Git
- [x] Wrzucić `AGENTS.md` na GitHuba
- [ ] Utworzyć strukturę projektu (`public/`, `src/`, `data/`)

## Etap 2 — Baza danych (SQLite)

- [ ] Utworzyć plik bazy `data/dziennik.db`
- [ ] Tabela `wpisy`: `id`, `data` (unikalna), `godzina_od`, `godzina_do`, `opis`
- [ ] Skrypt inicjalizujący bazę (auto-twórzenie przy pierwszym uruchomieniu)

## Etap 3 — Backend (PHP)

- [ ] Połączenie z bazą (PDO + SQLite)
- [ ] Zapisywanie nowego wpisu (dodawanie)
- [ ] Edycja istniejącego wpisu
- [ ] Usuwanie wpisu
- [ ] Pobieranie wpisu po dacie
- [ ] Lista wszystkich dat (do nawigacji ← / →)

## Etap 4 — Widok (HTML/CSS/JS)

- [ ] Formularz wpisu: data, godziny (od–do), opis czynności
- [ ] Wyświetlanie jednego dnia na ekranie
- [ ] Guziki przełączania: ← poprzedni dzień / następny dzień →
- [ ] Lista dni (skrócona) do szybkiego wyboru
- [ ] Obsługa pustego dnia (komunikat + przycisk „Dodaj wpis")
- [ ] Stylowanie CSS (czytelny, prosty wygląd)

## Etap 5 — Wydruk

- [ ] Przycisk „Drukuj" dla aktualnego dnia
- [ ] Style `@media print` — chowanie nawigacji i przycisków
- [ ] Układ dokumentowy: nagłówek, data, godziny, opis, miejsce na podpis

## Etap 6 — Testy i finalizacja

- [ ] Test dodawania / edycji / usuwania wpisów
- [ ] Test nawigacji między dniami
- [ ] Test wydruku (podgląd wydruku w przeglądarce)
- [ ] Przegląd zgodności z `AGENTS.md`
- [ ] Commit i push na GitHuba
