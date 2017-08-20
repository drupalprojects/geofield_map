(function ($, Drupal, drupalSettings) {

  Drupal.behaviors.geofieldGoogleMap = {
    attach: function (context, settings) {
      Drupal.geoField = Drupal.geoField || {};
      Drupal.geoField.maps = Drupal.geoField.maps || {};


      if (drupalSettings['geofield_google_map']) {
        $(context).find('.geofield-google-map').once('geofield-processed').each(function (index, element) {
          var mapid = $(element).attr('id');

          // Check if the Map container really exists and hasn't been yet initialized.
          if (drupalSettings['geofield_google_map'][mapid] && !Drupal.geoFieldMap.map_data[mapid]) {

            var map_settings = drupalSettings['geofield_google_map'][mapid]['map_settings'];
            var data = drupalSettings['geofield_google_map'][mapid]['data'];

            // Set the map_data[mapid] settings.
            Drupal.geoFieldMap.map_data[mapid] = map_settings;

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

    place_feature: function(feature, icon_image, mapid) {
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

      var map = self.map_data[mapid].map;
      feature.setMap(map);
      self.map_data[mapid].markers.push(feature);

      if (feature.getPosition) {
        self.map_data[mapid].map_bounds.extend(feature.getPosition());
      } else {
        var path = feature.getPath();
        path.forEach(function(element) {
          self.map_data[mapid].map_bounds.extend(element);
        });
      }

      if (properties && properties.description) {
        var bounds = feature.get('bounds');
        google.maps.event.addListener(feature, 'click', function() {
          map.infowindow.setContent(properties.description);
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

      var zoomForce = !!map_settings.map_zoom_and_pan.zoom.force;
      var centerForce = !!map_settings.map_center.center_force;

      // Checking to see if google variable exists. We need this b/c views breaks this sometimes. Probably
      // an AJAX/external javascript bug in core or something.
      if (typeof google !== 'undefined' && typeof google.maps.ZoomControlStyle !== 'undefined' && data !== undefined) {

        var mapOptions = {
          center: map_settings.map_center ? new google.maps.LatLng(map_settings.map_center.lat, map_settings.map_center.lon) : new google.maps.LatLng(42, 12.5),
          zoom: map_settings.map_zoom_and_pan.zoom.initial ? parseInt(map_settings.map_zoom_and_pan.zoom.initial) : 8,
          minZoom: map_settings.map_zoom_and_pan.zoom.min ? parseInt(map_settings.map_zoom_and_pan.zoom.min) : 1,
          maxZoom: map_settings.map_zoom_and_pan.zoom.max ? parseInt(map_settings.map_zoom_and_pan.zoom.max) : 20,
          scrollwheel: !!map_settings.map_zoom_and_pan.scrollwheel,
          draggable: !!map_settings.map_zoom_and_pan.draggable,
          mapTypeId: map_settings.map_controls.map_type_id ? map_settings.map_controls.map_type_id : 'roadmap',
        };

        if(!!map_settings.map_controls.disable_default_ui) {
          mapOptions.disableDefaultUI = map_settings.map_controls.disable_default_ui;
        } else {
          mapOptions.zoomControl = !!map_settings.map_controls.zoom_control;
          mapOptions.mapTypeControl = !!map_settings.map_controls.map_type_control;
          mapOptions.mapTypeControlOptions = {
            mapTypeIds: map_settings.map_controls.map_type_control_options_type_ids ? map_settings.map_controls.map_type_control_options_type_ids : ['roadmap', 'satellite', 'hybrid'],
            position: google.maps.ControlPosition.TOP_LEFT,
          };
          mapOptions.scaleControl = !!map_settings.map_controls.scale_control;
          mapOptions.streetViewControl = !!map_settings.map_controls.street_view_control;
          mapOptions.fullscreenControl = !!map_settings.map_controls.fullscreen_control;
        }

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

        // Define a mapid self property, so other code can interact with it.
        self.map_data[mapid].map = map;
        self.map_data[mapid].map_center = new google.maps.LatLng(map_settings.map_center.lat, map_settings.map_center.lon);
        self.map_data[mapid].markers = [];

        // Define the MapBounds property.
        self.map_data[mapid].map_bounds = new google.maps.LatLngBounds();

        // Fix map issue in field_groups / details & vertical tabs
        google.maps.event.addListenerOnce(map, "idle", function () {

          // Show all map tiles when a map is shown in a vertical tab.
          $('#' + mapid).closest('div.vertical-tabs').find('.vertical-tabs__menu-item a').click(function () {
            self.map_refresh(mapid);
          });

          // Show all map tiles when a map is shown in a collapsible detail/ single tab.
          $('#' + mapid).closest('.field-group-details, .field-group-tab').find('summary').click(function () {
              self.map_refresh(mapid);
            }
          );
        });

        // Parse the Geojson data into Google Maps Locations.
        var features = data.features && data.features.length > 0 ? GeoJSON(data) : null;

        if (features && (!features.type || features.type !== 'Error')) {

          map.infowindow = new google.maps.InfoWindow({
            content: ''
          });

          // Define the icon_image, if set.
          var icon_image = map_settings.map_marker_and_infowindow.icon_image_path.length > 0 ? map_settings.map_marker_and_infowindow.icon_image_path : null;

          if (features.setMap) {
            self.place_feature(features, icon_image, mapid);
          } else {
            for (var i in features) {
              if (features[i].setMap) {
                self.place_feature(features[i], icon_image, mapid);
              } else {
                for (var j in features[i]) {
                  if (features[i][j].setMap) {
                    self.place_feature(features[i][j], icon_image, mapid);
                  }
                }
              }
            }
          }

          // Implement Markeclustering, if more than 1 marker on the map,
          // and the markercluster option is set to true.
          if(self.map_data[mapid].markers.length > 1 && typeof MarkerClusterer !== 'undefined' && map_settings.map_markercluster.markercluster_control) {

            var markeclusterOption = {
              imagePath: 'https://developers.google.com/maps/documentation/javascript/examples/markerclusterer/m'
            };

            var markeclusterAdditionalOptions = map_settings.map_markercluster.markercluster_additional_options.length > 0 ? JSON.parse(map_settings.map_markercluster.markercluster_additional_options) : {};
            // Merge markeclusterOption with markeclusterAdditionalOptions.
            Object.assign(markeclusterOption, markeclusterAdditionalOptions);

            var markerCluster = new MarkerClusterer(map, self.map_data[mapid].markers, markeclusterOption);
          }
        }

        if(!self.map_data[mapid].map_bounds.isEmpty() && self.map_data[mapid].markers.length > 1) {
          map.fitBounds(self.map_data[mapid].map_bounds);
          google.maps.event.addListenerOnce(map, 'idle', function() {
            // Just once the fitBounds completes we can check to override it
            // https://stackoverflow.com/questions/10835496/is-there-a-callback-after-map-fitbounds
            if (centerForce) {
              map.setCenter(mapOptions.center);
            }
            if (zoomForce) {
              map.setZoom(mapOptions.zoom);
            }
          });
        }
        else if (self.map_data[mapid].markers.length === 1 && !centerForce) {
          map.setCenter(self.map_data[mapid].markers[0].getPosition());
          map.setZoom(mapOptions.zoom);
        }
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
