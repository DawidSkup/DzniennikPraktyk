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
- [x] Panel admina: każda klasa jako **rozwijany pasek** (`<details>`/`<summary>`); strzałka obracana przy rozwinięciu, nazwa w jednej linii (długa skracana z „…" + pełna w `title`), przyciski „Zwiń wszystkie"/„Rozwiń wszystkie" przy ≥2 klasach, stan zwinięcia zapamiętywany (`localStorage.dp-admin-collapsed`)
- [x] Testy HTTP: akordeon klas (details/summary, data-class, domyślnie otwarte, brak przycisków zbiorczych przy 1 klasie, przyciski + liczniki + tooltip przy 2 klasach) — 14/14 OK
- [x] Panel admina: **pasek postępu praktyk w karcie każdego ucznia** (procent, „X h z N h", „Zostało …", zielony przy 100%); wymagane godziny pobierane per uczeń (`wymagane_godziny($uid)`), godziny z `$stat`; przy braku wymaganych godzin — informacja zamiast paska; pasek tylko dla uczniów (nie dla adminów)
- [x] Testy HTTP: pasek ucznia z wymaganymi godzinami (40%, „4 h z 10 h", „Zostało 6 h", aria-max), brak paska + komunikat gdy godzin nie ustawiono, dokładnie 1 pasek i 1 karta bez godzin, admin bez paska — 12/12 OK
- [x] Panel admina: **ustawianie wymaganych godzin praktyk uczniowi** — akcja `set_wymagane` (tylko uczniowie; admin odrzucony), walidacja 1–100000, puste/0 czyści wymaganie, komunikat ok/błąd; pole w karcie z aktualną wartością
- [x] Testy HTTP: zapis 10 h i 20 h (ok=godziny, pole z wartością, pasek postępu), wyczyszczenie 0 (brak paska, puste pole), odrzucenie `abc`/`-5`, odrzucenie dla administratora, komunikaty ok/błąd, formularz tylko u uczniów — 18/18 OK
- [x] Panel admina: **dwie zakładki najwyższego poziomu** — „✏️ Edycja kont" (lista, domyślna) i „➕ Nowe konto" (formularz); CSS-only radio, wybór pamiętany (`localStorage.dp-admin-panel-tab`); po dodaniu konta wymuszany powrót na „Edycja kont"
- [x] Testy HTTP: obecność zakładek, domyślna Edycja, formularz w panelu „nowe", lista i wewnętrzne zakładki w panelu „edycja", licznik kont, wymuszenie Edycji po dodaniu, komunikat i wewnętrzne zakładki nadal działają — 16/16 OK
- [x] Panel admina: **wyszukiwarka uczniów** w zakładce „🎓 Uczniowie" — pole „🔎 Szukaj ucznia po imieniu i nazwisku…" (formularz GET `admin.php?q=`, bez CSRF, tylko do odczytu); filtruje listę uczniów po imieniu i nazwisku oraz dodatkowo po **loginie i klasie** (`mb_stripos` — bez rozróżniania wielkości liter, polskie znaki działają); wynik obejmuje grupy klas, liczniki zakładek i karty; nad listą „Znaleziono N uczniów dla „fraza"" (przy 1 wyniku „ucznia"), przy braku wyników „Brak uczniów pasujących do…" + link „Wyczyść wyszukiwanie"; pole prefilled, przycisk „✕ Wyczyść"; przy szukaniu automatycznie wymuszana zakładka 🎓 Uczniowie (`data-admin-tab` + `localStorage.dp-admin-tab`)
- [x] Testy HTTP: bez `q` — wszyscy widoczni, brak komunikatu i przycisku „Wyczyść"; `Kowal` → 2 wyniki, 2 niechcianych ukrytych; `nowak` → 1 (wielkość liter); `Zieliński` i `zieli` → 1 (polskie znaki); `2B` → 2; `uczen.a1` → 1; prefilled pola; „Wyczyść"; wymuszenie zakładki Uczniowie; licznik Uczniowie=2 / Administratorzy=1 (bez wpływu na adminów); `nikt` → komunikat o braku — 36/36 OK
- [x] Panel admina: **przycisk „➕ Nowe konto" w pasku akcji w prawym górnym rogu** (między przełącznikiem motywu a ⚙️) — klik przełącza na zakładkę „➕ Nowe konto", zapisuje wybór (`localStorage.dp-admin-panel-tab` przez `dispatchEvent('change')`), przewija stronę do formularza i ustawia kursor w polu loginu (`#nu-login`); styl `.btn-new` (zielony gradient zgodny z zakładką)
- [x] Testy HTTP: przycisk w `#actions-bar`, kolejność przycisków (przed ⚙️ i 🚪), cele `#ptab-nowe`/`#nu-login`, podpięty skrypt + style `.btn-new` — 15/15 OK
- [x] Testy w prawdziwej przeglądarce (Chrome headless, sterowanie CDP): start = domyślna „Edycja kont" (panel edycji widoczny, „nowe" ukryty); klik → przełączenie na „Nowe konto", widoczny formularz, ukryta edycja, wybór w `localStorage`, kursor w `#nu-login`, strona przewinięta; po odświeżeniu zakładka zostaje; powrót przez zakładkę „Edycja kont" działa — 14/14 OK
- [x] Panel admina: **formularz „Nowe konto" jako wyskakujące okienko (popup)** — panel „nowe" zamienia się w nakładkę `position: fixed` na cały ekran; przyciemnione rozmyte tło (`.modal-backdrop` = `<label for="ptab-edycja">`, więc zamyka też bez JS) i wyśrodkowana karta (`.modal-card`, `role="dialog"`, `aria-modal`, `aria-labelledby`); zamykanie **✕ / Esc / klik w tło**; kursor wskakuje w pole loginu; prosta **pułapka fokusu** (Tab) i blokada przewijania tła (`body:has(#ptab-nowe:checked)`); animacja wejścia; po utworzeniu konta okienko zamyka się i pokazuje listę
- [x] Testy HTTP: markup popupu (tło, karta-dialog, ✕), kolejność DOM, skrypt (✕/Esc/otwieranie/pułapka Tab), style, poprawność POST-a i powrót do listy po dodaniu konta — 23/23 OK
- [x] Testy w przeglądarce (Chrome headless, CDP): start zamknięty i tło nieblokowane; otwarcie przyciskiem i zakładką; `position: fixed`, z-index 1000, przyciemnione tło na całym ekranie, karta nad tłem i wyśrodkowana, fokus w loginie, blokada przewijania, `role=dialog`+`aria-modal`; zamknięcie ✕ (fokus wraca na przycisk), Esc, klik w tło; pułapka Tab (z ostatniego na ✕); zapis konta z popupu → `ok=dodano`, popup zamknięty, konto widoczne na liście — 24/24 OK
- [x] Panel admina: **przejrzystsza i czytelniejsza karta konta** (zakładka „✏️ Edycja kont") — awatar z inicjałami (`.admin-user-avatar`), nagłówek w dwóch liniach (nazwa + odznaka roli + klasa + „to Ty" / login z ikoną 👤), statystyki po prawej, nagłówek oddzielony linią; wszystkie operacje przeniesione do **rozwijanej sekcji „⚙️ Zarządzaj kontem"** (`<details>`/`<summary>`), gdzie każda operacja ma **widoczną etykietę** (Rola konta, Wymagane godziny praktyk, Imię i nazwisko oraz klasa, Nowe hasło) i własną podpowiedź, a usuwanie konta jest w osobnej „strefie zagrożenia"; stan rozwinięcia pamiętany w `localStorage` (`dp-admin-open-user`) — po zapisie sekcja zostaje otwarta; `min="0"` przy godzinach zgodne z serwerem (0 czyści wymaganie)
- [x] Testy HTTP: nowa struktura karty (awatar z inicjałami, dwie linie nagłówka, brak starego inline loginu), zwijana sekcja „Zarządzaj kontem", widoczne etykiety, strefa usuwania, wszystkie 5 akcji nadal obecne i **działają end-to-end** (set_role, set_wymagane=120/0, set_name, reset_password, delete_user), style + tryb ciemny, skrypt pamięci — 44/44 OK
- [x] Testy w przeglądarce (Chrome headless, CDP, 1440×900): awatar 42×42 okrągły, nagłówek w dwóch liniach, sekcja domyślnie zwinięta (`checkVisibility()`), rozwija się w siatkę 2 kolumn, 4 etykiety PL, pola zachowane, strefa usuwania; pamięć rozwinięcia po przeładowaniu; ustawienie godzin przez UI → `ok=godziny`, pasek „z 100 h", sekcja nadal otwarta; brak rozjechania karty; tryb ciemny — 30/30 OK
- [x] Panel admina: **masowa zmiana klasy całej grupy** (np. 2A → 3A) — w każdej grupie klas pasek „✏️ Zmień klasę całej grupie (N)" z polem nowej nazwy i przyciskiem „✏️ Zmień klasę"; nowa funkcja `user_set_klasa_bulk()` w `src/auth.php` (liczy uczniów grupy, potem `UPDATE ... WHERE rola='uczen' AND COALESCE(klasa,'')=:stara` — **administratorzy nigdy nie są objęci**); akcja `set_klasa` z potwierdzeniem `confirm()`, walidacja maks. 40 znaków (`err=klasa`), brak uczniów w grupie → `err=klasa-pusto`; puste pole przenosi uczniów do grupy „Bez klasy" (można też odwrotnie — przypisać klasę uczniom bez klasy); komunikat z poprawną odmianą „✔ Zmieniono klasę (N kont/konta/konto)."; po zmianie wymuszana zakładka 🎓 Uczniowie; pasek ukryty, gdy trwa wyszukiwanie (żeby nie zmienić klasy uczniom poza filtrem); kolorowy styl `.admin-class-rename` (tło i przerywana ramka w kolorze klasy, tryb ciemny)
- [x] Testy HTTP: markup paska (akcja, ukryte `from`, pole nowej klasy, podpowiedź, etykieta z licznikiem, CSS), zmiana 2A→3A (3 konta, komunikat, grupy, karty uczniów), 3B→puste („Bez klasy"), „Bez klasy"→1A, administrator **nie** dostaje klasy przy zmianie grupy pustej (`err=klasa-pusto`), nazwa > 40 znaków → `err=klasa`, brak paska przy wyszukiwaniu — 38/38 OK
- [x] Testy w przeglądarce (Chrome headless, CDP, 1440×900): pasek widoczny w grupie, wypełniony obecną nazwą, kolorowe tło + przerywana ramka, podpowiedź o „Bez klasy"; zmiana nazwy przez UI → okno `confirm()` akceptowane, komunikat z licznikiem, stara grupa znika, nowa się pojawia, odznaka klasy w karcie ucznia zaktualizowana, liczba kart bez zmian; zmiana na tę samą nazwę działa; pusta nazwa → „Bez klasy"; brak paska przy wyszukiwaniu — 20/20 OK
- [x] Panel admina: **zmiana klasy bez rozwijania paska** — pole „Klasa:" z obecną nazwą grupy i przycisk „✏️ Zapisz" przeniesione do nagłówka paska (`<summary class="admin-class-head">`), więc klasę zmienia się przy zwiniętej grupie; `title` formularza wyjaśnia, że puste pole przenosi uczniów do „Bez klasy"; w środku grupy została tylko lista uczniów (usunięty `.admin-class-body`, wróciło `.admin-class-group .admin-users`), zniknęła też stała podpowiedź; pole jest zaznaczalne (mimo `user-select: none` na nagłówku), a skrypt blokuje rozwijanie/zwijanie paska przy pracy z formularzem (`e.target.closest('.admin-class-rename')` → `preventDefault()`, z wyjątkiem przycisku „Zapisz", żeby wysyłka działała też bez JS); na wąskich ekranach (do 620 px) formularz przechodzi w drugą linię nagłówka
- [x] Testy HTTP: formularz w nagłówku (`<summary>`), etykieta „Klasa:", pole wypełnione obecną nazwą, przycisk „Zapisz", ukryte `action=set_klasa` + `from` + CSRF, brak formularza wewnątrz grupy, brak `.admin-class-body`/`.manage-hint`, zmiana 2A → 4C (2 konta), nazwa > 40 znaków → `err=klasa`, grupa bez uczniów → `err=klasa-pusto`, pusta nazwa → „Bez klasy" (puste pole i puste `from`), brak formularza przy wyszukiwaniu, style + skrypt — 42/42 OK
- [x] Testy w przeglądarce (Chrome headless, CDP): formularz i pole widoczne przy ZWINIĘTYM pasku (`checkVisibility()`), brak wystawania poza pasek, klik w pole nie rozwija/zwija paska, klik w nazwę klasy nadal rozwija (kontrola metody), anulowanie `confirm()` nie zmienia stanu paska, zapis przez UI → komunikat z licznikiem + nowa grupa + zaktualizowane odznaki klas, druga (zwinięta) grupa zostaje zwinięta, liczba kart bez zmian, pusta nazwa → „Bez klasy", zawijanie w drugą linię przy 380 px, kontrast pola w trybie ciemnym; dodatkowo geometria (brak nachodzenia i wystawania) na 1440/1024/900/700/620 px — 29/29 + 10/10 OK
- [x] Panel admina: **przycisk „⚙️ Zarządzaj klasą" w nagłówku paska klasy** (zamiast formularza w nagłówku) — otwiera **wyskakujące okienko** i działa też przy zwiniętym pasku. Okno trzyma ukryty checkbox (`.class-mgr-input:checked + .class-mgr`), więc **działa bez JS**; przyciemnione rozmyte tło (`.modal-backdrop` = `<label>`), zamykanie **✕ / Esc / klik w tło**, blokada przewijania tła (`body:has(.class-mgr-input:checked)`), pułapka Tab, kursor wskakuje w pole nazwy klasy. W okienku **dwie akcje**: (1) **Zmiana klasy całej grupy** (dotychczasowa akcja `set_klasa` z potwierdzeniem) oraz (2) **Usunięcie całej klasy na raz** — nowa akcja `delete_class` i funkcja `user_delete_class()` w `src/auth.php` trwale usuwa wszystkie konta uczniów tej klasy wraz z wpisami i załącznikami (po jednym `user_delete()`), z potwierdzeniem `confirm()` i licznikiem wpisów grupy; grupa „Bez klasy" **nie ma** przycisku usuwania (jest informacja, żeby usuwać konta pojedynczo). Formularze ukryte przy wyszukiwaniu; komunikaty ok (`ok=usun-klase`, poprawna odmiana kont/konta/konto) i błąd (`err=klasa-pusto`)
- [x] Testy HTTP: przycisk „⚙️ Zarządzaj klasą" w nagłówku (bez formularza w nagłówku), okno poza `<details>` (działa przy zwiniętym pasku), ukryty checkbox + akcje `set_klasa` i `delete_class`, brak usuwania dla „Bez klasy", usunięcie klasy (konta i dzienniki znikają, `ok=usun-klase`), brak grupy → `err=klasa-pusto`, brak przycisku/okna przy wyszukiwaniu — OK
- [x] Testy w przeglądarce (Chrome headless, CDP): okno otwierane przy ZWINIĘTYM pasku (`position: fixed`, tło na całym ekranie, karta nad tłem), klik w przycisk nie rozwija paska, zamknięcie ✕ / Esc / klik w tło, zmiana klasy przez UI i usunięcie całej klasy z potwierdzeniem, kontrast w trybie ciemnym, zawijanie przycisku przy 380 px — OK
- [x] Panel admina: **wyszukiwarka administratorów po imieniu i nazwisku** — w zakładce „👑 Administratorzy" nowe pole (parametr `qa`, osobny od `q`), więc szukanie kont jednego typu nie filtruje drugiego; filtr po imieniu i nazwisku (dodatkowo login), ignoruje wielkość liter i przycina spacje; licznik w zakładce pokazuje liczbę trafień, po wyszukaniu wymuszana jest zakładka Administratorzy (`data-admin-tab`), jest komunikat „Znaleziono…", stan pusty i przycisk „✕ Wyczyść"
- [x] Testy HTTP (serwer wbudowany PHP, świeża baza): brak `data-admin-tab` bez frazy; `?qa=anna` → zakładka admini, 2 administratorów, licznik 2, zachowana fraza; `?qa=Kowalski` → 1 trafienie; brak trafień → pusty komunikat; same spacje → brak wymuszenia; `?q=Kowal` nadal filtruje tylko uczniów (admini pełni); oba parametry → wygrywa zakładka uczniów i tylko jeden atrybut `data-admin-tab`; spacje przycinane; `?qa[]=anna` nie wywala strony; brak ostrzeżeń PHP — 40/40 OK
- [x] Panel admina: **karta administratora bez statystyk dziennika** — blok `.admin-user-stats` („📄 N wpisów", „⏱ czas") renderowany tylko dla uczniów; administrator nie ma własnego dziennika, więc nie widzi licznika wpisów ani łącznego czasu (dotyczy też karty własnego konta z „to Ty"); uczniowie bez zmian
- [x] Testy HTTP: dokładnie 4 wystąpienia `admin-user-stats` (tylko karty 4 uczniów), wszyscy 3 administratorzy i 4 uczniowie nadal na liście, brak ostrzeżeń PHP — 8/8 OK (plus regresja 40/40)
- [x] Panel admina: **innemu administratorowi nie można zmienić hasła ani imienia i nazwiska** — na karcie innego admina (rola `admin` i nie „to Ty") ukryte są formularze `set_name` i `reset_password`, a zamiast nich jest żółta notka `.manage-note` (nowy styl + tryb ciemny); ukrycie to tylko UI, więc **serwer też blokuje**: nowy strażnik `$cudzy_admin($id)` przed `user_set_password` / `user_set_name` odsyła do `err=admin-chroniony` (nowy komunikat błędu). Własne konto i konta uczniów edytuje się jak dotąd; rola i usuwanie konta bez zmian (poza ostatnim administratorem)
- [x] Testy HTTP (serwer wbudowany PHP, świeża baza): 2 notki „🔒" i tylko 5× akcje `set_name`/`reset_password` (4 uczniów + ja) przy 7× `set_role`; POST zmiany hasła i imienia cudzego administratora → `err=admin-chroniony`, hash i imię w bazie bez zmian; własne konto (hasło i imię) oraz konto ucznia edytują się poprawnie; stan przywrócony po testach; brak ostrzeżeń PHP — 24/24 OK (plus regresja 40/40 i 8/8)
- [x] Panel admina: **zmiana loginu (nazwy konta)** — w karcie ucznia i własnego konta nowy formularz „👤 Login (nazwa konta)" z przyciskiem „Zmień login"; nowa funkcja `user_set_login()` w `src/auth.php` (+ pomocnicze `user_login_exists()`), akcja `set_login` z komunikatami ok (`ok=login`) i błędami (`err=login` za krótki, `err=login-zajety` gdy nazwa zajęta). Login musi mieć min. 3 znaki i być unikalny (`login` w bazie jest `UNIQUE`); po zmianie loguje się nowa nazwa, a sesja działa dalej (bo sesja trzyma `id`, nie login)
- [x] Testy HTTP: formularz loginu dokładnie dla 5 kont (4 uczniów + ja), brak dla innych adminów; zmiana loginu uczniowi → `ok=login` i w bazie `jan-nowy` (to samo konto/id); zajęty login → `err=login-zajety` bez zmian; za krótki → `err=login` bez zmian; ten sam login przechodzi; własny login → `ok=login`, strona i sesja działają dalej; login innego administratora → `err=admin-chroniony` i brak nowego konta; stan przywrócony; brak ostrzeżeń PHP — 28/28 OK (plus regresja 40/40, 8/8 i 24/24)

