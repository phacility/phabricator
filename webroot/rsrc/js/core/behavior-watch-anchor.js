/**
 * @provides javelin-behavior-phabricator-watch-anchor
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           javelin-vector
 */

JX.behavior('phabricator-watch-anchor', function() {

  // When the user loads a page with an "#anchor" or changes the "#anchor" on
  // an existing page, we try to scroll the page to the relevant location.

  // Browsers do this on their own, but we have some additional rules to try
  // to match anchors more flexibly and handle cases where an anchor is not
  // yet present in the document because something is still loading or
  // rendering it, often via Ajax.

  // Number of milliseconds we'll keep trying to find an anchor for.
  var wait_max = 5000;

  // Wait between retries.
  var wait_ms = 100;

  var target;
  var display_target;
  var retry_ms;

  function try_anchor() {
    retry_ms = wait_max;
    seek_anchor();
  }

  function seek_anchor() {
    var anchor = window.location.hash.replace('#', '');

    if (!anchor.length) {
      return;
    }

    var ii;
    var node = null;

    // When the user navigates to "#abc", we'll try to find a node with
    // either ID "abc" or ID "anchor-abc".
    var ids = [anchor, 'anchor-' + anchor];

    for (ii = 0; ii < ids.length; ii++) {
      try {
        node = JX.$(ids[ii]);
        break;
      } catch (e) {
        // Continue.
      }
    }

    // If we haven't found a matching node yet, look for an "<a />" tag with
    // a "name" attribute that has our anchor as a prefix. For example, you
    // can navigate to "#cat" and we'll match "#cat-and-mouse".

    if (!node) {
      var anchor_nodes = JX.DOM.scry(document.body, 'a');
      for (ii = 0; ii < anchor_nodes.length; ii++) {
        if (!anchor_nodes[ii].name) {
          continue;
        }

        if (anchor_nodes[ii].name.substring(0, anchor.length) === anchor) {
          node = anchor_nodes[ii];
          break;
        }
      }
    }

    // If we already have an anchor highlighted, unhighlight it and throw
    // it away if it doesn't match the new target.
    if (target && (target !== node)) {
      JX.DOM.alterClass(display_target, 'anchor-target', false);
      target = null;
      display_target = null;
    }

    // If we didn't find a matching anchor, try again soon. This allows
    // rendering logic some time to complete Ajax requests and draw elements
    // onto the page.
    if (!node) {
      if (retry_ms > 0) {
        retry_ms -= wait_ms;
        setTimeout(try_anchor, wait_ms);
        return;
      }
    }

    // If we've found a new target, highlight it.
    if (target !== node) {
      target = node;

      // If there's an "anchor-container" parent element, we'll make the
      // display adjustment to that node instead. For example, this is used
      // by the timeline to highlight timeline stories.

      var container;

      try {
        container = JX.DOM.findAbove(node, null, 'anchor-container');
      } catch (ex) {
        // Ignore.
      }

      if (container) {
        display_target = container;
      } else {
        display_target = node;
      }

      JX.DOM.alterClass(display_target, 'anchor-target', true);
    }

    // Try to scroll to the new target.
    try {
      var pos = JX.Vector.getPosWithScroll(node);
      JX.DOM.scrollToPosition(0, pos.y - 60);
    } catch (e) {
      // Ignore issues with scrolling the document.
    }
  }

  JX.Stratcom.listen('hashchange', null, try_anchor);
  try_anchor();
});
