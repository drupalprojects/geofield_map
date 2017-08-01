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

          // Check if the element id matches the mapid.
          if ($(element).attr('id') === mapid) {
            // Load before the Gmap Library, if needed.
            Drupal.geofieldGoogleMap.loadGoogle(mapid, map_settings.gmap_api_key, function () {
              Drupal.geofieldGoogleMap.map_initialize(mapid, map_settings, data);
            });
          }
        });
      }
    }
  };

  Drupal.geofieldGoogleMap = {

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
     * @param {geopositionCallback} callback - The callback
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

    placeFeature: function(feature, map, range) {
      var properties = feature.get('geojsonProperties');
      if (feature.setTitle && properties && properties.title) {
        feature.setTitle(properties.title);
      }
      feature.setMap(map);
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
          infowindow.setPosition(bounds.getCenter());
          infowindow.setContent(properties.description);
          infowindow.open(map);
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
        var features = GeoJSON(data);
        // controltype
        var controltype = map_settings.controltype;
        if (controltype === 'default') { controltype = google.maps.ZoomControlStyle.DEFAULT; }
        else if (controltype === 'small') { controltype = google.maps.ZoomControlStyle.SMALL; }
        else if (controltype === 'large') { controltype = google.maps.ZoomControlStyle.LARGE; }
        else { controltype = false }

        // map type
        var maptype = map_settings.maptype;
        if (maptype) {
          if (maptype === 'map' && map_settings.baselayers_map) { maptype = google.maps.MapTypeId.ROADMAP; }
          if (maptype === 'satellite' && map_settings.baselayers_satellite) { maptype = google.maps.MapTypeId.SATELLITE; }
          if (maptype === 'hybrid' && map_settings.baselayers_hybrid) { maptype = google.maps.MapTypeId.HYBRID; }
          if (maptype === 'physical' && map_settings.baselayers_physical) { maptype = google.maps.MapTypeId.TERRAIN; }
        }
        else { maptype = google.maps.MapTypeId.ROADMAP; }

        // menu type
        var mtc = map_settings.mtc;
        if (mtc === 'standard') { mtc = google.maps.MapTypeControlStyle.HORIZONTAL_BAR; }
        else if (mtc === 'menu' ) { mtc = google.maps.MapTypeControlStyle.DROPDOWN_MENU; }
        else { mtc = false; }

        var myOptions = {
          zoom: parseInt(map_settings.map_zoom),
          minZoom: parseInt(map_settings.map_min_zoom),
          maxZoom: parseInt(map_settings.map_max_zoom),
          mapTypeId: (map_settings.map_maptype) ? map_settings.map_maptype : 'roadmap',
          mapTypeControl: (!!map_settings.map_mtc),
          mapTypeControlOptions: {style: map_settings.map_mtc},
          zoomControl: map_settings.map_controltype !== false,
          zoomControlOptions: {style: map_settings.map_controltype},
          panControl: !!map_settings.map_pancontrol,
          scrollwheel: !!map_settings.map_scrollwheel,
          draggable: !!map_settings.map_draggable,
          overviewMapControl: !!map_settings.map_overview,
          overviewMapControlOptions: {opened: !!map_settings.map_overview_opened},
          streetViewControl: !!map_settings.map_streetview_show,
          scaleControl: !!map_settings.map_scale,
          scaleControlOptions: {style: google.maps.ScaleControlStyle.DEFAULT},
          center: {lat: -34.397, lng: 150.644},
        };

        var map = new google.maps.Map(document.getElementById(mapid), myOptions);
        // Store a reference to the map object so other code can interact
        // with it.
        Drupal.geoField.maps[mapid] = map;

        var range = new google.maps.LatLngBounds();

        var infowindow = new google.maps.InfoWindow({
          content: ''
        });

        if (features.setMap) {
          self.placeFeature(features, map, range);
          // Don't move the default zoom if we're only displaying one point.
          if (features.getPosition) {
            resetZoom = false;
          }
        } else {
          for (var i in features) {
            if (features[i].setMap) {
              self.placeFeature(features[i], map, range);
            } else {
              for (var j in features[i]) {
                if (features[i][j].setMap) {
                  self.placeFeature(features[i][j], map, range);
                }
              }
            }
          }
        }

        for (first in features) break;
        if (first !== 'type') {
          if (resetZoom) {
            map.fitBounds(range);
          } else {
            map.setCenter(range.getCenter());
          }
        } else {
          var center = map_settings.center;
          map.setCenter(new google.maps.LatLng(center.lat, center.lon));
        }
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
