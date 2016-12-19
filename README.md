INTRODUCTION
------------

Geofield Map module provides a (Google or leaflet) Map widget for Geofield Module.
Represent the perfect option to input a Location / Geofield value to a content type, throughout an Interactive Map widget.

INSTALLATION AND USE
---------------------

1. Install the module the drupal way [1]

2. In a Content Type including a Geofield Field, go to "Manage form display" and select "Geofield Map" as geofield Widget.

Specifications
---------------------

The Geofield Map Widget provides interactive Map Click and Geo Marker Dragging functionalities to set Geofield Lat/Lon values.
An input search field is embedded in the Widget with Google Api Geocoding functionalities, for detailed Addresses Geocoding.

The Module settings comprehend the following options:

1. Use HTML5 Geolocation to find user location;
2. Choose among different Map Types (Roadmap, Satellite, Hybrid, Terrain);
2. Click to Find marker: Provides a button to recenter the map on the marker location;
3. Click to place marker: Provides a button to place the marker in the center location;
4. Geoaddress Field: Allows to choose a "string" type field (among the ones on the content type) to sync and populate with the Search / Reverse Geocoded Address. Further settings allows to hide and disable the Geoaddress Field in the content edit form;

[1] http://drupal.org/documentation/install/modules-themes/modules-8