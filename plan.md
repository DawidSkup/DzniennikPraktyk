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

## Etap 6 — Widok tygodniowy

- [x] Przełącznik widoku **dzień / tydzień** (`widok=dzien|tydzien`)
- [x] Tabela tygodnia: poniedziałek–niedziela (data, godziny, opis, status)
- [x] Podsumowanie tygodnia (łączny czas pracy, liczba dni z wpisem)
- [x] Nawigacja ← / → skacząca o tydzień oraz skrót klawiszowy `T`
- [x] Skróty/edycja pojedynczego dnia z poziomu tabeli tygodnia
- [x] Wydruk całego tygodnia (`@media print` dla tabeli)
- [x] Poprawa czytelności: większa typografia, zebra, scalenie „Godziny i czas", lepszy kontrast
- [x] Klasyczna tabela z siatką (wyraźne linie komórek), także na wydruku

## Etap 7 — Testy i finalizacja

- [x] Test dodawania / edycji / usuwania wpisów
- [x] Test nawigacji między dniami
- [x] Test przełączania widoku dzień / tydzień i nawigacji tygodniowej
- [x] Test zapisu w bazie SQLite
- [ ] Test wydruku (podgląd wydruku w przeglądarce — do sprawdzenia przez użytkownika)
- [x] Przegląd zgodności z `AGENTS.md`
- [x] Commit i push na GitHuba

## Etap 8 — Kopia zapasowa

- [x] Endpoint `backup.php` — pobranie spójnej kopii bazy jako plik `.db` (`VACUUM INTO`, fallback `copy`)
- [x] Przywracanie dziennika z wgranej kopii `.db` (walidacja struktury bazy)
- [x] Automatyczna kopia bezpieczeństwa obecnego stanu przed przywróceniem
- [x] Okno „Kopia zapasowa" w UI: pobierz / przywróć (z potwierdzeniem)
- [x] Komunikaty sukcesu i błędów kopii zapasowej
- [ ] Test kopii i przywrócenia — do sprawdzenia przez użytkownika
