# Sbrigo API

API REST Laravel 13 per il frontend Angular Sbrigo. L'autenticazione usa OTP
via email e sessioni cookie Sanctum stateful; i permessi amministrativi usano
`spatie/laravel-permission`.

## Avvio locale

Requisiti: PHP 8.3 o superiore, Composer e SQLite (oppure un database
configurato in `.env`).

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

In locale il frontend gira su `http://localhost:4200` e l'API su
`http://localhost:8000`. `.env.example` configura l'origine locale per CORS e
Sanctum. Il mailer `log` scrive l'OTP nei log ed è adatto solo allo sviluppo:
configura un provider email prima di inviare codici a utenti reali.

Il seeder crea i ruoli `user` e `admin`; all'admin assegna `manage-users` e
`manage-roles` e non crea credenziali predefinite.

## Autenticazione SPA

| Metodo | Endpoint | Accesso e comportamento |
| --- | --- | --- |
| `GET` | `/sanctum/csrf-cookie` | Imposta i cookie di sessione e CSRF |
| `POST` | `/api/register` | Pubblico; crea account non verificato o invia OTP se esiste già |
| `POST` | `/api/otp/send` | Pubblico; invio neutrale di un OTP a un account esistente |
| `POST` | `/api/otp/verify` | Pubblico; verifica il codice, verifica l'email e apre la sessione |
| `GET` | `/api/me` | Sessione autenticata; restituisce l'utente corrente |
| `GET` | `/api/user` | Alias compatibile di `/api/me` |
| `POST` | `/api/logout` | Invalida la sessione corrente |
| `GET` | `/api/admin/users` | Richiede sessione e permesso `manage-users` |

Registrazione:

```json
{
  "name": "Mario Rossi",
  "email": "mario@example.com"
}
```

L'API non rilascia token Bearer e non accetta tale modalità sulle route
protette. Il frontend deve inviare le richieste con credenziali, ottenere prima
`/sanctum/csrf-cookie` e inoltrare il valore `XSRF-TOKEN` come
`X-XSRF-TOKEN`. Laravel valida il CSRF per le richieste SPA stateful; un
middleware rifiuta inoltre le richieste mutative con un header `Origin` non
consentito.

I codici OTP hanno 6 cifre, scadono dopo 5 minuti e sono conservati in cache
come HMAC, non in chiaro. L'invio è limitato a 3 richieste ogni 15 minuti per
email; la verifica a 5 tentativi ogni 15 minuti per email. Sono inoltre
applicati limiti per IP. La risposta all'invio non rivela se l'email è
registrata.

Per il deploy configura `FRONTEND_URL` con le sole origini HTTPS consentite
(separate da virgole) e
`SANCTUM_STATEFUL_DOMAINS` con i domini SPA effettivi (inclusa la porta, se
necessaria). Se frontend e API sono su sottodomini distinti, configura
`SESSION_DOMAIN` sul dominio condiviso e verifica i cookie dal browser. Non
usare origini wildcard con credenziali. Usa HTTPS e lascia attive le
impostazioni cookie sicure in produzione. Non distribuire
`.env.example` come configurazione di produzione: imposta almeno
`APP_ENV=production`, `APP_DEBUG=false`, un `APP_KEY` segreto, database e cache
protetti, mailer di produzione, storage adatto, `SESSION_ENCRYPT=true` e
`SESSION_SECURE_COOKIE=true`. Il codice forza comunque cifratura e cookie
`Secure` con `APP_ENV=production`; verifica la configurazione effettiva di
deploy e gli header restituiti dal browser.

## Ruoli e permessi

Il middleware `role`, `permission` e `role_or_permission` è registrato in
`bootstrap/app.php`. Per proteggere una route:

```php
Route::get('/reports', ReportController::class)
    ->middleware(['auth:web', 'verified', 'permission:view-reports']);
```

I ruoli e i permessi possono essere creati e assegnati con i modelli Spatie
`Role` e `Permission`, oppure con `assignRole()` e `givePermissionTo()`. Per
assegnare il ruolo amministratore a un utente esistente, usare Tinker:

```bash
php artisan tinker
```

```php
App\Models\User::where('email', 'mario@example.com')->firstOrFail()->assignRole('admin');
```

## Limiti attuali

L'API non implementa ancora aziende/tenant, clienti, fatture, incassi,
caricamento documenti o importazione AI; non trattare dati operativi reali
finché questi trattamenti, l'isolamento tra aziende, i fornitori e le misure di
produzione non sono stati progettati e verificati. I documenti legali e le
procedure privacy devono essere predisposti separatamente con un professionista.

## Test

```bash
php artisan test
```
