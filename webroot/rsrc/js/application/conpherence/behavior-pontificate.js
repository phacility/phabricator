/**
 * @provides javelin-behavior-conpherence-pontificate
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-workflow
 *           javelin-stratcom
 *           conpherence-thread-manager
 */

JX.behavior('conpherence-pontificate', function() {

  var _sendMessage = function(e) {
    e.kill();
    var form = e.getNode('tag:form');
    var threadManager = JX.ConpherenceThreadManager.getInstance();
    threadManager.sendMessage(form, {});
  };

  JX.Stratcom.listen(
    ['submit', 'didSyntheticSubmit'],
    'conpherence-pontificate',
    _sendMessage);

  // Send on enter if the shift key is not held.
  JX.Stratcom.listen(
    'keydown',
    'conpherence-pontificate',
    function(e) {
      if (e.getSpecialKey() != 'return') {
        return;
      }

      var raw = e.getRawEvent();
      if (raw.shiftKey) {
        // If the shift key is pressed, let the browser write a newline into
        // the textarea.
        return;
      }

      // From here on, interpret this as a "send" action, not a literal
      // newline.
      e.kill();

      _sendMessage(e);
    });

});
