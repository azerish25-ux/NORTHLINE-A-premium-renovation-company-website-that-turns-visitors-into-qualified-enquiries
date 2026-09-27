# NORTHLINE — run the real WordPress implementation locally

This is a local development installation, not a production hosting configuration. The website and test inbox bind to your own computer's loopback interface. Use fictional details and non-sensitive reference images.

## Start

Install and start Docker with Docker Compose support. Clone this repository, then run from its root:

```sh
cd tools/local-wordpress
docker compose up -d
docker compose logs -f bootstrap
```

The bootstrap service waits for WordPress, creates a local installation when one does not already exist, activates the NORTHLINE theme and plugin, and installs the missing editable content. Stop following the log with Ctrl+C after the completion message; this does not stop the containers.

Open the website at `http://localhost:8080`. The real authenticated WordPress dashboard is at `http://localhost:8080/wp-admin`. The test email inbox is at `http://localhost:8025`.

For this loopback-only development installation, the default staff username is `studio` and the default password is `Northline-Local-Only-ChangeMe`. These are public development credentials, not secrets. Before creating a new installation, set `NORTHLINE_ADMIN_PASSWORD` to override the initial password. Changing that environment variable after installation does not change an existing WordPress user's password. Do not expose this stack or its default credentials to the internet.

## Demonstrate enquiry → email → consultation → staff

Complete the project planner using fictional details, an approximate affected area and a non-sensitive JPEG, PNG or WebP reference. Submit the brief. Its persisted confirmation is independent of email processing.

To process the due email outbox immediately rather than waiting for the scheduled job, run from `tools/local-wordpress`:

```sh
docker compose --profile tools run --rm cli northline outbox
```

Open the Mailpit inbox to inspect the follow-up email and its private brief/booking link. The local SMTP adapter captures messages here; they are not delivered externally. Reserve a consultation from the saved brief. Sign in to WordPress, open **NORTHLINE**, select the enquiry and review its project fields, private references, booking and email status. Save a staff note.

Open **Project case studies** to edit a case narrative in the native block editor. Open **Appearance → Editor** to edit the theme's header, footer, templates and styles. Existing edited case-study and page content is preserved when the content installer is run again.

## Demonstrate email failure without losing an enquiry

Stop the local email catcher:

```sh
docker compose stop mailpit
```

Submit a new fictional brief, then process the outbox with the command above. The enquiry must remain available in the NORTHLINE dashboard even when the email transport fails. Restart the catcher:

```sh
docker compose start mailpit
```

Use **Retry failed messages** in the enquiry's WordPress dashboard. Inspect the captured message in Mailpit. Transport acceptance is not the same as delivery to an external inbox.

## Stop and preserve data

```sh
docker compose down
```

The named database and WordPress volumes are retained. Restarting the stack reuses them. Do not add `--volumes` or `-v` unless you deliberately intend to erase the local site's stored data.

## Production boundary

The Docker recipe is supplied for development and portfolio demonstrations; it has not been represented as a tested production deployment. Real operation requires HTTPS hosting, controlled credentials, backups, a verified business identity and privacy contact, a configured external email provider, reliable scheduled-job execution and staff management of consultation availability. The supplied budget figures remain fictional illustrative allowances until reviewed and replaced for the actual business.
