# Dziennik Praktyk — wymagania

> Postęp prac śledzę w pliku **`plan.md`** — realizuję zadania po kolei i odhaczam je tam na bieżąco.

## Wymagania techniczne

- Baza danych: **SQLite** (plik `.db`, bez dodatkowego serwera baz danych)
- Technologie: **PHP + HTML + CSS + JS**
- Logowanie: **wiele kont** (login + hasło) z rolami — **administrator** i **uczeń**. Każdy uczeń ma **własny dziennik** (widzi i edytuje tylko swoje wpisy). Administrator ma **panel admina** (`admin.php`): zakłada konta uczniów i administratorów oraz **otwiera i edytuje** dzienniczki wszystkich uczniów (`index.php?uczen=ID` / `kalendarz.php?uczen=ID` — wpisy zapisują się na koncie ucznia). Administrator **nie prowadzi własnego dziennika ani kalendarza** — nie ma własnych wpisów, a jego stroną startową jest panel; wejście na `index.php` / `kalendarz.php` bez `?uczen=ID` przekierowuje go do `admin.php`
- Bezpieczeństwo: hasła hashowane (`password_hash`), sesja po stronie serwera, ochrona CSRF na operacjach zapisu. Pierwsze konto tworzone przy pierwszym uruchomieniu (kreator) zostaje **administratorem**. Hasło zmieniane w UI oraz przez admina w panelu. Po **5 nieudanych próbach** logowanie blokowane na **10 sekund** (licznik po stronie serwera; pole hasła zablokowane z odliczaniem)
- Reset hasła: link „Nie pamiętam hasła" na ekranie logowania. Kod 6-cyfrowy wysyłany **e-mailem** (SMTP), ważny 10 min, max 5 błędnych prób. Adres e-mail ustawia się w kreatorze konta oraz w sekcji „Konto" w aplikacji. Kod dopasowuje konto po loginie **lub** e-mailu. Gdy podane dane nie pasują do żadnego konta, aplikacja nic nie wysyła i nie pokazuje komunikatu. Uwaga: przywrócenie kopii zapasowej cofa też adresy e-mail i konta (bo kopia zawiera całą bazę)

## Funkcjonalność

- **Strona główna** (`index.php`) z podsumowaniem godzin praktyk (łącznie, średnio
  dziennie, bieżący tydzień i miesiąc) oraz przyciskiem przechodzącym do kalendarza.
  Podsumowanie godzin znajduje się **tylko** na stronie głównej
- **Pasek postępu praktyk** na stronie głównej: pokazuje, ile godzin już wypracowano
  z wymaganej liczby godzin (procent oraz „zostało N h"). Wymaganą liczbę godzin
  wpisuje się bezpośrednio na stronie głównej (edytowalna w aplikacji)
- Kalendarz (`kalendarz.php`) — widok dnia i tygodnia z wpisami
- Możliwość wpisywania **daty** oraz **co zrobiłem/am** danego dnia (opis czynności)
- Każdy wpis zawiera dodatkowo **godziny pracy (od – do)**
- Zakres dat **dowolny** — sam wpisuję daty, aplikacja pokazuje tylko wpisane dni
- Każdy dzień wyświetlany jest na osobnej „stronie" (jeden dzień na ekranie)
- Guziki przełączania między stronami: **← poprzedni dzień** / **następny dzień →**
- Przełączanie widoku **dzień / tydzień** — widok tygodniowy pokazuje cały tydzień
  (poniedziałek–niedziela) w tabeli z podsumowaniem
- Dodawanie, edycja i usuwanie wpisów
- **Załączniki do dnia**: do każdego dnia można dodać zdjęcie lub plik (maks. 10 MB). Zdjęcia pokazują się jako miniatury, pozostałe pliki jako lista do otwarcia/pobrania; usuwanie pojedynczego pliku. Pliki leżą na dysku w katalogu danych (poza docroot) i są podawane przez `plik.php` wyłącznie po zalogowaniu (uczeń widzi tylko własne pliki, administrator wszystkie)
- **Panel administratora** (`admin.php`, tylko dla roli admin): podzielony na **dwie zakładki** — **„✏️ Edycja kont"** (lista kont) i **„➕ Nowe konto"** (formularz zakładania), z dodatkowym **przyciskiem „➕ Nowe konto" w pasku akcji w prawym górnym rogu** (klik przełącza na zakładkę tworzenia, przewija do formularza i ustawia kursor w polu loginu). Zakładanie kont (uczeń/administrator — przy koncie ucznia **wymagana klasa**), lista kont w **dwóch zakładkach** (🎓 Uczniowie / 👑 Administratorzy), a uczniowie dodatkowo **pogrupowani według klasy** — każda klasa jako **rozwijany pasek/akordeon** (`<details>`/`<summary>`, jeden wiersz z nazwą i licznikiem, „Bez klasy" na końcu, sortowanie naturalne), z klasą, liczbą wpisów i godzin, **paskiem postępu praktyk w karcie każdego ucznia** (procent, ile ma godzin i ile jeszcze brakuje; gdy nie ustawiono wymaganych godzin, krótka informacja zamiast paska) oraz **wyszukiwarką uczniów po imieniu i nazwisku** (pole „Szukaj ucznia po imieniu i nazwisku" nad listą — szuka też po loginie i klasie, filtruje listę i liczniki, prefilled po podaniu, przycisk „Wyczyść"; przy szukaniu automatycznie otwiera się zakładka 🎓 Uczniowie), **ustawianiem wymaganych godzin praktyk uczniowi** z panelu (pole + „Zapisz godziny"; puste/0 czyści wymaganie), zmiana hasła, roli oraz nazwy i klasy, usuwanie konta wraz z jego dziennikiem, **otwieranie i edycja dzienniczka ucznia** (`index.php?uczen=ID` / `kalendarz.php?uczen=ID` — wpisy i załączniki zapisują się na koncie ucznia). Nie można usunąć ani pozbawić rangi ostatniego administratora. Administrator nie ma własnego kalendarza/dziennika: w panelu i ustawieniach nie ma do niego linków, a `index.php` / `kalendarz.php` bez `?uczen=ID` przekierowują do panelu. W ustawieniach administratora nie ma też sekcji „Wymagane godziny praktyk"
- Kopia zapasowa (tylko administrator): pobranie **całej bazy** do pliku `.db` oraz przywrócenie z kopii (kopia obejmuje wpisy, konta i listę plików, ale **nie zawiera samych załączników** — te leżą osobno na serwerze)
- Strona „O mnie" z danymi autora (link na samym dole strony)

## Wydruk

- Gotowy do wydruku **pojedynczy dzień** lub **cały tydzień** — przycisk „Drukuj" drukuje aktualnie oglądany widok
- Układ wydruku: czysty, dokumentowy (bez zbędnych elementów GUI), styl `@media print`

## Konfiguracja poczty (reset hasła e-mailem)

Kod resetu wysyłany jest przez SMTP. Konfiguracja (w kolejności ważności):

1. **Zmienne środowiskowe**: `DZIENNIK_SMTP_HOST`, `DZIENNIK_SMTP_PORT`, `DZIENNIK_SMTP_USER`,
   `DZIENNIK_SMTP_PASS`, `DZIENNIK_SMTP_SECURE` (`tls`/`ssl`), `DZIENNIK_SMTP_FROM`.
2. **Plik `poczta.json`** w katalogu danych (obok bazy, poza docroot) — wzór: `poczta.example.json`.

```json
{
  "host":   "smtp.gmail.com",
  "port":   587,
  "user":   "twoj.adres@gmail.com",
  "pass":   "haslo-aplikacji",
  "secure": "tls",
  "from":   "twoj.adres@gmail.com",
  "debug":  false
}
```

Tryb `"debug": true` **nie wysyła** wiadomości — zapisuje ją do pliku `poczta-log.txt`
w katalogu danych (przydatne lokalnie, bez serwera SMTP). Uwaga: Gmail wymaga
**hasła aplikacji**, nie zwykłego hasła do konta.
