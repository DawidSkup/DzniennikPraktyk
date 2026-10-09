# Dziennik Praktyk — wymagania

## Wymagania techniczne

- Baza danych: **SQLite** (plik `.db`, bez dodatkowego serwera baz danych)
- Technologie: **PHP + HTML + CSS + JS**
- Brak logowania — jeden wspólny dziennik, jedno konto, dane widoczne bez hasła

## Funkcjonalność

- Możliwość wpisywania **daty** oraz **co zrobiłem/am** danego dnia (opis czynności)
- Każdy wpis zawiera dodatkowo **godziny pracy (od – do)**
- Zakres dat **dowolny** — sam wpisuję daty, aplikacja pokazuje tylko wpisane dni
- Każdy dzień wyświetlany jest na osobnej „stronie" (jeden dzień na ekranie)
- Guziki przełączania między stronami: **← poprzedni dzień** / **następny dzień →**
- Dodawanie, edycja i usuwanie wpisów

## Wydruk

- Gotowy do wydruku **pojedynczy dzień** — przycisk „Drukuj" drukuje aktualnie oglądany dzień
- Układ wydruku: czysty, dokumentowy (bez zbędnych elementów GUI), styl `@media print`
