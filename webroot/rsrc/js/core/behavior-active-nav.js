/**
 * @provides javelin-behavior-phabricator-active-nav
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-vector
 *           javelin-dom
 *           javelin-uri
 */

JX.behavior('phabricator-active-nav', function(config) {

  var local = JX.$(config.localID);

  /**
   * Select the navigation item corresponding to a given anchor.
   */
  var selectnav = function(anchor) {
    var links = JX.DOM.scry(local, 'a');
    var link;
    var link_anchor;
    var selected;
    for (var ii = 0; ii < links.length; ii++) {
      link = links[ii];
      link_anchor = JX.$U(link.href).getFragment();

      selected = (link_anchor == anchor);
      JX.DOM.alterClass(
        link,
        'phabricator-active-nav-focus',
        selected);
    }
  };


  /**
   * Identify the current anchor based on the document scroll position.
   */
  var updateposition = function() {
    // Find all the markers in the document.
    var scroll_position = JX.Vector.getScroll().y;
    var document_size = JX.Vector.getDocument();
    var viewport_size = JX.Vector.getViewport();

    // If we're scrolled all the way down, we always want to select the last
    // anchor.
    var is_at_bottom = (viewport_size.y + scroll_position >= document_size.y);

    var markers = JX.DOM.scry(document.body, 'legend', 'marker');

    // Sort the markers by Y position, descending.
    var markinfo = [];
    var ii;
    for (ii = 0; ii < markers.length; ii++) {
      markinfo.push({
        marker: markers[ii],
        position: JX.$V(markers[ii]).y - 15
      });
    }
    markinfo.sort(function(u, v) { return (v.position - u.position); });

    // Find the first marker above the current scroll position, or the first
    // marker in the document if we're above all the markers.
    var active = null;
    for (ii = 0; ii < markinfo.length; ii++) {
      active = markinfo[ii].marker;
      if (markinfo[ii].position <= scroll_position) {
        break;
      }
      if (is_at_bottom) {
        break;
      }
    }

    // If we get above the first marker, select it.
    selectnav(active && JX.Stratcom.getData(active).anchor);
  };

  var pending = null;
  var onviewportchange = function() {
    pending && clearTimeout(pending);
    pending = setTimeout(updateposition, 100);
  };

  JX.Stratcom.listen('scroll', null, onviewportchange);
  JX.Stratcom.listen('resize', null, onviewportchange);
  JX.Stratcom.listen('hashchange', null, onviewportchange);
});
