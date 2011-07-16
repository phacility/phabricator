/**
 * @provides phabricator-prefab
 * @requires javelin-install
 *           javelin-util
 *           javelin-dom
 * @javelin
 */

/**
 * Utilities for client-side rendering (the greatest thing in the world).
 */
JX.install('Prefab', {

  statics : {
    renderSelect : function(map, selected, attrs) {
      var select = JX.$N('select', attrs || {});
      for (var k in map) {
        select.options[select.options.length] = new Option(map[k], k);
        if (k == selected) {
          select.value = k;
        }
      }
      select.value = select.value || JX.keys(map)[0];
      return select;
    }
  }

});
