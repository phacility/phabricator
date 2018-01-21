/**
 * @provides javelin-behavior-phui-selectable-list
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 */

JX.behavior('phui-selectable-list', function() {

  JX.Stratcom.listen('click', 'phui-oi-selectable', function(e) {
    if (!e.isNormalClick()) {
      return;
    }

    // If the user clicked a link, ignore it.
    if (e.getNode('tag:a')) {
      return;
    }

    var root = e.getNode('phui-oi-selectable');

    // If the user did not click the checkbox, pretend they did. This makes
    // the entire element a click target to make changing the selection set a
    // bit easier.
    if (!e.getNode('tag:input')) {
      var checkbox = getCheckbox(root);
      checkbox.checked = !checkbox.checked;

      e.kill();
    }

    setTimeout(JX.bind(null, redraw, root), 0);
  });

  function getCheckbox(root) {
    return JX.DOM.find(root, 'input');
  }

  function redraw(root) {
    var checkbox = getCheckbox(root);
    JX.DOM.alterClass(root, 'phui-oi-selected', !!checkbox.checked);
  }

});
