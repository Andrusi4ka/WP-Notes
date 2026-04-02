# WP Notes

WP Notes er en WordPress admin-plugin for å opprette interne notater direkte i WordPress-administrasjonen.

Den er laget for team som trenger kontekstbaserte notater på adminsider, redaksjonelle påminnelser, prosessinstruksjoner eller tekniske kommentarer som forblir i admin-grensesnittet.

## Skjermbilde-plassholdere

Sett inn skjermbilder i seksjonene under ved behov.

### Info-side

![screen](./assets/img/screens/screen-5.png)

### Visning av notat i admin
![screen](./assets/img/screens/screen-3.png)
![screen](./assets/img/screens/screen-4.png)

### Redigeringsmodal

![screen](./assets/img/screens/screen-1.png)

### Alle notater-side

![screen](./assets/img/screens/screen-6.png)

## Hovedfunksjoner

- Opprette et notat for gjeldende adminside.
- Opprette et globalt notat for hele nettstedet.
- Vise notater direkte i WordPress admin.
- Administrere notater fra en egen adminside.
- Redigere notater i en modal eller på en egen side.
- Støtte for rik tekst, lister, kodeblokker, lenker og bilder.
- Laste opp bilder direkte i notater.
- Kontrollere redigeringstilgang per notat.
- Bruke WordPress-språk for plugin-UI.

## Hvordan pluginen fungerer

WP Notes legger til en admin-meny og en oppføring i adminbaren.

Fra adminbaren kan en autorisert bruker opprette:

- et sidespesifikt notat
- et globalt notat

Når et notat finnes, vises det øverst på siden som et sammenleggbart kort.

Brukere med tilgang kan:

- åpne notatet
- redigere notatet
- slette notatet

Det finnes også en «All Notes»-side hvor alle notater kan administreres.

## Datalagring

Pluginen lagrer data på to steder:

1. WordPress-databasen
2. Pluginens lagringsmappe for bilder

### Databasetabell

Pluginen oppretter en egen tabell:

- `{$wpdb->prefix}wp_notes`

Tabellen inneholder:

- notattype
- unik nøkkel
- screen ID
- side-URL
- sidetittel
- notatinnhold
- forfatter-ID
- redigeringsmodus
- opprettet tidspunkt
- oppdatert tidspunkt

## Hvor data lagres

### Notatinnhold

Lagres som sanitisert HTML i databasen.

### Opplastede bilder

Lagres i:

- `storage/uploads/`

Eksempel:

- `wp-content/plugins/WP-Notes/storage/uploads/filename.png`

## Tillatelser

- `edit_pages` – tilgang
- `manage_options` – full tilgang

## Grensesnitt

Pluginen inkluderer:

- adminbar snarveier
- «All Notes»-side
- «Info»-side
- modal redigering

## Editor

Støtter:

- overskrifter
- fet/kursiv
- lister
- kode
- lenker
- bilder

## Biblioteker

- Quill
- highlight.js

## Sikkerhet

Innhold blir sanitisert før lagring.

## Språk

- Engelsk
- Norsk

## Aktivering

- oppretter tabell
- oppretter mapper

## Avinstallering

Fjerner:

- alle notater
- alle bilder

## Struktur

- `wp-notes.php`
- `includes/`
- `assets/`
- `storage/uploads/`

## Tekniske notater

- fungerer kun i admin
- bilder lagres i plugin

