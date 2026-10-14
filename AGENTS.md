# Dziennik Praktyk — wymagania

> Postęp prac śledzę w pliku **`plan.md`** — realizuję zadania po kolei i odhaczam je tam na bieżąco.

## Wymagania techniczne

- Baza danych: **SQLite** (plik `.db`, bez dodatkowego serwera baz danych)
- Technologie: **PHP + HTML + CSS + JS**
- Logowanie: **wiele kont** (login + hasło) z rolami — **administrator** i **uczeń**. Każdy uczeń ma **własny dziennik** (widzi i edytuje tylko swoje wpisy). Administrator ma **panel admina** (`admin.php`): zakłada konta uczniów i administratorów oraz przegląda dzienniczki wszystkich uczniów (tylko podgląd)
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
- **Panel administratora** (`admin.php`, tylko dla roli admin): zakładanie kont (uczeń/administrator), lista kont z liczbą wpisów i godzin, zmiana hasła, roli i nazwy, usuwanie konta wraz z jego dziennikiem, podgląd dzienniczka ucznia (tylko odczyt przez `index.php?uczen=ID` / `kalendarz.php?uczen=ID`). Nie można usunąć ani pozbawić rangi ostatniego administratora
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
