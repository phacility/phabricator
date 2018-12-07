/**
 * @provides javelin-behavior-linked-container
 * @requires javelin-behavior javelin-dom
 */

JX.behavior('linked-container', function() {

  JX.Stratcom.listen(
    'click',
    'linked-container',
    function(e) {

      // If the user clicked some link inside the container, bail out and just
      // click the link.
      if (e.getNode('tag:a')) {
        return;
      }

      // If this is some sort of unusual click, bail out. Note that we'll
      // handle "Left Click" and "Command + Left Click" differently, below.
      if (!e.isLeftButton()) {
        return;
      }

      var container = e.getNode('linked-container');

      // Find the first link in the container. We're going to pretend the user
      // clicked it.
      var link = JX.DOM.scry(container, 'a')[0];
      if (!link) {
        return;
      }

      // If the click is a "Command + Left Click", change the target of the
      // link so we open it in a new tab.
      var is_command = !!e.getRawEvent().metaKey;
      if (is_command) {
        var old_target = link.target;
        link.target = '_blank';
        link.click();
        link.target = old_target;
      } else {
        link.click();
      }
    });

});
