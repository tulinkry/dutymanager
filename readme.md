DutyManager Web Page
=============

Running with Docker (recommended - no local PHP/Composer needed)
----------------------------------------------------------------

```
cd /var/www/
git clone https://github.com/tulinkry/dutymanager
cd dutymanager
docker compose up --build
```

The build runs the unit test suite as part of the image build (see the `test` stage
in `Dockerfile`) - if a test fails, the build fails. Once it's up, the app is at
`http://localhost:8080`.

Before logging in, set up Google OAuth credentials - see "Google OAuth setup" below -
and put them in a `.env` file next to `docker-compose.yml`:

```
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8080/check
```

`log/` and `temp/` are bind-mounted into the container so Tracy error logs and the
compiled DI container cache persist across restarts.


Google OAuth setup
-------------------

The app reads a calendar via the Google Calendar API on the user's behalf. To wire
this up for your own Google account/project:

1. **Create (or reuse) a project** in the [Google Cloud Console](https://console.cloud.google.com/).
2. **Enable the Google Calendar API** for that project (APIs & Services -> Enable APIs and Services -> search "Google Calendar API" -> Enable).
3. **Configure the OAuth consent screen** (APIs & Services -> OAuth consent screen). Add the scope
   `https://www.googleapis.com/auth/calendar` (the app only needs this one scope; it's a "sensitive"
   scope, so Google will keep your app in "Testing" mode until/unless you submit it for verification -
   that's fine for personal/small-scale use, you just have to add your own Google account as a
   "test user" on that screen).
4. **Create an OAuth 2.0 Client ID** (APIs & Services -> Credentials -> Create Credentials -> OAuth
   client ID -> Application type: **Web application**). Add an **Authorized redirect URI** matching
   exactly where you're running the app plus `/check`, e.g. `http://localhost:8080/check` for the
   default docker-compose setup, or `https://your-domain/check` in production.
5. Copy the generated **Client ID** and **Client Secret** into the environment variables
   `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` (via `.env` for docker-compose, see above), and set
   `GOOGLE_REDIRECT_URI` to the exact same redirect URI you registered in step 4.
6. Restart the container (`docker compose up -d --build`) and open `/sign-in`. The generated Google
   auth link should include `access_type=offline&prompt=consent` and the
   `https://www.googleapis.com/auth/calendar` scope - that combination is what makes Google actually
   hand back a refresh token, which the app stores in your session and uses to keep you logged in past
   the ~1 hour access-token expiry without you having to log in again every hour.
7. Log in once with the Google account you added as a test user in step 3, and grant calendar access.
   You should land back on the calendar list.

If you ever need to rotate the client secret (e.g. because an old one leaked): create a new secret
for the same OAuth client in the Cloud Console credentials page, update the environment variable, and
restart the container - nothing else needs to change.


Installing (without Docker)
----------------------------

Clone repository to web server's document path and run composer update
```
cd /var/www/
git clone https://github.com/tulinkry/dutymanager
cd dutymanager
composer update

# log and temp must be writable by the web server
sudo chown -R www-data:www-data {log,temp}
```

Set the same `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` environment
variables for your web server process (or add them to `app/config/config.local.neon` as
static parameters - that file is gitignored).
