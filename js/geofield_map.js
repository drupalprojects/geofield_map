(function ($, Drupal, drupalSettings) {

  'use strict';

  Drupal.behaviors.geofieldMapInit = {
    attach: function (context, drupalSettings) {

      // Init all maps in drupalSettings.
      if (drupalSettings['geofield_map']) {
        $.each(drupalSettings['geofield_map'], function (mapid, options) {
          Drupal.geofieldMap.loadGoogle(mapid, function () {
            Drupal.geofieldMap.map_initialize({
              entity_operation: options.entity_operation,
              lat: options.lat,
              lng: options.lng,
              zoom_start: parseInt(options.zoom_start),
              zoom_focus: parseInt(options.zoom_focus),
              zoom_min: parseInt(options.zoom_min),
              zoom_max: parseInt(options.zoom_max),
              latid: options.latid,
              lngid: options.lngid,
              searchid: options.searchid,
              mapid: options.mapid,
              widget: options.widget,
              map_library: options.map_library,
              map_type: options.map_type,
              map_type_selector: options.map_type_selector,
              map_types_google: options.map_types_google,
              map_types_leaflet: options.map_types_leaflet,
              click_to_find_marker_id: options.click_to_find_marker_id,
              click_to_find_marker: options.click_to_find_marker,
              click_to_place_marker_id: options.click_to_place_marker_id,
              click_to_place_marker: options.click_to_place_marker,
              geoaddress_field: options.geoaddress_field,
              geoaddress_field_id: options.geoaddress_field_id
            });
          });
        });
      }
    }
  };

  Drupal.geofieldMap = {

    geocoder: null,
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

      // Ensure callbacks array;
      self.googleCallbacks = self.googleCallbacks || [];

      // Wait until the window load event to try to use the maps library.
      $(document).ready(function (e) {
        _.invoke(self.googleCallbacks, 'callback');
        self.googleCallbacks = [];
      });
    },

    /**
     * Adds a callback that will be called once the maps library is loaded.
     *
     * @param {geolocationCallback} callback - The callback
     */
    addCallback: function (callback) {
      var self = this;
      self.googleCallbacks = self.googleCallbacks || [];
      self.googleCallbacks.push({callback: callback});
    },

    // Lead Google Maps library.
    loadGoogle: function (mapid, callback) {
      var self = this;

      // If a Google API key is set, define it.
      if (typeof drupalSettings['geofield_map'][mapid]['gmap_api_key'] !== 'undefined' && drupalSettings['geofield_map'][mapid]['gmap_api_key'] !== null) {
        self.gmap_api_key = drupalSettings['geofield_map'][mapid]['gmap_api_key'];
      }

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
        if (self.gmap_api_key) {
          scriptPath += '&key=' + drupalSettings['geofield_map'][mapid]['gmap_api_key'];
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

    // Center the map to the marker location.
    find_marker: function (mapid) {
      var self = this;
      google.maps.event.trigger(self.map_data[mapid].map, 'resize');
      self.mapSetCenter(mapid, self.getMarkerPosition(mapid));
    },

    // Place marker at the current center of the map.
    place_marker: function (mapid) {
      var self = this;
      if (self.map_data[mapid].click_to_place_marker) {
        if (!window.confirm('Change marker position ?')) {
          return;
        }
      }

      google.maps.event.trigger(self.map_data[mapid].map, 'resize');
      var position = self.map_data[mapid].map.getCenter();
      self.setMarkerPosition(mapid, position);
      self.setLatLngValues(mapid, position);
      if (self.map_data[mapid].search) {
        self.geocoder.geocode({latLng: position}, function (results, status) {
          if (status === google.maps.GeocoderStatus.OK) {
            if (results[0]) {
              self.map_data[mapid].search.val(results[0].formatted_address);
              self.map_data[mapid].geoaddress_field.val(results[0].formatted_address);
            }
          }
        });
      }
    },

    // Geofields update.
    geofields_update: function (mapid, position) {
      var self = this;
      self.setLatLngValues(mapid, position);
      self.reverse_geocode(mapid, position);
    },

    // Onchange of Geofields.
    geofield_onchange: function (mapid) {
      var self = this;
      var location = {};
      switch (self.map_data[mapid].map_library) {
        case 'leaflet':
          location = L.latLng(
            self.map_data[mapid].lat.val(),
            self.map_data[mapid].lng.val()
          );
          break;
        default:
          location = new google.maps.LatLng(
            self.map_data[mapid].lat.val(),
            self.map_data[mapid].lng.val()
          );
      }
      self.setMarkerPosition(mapid, location);
      self.mapSetCenter(mapid, location);
      self.setZoomToFocus(mapid);
      self.reverse_geocode(mapid, location);
    },

    // Coordinates update.
    setLatLngValues: function (mapid, position) {
      var self = this;
      switch (self.map_data[mapid].map_library) {
        case 'leaflet':
          self.map_data[mapid].lat.val(position.lat.toFixed(6));
          self.map_data[mapid].lng.val(position.lng.toFixed(6));
          break;
        default:
          self.map_data[mapid].lat.val(position.lat().toFixed(6));
          self.map_data[mapid].lng.val(position.lng().toFixed(6));
      }
    },

    // Reverse geocode.
    reverse_geocode: function (mapid, position) {
      var self = this;
      self.geocoder.geocode({latLng: position}, function (results, status) {
        if (status === google.maps.GeocoderStatus.OK) {
          if (results[0] && self.map_data[mapid].search) {
            self.map_data[mapid].search.val(results[0].formatted_address);
            self.map_data[mapid].geoaddress_field.val(self.map_data[mapid].search.val());
          }
        }
      });
    },

    // Define a Geographical point, from coordinates.
    getLatLng: function (mapid, lat, lng) {
      var self = this;
      var latLng = {};
      switch (self.map_data[mapid].map_library) {
        case 'leaflet':
          latLng = L.latLng(lat, lng);
          break;
        default:
          latLng = new google.maps.LatLng(lat, lng);
      }
      return latLng;
    },

    // Define the Geofield Map.
    getGeofieldMap: function (mapid) {
      var self = this;
      var map = {};
      var zoom_start = self.map_data[mapid].entity_operation !== 'edit' ? Number(self.map_data[mapid].zoom_start) : Number(self.map_data[mapid].zoom_focus);
      switch (self.map_data[mapid].map_library) {
        case 'leaflet':
          map = L.map(mapid, {
            center: self.map_data[mapid].location,
            zoom: zoom_start
          });

          var baseLayers = {};
          for (var key in self.map_data[mapid].map_types_leaflet) {
            if (self.map_data[mapid].map_types_leaflet.hasOwnProperty(key)) {
              baseLayers[key] = L.tileLayer(self.map_data[mapid].map_types_leaflet[key].url, self.map_data[mapid].map_types_leaflet[key].options);
            }
          }
          baseLayers[self.map_data[mapid].map_type].addTo(map);
          if (self.map_data[mapid].map_type_selector) {
            L.control.layers(baseLayers).addTo(map);
          }

          break;

        default:
          var options = {
            zoom: zoom_start,
            center: self.map_data[mapid].location,
            mapTypeId: google.maps.MapTypeId[self.map_data[mapid].map_type],
            mapTypeControl: !!self.map_data[mapid].map_type_selector,
            mapTypeControlOptions: {
              position: google.maps.ControlPosition.TOP_RIGHT
            },
            scaleControl: true,
            streetViewControlOptions: {
              position: google.maps.ControlPosition.TOP_RIGHT
            },
            zoomControlOptions: {
              style: google.maps.ZoomControlStyle.LARGE,
              position: google.maps.ControlPosition.TOP_LEFT
            }
          };
          map = new google.maps.Map(document.getElementById(self.map_data[mapid].mapid), options);
      }
      return map;
    },

    setZoomToFocus: function (mapid) {
      var self = this;
      switch (self.map_data[mapid].map_library) {
        case 'leaflet':
          self.map_data[mapid].map.setZoom(self.map_data[mapid].zoom_focus, {animate: false});
          break;

        default:
          self.map_data[mapid].map.setZoom(self.map_data[mapid].zoom_focus);
      }
    },

    setMarker: function (mapid, location) {
      var self = this;
      var marker = {};
      switch (self.map_data[mapid].map_library) {
        case 'leaflet':
          marker = L.marker(location, {draggable: true});
          marker.addTo(self.map_data[mapid].map);
          break;

        default:
          marker = new google.maps.Marker({
            map: self.map_data[mapid].map,
            draggable: self.map_data[mapid].widget
          });
          marker.setPosition(location);
      }
      return marker;
    },

    setMarkerPosition: function (mapid, location) {
      var self = this;
      switch (self.map_data[mapid].map_library) {
        case 'leaflet':
          self.map_data[mapid].marker.setLatLng(location);
          break;

        default:
          self.map_data[mapid].marker.setPosition(location);
      }
    },

    getMarkerPosition: function (mapid) {
      var self = this;
      var latLng = {};
      switch (self.map_data[mapid].map_library) {
        case 'leaflet':
          latLng = self.map_data[mapid].marker.getLatLng();
          break;

        default:
          latLng = self.map_data[mapid].marker.getPosition();
      }
      return latLng;
    },


    mapSetCenter: function (mapid, location) {
      var self = this;
      switch (self.map_data[mapid].map_library) {
        case 'leaflet':
          self.map_data[mapid].map.panTo(location, {animate: false});
          break;

        default:
          self.map_data[mapid].map.setCenter(location);
      }
    },

    // Init Geofield Map and its functions.
    map_initialize: function (params) {
      this.map_data[params.mapid] = params;
      var self = this;
      jQuery.noConflict();

      // Define a google Geocoder, if not yet done.
      if (!self.geocoder) {
        self.geocoder = new google.maps.Geocoder();
      }

      if(params.searchid !== null) {

        // Define the Geocoder Search Field Selector;
        self.map_data[params.mapid].search = jQuery('#' + params.searchid);

        // Define the Geoaddress Associated Field Selector;
        self.map_data[params.mapid].geoaddress_field = jQuery('#' + params.geoaddress_field_id);

      }

      // Define the Geofield Location.
      var location = self.getLatLng(params.mapid, params.lat, params.lng);
      self.map_data[params.mapid].location = location;

      // Define the Geofield Map.
      var map = self.getGeofieldMap(params.mapid);

      // Define a a Drupal.geofield_map map self property.
      self.map_data[params.mapid].map = map;

      // Generate and Set Marker Location.
      var marker = self.setMarker(params.mapid, location);

      // Define a Drupal.geofield_map marker self property.
      self.map_data[params.mapid].marker = marker;

      // Bind click to find_marker functionality.
      jQuery('#' + self.map_data[params.mapid].click_to_find_marker_id).click(function (e) {
        e.preventDefault();
        self.find_marker(self.map_data[params.mapid].mapid);
      });

      // Bind click to place_marker functionality.
      jQuery('#' + self.map_data[params.mapid].click_to_place_marker_id).click(function (e) {
        e.preventDefault();
        self.place_marker(self.map_data[params.mapid].mapid);
      });

      // Define Lat & Lng input selectors and all related functionalities and Geofield Map Listeners
      if (params.widget && params.latid && params.lngid) {
        self.map_data[params.mapid].lat = jQuery('#' + params.latid);
        self.map_data[params.mapid].lng = jQuery('#' + params.lngid);

        // If it is defined the Geocode address Search field (dependant on the Gmaps API key)
        if (self.map_data[params.mapid].search) {
          self.map_data[params.mapid].search.autocomplete({
            // This bit uses the geocoder to fetch address values.
            source: function (request, response) {
              self.geocoder.geocode({address: request.term}, function (results, status) {
                response(jQuery.map(results, function (item) {
                  return {
                    label: item.formatted_address,
                    value: item.formatted_address,
                    latitude: item.geometry.location.lat(),
                    longitude: item.geometry.location.lng()
                  };
                }));
              });
            },
            // This bit is executed upon selection of an address.
            select: function (event, ui) {
              var location = self.getLatLng(params.mapid, ui.item.latitude, ui.item.longitude);
              // Set the location
              self.setMarkerPosition(params.mapid, location);
              self.mapSetCenter(params.mapid, location);
              self.setZoomToFocus(params.mapid);
              // Fill the lat/lon fields with the new info
              self.setLatLngValues(params.mapid, self.getMarkerPosition(params.mapid, marker));
              self.map_data[params.mapid].geoaddress_field.val(ui.item.value);
            }
          });

          // Geocode user input on enter.
          self.map_data[params.mapid].search.keydown(function (e) {
            if (e.which === 13) {
              e.preventDefault();
              var input = self.map_data[params.mapid].search.val();
              // Execute the geocoder
              self.geocoder.geocode({address: input}, function (results, status) {
                if (status === google.maps.GeocoderStatus.OK) {
                  if (results[0]) {
                    // Set the location
                    var location = self.getLatLng(params.mapid, results[0].geometry.location.lat(), results[0].geometry.location.lng());
                    self.setMarkerPosition(params.mapid, location);
                    self.mapSetCenter(params.mapid, location);
                    self.setZoomToFocus(params.mapid);
                    // Fill the lat/lon fields with the new info
                    self.setLatLngValues(params.mapid, self.getMarkerPosition(params.mapid));
                    self.map_data[params.mapid].geoaddress_field.val(self.map_data[params.mapid].search.val());
                  }
                }
              });
            }
          });
        }

        if (params.map_library === 'gmap') {
          // Add listener to marker for reverse geocoding.
          google.maps.event.addListener(marker, 'dragend', function () {
            self.geofields_update(params.mapid, marker.getPosition());
          });

          google.maps.event.addListener(map, 'click', function (event) {
            var position = self.getLatLng(params.mapid, event.latLng.lat(), event.latLng.lng());
            self.setMarkerPosition(params.mapid, position);
            self.geofields_update(params.mapid, position);
          });

        }

        if (params.map_library === 'leaflet') {
          marker.on('dragend', function (e) {
            self.geofields_update(params.mapid, marker.getLatLng());
          });

          map.on('click', function (event) {
            var position = event.latlng;
            self.setMarkerPosition(params.mapid, position);
            self.geofields_update(params.mapid, position);
          });

        }

        // Events on Lat field change.
        jQuery('#' + self.map_data[params.mapid].latid).on('change', function (e) {
          self.geofield_onchange(params.mapid);
        }).keydown(function (e) {
          if (e.which === 13) {
            e.preventDefault();
            self.geofield_onchange(params.mapid);
          }
        });

        // Events on Lon field change.
        jQuery('#' + self.map_data[params.mapid].lngid).on('change', function (e) {
          self.geofield_onchange(params.mapid);
        }).keydown(function (e) {
          if (e.which === 13) {
            e.preventDefault();
            self.geofield_onchange(params.mapid);
          }
        });

        // Set default search field value.
        if (self.map_data[params.mapid].search && self.map_data[params.mapid].geoaddress_field.length) {
          // Copy from the geoaddress_field.val
          self.map_data[params.mapid].search.val(self.map_data[params.mapid].geoaddress_field.val());
        }
        else {
          // Sets as reverse geocode from the Geofield.
          self.reverse_geocode(params.mapid, location);
        }
      }
    }
  };

})(jQuery, Drupal, drupalSettings);
