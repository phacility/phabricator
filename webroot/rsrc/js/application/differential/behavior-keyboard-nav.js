/**
 * @provides javelin-behavior-differential-keyboard-navigation
 * @requires javelin-behavior
 *           javelin-dom
 *           phabricator-keyboard-shortcut
 */

JX.behavior('differential-keyboard-navigation', function(config) {

  var cursor = null;
  var changesets;

  function init() {
    if (changesets) {
      return;
    }
    changesets = JX.DOM.scry(document.body, 'div', 'differential-changeset');
  }

  function jump(manager, delta) {
    init();

    if (cursor === null) {
      cursor = -1;
    }

    cursor = (cursor + changesets.length + delta) % changesets.length;

    var selected = changesets[cursor];

    manager.scrollTo(selected);
    manager.focusOn(selected);
  }

  new JX.KeyboardShortcut('j', 'Jump to next change.')
    .setHandler(function(manager) {
      jump(manager, 1);
    })
    .register();

  new JX.KeyboardShortcut('k', 'Jump to previous change.')
    .setHandler(function(manager) {
      jump(manager, -1);
    })
    .register();

});

