=== De Zakkenspecialist - Vacatures (Dimensio API) ===
Contributors: dezakkenspecialist
Requires PHP: 7.4
Requires at least: 5.8
Tested up to: 6.6
Stable tag: 1.0.0
License: GPLv2 or later

Toont de vacatures van De Zakkenspecialist via de shortcode [dimensio_vacatures],
opgehaald uit de centrale vacature-API op dimensio.nl.

== Beschrijving ==

Deze plugin registreert de shortcode `[dimensio_vacatures]`. Deze haalt vacatures
op bij de "Dimensio Vacatures API" (de plugin die op dimensio.nl draait),
gefilterd op entiteit "dzs" (De Zakkenspecialist), en toont ze in:

* Filtertabs per vakgebied (alleen zichtbaar als er meerdere vakgebieden zijn).
* Een grid van vacature-kaarten met afbeelding, "Vakgebied • Plaatsnaam • Uren
  per week" en de functietitel.

De data wordt 15 minuten gecached (transient) zodat de pagina niet bij elk
bezoek een live call naar dimensio.nl hoeft te doen.

De vormgeving gebruikt de bestaande groene huisstijl van De Zakkenspecialist
(#00a651 / hover #007e3d), met dezelfde opzet (filtertabs + kaarten-grid) als
de vacature-pagina op dimensio.nl.

== Installatie ==

1. Upload deze plugin-map naar `wp-content/plugins/` (of via Plugins > Nieuwe
   plugin > Plugin uploaden).
2. Activeer de plugin.
3. Open de pagina /vacatures/ in Elementor, sleep een "Shortcode"-widget op de
   gewenste plek en vul in: `[dimensio_vacatures]`
4. Klaar.

== Configuratie ==

Bovenin `zakkenspecialist-vacatures.php` staan drie constantes:

* `DIMENSIO_VACATURES_API_URL`   Staat nu op de testomgeving
  (`https://test.dimensio.sixpaths.nl/wp-json/dimensio/v1/vacatures`).
  Verander dit naar de productie-URL zodra de "Dimensio Vacatures API"
  plugin op www.dimensio.nl live staat, bijv.
  `https://www.dimensio.nl/wp-json/dimensio/v1/vacatures`.
* `DIMENSIO_VACATURES_ENTITEIT`  Welke entiteit getoond wordt. Staat op `dzs`.
* `DIMENSIO_VACATURES_CACHE_TTL` Cache-tijd in seconden (standaard 15 minuten).

Je kunt deze ook overschrijven vanuit wp-config.php met bijvoorbeeld:
`define( 'DIMENSIO_VACATURES_API_URL', 'https://www.dimensio.nl/wp-json/dimensio/v1/vacatures' );`
zodat je bij een volgende plugin-update de instelling niet kwijtraakt.

== Changelog ==

= 1.0.0 =
* Eerste versie: shortcode met filtertabs, kaarten-grid, transient-caching en
  DZS-groene vormgeving.
