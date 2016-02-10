/**
 * @provides javelin-behavior-remarkup-preview
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           phabricator-shaped-request
 */

JX.behavior('remarkup-preview', function(config) {

  // Don't bother with any of this on mobile.
  if (JX.Device.getDevice() !== 'desktop') {
    return;
  }

  var preview = JX.$(config.previewID);
  var control = JX.$(config.controlID);

  var callback = function(r) {
    // This currently accepts responses from two controllers:
    // Old: PhabricatorMarkupPreviewController
    // New: PhabricatorApplicationTransactionRemarkupPreviewController
    // TODO: Swap everything to just the new controller.

    JX.DOM.setContent(preview, JX.$H(r.content || r));
  };

  var getdata = function() {
    return {
      text: control.value
    };
  };

  var request = new JX.PhabricatorShapedRequest(config.uri, callback, getdata);
  var trigger = JX.bind(request, request.trigger);

  JX.DOM.listen(control, 'keydown', null, trigger);
  request.start();

  trigger();
});
