/**
 * @provides javelin-behavior-phabricator-nav
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-magical-init
 *           javelin-vector
 * @javelin
 */

JX.behavior('phabricator-nav', function(config) {

  var dragging;
  var track;

  var nav = JX.$(config.navID);
  var drag = JX.$(config.dragID);
  var content = JX.$(config.contentID);

  JX.enableDispatch(document.body, 'mousemove');

  JX.DOM.listen(drag, 'mousedown', null, function(e) {
    dragging = JX.$V(e);

    // Show the "col-resize" cursor on the whole document while we're
    // dragging, since the mouse will slip off the actual bar fairly often and
    // we don't want it to flicker.
    JX.DOM.alterClass(document.body, 'jx-drag-col', true);

    track = [
      {
        element: nav,
        parameter: 'width',
        start: JX.Vector.getDim(nav).x,
        scale: 1,

        width: JX.Vector.getDim(nav).x,
        minWidth: 150,
        minScale: 1
      },
      {
        element: drag,
        parameter: 'left',
        start: JX.$V(drag).x,
        scale: 1
      },
      {
        element: content,
        parameter: 'marginLeft',
        start: parseInt(getComputedStyle(content).marginLeft, 10),
        scale: 1,

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

    for (var k = 0; k < track.length; k++) {
      panel = track[k];
      if (!panel.minWidth) {
        continue;
      }
      var new_width = panel.width + (dx * panel.minScale);
      if (new_width < panel.minWidth) {
        dx = (panel.minWidth - panel.width) * panel.minScale;
      }
    }

    for (var k = 0; k < track.length; k++) {
      panel = track[k];
      var v = (panel.start + (dx * panel.scale));
      panel.element.style[panel.parameter] = v + 'px';
    }
  });

  JX.Stratcom.listen('mouseup', null, function(e) {
    if (!dragging) {
      return;
    }

    JX.DOM.alterClass(document.body, 'jx-drag-col', false);
    dragging = false;
  });

});
