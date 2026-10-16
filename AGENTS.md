# Dziennik Praktyk — wymagania

> Postęp prac śledzę w pliku **`plan.md`** — realizuję zadania po kolei i odhaczam je tam na bieżąco.

## Wymagania techniczne

- Baza danych: **SQLite** (plik `.db`, bez dodatkowego serwera baz danych)
- Technologie: **PHP + HTML + CSS + JS**
- Logowanie: **wiele kont** (login + hasło) z rolami — **administrator** i **uczeń**. Na ekranie logowania widnieje **wybór roli** („Loguję się jako": **🎓 Uczeń** / **👑 Administrator**, domyślnie uczeń) i jest **sprawdzany**: gdy wybrana rola nie zgadza się z rolą konta, logowanie jest odrzucane z komunikatem (błędny wybór **nie** liczy się do blokady po nieudanych próbach); kreator pierwszego konta nie ma tego wyboru, bo zawsze zakłada administratora. Każdy uczeń ma **własny dziennik** (widzi i edytuje tylko swoje wpisy). Administrator ma **panel admina** (`admin.php`): zakłada konta uczniów i administratorów oraz **otwiera i edytuje** dzienniczki wszystkich uczniów (`index.php?uczen=ID` / `kalendarz.php?uczen=ID` — wpisy zapisują się na koncie ucznia). Administrator **nie prowadzi własnego dziennika ani kalendarza** — nie ma własnych wpisów, a jego stroną startową jest panel; wejście na `index.php` / `kalendarz.php` bez `?uczen=ID` przekierowuje go do `admin.php`
- Bezpieczeństwo: hasła hashowane (`password_hash`), sesja po stronie serwera, ochrona CSRF na operacjach zapisu. Pierwsze konto tworzone przy pierwszym uruchomieniu (kreator) zostaje **administratorem**. Hasło zmieniane w UI oraz przez admina w panelu. Po **5 nieudanych próbach** logowanie blokowane na **10 sekund** (licznik po stronie serwera; pole hasła zablokowane z odliczaniem)
- Reset hasła: link „Nie pamiętam hasła" na ekranie logowania. Kod 6-cyfrowy wysyłany **e-mailem** (SMTP), ważny 10 min, max 5 błędnych prób. Adres e-mail ustawia się w kreatorze konta oraz w sekcji „Konto" w aplikacji. Kod dopasowuje konto po loginie **lub** e-mailu. Gdy podane dane nie pasują do żadnego konta, aplikacja nic nie wysyła i nie pokazuje komunikatu. Uwaga: przywrócenie kopii zapasowej cofa też adresy e-mail i konta (bo kopia zawiera całą bazę)

## Funkcjonalność

- **Strona główna** (`index.php`) z podsumowaniem godzin praktyk (łącznie, średnio
  dziennie, bieżący tydzień i miesiąc) oraz przyciskiem przechodzącym do kalendarza.
  Podsumowanie godzin znajduje się **tylko** na stronie głównej
- **Pasek postępu praktyk** na stronie głównej: pokazuje, ile godzin już wypracowano
  z wymaganej liczby godzin (procent oraz „zostało N h"). Wymaganą liczbę godzin
  wpisuje się bezpośrednio na stronie głównej (edytowalna w aplikacji). **Pod paskiem
  widnieje licznik dni do końca praktyk** (liczony od dzisiejszej daty do daty „do"
  z okresu praktyk): „📅 Do końca praktyk — N dni (do …)", w ostatnim dniu
  „⏳ Ostatni dzień praktyk", a po zakończeniu „🏁 Praktyki zakończone — minęło N dni".
  Licznik pokazuje się także, gdy wymagane godziny nie są ustawione, a przy braku
  okresu praktyk go nie ma
- **Okres praktyk (od – do)**: uczeń **musi podać, od kiedy do kiedy ma praktyki** —
  obie daty są wymagane. Uczeń ustawia go w **Ustawieniach** (sekcja „📅 Okres praktyk"),
  a administrator może go uzupełnić/poprawić w karcie ucznia w panelu. Dopóki okres nie
  jest podany, na stronie głównej i w ustawieniach widać przypomnienie; gdy jest podany,
  na stronie głównej pokazuje się jako pigułka przy dacie (a w karcie ucznia w statystykach).
  Data „od" nie może być późniejsza niż data „do". Zakres dat wpisów pozostaje **dowolny** —
  aplikacja **nie blokuje** wpisów spoza podanego okresu (okres to informacja, nie ograniczenie),
  ale gdy uczeń **zapisuje wpis w dniu spoza okresu**, przed zapisem pokazuje się pytanie
  „**Nie masz praktyk w ten dzień. Zapisać mimo to?**" — po potwierdzeniu wpis zapisuje się
  normalnie, a anulowanie wstrzymuje zapis (działa w formularzu dnia i w okienku „Dodaj wpis")
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
- **Panel administratora** (`admin.php`, tylko dla roli admin): podzielony na **dwie zakładki** — **„✏️ Edycja kont"** (lista kont) i **„➕ Nowe konto"**, gdzie formularz zakładania konta otwiera się jako **wyskakujące okienko (popup)** — przyciemnione tło na całym ekranie i wyśrodkowana karta; zamykanie przyciskiem **✕**, klawiszem **Esc** albo kliknięciem w tło (tło to `<label>` zakładki, więc zamykanie działa też bez JS). Okienko otwiera zakładka oraz **przycisk „➕ Nowe konto" w pasku akcji w prawym górnym rogu** (kursor wskakuje w pole loginu; po utworzeniu konta okienko samo się zamyka i widać listę). Zakładanie kont (uczeń/administrator — przy koncie ucznia **wymagana klasa**, a samo **pole klasy pojawia się tylko dla roli „Uczeń"** i znika dla „Administrator"; działa to w JS, a serwer i tak wymusza klasę ucznia), lista kont w **dwóch zakładkach** (🎓 Uczniowie / 👑 Administratorzy), a uczniowie dodatkowo **pogrupowani według klasy** — każda klasa jako **rozwijany pasek/akordeon** (`<details>`/`<summary>`, jeden wiersz z nazwą i licznikiem, „Bez klasy" na końcu, sortowanie naturalne) z **przyciskiem „⚙️ Zarządzaj klasą" w nagłówku paska** (działa bez rozwijania paska — otwiera wyskakujące okienko z **masową zmianą klasy całej grupy**: np. 2A → 3A, z potwierdzeniem, puste pole przenosi uczniów do „Bez klasy", zmiana dotyczy tylko uczniów; oraz z **usunięciem całej klasy na raz** — trwale usuwa konta wszystkich uczniów tej klasy wraz z dziennikami i załącznikami, z potwierdzeniem i licznikiem wpisów; grupa „Bez klasy" nie ma opcji usunięcia całej grupy), **dodaniem nowego ucznia od razu do tej klasy** (w tym samym okienku: nazwa konta, imię i nazwisko, hasło i opcjonalny e-mail; nowe konto ucznia od razu dostaje klasę tej grupy — grupa „Bez klasy" nie ma tego formularza), **ustawianiem wymaganych godzin i okresu praktyk (od – do) dla całej klasy na raz** (w tym samym okienku: pole godzin + dwie daty; puste pole zostawia dotychczasową wartość bez zmian, wpisane 0 w godzinach czyści wymaganie, a okres zmienia się tylko wtedy, gdy podano obie daty — komunikat pokazuje, ile kont objęła zmiana), z klasą, liczbą wpisów i godzin, **paskiem postępu praktyk w karcie każdego ucznia** (procent, ile ma godzin i ile jeszcze brakuje; gdy nie ustawiono wymaganych godzin, krótka informacja zamiast paska) oraz **wyszukiwarką uczniów po imieniu i nazwisku** (pole „Szukaj ucznia po imieniu i nazwisku" nad listą — szuka też po loginie i klasie, filtruje listę i liczniki, prefilled po podaniu, przycisk „Wyczyść"; przy szukaniu automatycznie otwiera się zakładka 🎓 Uczniowie) oraz w zakładce 👑 Administratorzy **wyszukiwarką administratorów po imieniu i nazwisku** (osobne pole i osobny parametr `qa`, więc szukanie kont jednego typu nie filtruje drugiego; po wyszukaniu automatycznie otwiera się zakładka 👑 Administratorzy), **czytelną kartą konta** (kolorowy awatar z inicjałami, nazwa z odznaką roli i klasy, login; dla ucznia także licznik wpisów i łączny czas — karta administratora **nie** pokazuje wpisów ani godzin, bo nie ma własnego dziennika; wszystkie operacje są schowane w rozwijanej sekcji **„⚙️ Zarządzaj kontem"** z widocznymi etykietami pól — stan rozwinięcia jest pamiętany w `localStorage`, więc po zapisie sekcja zostaje otwarta), **ustawianiem wymaganych godzin praktyk uczniowi** z panelu (pole + „Zapisz godziny"; puste/0 czyści wymaganie), **okresem praktyk ucznia (od – do)** (dwie daty + „Zapisz okres"; obie wymagane, data „od" nie może być późniejsza niż „do"), zmiana loginu (nazwy konta — unikalna, min. 3 znaki, z komunikatem o zajętej nazwie), hasła oraz nazwy i klasy, usuwanie **kont uczniów** (wraz z ich dziennikami), **otwieranie i edycja dzienniczka ucznia** (`index.php?uczen=ID` / `kalendarz.php?uczen=ID` — wpisy i załączniki zapisują się na koncie ucznia). **Role kont są stałe**: roli nie zmienia się nikomu — ani uczniowi (nie da się go awansować na administratora), ani administratorowi; w karcie nie ma formularza „🎭 Rola konta", a akcja `set_role` zawsze odsyła do `err=rola-stala`. Rolę wybiera się tylko raz, przy zakładaniu konta („➕ Nowe konto"). **Konta administratorów są trwałe**: cudzego konta administratora nie można usunąć (`err=admin-nieusuwalny`); własne konto administrator może usunąć — na swojej karcie ma przycisk „🗑 Usuń moje konto" (po usunięciu sesja się kończy, a ekran logowania pokazuje komunikat), z zastrzeżeniem, że ostatniego administratora usunąć nie można (`err=usun-ostatni`; wtedy przycisku nie ma). Zmiana hasła, imienia i nazwiska oraz loginu **innego administratora** jest zablokowana (te dane ustawia tylko właściciel konta): w jego karcie te formularze są ukryte i zastąpione notką, a akcje `reset_password`, `set_name` i `set_login` odrzucają taki zapis po stronie serwera (`err=admin-chroniony`) — własne konto każdy może edytować. Administrator nie ma własnego kalendarza/dziennika: w panelu i ustawieniach nie ma do niego linków, a `index.php` / `kalendarz.php` bez `?uczen=ID` przekierowują do panelu. W ustawieniach administratora nie ma też sekcji „Wymagane godziny praktyk" ani „Okres praktyk"
- **Ustawienia** (`ustawienia.php`): każdy zalogowany — uczeń i administrator — może tu **zmienić własne imię i nazwisko** (sekcja „Konto", pole **„🪪 Imię i nazwisko"**, maks. 120 znaków; zmiana dotyczy tylko własnego konta, a klasa ucznia zostaje bez zmian; puste pole **czyści** nazwę i w dzienniku pokazuje się wtedy login), obok nazwę konta (login), hasło oraz e-mail do resetu. Uczeń ustawia tu dodatkowo wymagane godziny i okres praktyk, a administrator ma tylko konto i kopię zapasową
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
