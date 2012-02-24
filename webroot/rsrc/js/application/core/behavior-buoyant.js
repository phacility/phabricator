/**
 * @provides javelin-behavior-buoyant
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-vector
 *           javelin-dom
 */

JX.behavior('buoyant', function() {

  // The display element which shows the "buoyant" header to the user.
  var element = JX.$N('div', {className : 'buoyant'});

  // Keeps track of whether we're currently showing anything or not.
  var visible = false;

  // If we're showing something, the positional DOM element that triggered the
  // currently shown header.
  var active_marker = null;

  // When the header is clicked, jump to the element that triggered it.
  JX.DOM.listen(element, 'click', null, function(e) {
    window.scrollTo(0, JX.$V(active_marker).y - 40);
  });

  function hide() {
    if (visible) {
      JX.DOM.remove(element);
      visible = false;
    }
  }

  function show(text) {
    if (!visible) {
      document.body.appendChild(element);
      visible = true;
    }
    JX.DOM.setContent(element, text);
  }

  var onviewportchange = function(e) {

    // If we're currently showing a header but we've scrolled back up past its
    // marker, hide it.

    var scroll_position = JX.Vector.getScroll().y;
    if (visible && (scroll_position < JX.$V(active_marker).y)) {
      hide();
    }

    // Find all the markers in the document.

    var markers = JX.DOM.scry(document.body, 'div', 'buoyant');

    // Sort the markers by Y position, descending.

    var markinfo = [];
    for (var ii = 0; ii < markers.length; ii++) {
      markinfo.push({
        marker: markers[ii],
        position: JX.$V(markers[ii]).y
      });
    }
    markinfo.sort(function(u, v) { return (v.position - u.position); });

    // Find the first marker above the current scroll position.

    for (var ii = 0; ii < markinfo.length; ii++) {
      if (markinfo[ii].position > scroll_position) {
        // This marker is below the current scroll position, so ignore it.
        continue;
      }

      // We've found a marker. Display it as appropriate;

      active_marker = markinfo[ii].marker;
      var text = JX.Stratcom.getData(active_marker).text;
      if (text) {
        show(text);
      } else {
        hide();
      }

      break;
    }

  }

  JX.Stratcom.listen('scroll', null, onviewportchange);
  JX.Stratcom.listen('resize', null, onviewportchange);
});
