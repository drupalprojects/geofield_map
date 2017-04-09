###General Information

Geofield Map module provides a (Google or leaflet) Map widget for
[Geofield Module](https://www.drupal.org/project/geofield).
Represent the perfect option to input a Location / Geofield value to a 
content type, throughout an Interactive Map widget.
This module extends, the Drupal 8 way, the same functionalities of the
 [Geofield Gmap](https://www.drupal.org/project/geofield_gmap) module,
adding the option to manage a [Leaflet Map](http://leafletjs.com/), 
besides the only [Google Map](https://developers.google.com/maps/web/) type one.

Moreover it adds:
- the ability to search an address throughout a Geocoder Field, with 
Autocompletion based on the Google Places API,
- a Reverse Geocoding on the same field based on the map click or marker 
dragging, etc.,
- the possibility to permanently store the Geocoded address into the Entity Title
or in a "string" type field (among the content type ones).

###Installation and Use

1. Install the module the 
[drupal way](http://drupal.org/documentation/install/modules-themes/modules-8)
2. In a Content Type including a Geofield Field, go to "Manage form display" 
and select "Geofield Map" as geofield Widget.
3. Specify the Widget further settings for both Google or Leaflet Map types;

###Specifications

The Geofield Map Widget provides interactive Map Click and Geo Marker Dragging 
functionalities to set Geofield Lat/Lon values.
An input search field is embedded in the Widget with Google Api Geocoding 
functionalities, for detailed Addresses Geocoding.

The Module settings comprehend the following options:

1. Use HTML5 Geolocation to find user location;
2. Choose among different Map Types between Google Maps 
(Roadmap, Satellite, Hybrid, Terrain) and Leaflet ones;
3. Set specific Zoom levels for the Map (start, min, max);
4. Click to Find marker: Provides a button to recenter the map 
on the marker location;
5. Click to place marker: Provides a button to place the marker 
in the center location;
6. Geoaddress Field: If a not null Google Maps API Key is set, 
allows to choose the Entity Title, or a "string" type field 
(among the content type ones), to sync and populate 
with the Search / Reverse Geocoded Address. Further settings allows to hide 
and disable this "Geo address" Field in the content edit form;

Note: Valid Gmap Api Key is needed for the Geocoding and Reverse Geocoding functionalities, also with Leaflet Map rendering, 
as they are based on Google Places library and geocoder.
It is also needed to enable the Geoaddressed Field options, to allow the Searched / Reverse Geocoded Address functionalities.