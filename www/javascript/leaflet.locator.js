var Locator = L.Control.extend({
  options: {
    /** Position of the control */
    position: 'topleft',
    /** The layer that the user's location should be drawn on. By default creates a new layer. */
    layer: undefined,
    /**
     * Automatically sets the map view (zoom and pan) to the user's location as it updates.
     * While the map is following the user's location, the control is in the `following` state,
     * which changes the style of the control and the circle marker.
     *
     * Possible values:
     *  - false: never updates the map view when location changes.
     *  - 'once': set the view when the location is first determined
     *  - 'always': always updates the map view when location changes.
     *              The map view follows the users location.
     *  - 'untilPan': (default) like 'always', except stops updating the
     *                view if the user has manually panned the map.
     *                The map view follows the users location until she pans.
     */
    setView: 'untilPan',
    /** Keep the current map zoom level when setting the view and only pan. */
    keepCurrentZoomLevel: false,
    /** Smooth pan and zoom to the location of the marker. Only works in Leaflet 1.0+. */
    flyTo: false,
    /**
     * The user location can be inside and outside the current view when the user clicks on the
     * control that is already active. Both cases can be configures separately.
     * Possible values are:
     *  - 'setView': zoom and pan to the current location
     *  - 'stop': stop locating and remove the location marker
     */
    clickBehavior: {
      /** What should happen if the user clicks on the control while the location is within the current view. */
      inView: 'stop',
      /** What should happen if the user clicks on the control while the location is outside the current view. */
      outOfView: 'setView'
    },
    /**
     * If set, save the map bounds just before centering to the user's
     * location. When control is disabled, set the view back to the
     * bounds that were saved.
     */
    returnToPrevBounds: false,
    /** If set, a circle that shows the location accuracy is drawn. */
    drawCircle: true,
    /** If set, the marker at the users' location is drawn. */
    drawMarker: true,
    /** The class to be used to create the marker. For example L.CircleMarker or L.Marker */
    markerClass: L.CircleMarker,
    /** Accuracy circle style properties. */
    circleStyle: {
      color: '#136AEC',
      fillColor: '#136AEC',
      fillOpacity: 0.15,
      weight: 2,
      opacity: 0.5
    },
    /** Inner marker style properties. */
    markerStyle: {
      color: '#136AEC',
      fillColor: '#2A93EE',
      fillOpacity: 0.7,
      weight: 2,
      opacity: 0.9,
      radius: 5
    },
    /**
     * Changes to accuracy circle and inner marker while following.
     * It is only necessary to provide the properties that should change.
     */
    followCircleStyle: {},
    followMarkerStyle: {
        // color: '#FFA500',
        // fillColor: '#FFB000'
    },
    /** The CSS class for the icon. For example fa-location-arrow or fa-map-marker */
    icon: 'fa fa-map-marker',
    iconLoading: 'fa fa-spinner fa-spin',
    /** The element to be created for icons. For example span or i */
    iconElementTag: 'span',
    /** Padding around the accuracy circle. */
    circlePadding: [0, 0],
    /** Use metric units. */
    metric: true,
    /** This event is called in case of any location error that is not a time out error. */
    onLocationError: function (err, control) {
      alert(err.message);
    },
    /**
     * This even is called when the user's location is outside the bounds set on the map.
     * The event is called repeatedly when the location changes.
     */
    onLocationOutsideMapBounds: function (control) {
      control.stop();
      alert(control.options.strings.outsideMapBoundsMsg);
    },
    /** Display a pop-up when the user click on the inner marker. */
    showPopup: true,
    strings: {
      title: 'Show me where I am',
      metersUnit: 'meters',
      feetUnit: 'feet',
      popup: 'You are within {distance} {unit} from this point',
      outsideMapBoundsMsg: 'You seem located outside the boundaries of the map'
    },
    /** The default options passed to leaflets locate method. */
    locateOptions: {
      maxZoom: Infinity,
      watch: true,  // if you overwrite this, visualization cannot be updated
      setView: false // have to set this to false because we have to
                     // do setView manually
    }
  },

  initialize: function (options) {
    // set default options if nothing is set (merge one step deep)
    for (var i in options) {
      if (typeof this.options[i] === 'object') {
        L.extend(this.options[i], options[i]);
      } else {
        this.options[i] = options[i];
      }
    }

    // extend the follow marker style and circle from the normal style
    this.options.followMarkerStyle = L.extend({}, this.options.markerStyle, this.options.followMarkerStyle);
    this.options.followCircleStyle = L.extend({}, this.options.circleStyle, this.options.followCircleStyle);
  },

  /**
   * Add control to map. Returns the container for the control.
   */
  onAdd: function (map) {
    var container = L.DomUtil.create('div',
        'leaflet-control-locate leaflet-bar leaflet-control');

    this._layer = this.options.layer || new L.LayerGroup();
    this._layer.addTo(map);
    this._event = undefined;
    this._prevBounds = null;

    this._link = L.DomUtil.create('a', 'leaflet-bar-part leaflet-bar-part-single', container);
    this._link.href = '#';
    this._link.title = this.options.strings.title;
    this._icon = L.DomUtil.create(this.options.iconElementTag, this.options.icon, this._link);

    L.DomEvent
      .on(this._link, 'click', L.DomEvent.stopPropagation)
      .on(this._link, 'click', L.DomEvent.preventDefault)
      .on(this._link, 'click', this._onClick, this)
      .on(this._link, 'dblclick', L.DomEvent.stopPropagation);

    this._resetVariables();

    this._map.on('unload', this._unload, this);

    return container;
  },

  /**
   * This method is called when the user clicks on the control.
   */
  _onClick: function () {
    this._justClicked = true;
    this._userPanned = false;

    if (this._active && !this._event) {
      // click while requesting
      this.stop();
    } else if (this._active && this._event !== undefined) {
      var behavior = this._map.getBounds().contains(this._event.latlng) ? this.options.clickBehavior.inView : this.options.clickBehavior.outOfView;
      switch (behavior) {
        case 'setView':
          this.setView();
          break;
        case 'stop':
          this.stop();
          if (this.options.returnToPrevBounds) {
            var f = this.options.flyTo ? this._map.flyToBounds : this._map.fitBounds;
            f.bind(this._map)(this._prevBounds);
          }
          break;
      }
    } else {
      if (this.options.returnToPrevBounds) {
        this._prevBounds = this._map.getBounds();
      }
      this.start();
    }

    this._updateContainerStyle();
  },

  /**
   * Starts the plugin:
   * - activates the engine
   * - draws the marker (if coordinates available)
   */
  start: function () {
    this._activate();

    if (this._event) {
      this._drawMarker(this._map);

      // if we already have a location but the user clicked on the control
      if (this.options.setView) {
        this.setView();
      }
    }
    this._updateContainerStyle();
  },

  /**
   * Stops the plugin:
   * - deactivates the engine
   * - reinitializes the button
   * - removes the marker
   */
  stop: function () {
    this._deactivate();

    this._cleanClasses();
    this._resetVariables();

    this._removeMarker();
  },

  /**
   * This method launches the location engine.
   * It is called before the marker is updated,
   * event if it does not mean that the event will be ready.
   *
   * Override it if you want to add more functionalities.
   * It should set the this._active to true and do nothing if
   * this._active is true.
   */
  _activate: function () {
    if (!this._active) {
      this._map.locate(this.options.locateOptions);
      this._active = true;

      // bind event listeners
      this._map.on('locationfound', this._onLocationFound, this);
      this._map.on('locationerror', this._onLocationError, this);
      this._map.on('dragstart', this._onDrag, this);
    }
  },

  /**
   * Called to stop the location engine.
   *
   * Override it to shutdown any functionalities you added on start.
   */
  _deactivate: function () {
    this._map.stopLocate();
    this._active = false;

    // unbind event listeners
    this._map.off('locationfound', this._onLocationFound, this);
    this._map.off('locationerror', this._onLocationError, this);
    this._map.off('dragstart', this._onDrag, this);
  },

  /**
   * Zoom (unless we should keep the zoom level) and an to the current view.
   */
  setView: function () {
    this._drawMarker();
    if (this._isOutsideMapBounds()) {
      this.options.onLocationOutsideMapBounds(this);
    } else {
      if (this.options.keepCurrentZoomLevel) {
        var f = this.options.flyTo ? this._map.flyTo : this._map.panTo;
        f.bind(this._map)([this._event.latitude, this._event.longitude]);
      } else {
        var f = this.options.flyTo ? this._map.flyToBounds : this._map.fitBounds; // eslint-disable-line
        f.bind(this._map)(this._event.bounds, {
          padding: this.options.circlePadding,
          maxZoom: this.options.locateOptions.maxZoom
        });
      }
    }
  },

  /**
   * Draw the marker and accuracy circle on the map.
   *
   * Uses the event retrieved from onLocationFound from the map.
   */
  _drawMarker: function () {
    if (this._event.accuracy === undefined) {
      this._event.accuracy = 0;
    }

    var radius = this._event.accuracy;
    var latlng = this._event.latlng;

    // circle with the radius of the location's accuracy
    if (this.options.drawCircle) {
      var style = this._isFollowing() ? this.options.followCircleStyle : this.options.circleStyle;

      if (!this._circle) {
        this._circle = L.circle(latlng, radius, style).addTo(this._layer);
      } else {
        this._circle.setLatLng(latlng).setRadius(radius).setStyle(style);
      }
    }

    var distance, unit;
    if (this.options.metric) {
      distance = radius.toFixed(0);
      unit = this.options.strings.metersUnit;
    } else {
      distance = (radius * 3.2808399).toFixed(0);
      unit = this.options.strings.feetUnit;
    }

    // small inner marker
    if (this.options.drawMarker) {
      var mStyle = this._isFollowing() ? this.options.followMarkerStyle : this.options.markerStyle;

      if (!this._marker) {
        this._marker = new this.options.markerClass(latlng, mStyle).addTo(this._layer); // eslint-disable-line
      } else {
        this._marker.setLatLng(latlng).setStyle(mStyle);
      }
    }

    var t = this.options.strings.popup;
    if (this.options.showPopup && t && this._marker) {
      this._marker
          .bindPopup(L.Util.template(t, {distance: distance, unit: unit}))
          ._popup.setLatLng(latlng);
    }
  },

  /**
   * Remove the marker from map.
   */
  _removeMarker: function () {
    this._layer.clearLayers();
    this._marker = undefined;
    this._circle = undefined;
  },

  /**
   * Unload the plugin and all event listeners.
   * Kind of the opposite of onAdd.
   */
  _unload: function () {
    this.stop();
    this._map.off('unload', this._unload, this);
  },

  /**
   * Calls deactivate and dispatches an error.
   */
  _onLocationError: function (err) {
    // ignore time out error if the location is watched
    if (err.code === 3 && this.options.locateOptions.watch) {
      return;
    }

    this.stop();
    this.options.onLocationError(err, this);
  },

  /**
   * Stores the received event and updates the marker.
   */
  _onLocationFound: function (e) {
    // no need to do anything if the location has not changed
    if (this._event &&
      (this._event.latlng.lat === e.latlng.lat &&
        this._event.latlng.lng === e.latlng.lng &&
        this._event.accuracy === e.accuracy)) {
      return;
    }

    if (!this._active) {
      // we may have a stray event
      return;
    }

    this._event = e;

    this._drawMarker();
    this._updateContainerStyle();

    switch (this.options.setView) {
      case 'once':
        if (this._justClicked) {
          this.setView();
        }
        break;
      case 'untilPan':
        if (!this._userPanned) {
          this.setView();
        }
        break;
      case 'always':
        this.setView();
        break;
      case false:
        // don't set the view
        break;
    }

    this._justClicked = false;
  },

  /**
   * When the user drags. Need a separate even so we can bind and unbind even listeners.
   */
  _onDrag: function () {
    // only react to drags once we have a location
    if (this._event) {
      this._userPanned = true;
      this._updateContainerStyle();
      this._drawMarker();
    }
  },

  /**
   * Compute whether the map is following the user location with pan and zoom.
   */
  _isFollowing: function () {
    if (!this._active) {
      return false;
    }

    if (this.options.setView === 'always') {
      return true;
    } else if (this.options.setView === 'untilPan') {
      return !this._userPanned;
    }
  },

  /**
   * Check if location is in map bounds
   */
  _isOutsideMapBounds: function () {
    if (this._event === undefined) {
      return false;
    }
    return this._map.options.maxBounds &&
      !this._map.options.maxBounds.contains(this._event.latlng);
  },

  /**
   * Toggles button class between following and active.
   */
  _updateContainerStyle: function () {
    if (!this._container) {
      return;
    }

    if (this._active && !this._event) {
      // active but don't have a location yet
      this._setClasses('requesting');
    } else if (this._isFollowing()) {
      this._setClasses('following');
    } else if (this._active) {
      this._setClasses('active');
    } else {
      this._cleanClasses();
    }
  },

  /**
   * Sets the CSS classes for the state.
   */
  _setClasses: function (state) {
    if (state === 'requesting') {
      L.DomUtil.removeClasses(this._container, 'active following');
      L.DomUtil.addClasses(this._container, 'requesting');

      L.DomUtil.removeClasses(this._icon, this.options.icon);
      L.DomUtil.addClasses(this._icon, this.options.iconLoading);
    } else if (state === 'active') {
      L.DomUtil.removeClasses(this._container, 'requesting following');
      L.DomUtil.addClasses(this._container, 'active');

      L.DomUtil.removeClasses(this._icon, this.options.iconLoading);
      L.DomUtil.addClasses(this._icon, this.options.icon);
    } else if (state === 'following') {
      L.DomUtil.removeClasses(this._container, 'requesting');
      L.DomUtil.addClasses(this._container, 'active following');

      L.DomUtil.removeClasses(this._icon, this.options.iconLoading);
      L.DomUtil.addClasses(this._icon, this.options.icon);
    }
  },

  /**
   * Removes all classes from button.
   */
  _cleanClasses: function () {
    L.DomUtil.removeClass(this._container, 'requesting');
    L.DomUtil.removeClass(this._container, 'active');
    L.DomUtil.removeClass(this._container, 'following');

    L.DomUtil.removeClasses(this._icon, this.options.iconLoading);
    L.DomUtil.addClasses(this._icon, this.options.icon);
  },

  /**
   * Reinitializes state variables.
   */
  _resetVariables: function () {
    // whether locate is active or not
    this._active = false;

    // true if the control was clicked for the first time
    // we need this so we can pan and zoom once we have the location
    this._justClicked = false;

    // true if the user has panned the map after clicking the control
    this._userPanned = false;
  }
});

(function () {
  // leaflet.js raises bug when trying to addClass / removeClass multiple classes at once
  // Let's create a wrapper on it which fixes it.
  var LDomUtilApplyClassesMethod = function (method, element, classNames) {
    classNames = classNames.split(' ');
    classNames.forEach(function (className) {
      L.DomUtil[method].call(this, element, className);
    });
  };

  L.DomUtil.addClasses = function (el, names) { LDomUtilApplyClassesMethod('addClass', el, names); };
  L.DomUtil.removeClasses = function (el, names) { LDomUtilApplyClassesMethod('removeClass', el, names); };
})();
