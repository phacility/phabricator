/**
 * @provides javelin-behavior-badge-view
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 */

/**
 * Toggle CSS classes when an element is clicked. This behavior is activated
 * by adding the sigil `jx-badge-view` to an element, and a key `map` to its
 * data. The `map` should be a map from element IDs to the classes that should
 * be toggled on them.
 *
 * Optionally, you may provide a `state` key to set the default state of the
 * element.
 */
JX.behavior('badge-view', function(config, statics) {
  function install() {
    JX.Stratcom.listen(
      ['click'],
      'jx-badge-view',
      function(e) {
        if (e.getNode('tag:a')) {
           // If the event has a 'tag:a' node on it, that means the user
           // either clicked a link or some other node inside a link.
           return;
         }

          var t = e.getNodeData('jx-badge-view');
          t.state = !t.state;
          for (var k in t.map) {
            JX.DOM.alterClass(JX.$(k), t.map[k], t.state);
          }
          e.kill();
     });

    return true;
  }

  statics.install = statics.install || install();
});
