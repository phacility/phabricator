/**
 * @provides javelin-behavior-toggle-class
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 */

/**
 * Toggle CSS classes when an element is clicked. This behavior is activated
 * by adding the sigil `jx-toggle-class` to an element, and a key `map` to its
 * data. The `map` should be a map from element IDs to the classes that should
 * be toggled on them.
 *
 * Optionally, you may provide a `state` key to set the default state of the
 * element.
 */
JX.behavior('toggle-class', function(config, statics) {
  function install() {
    JX.Stratcom.listen(
      ['touchstart', 'mousedown'],
      'jx-toggle-class',
      function(e) {
        e.kill();

        var t = e.getNodeData('jx-toggle-class');
        t.state = !t.state;
        for (var k in t.map) {
          JX.DOM.alterClass(JX.$(k), t.map[k], t.state);
        }
      });

    // Swallow the regular click handler event so e.g. Quicksand
    // click handler doesn't get a hold of it
    JX.Stratcom.listen(
      ['click'],
      'jx-toggle-class',
      function(e) {
        e.kill();
      });

    return true;
  }

  statics.install = statics.install || install();
});
