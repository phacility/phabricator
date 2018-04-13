/**
 * @provides javelin-behavior-phabricator-nav
 * @requires javelin-behavior
 *           javelin-behavior-device
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-magical-init
 *           javelin-vector
 *           javelin-request
 *           javelin-util
 * @javelin
 */

JX.behavior('phabricator-nav', function(config) {

  var content = JX.$(config.contentID);
  var local = JX.$(config.localID);
  var main = JX.$(config.mainID);
  var drag = JX.$(config.dragID);

// - Flexible Navigation Column ------------------------------------------------


  var dragging;
  var track;

  JX.enableDispatch(document.body, 'mousemove');

  JX.DOM.listen(drag, 'mousedown', null, function(e) {
    if (!e.isNormalMouseEvent()) {
      return;
    }

    dragging = JX.$V(e);

    // Show the "col-resize" cursor on the whole document while we're
    // dragging, since the mouse will slip off the actual bar fairly often and
    // we don't want it to flicker.
    JX.DOM.alterClass(document.body, 'jx-drag-col', true);

    track = [
      {
        element: local,
        parameter: 'width',
        start: JX.Vector.getDim(local).x,
        width: JX.Vector.getDim(local).x,
        minWidth: 1
      },
      {
        element: drag,
        parameter: 'left',
        start: get_width()
      },
      {
        element: content,
        parameter: 'marginLeft',
        start: parseInt(getComputedStyle(content).marginLeft, 10),
        width: JX.Vector.getDim(content).x,
        minWidth: 300,
        minScale: -1
      }
    ];

    e.kill();
  });

  JX.Stratcom.listen('mousemove', null, function(e) {
    if (!dragging) {
      return;
    }

    var dx = JX.$V(e).x - dragging.x;
    var panel;
    var k;

    for (k = 0; k < track.length; k++) {
      panel = track[k];
      if (!panel.minWidth) {
        continue;
      }
      var new_width = panel.width + (dx * (panel.minScale || 1));
      if (new_width < panel.minWidth) {
        dx = (panel.minWidth - panel.width) * panel.minScale;
      }
    }

    for (k = 0; k < track.length; k++) {
      panel = track[k];
      var v = (panel.start + (dx * (panel.scale || 1)));
      panel.element.style[panel.parameter] = v + 'px';
    }
  });

  JX.Stratcom.listen('mouseup', null, function() {
    if (!dragging) {
      return;
    }
    JX.DOM.alterClass(document.body, 'jx-drag-col', false);
    dragging = false;

    new JX.Request('/settings/adjust/', JX.bag)
      .setData(
        {
          key: 'filetree.width',
          value: get_width()
        })
      .send();
  });

  function get_width() {
    // See PHI568. If the document has scrolled horizontally, the "x" position
    // of the bar will be the actual width of the menu plus the horizontal
    // scroll position (because the element is "position: fixed"). Subtract the
    // document scroll position when saving the element width so that scrolling
    // to the right and then toggling the filetree UI does not make it grow
    // any wider.
    return (JX.$V(drag).x - JX.Vector.getScroll().x);
  }

  var saved_width = config.width;
  function savedrag() {
    saved_width = get_width();

    local.style.width = '';
    drag.style.left = '';
    content.style.marginLeft = '';
  }

  function restoredrag() {
    if (!saved_width) {
      return;
    }

    local.style.width = saved_width + 'px';
    drag.style.left = saved_width + 'px';
    content.style.marginLeft = (saved_width + JX.Vector.getDim(drag).x) + 'px';
  }

  var collapsed = config.collapsed;
  JX.Stratcom.listen('differential-filetree-toggle', null, function() {
    collapsed = !collapsed;

    if (collapsed) {
      savedrag();
    }

    JX.DOM.alterClass(main, 'has-local-nav', !collapsed);
    JX.DOM.alterClass(main, 'has-drag-nav', !collapsed);
    JX.DOM.alterClass(main, 'has-closed-nav', collapsed);

    if (!collapsed) {
      restoredrag();
    }

    new JX.Request('/settings/adjust/', JX.bag)
      .setData({ key : 'nav-collapsed', value : (collapsed ? 1 : 0) })
      .send();

    // Invoke a resize event so page elements can redraw if they need to. One
    // example is the selection reticles in Differential.
    JX.Stratcom.invoke('resize');
  });


// - Scroll --------------------------------------------------------------------

  // When the user scrolls or resizes the window, anchor the menu to to the top
  // of the navigation bar.

  function onresize() {
    if (JX.Device.getDevice() != 'desktop') {
      return;
    }

    // When the buoyant header is visible, move the menu down below it. This
    // is a bit of a hack.
    var banner_height = 0;
    try {
      var banner = JX.$('diff-banner');
      banner_height = JX.Vector.getDim(banner).y;
    } catch (error) {
      // Ignore if there's no banner on the page.
    }

    local.style.top = Math.max(
      0,
      banner_height,
      JX.$V(content).y - Math.max(0, JX.Vector.getScroll().y)) + 'px';
  }

  local.style.position = 'fixed';
  local.style.bottom = 0;
  local.style.left = 0;

  JX.Stratcom.listen(['scroll', 'resize'], null, onresize);
  onresize();


// - Navigation Reset ----------------------------------------------------------

  JX.Stratcom.listen('phabricator-device-change', null, function() {
    resetdrag();
    onresize();
  });

});
