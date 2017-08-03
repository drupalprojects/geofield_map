(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.geofieldGoogleMap = {
    attach: function(context, settings) {
      Drupal.geoField = Drupal.geoField || {};
      Drupal.geoField.maps = Drupal.geoField.maps || {};


      if (drupalSettings['geofield_google_map']) {
        $(context).find('.geofield-google-map').once('geofield-processed').each(function (index, element) {
          var mapid = drupalSettings['geofield_google_map']['mapid'];
          var map_settings = drupalSettings['geofield_google_map']['map_settings'];
          var data = drupalSettings['geofield_google_map']['data'];

          // Set the map_data[mapid] settings.
          Drupal.geoFieldMap.map_data[mapid] = map_settings;

          // Check if the element id matches the mapid.
          if ($(element).attr('id') === mapid) {
            // Load before the Gmap Library, if needed.
            Drupal.geoFieldMap.loadGoogle(mapid, map_settings.gmap_api_key, function () {
              Drupal.geoFieldMap.map_initialize(mapid, map_settings, data);
            });
          }
        });
      }
    }
  };

  Drupal.geoFieldMap = {

    map_data: {},
    markers: [],

    // Google Maps are loaded lazily. In some situations load_google() is called twice, which results in
    // "You have included the Google Maps API multiple times on this page. This may cause unexpected errors." errors.
    // This flag will prevent repeat $.getScript() calls.
    maps_api_loading: false,

    /**
     * Provides the callback that is called when maps loads.
     */
    googleCallback: function () {
      var self = this;
      // Wait until the window load event to try to use the maps library.
      $(document).ready(function (e) {
        _.invoke(self.googleCallbacks, 'callback');
        self.googleCallbacks = [];
      });
    },

    /**
     * Adds a callback that will be called once the maps library is loaded.
     *
     * @param callback - The callback
     */
    addCallback: function (callback) {
      var self = this;
      // Ensure callbacks array;
      self.googleCallbacks = self.googleCallbacks || [];
      self.googleCallbacks.push({callback: callback});
    },

    // Lead Google Maps library.
    loadGoogle: function (mapid, gmap_api_key, callback) {
      var self = this;

      // Add the callback.
      self.addCallback(callback);

      // Check for google maps.
      if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
        if (self.maps_api_loading === true) {
          return;
        }

        self.maps_api_loading = true;
        // Google maps isn't loaded so lazy load google maps.

        // Default script path.
        var scriptPath = '//maps.googleapis.com/maps/api/js?v=3.exp&sensor=false';

        // If a Google API key is set, use it.
        if (typeof gmap_api_key !== 'undefined' && gmap_api_key !== null) {
          scriptPath += '&key=' + gmap_api_key;
        }

        $.getScript(scriptPath)
          .done(function () {
            self.maps_api_loading = false;
            self.googleCallback();
          });

      }
      else {
        // Google maps loaded. Run callback.
        self.googleCallback();
      }
    },

    place_feature: function(feature, icon_image, map, range) {
      var self = this;
      var properties = feature.get('geojsonProperties');

      if (feature.setTitle && properties && properties.title) {
        feature.setTitle(properties.title);
      }

      // Set the personalized Icon Image, if set.
      if (feature.setIcon && icon_image && icon_image.length > 0) {
        $.ajax({
          url: icon_image,
          type:'HEAD',
          error: function()
          {
            console.log('Geofield Gmap: The Icon Image doesn\'t exist at the set path');
          },
          success: function()
          {
            feature.setIcon(icon_image);
          }
        });

      }
      feature.setMap(map);
      self.markers.push(feature);

      if (feature.getPosition) {
        range.extend(feature.getPosition());
      } else {
        var path = feature.getPath();
        path.forEach(function(element) {
          range.extend(element);
        });
      }

      if (properties && properties.description) {
        var bounds = feature.get('bounds');
        google.maps.event.addListener(feature, 'click', function() {
          map.infowindow.setContent("<div style='max-width:200px; text-align: center;'>" + properties.description + "</div>");
          map.infowindow.setOptions({pixelOffset: new google.maps.Size(0,-30)});
          map.infowindow.setPosition(bounds.getCenter());
          map.infowindow.open(map);
        });
      }
    },

    // Init Geofield Google Map and its functions.
    map_initialize: function (mapid, map_settings, data) {
      var self = this;
      $.noConflict();

      var resetZoom = true;

      // Checking to see if google variable exists. We need this b/c views breaks this sometimes. Probably
      // an AJAX/external javascript bug in core or something.
      if (typeof google !== 'undefined' && typeof google.maps.ZoomControlStyle !== 'undefined' && data !== undefined) {

        // Parse the Geojson data into Google Maps Locations.
        var features = GeoJSON(data);

        var mapOptions = {
          center: map_settings.map_center ? new google.maps.LatLng(map_settings.map_center.lat, map_settings.map_center.lon) : new google.maps.LatLng(42, 12.5),
          zoom: map_settings.map_zoom_and_pan.zoom ? parseInt(map_settings.map_zoom_and_pan.zoom) : 8,
          minZoom: map_settings.map_zoom_and_pan.min_zoom ? parseInt(map_settings.map_zoom_and_pan.min_zoom) : 1,
          maxZoom: map_settings.map_zoom_and_pan.max_zoom ? parseInt(map_settings.map_zoom_and_pan.max_zoom) : 20,
          scrollwheel: !!map_settings.map_zoom_and_pan.scrollwheel,
          draggable: !!map_settings.map_zoom_and_pan.draggable,
          disableDefaultUI: !!map_settings.map_controls.disable_default_ui,
          zoomControl: !!map_settings.map_controls.zoom_control,
          mapTypeId: map_settings.map_controls.map_type_id ? map_settings.map_controls.map_type_id : 'roadmap',
          mapTypeControl: !!map_settings.map_controls.map_type_control,
          mapTypeControlOptions: {
            mapTypeIds: map_settings.map_controls.map_type_control_options_type_ids ? map_settings.map_controls.map_type_control_options_type_ids : ['roadmap', 'satellite', 'hybrid'],
            position: google.maps.ControlPosition.TOP_LEFT,
          },
          scaleControl: !!map_settings.map_controls.scale_control,
          streetViewControl: !!map_settings.map_controls.street_view_control,
          fullscreenControl: !!map_settings.map_controls.fullscreen_control,
        };

        var additionalOptions = map_settings.map_additional_options.length > 0 ? JSON.parse(map_settings.map_additional_options) : {};
        // Transforms additionalOptions "true", "false" values into true & false.
        for (var prop in additionalOptions) {
          if (additionalOptions.hasOwnProperty(prop)) {
            if (additionalOptions[prop] === 'true') {
              additionalOptions[prop] = true;
            }
            if (additionalOptions[prop] === 'false') {
              additionalOptions[prop] = false;
            }
          }
        }

        // Merge mapOptions with additionalOptions.
        Object.assign(mapOptions, additionalOptions);

        // Define the Geofield Google Map.
        var map = new google.maps.Map(document.getElementById(mapid), mapOptions);

        // Define a map self property, so other code can interact with it.
        self.map_data[mapid].map = map;
        self.map_data[mapid].map_center = new google.maps.LatLng(map_settings.map_center.lat, map_settings.map_center.lon);

        var range = new google.maps.LatLngBounds();

        map.infowindow = new google.maps.InfoWindow({
          content: ''
        });

        // Define the icon_image, if set.
        var icon_image = map_settings.map_marker_and_infowindow.icon_image_path.length > 0 ? map_settings.map_marker_and_infowindow.icon_image_path : null;

        if (features.setMap) {
          self.place_feature(features, icon_image, map, range);
          // Don't move the default zoom if we're only displaying one point.
          if (features.getPosition) {
            resetZoom = false;
          }
        } else {
          for (var i in features) {
            if (features[i].setMap) {
              self.place_feature(features[i], icon_image, map, range);
            } else {
              for (var j in features[i]) {
                if (features[i][j].setMap) {
                  self.place_feature(features[i][j], icon_image, map, range);
                }
              }
            }
          }
        }

        // Implement Markeclustering.
        var markeclusterOption = {
          imagePath: 'https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/m'
        };

        var markeclusterAdditionalOptions = map_settings.map_markercluster.markercluster_additional_options.length > 0 ? JSON.parse(map_settings.map_markercluster.markercluster_additional_options) : {};
        // Merge markeclusterOption with markeclusterAdditionalOptions.
        Object.assign(markeclusterOption, markeclusterAdditionalOptions);

        if (typeof MarkerClusterer !== 'undefined' && map_settings.map_markercluster.markercluster_control) {
          var markerCluster = new MarkerClusterer(map, self.markers, markeclusterOption);
        }

        for (first in features) break;
        if (first !== 'type') {
          if (resetZoom) {
            map.fitBounds(range);
          } else {
            map.setCenter(range.getCenter());
          }
        } else {
          map.setCenter(self.map_data[mapid].map_center);
        }
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
