# BiblioVicino

Prototipo universitario per condividere e scoprire collezioni private di libri. Consente di gestire una libreria personale, pubblicare copie disponibili al prestito e cercare libri nelle vicinanze, su una lista e una mappa.

## Funzionalità

- Registrazione, login, logout e modifica del nome nel profilo.
- Configurazione e disattivazione della zona di ricerca tramite geolocalizzazione esplicita.
- Catalogo bibliografico locale, ricerca facoltativa su Open Library e inserimento manuale.
- Gestione delle copie personali: note, pubblicazione, ritiro e archiviazione.
- Copertine da Open Library o upload JPEG/PNG/WebP, con generazione di miniature.
- Ricerca testuale e geografica delle copie disponibili.
- Richieste di prestito, accettazione/rifiuto, annullamento, consegna e restituzione.
- Dashboard riservata agli amministratori con conteggi aggregati e grafico settimanale.


## Tecnologie e organizzazione

Backend Symfony 7.4 con PHP 8.4; frontend React, TypeScript e Vite; database PostgreSQL 17 con PostGIS; mappe Leaflet e OpenStreetMap.

Il backend è un monolite modulare con i contesti Identity, Library e Loan. Le regole di dominio sono distinte dai casi d’uso applicativi e dalle implementazioni infrastrutturali. I modelli di lettura compongono i dati necessari a ricerca, dettagli e statistiche.

```text
backend/src/       Dominio, casi d’uso, API e persistenza
backend/migrations/ Schema e dati di riferimento
backend/fixtures/  Dati locali per la dimostrazione
backend/tests/     Test backend fondamentali
frontend/src/      Interfaccia e test frontend fondamentali
docker/            Runtime PHP e inizializzazione PostgreSQL
compose.yaml       Ambiente locale riproducibile
```

## Prerequisiti

- Docker Engine con Docker Compose v2, oppure Docker Desktop avviato.
- Con WSL 2: integrazione Docker abilitata per la distribuzione usata.
- Connessione Internet per costruire le immagini e installare le dipendenze.

PHP, Composer e Node.js sono forniti dai container. Le porte predefinite sono 5173 (interfaccia), 8080 (API) e 5432 (database, esposto solo su localhost). Le porte API e database sono configurabili in `.env`.

## Installazione e avvio

Eseguire dalla cartella principale del progetto:

```bash
cp .env.example .env
docker compose up --build -d
docker compose exec backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec backend php bin/console app:seed-demo
```

Il primo avvio può richiedere alcuni minuti. Controllare lo stato e gli eventuali errori con:

```bash
docker compose ps
docker compose logs backend frontend
```

Aprire <http://localhost:5173>.

Per arrestare i servizi conservando i dati:

```bash
docker compose down
```

## Dati e account dimostrativi

Tutti gli account dimostrativi usano la password **`BiblioVicinoDemo!`**.

| Nome | Email | Ruolo |
|---|---|---|
| Demo BiblioVicino | demo@bibliovicino.test | Utente |
| Anna | anna@bibliovicino.test | Utente |
| Luca | luca@bibliovicino.test | Utente |
| Giulia | giulia@bibliovicino.test | Utente |
| Marco | marco@bibliovicino.test | Utente |
| Elena | elena@bibliovicino.test | Utente |
| Davide | davide@bibliovicino.test | Utente |
| Sara | sara@bibliovicino.test | Utente |
| Paolo | paolo@bibliovicino.test | Utente |
| Amministrazione | admin@bibliovicino.test | Amministratore |

L’utente demo dispone di una zona approssimata e di una libreria. Gli altri utenti di esempio consentono di provare ricerca e prestiti passando da un account all’altro. Paolo non ha una zona configurata, quindi le sue copie non compaiono nella ricerca geografica. L’amministratore può aprire `/admin`. Per provare il consenso alla posizione del browser, registrare un nuovo account.

Il comando `app:seed-demo` è idempotente: una seconda esecuzione conserva il dataset già presente. Le 30 copie dimostrative utilizzano 23 immagini locali: [fonti delle copertine](backend/fixtures/demo/covers/README.md). Seed e visualizzazione di queste copertine non richiedono Open Library.

**Attenzione:** il comando seguente elimina tutti i dati applicativi e ricrea il dataset; usarlo soltanto su un database dimostrativo sacrificabile. È disponibile solo negli ambienti dev e test.

```bash
docker compose exec backend php bin/console app:seed-demo --reset
```

## Test e controlli

Con i servizi avviati, inizializzare anche lo schema del database separato dei test:

```bash
docker compose exec backend php bin/console doctrine:migrations:migrate --env=test --no-interaction
docker compose exec backend php bin/phpunit
docker compose exec frontend npm test
docker compose exec frontend npm run lint
docker compose exec frontend npm run build
```

L’inizializzazione PostgreSQL crea `bibliovicino_test` con PostGIS.

I test coprono i flussi principali: sessioni e CSRF, proprietà delle copie, privacy della posizione, ricerca PostGIS, copertine, prestiti e statistiche riservate.
