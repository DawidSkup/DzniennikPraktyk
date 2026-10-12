# Dziennik Praktyk — wymagania

> Postęp prac śledzę w pliku **`plan.md`** — realizuję zadania po kolei i odhaczam je tam na bieżąco.

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
- Przełączanie widoku **dzień / tydzień** — widok tygodniowy pokazuje cały tydzień
  (poniedziałek–niedziela) w tabeli z podsumowaniem
- Dodawanie, edycja i usuwanie wpisów

## Wydruk

- Gotowy do wydruku **pojedynczy dzień** lub **cały tydzień** — przycisk „Drukuj" drukuje aktualnie oglądany widok
- Układ wydruku: czysty, dokumentowy (bez zbędnych elementów GUI), styl `@media print`
