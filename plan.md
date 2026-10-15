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

## Etap 9 — Logowanie

- [x] Tabela `uzytkownicy` (login + hash hasła) w `src/db.php`
- [x] Moduł `src/auth.php`: sesja, konto, logowanie, wylogowanie, zmiana hasła
- [x] Kreator pierwszego konta (`login.php` — tryb „setup" gdy brak konta)
- [x] Ekran logowania (`login.php`) — kolorowy, z tęczowym akcentem
- [x] Wylogowanie (`logout.php`)
- [x] Zmiana hasła w UI (okno „Zmień hasło") + panel konta na dole strony
- [x] Ochrona całej aplikacji, w tym kopii zapasowej (`auth_require_login()`)
- [x] Ochrona CSRF dla wszystkich operacji zapisu (`csrf_field()` / `csrf_check()`)
- [x] Wspólny skrypt motywu `assets/theme.js`
- [x] Testy: dostęp bez logowania, kreator, logowanie, zmiana hasła, CSRF, wylogowanie

## Etap 10 — Strona „O mnie"

- [x] Osobna strona `o-mnie.php` (za logowaniem)
- [x] Dane autora edytowalne w tablicy `$autor` na górze pliku
- [x] Awatar z inicjałami, opis, kafelki z danymi (szkoła, klasa, opiekun, rok, kontakt)
- [x] Link „O mnie" na samym dole strony głównej (+ powrót do dziennika)

## Etap 11 — Pasek akcji chowający się w bok

- [x] Pasek (Dzisiaj / Dodaj / Kopia / Drukuj) zsuwa się w bok przy przewijaniu w dół
- [x] Wraca przy przewijaniu w górę i przy samej górze strony
- [x] Przycisk „⋮" po prawej — ręczne schowanie / przywołanie paska
- [x] Bez poziomego paska przewijania (`overflow-x: clip`), obsługa `prefers-reduced-motion`

## Etap 12 — Optymalizacja

- [x] SQLite: `journal_mode=WAL`, `synchronous=NORMAL`, `busy_timeout=5000`, `temp_store=MEMORY`, `cache_size=-20000`
- [x] `index.php`: jedno zapytanie `all_wpisy()` zamiast wielu (daty, mapa, czasy, statystyki, prev/next z pamięci)
- [x] `konto_login` z sesji (`auth_user()`) zamiast dodatkowego zapytania — ok. 11 → 3 zapytań na stronę
- [x] `.htaccess`: kompresja `mod_deflate` + długie buforowanie zasobów (`mod_expires`, pliki z `?v=mtime`)
- [x] Zachowanie bez zmian (nawigacja, widok tygodnia, statystyki) — potwierdzone testami

## Etap 13 — Płynne przewijanie

- [x] Tło przeniesione z `background-attachment: fixed` na osobną warstwę `body::before` (`position: fixed`) — koniec odmalowywania wielkiego tła przy każdej klatce
- [x] Usunięty `backdrop-filter` z elementów przewijanych/przyklejonych (topbar, pasek akcji, „⋮", motyw, nawigacja, przełącznik widoku, pasek konta) — zostaje tylko na nakładce modala
- [x] Lekko podniesiona kryjność teł (`rgba(...,.82)` → `,.9`/`.92`), by zachować kontrast bez rozmycia
- [x] Dotyczy też ekranu logowania (`body.auth-body::before`) i trybu ciemnego

## Etap 14 — Blokada logowania po nieudanych próbach

- [x] Licznik nieudanych prób po stronie serwera (plik `logowanie.json` obok bazy, poza docroot)
- [x] Po **5** nieudanych próbach logowanie zablokowane na **10 s** (blokada działa też po odświeżeniu i z innej przeglądarki)
- [x] W UI pole hasła i przycisk „Zaloguj się" wyłączone, z odliczaniem na żywo do końca blokady
- [x] Po udanym logowaniu licznik zerowany; komunikat pokazuje liczbę pozostałych prób
- [x] Testy: 5 prób → blokada (pole `disabled`), próba w trakcie blokady odrzucona, po 10 s poprawne logowanie → 302

## Etap 15 — Reset hasła kodem e-mail

- [x] Baza: kolumny `email` oraz `reset_hash`/`reset_wygasa`/`reset_proby`/`reset_wyslano` + automatyczna migracja starych baz (`ALTER TABLE`)
- [x] E-mail konta ustawiany w kreatorze (wymagany) oraz w sekcji „Konto" w aplikacji (zapis + walidacja)
- [x] Link „Nie pamiętam hasła" na ekranie logowania → `reset-hasla.php`
- [x] Krok 1: login lub e-mail → wysyłka 6-cyfrowego kodu (ważny 10 min, max 5 prób, odstęp 30 s). Gdy dane nie pasują do konta — nic nie wysyłamy i nie pokazujemy komunikatu
- [x] Pole e-maila w sekcji „Konto" zabezpieczone: `required` + puste pole nie czyści zapisanego adresu (chroni przed przypadkowym skasowaniem)
- [x] Krok 2: kod + nowe hasło; po sukcesie przekierowanie na logowanie z komunikatem
- [x] Wysyłka: `src/mail.php` (SMTP z STARTTLS/SSL, konfiguracja `poczta.json`/zmienne środowiskowe, tryb `debug`, awaryjnie `mail()`)
- [x] Testy: migracja bazy, pełny reset (zły i poprawny kod), identyfikacja login/e-mail, limit 30 s, brak e-maila, brak konfiguracji poczty, brak ostrzeżeń PHP

## Etap 16 — Załączniki do dnia (zdjęcia / pliki)

- [x] Baza: tabela `zalaczniki` (`data`, `nazwa`, `plik`, `typ`, `rozmiar`, `dodano`) + indeks po dacie (auto-tworzona)
- [x] Pliki trzymane na dysku w katalogu danych (`zalaczniki/`, poza docroot), losowe nazwy, metadane w bazie
- [x] Wgrywanie w karcie wpisu (`enctype="multipart/form-data"`, akcja `add_file`), limit rozmiaru 10 MB, walidacja i rozpoznanie typu (`finfo`)
- [x] Możliwość dołączenia pliku (opcjonalnie) bezpośrednio w oknie „➕ Dodaj wpis" — plik zapisuje się razem z wpisem (akcja `save`); brak pliku nie daje błędu, a odrzucony plik nie blokuje zapisu wpisu
- [x] Odrzucanie niebezpiecznych rozszerzeń (php, phtml, phar, cgi, exe itd.)
- [x] Serwowanie przez `plik.php` (za logowaniem): obrazy/PDF `inline`, pozostałe jako pobranie (`attachment`) z `X-Content-Type-Options: nosniff`
- [x] Sekcja „Załączniki" w dokumencie dnia: miniatury zdjęć, ikony plików, nazwa + rozmiar, otwieranie/pobieranie, usuwanie pojedynczego pliku
- [x] Znacznik 📎 z liczbą plików w liście dni i w widoku tygodnia
- [x] Usunięcie wpisu oraz „Usuń wszystko" sprzątają też pliki z dysku; przywrócenie kopii nie usuwa plików z serwera (kopia `.db` nie zawiera samych plików — dopisek w oknie kopii)
- [x] Style jasne/ciemne + wydruk (lista plików); brak ostrzeżeń PHP
- [x] Wygląd sekcji załączników dopasowany do reszty aplikacji (bloki `--tint`, obramowanie 1px, pole pliku z firmowym przyciskiem `::file-selector-button` jak w oknie kopii)
- [x] Testy: upload PNG/PDF (poprawny MIME), odrzucenie `.php` i pliku 11 MB, podgląd i pobranie, brak dostępu bez logowania (302), 404 dla braku pliku, sprzątanie przy usuwaniu wpisu i całości

## Etap 17 — Strona główna z podsumowaniem godzin

- [x] Nowa strona główna `index.php`: podsumowanie godzin (łącznie, średnio dziennie, bieżący tydzień i miesiąc, pierwszy/ostatni wpis, liczba załączników)
- [x] Duży przycisk „📅 Przejdź do kalendarza" oraz „➕ Dodaj wpis" (`kalendarz.php?dodaj=1`)
- [x] Obecny widok dnia/tygodnia przeniesiony do `kalendarz.php` (wszystkie wewnętrzne linki i akcje formularzy zaktualizowane)
- [x] Link „🏠 Start" w pasku akcji i stopce kalendarza; kalendarz i „O mnie" linkują do siebie nawzajem
- [x] Przekierowania: po zalogowaniu/utworzeniu konta → `index.php` (strona główna); po przywróceniu kopii → `kalendarz.php`
- [x] Stan pusty (brak wpisów) z zaproszeniem do dodania pierwszego wpisu
- [x] Style jasne/ciemne + responsywność (`assets/style.css`); brak ostrzeżeń PHP
- [x] Testy: render z wpisami (łącznie 20 h, tydzień, miesiąc), stan pusty, linki kalendarz↔start, brak starych odnośników `index.php?data=`
- [x] Usunięte z kalendarza panele boczne „Podsumowanie" oraz „Ten tydzień / Ten dzień" — podsumowanie godzin widoczne już tylko na stronie głównej (zostają „Motyw dnia" i „Skróty")
- [x] Sprzątanie po usunięciu: nieużywane zmienne (`$total_min`, `$dni_z_czasem`, `$srednia_min`, `$dzien_min`, `$pierwszy`, `$ostatni`) oraz martwe reguły CSS `.stat`

## Etap 18 — Pasek postępu godzin praktyk na stronie głównej

- [x] Baza: tabela `ustawienia` (`klucz`, `wartosc`) — auto-tworzona w `src/db.php`
- [x] Funkcje `ustawienie()`, `ustawienie_set()` i `wymagane_godziny()` w `src/functions.php`
- [x] Pasek postępu na `index.php`: procent, „wypracowano z X h", „zostało Y"
- [x] Liczba wymaganych godzin edytowalna w aplikacji (pole w formularzu na stronie głównej), zapis z ochroną CSRF (POST/Redirect/GET)
- [x] Gdy nie ustawiono liczby godzin — zachęta do jej ustawienia zamiast paska; po wypracowaniu 100% pasek i komunikat w kolorze „sukcesu"
- [x] Komunikaty zapisu (`godziny=ok` / `zle` / `csrf`) oraz walidacja wartości (liczba całkowita 1–100000)
- [x] Style jasne/ciemne + responsywność (`assets/style.css`); brak ostrzeżeń PHP
- [x] Testy: helpery i logika progresu (0%/70%/100%), pełny zapis przez HTTP (302 → `?godziny=ok`), błędna wartość (`?godziny=zle`), błędny CSRF (`?godziny=csrf`)

## Etap 19 — Konto i kopia zapasowa w ustawieniach

- [x] Sekcja „🔐 Konto" przeniesiona do `ustawienia.php`: zmiana hasła (popup), e-mail do resetu hasła, wylogowanie
- [x] Sekcja „💾 Kopia zapasowa" przeniesiona do `ustawienia.php`: pobranie kopii i przywrócenie z pliku (formularz bezpośrednio na stronie)
- [x] Obsługa akcji `change_password` i `save_email` przeniesiona z `kalendarz.php` do `ustawienia.php` (kody `?haslo=`, `?email=`)
- [x] `backup.php` po przywróceniu przekierowuje na `ustawienia.php` (`?przywrocono=1` / `?blad=`)
- [x] Usunięty przycisk „💾 Kopia" z paska akcji w kalendarzu — kopia dostępna już tylko w ustawieniach
- [x] Usunięte z `kalendarz.php`: pasek konta, popup „Kopia zapasowa", popup „Zmień hasło", komunikaty hasła/e-maila/kopii oraz nieużywane zmienne
- [x] Sprzątanie: martwy blok JS obsługi popupu kopii (`assets/app.js`), style `.btn-backup`, odstępy i kontrast sekcji ustawień (jasny/ciemny)
- [x] Paski akcji: przycisk ustawień jako sama ikonka ⚙️ (bez napisu, z `aria-label`/tooltipem), dosunięty do prawej krawędzi
- [x] Ustawienia: pasek akcji (zmiana motywu + „Start" + „📅 Kalendarz") chowa się w bok przy przewijaniu i wraca przyciskiem „⋮" — jak w kalendarzu (`.actions-dock`); tytuł przewija się ze stroną
- [x] Strona główna: pasek akcji (motyw, „📅 Kalendarz", „🚪 Wyloguj", ⚙️) też w chowanym doku `.actions-dock` z przyciskiem „⋮"; tytuł przewija się ze stroną
- [x] Strona główna: przycisk „Dodaj wpis" przenosi do kalendarza i od razu otwiera popup „Dodaj wpis" (`?dodaj=1` → `window.__dpDodajAutoOpen` w `assets/app.js`)
- [x] Wygląd ustawień: nagłówki sekcji z ikonką w kolorowym kafelku (`.settings-head`/`.settings-ico`), podsumowanie godzin jako karta, panel formularza godzin, wyróżniona karta „Przywróć z kopii", responsywne odstępy (jasny/ciemny)
- [x] Konto: zmiana nazwy konta (login) — `auth_set_login()`, akcja `save_login`, kody `?login=`, walidacja min. 3 znaki; logowanie działa od razu nową nazwą
- [x] Kolory w ustawieniach: każda sekcja ma własny akcent (godziny — indygo/fiolet, konto — niebiesko-cyjanowy, kopia — zielony); kolorowe kafelki ikon, tła i tytuły sekcji, cyjanowe przyciski konta, zielony tytuł „Pobierz kopię"; warianty ciemne
- [x] Testy: `php -l` (ustawienia, kalendarz, backup), `node --check` (`app.js`) — poprawne

## Etap 20 — Wiele kont: administratorzy i uczniowie (branch `multi`)

- [x] Baza: tabela `uzytkownicy` rozszerzona o `rola` (`admin`/`uczen`), `imie_nazwisko`, `wymagane_godziny`; tabele `wpisy` i `zalaczniki` o `user_id` (unikalność `data` w obrębie konta)
- [x] Automatyczna migracja starej (jednokontowej) bazy: pierwsze konto → administrator, istniejące wpisy/załączniki i globalne „wymagane godziny" przypisane do niego
- [x] `src/functions.php`: wszystkie operacje CRUD na wpisach i załącznikach oraz wymagane godziny działają w kontekście konta (`user_id`)
- [x] `src/auth.php`: logowanie wielokontowe, role (`auth_is_admin`), funkcje zarządzania kontami (`user_get`, `user_create`, `user_delete`, `user_set_password/role/name`, `users_all`)
- [x] Reset hasła: wybór konta po loginie **lub** e-mailu (`user_by_login_or_email`), kod wiązany z kontem w sesji
- [x] Kontekst widoku (`auth_view_context()`): administrator może podejrzeć dziennik ucznia przez `?uczen=ID` (tylko odczyt); uczeń widzi wyłącznie własny dziennik
- [x] Nowy panel administratora `admin.php`: dodawanie kont (uczeń/administrator), lista kont ze statystykami, zmiana hasła/roli/nazwy, usuwanie konta z dziennikiem, podgląd dzienniczków
- [x] Ochrona: nie można usunąć ani pozbawić rangi ostatniego administratora; uczeń nie wejdzie do panelu (przekierowanie)
- [x] `plik.php`: uczeń pobiera tylko własne pliki, administrator wszystkie
- [x] Kopia zapasowa tylko dla administratora (`backup.php` → `auth_require_admin()`); sekcja „Kopia" ukryta u uczniów
- [x] Ustawienia: wymagane godziny per konto, znaczek roli, przycisk „Panel admina"; `auth_user()` cache'owany po `uid` (poprawne po przełączeniu konta)
- [x] Wygląd: przycisk „👑 Panel admina", znaczek roli (`role-badge`), baner podglądu (`alert-info`), styl panelu (`admin-*`), warianty ciemne i responsywne
- [x] `AGENTS.md` zaktualizowane do modelu wielokontowego
- [x] Testy CLI: świeża baza (role, logowanie, rozdzielenie dzienników, godziny per konto, ochrona ostatniego admina, reset hasła, usuwanie konta) — OK
- [x] Testy CLI: migracja starej bazy (admin, przypisanie wpisów/załączników/godzin, nadpisanie wpisu) — OK
- [x] Testy HTTP: kreator admina, panel, dodanie ucznia, podgląd (readonly), brak kopii u ucznia, brak dostępu do panelu, auto-otwarcie popupu — 21/21 OK
- [x] Testy: brak ostrzeżeń/notice PHP na wszystkich stronach; `php -l` wszystkich plików — poprawnie
- [x] Administrator nie ma własnego kalendarza/dziennika: `auth_guard_own_journal()` + `auth_home_url()` w `src/auth.php`; `index.php` i `kalendarz.php` bez `?uczen=ID` przekierowują admina do `admin.php` (POST zapisu też zablokowany)
- [x] Usunięte linki do własnego dziennika/kalendarza z panelu (`admin.php`), ustawień i „O mnie"; logowanie/reset hasła kierują admina na `admin.php`, ucznia na `index.php`
- [x] Ustawienia: administrator nie widzi sekcji „Wymagane godziny praktyk" ani nie może jej zapisać (akcja `save_wymagane_godziny` zablokowana)
- [x] Testy HTTP (serwer wbudowany PHP, świeża baza): admin — redirect `index.php`/`kalendarz.php` → `admin.php`, brak linków kalendarza, brak sekcji godzin, podgląd ucznia (readonly) działa; uczeń — własny dziennik i kalendarz bez zmian — OK
- [x] Administrator może **edytować** dziennik ucznia (nie tylko podglądać): `auth_view_context()` zwraca `as_admin` i czyta `uczen` z GET oraz POST; `kal_url()` dokleja kontekst ucznia do adresów i przekierowań; wejście w dziennik ucznia (`index.php?uczen=ID` / `kalendarz.php?uczen=ID`) ma pełny formularz dodawania/edycji/usuwania i załączników — wpisy zapisują się na koncie ucznia
- [x] Formularze POST w kalendarzu niosą ukryte `uczen=ID`, gdy działa administrator; przekierowania po zapisie/usunięciu zachowują kontekst ucznia; bez `?uczen=ID` admin nadal nie zapisze (guard)
- [x] Panel admina: przycisk „✏️ Otwórz dziennik" (zamiast „👀 Podgląd"), zaktualizowane opisy
- [x] Testy HTTP: admin edytuje dziennik ucznia (dodanie, usunięcie wpisu, banner edycji, formularz/modal obecne), redirecty z kontekstem, wpis widoczny u ucznia (8 h), blokada zapisu bez wyboru ucznia — 16/16 OK
- [x] Klasa ucznia: nowa kolumna `klasa` w `uzytkownicy` (`src/db.php`, migracja `db_ensure_column`); `user_klasa()` / `user_label_klasa()` w `src/auth.php`
- [x] Zakładanie konta ucznia wymaga podania klasy: `user_create()` zwraca kod `klasa`, a panel pokazuje „⚠ Podaj klasę ucznia."; konta administratorów nie wymagają klasy
- [x] Panel admina: pole „🎓 Klasa" w formularzu nowego konta (wymagane i widoczne tylko dla roli uczeń — przełączane JS), klasa widoczna na liście kont oraz edytowalna razem z nazwą; etykieta dziennika ucznia z klasą, np. „Jan Kowalski (4A)"
- [x] Testy HTTP: uczeń bez klasy → błąd i brak konta; uczeń z klasą → utworzony i widoczny; admin bez klasy → OK; edycja klasy; etykieta z klasą — 12/12 OK
- [x] Test migracji: stara baza bez kolumny `klasa` po uruchomieniu dostaje kolumnę z domyślną pustą wartością — OK
- [x] Panel admina: lista kont podzielona na **dwie zakładki** — „🎓 Uczniowie" i „👑 Administratorzy" (zakładki CSS na radio-inputach — działają bez JS; licznik kont na każdej zakładce; domyślnie otwarci uczniowie; wspólna karta konta przez domknięcie PHP; wybrana zakładka zapamiętywana w `localStorage`)
- [x] Testy HTTP: zakładki obecne, uczniowie w panelu uczniów, admini w panelu adminów (bez przeciekania), liczniki 2/2, domyślnie zaznaczeni uczniowie — 13/13 OK
- [x] Panel admina: w zakładce „Uczniowie" konta **pogrupowane według klasy** (nagłówek klasy + licznik; „Bez klasy" na końcu; sortowanie naturalne, np. 1A → 2B → 10A); uczniowie z tej samej klasy razem
- [x] Testy HTTP: grupy klas obecne, poprawna kolejność naturalna, uczniowie w swoich grupach, liczniki grup, „Bez klasy" dla konta bez klasy — 21/21 OK

