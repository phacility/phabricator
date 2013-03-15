/**
 * @provides javelin-behavior-releeph-request-state-change
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-util
 *           phabricator-keyboard-shortcut
 *           phabricator-notification
 */

JX.behavior('releeph-request-state-change', function(config) {
  var root = JX.DOM.find(document, 'div', 'releeph-request-header-list');

  function getRequestHeaderNodes() {
    return JX.DOM.scry(root, 'div', 'releeph-request-header');
  }

  /**
   * Keyboard navigation
   */
  var keynav_cursor = -1;
  var notification = new JX.Notification();

  function keynavJump(manager, delta) {
    // Calculate this everytime, because the DOM changes.
    var headers = getRequestHeaderNodes();
    keynav_cursor += delta;

    if (keynav_cursor < 0) {
      keynav_cursor = -1;
      window.scrollTo(0);
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
    for (ii in headers) {
      JX.DOM.alterClass(headers[ii], 'focus', ii == keynav_cursor);
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
    JX.DOM.find(header, 'a', 'hidden-link').click();
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

  new JX.KeyboardShortcut('g', "Open selected request's page in a new tab.")
    .setHandler(function(manager) {
      keynavNavigateToRequestPage();
    })
    .register();


  /**
   * AJAXy state changes for request buttons.
   */
  function request_action(node, url) {
    var request = new JX.Request(url, function(response) {
      if (config.reload) {
        window.location.reload();
      } else {
        var markup = JX.$H(response.markup);
        JX.DOM.replace(node, markup);
        keynavMarkup();
      }
    });

    request.send();
  }

  JX.Stratcom.listen(
    'click',
    'releeph-request-state-change',
    function(e) {
      var button = e.getNode('releeph-request-state-change');
      var node = e.getNode('releeph-request-header');
      var url = e.getNodeData('releeph-request-state-change');

      // If this button has no action, or we've already responded to the first
      // click...
      if (!url || button.disabled) {
        return;
      }

      // There's a race condition here though :(

      JX.DOM.alterClass(button, 'disabled', true);
      button.disabled = true;

      e.prevent();
      request_action(node, url);
    }
  );
});
