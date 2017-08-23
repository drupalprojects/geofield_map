###General Information

Geofield Map module represents an advanced, complete and easy-to-use Geo Mapping solution for Drupal 8,
based (on) and fully compatible with the [Geofield](https://www.drupal.org/project/geofield) Module.

It represents the perfect solution to:
- geolocate (with one or more Locations / Geofields) any fieldable Drupal entity throughout an Interactive Geofield Map widget;
- render each Content's Locations throughout a fully customizable Interactive Geofield Map Formatter;
- expose and query Contents throughout fully customizable Map Views Integration;
- implement front-end Google Maps with Marker Icon & Infowindow advanced customizations, and Marker Clustering capabilities;

####Technical Functionalities and specifications

The actual module release implements the following components:

####Geofield Map widget
An higly customizable Map widget, 
providing an interactive and very intuitive map on which to perform localization and input of geographic coordinates throughout:
- Google places autocomplete and geocoding APIs;
- Google Map or Leaflet Map JS libraries and interfaces;
- Map click and marker dragging, with instant reverse geocoding;
- HTML5 Geolocation of the user position;
- the possibility to permanently store the Geocoded address into the Entity Title
or in a "string type" field (among the content's ones).
- etc.

####Geofield Map Formatter
An highly customizable Google Map formatter, by which render and expose the contents Geofields / Geolocations, throughout:
- a wide set of Map options fully compliant with [Google Maps Js v3 APIs](https://developers.google.com/maps/documentation/javascript/);
- the possibility to fully personalize the Map Marker Icon and its Infowindow content;
- the integration of [Markecluster Google Maps Library](https://github.com/googlemaps/js-marker-clusterer) functionalities and its personalizations

####Views Integration
A dedicated Geofield Map View style plugin able to render a Views result on a higly customizable Google Map, 
with Marker and Infowindow specifications and Markers Clustering capabilities.

###Installation and Use

- Install the module the 
[drupal way](http://drupal.org/documentation/install/modules-themes/modules-8)
- Geofield Widget: In a Content Type including a Geofield Field, go to "Manage form display" 
and select "Geofield Map" as Geofield Widget. Specify the Widget further settings for both Google or Leaflet Map types;
- Geofield Google Map Formatter: In a Content Type including a Geofield Field, go to "Manage display" and select "Geofield Google Map" as Geofield field Formatter.  Specify the Formatter further settings for specific personalization;
- Geofield Map Views: In a View Display select the Geofield Google Map Format, and be sure to add a Geofield type field in the fields list. Specify the View Format settings for specific personalization;


####Notes & Warnings: 
- The Geofield Map module depends on the [Geofield](https://www.drupal.org/project/geofield) module;
- A valid <u>Gmap Api Key</u> is needed** for Google Maps rendering, and for any Geocoding and Reverse Geocoding functionalities, as actually based on the Google Geocoder;
- Although in mind, there is no <u>Leaflet Map library</u> support at the moment for the Geofield Map Formatter and the  Map Views Plugin. Please refer to the [Leaflet](https://www.drupal.org/project/leaflet) and the [Leaflet Markercluster](https://www.drupal.org/project/leaflet_markercluster) modules for Leaflet front-end mapping of Drupal 8 Geofields;
