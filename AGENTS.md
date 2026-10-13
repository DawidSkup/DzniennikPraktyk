# Dziennik Praktyk — wymagania

> Postęp prac śledzę w pliku **`plan.md`** — realizuję zadania po kolei i odhaczam je tam na bieżąco.

## Wymagania techniczne

- Baza danych: **SQLite** (plik `.db`, bez dodatkowego serwera baz danych)
- Technologie: **PHP + HTML + CSS + JS**
- Logowanie: **jedno wspólne konto** (login + hasło) — jeden dziennik, dane dostępne po zalogowaniu
- Bezpieczeństwo: hasła hashowane (`password_hash`), sesja po stronie serwera, ochrona CSRF na operacjach zapisu. Konto tworzone przy pierwszym uruchomieniu (kreator), hasło zmieniane w UI. Po **5 nieudanych próbach** logowanie blokowane na **10 sekund** (licznik po stronie serwera; pole hasła zablokowane z odliczaniem)
- Reset hasła: link „Nie pamiętam hasła" na ekranie logowania. Kod 6-cyfrowy wysyłany **e-mailem** (SMTP), ważny 10 min, max 5 błędnych prób. Adres e-mail ustawia się w kreatorze konta oraz w sekcji „Konto" w aplikacji. Gdy podane dane nie pasują do konta, aplikacja nic nie wysyła i nie pokazuje komunikatu. Uwaga: przywrócenie kopii zapasowej cofa też adres e-mail (bo kopia zawiera całą bazę)

## Funkcjonalność

- Możliwość wpisywania **daty** oraz **co zrobiłem/am** danego dnia (opis czynności)
- Każdy wpis zawiera dodatkowo **godziny pracy (od – do)**
- Zakres dat **dowolny** — sam wpisuję daty, aplikacja pokazuje tylko wpisane dni
- Każdy dzień wyświetlany jest na osobnej „stronie" (jeden dzień na ekranie)
- Guziki przełączania między stronami: **← poprzedni dzień** / **następny dzień →**
- Przełączanie widoku **dzień / tydzień** — widok tygodniowy pokazuje cały tydzień
  (poniedziałek–niedziela) w tabeli z podsumowaniem
- Dodawanie, edycja i usuwanie wpisów
- **Załączniki do dnia**: do każdego dnia można dodać zdjęcie lub plik (maks. 10 MB). Zdjęcia pokazują się jako miniatury, pozostałe pliki jako lista do otwarcia/pobrania; usuwanie pojedynczego pliku. Pliki leżą na dysku w katalogu danych (poza docroot) i są podawane przez `plik.php` wyłącznie po zalogowaniu
- Kopia zapasowa: pobranie dziennika do pliku `.db` oraz przywrócenie z kopii (kopia obejmuje wpisy i listę plików, ale **nie zawiera samych załączników** — te leżą osobno na serwerze)
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
