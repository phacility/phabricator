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

});
