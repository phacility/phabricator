/**
 * @provides javelin-behavior-releeph-request-state-change
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-util
 *           phabricator-keyboard-shortcut
 */

JX.behavior('releeph-request-state-change', function() {
  function getRequestHeaderNodes() {
    return JX.DOM.scry(document.body, 'div', 'releeph-request-box');
  }

  var keynav_cursor = -1;

  function keynavJump(manager, delta) {
    // Calculate this everytime, because the DOM changes.
    var headers = getRequestHeaderNodes();
    keynav_cursor += delta;

    if (keynav_cursor < 0) {
      keynav_cursor = -1;
      JX.DOM.scrollToPosition(0, 0);
      keynavMarkup();
      return;
    }

    if (keynav_cursor >= headers.length) {
      keynav_cursor = headers.length - 1;
    }

    var focus = headers[keynav_cursor];
    manager.scrollTo(focus);

    keynavMarkup();
  }

  function keynavMarkup() {
    var headers = getRequestHeaderNodes();
    for (var k in headers) {
      JX.DOM.alterClass(headers[k], 'focus', k == keynav_cursor);
    }
  }

  function keynavAction(manager, action_name) {
    var headers = getRequestHeaderNodes();
    var header = headers[keynav_cursor];

    if (keynav_cursor < 0) {
      return;
    }

    var sigil = action_name;
    var button = JX.DOM.find(header, 'a', sigil);
    if (button) {
      button.click();
    }
  }

  function keynavNavigateToRequestPage() {
    var headers = getRequestHeaderNodes();
    var header = headers[keynav_cursor];
    window.open(JX.Stratcom.getData(header).uri);
  }

  new JX.KeyboardShortcut('j', 'Jump to next request.')
    .setHandler(function(manager) {
      keynavJump(manager, +1);
    })
    .register();

  new JX.KeyboardShortcut('k', 'Jump to previous request.')
    .setHandler(function(manager) {
      keynavJump(manager, -1);
    })
    .register();

  new JX.KeyboardShortcut('a', 'Approve the selected request.')
    .setHandler(function(manager) {
      keynavAction(manager, 'want');
    })
    .register();

  new JX.KeyboardShortcut('r', 'Reject the selected request.')
    .setHandler(function(manager) {
      keynavAction(manager, 'pass');
    })
    .register();

  new JX.KeyboardShortcut(
    ['g', 'return'],
    'Open selected request\'s page in a new tab.')
    .setHandler(function() {
      keynavNavigateToRequestPage();
    })
    .register();

  function onresponse(box, response) {
    JX.DOM.replace(box, JX.$H(response.markup));
    keynavMarkup();
  }

  JX.Stratcom.listen(
    'click',
    'releeph-request-state-change',
    function(e) {
      e.kill();

      var box = e.getNode('releeph-request-box');
      var link = e.getNode('releeph-request-state-change');

      box.style.opacity = '0.5';

      JX.Workflow.newFromLink(link)
        .setData({render: true})
        .setHandler(JX.bind(null, onresponse, box))
        .start();
    });
});
