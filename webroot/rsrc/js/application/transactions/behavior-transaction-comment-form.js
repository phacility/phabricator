/**
 * @provides javelin-behavior-phabricator-transaction-comment-form
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-request
 *           phabricator-shaped-request
 */

JX.behavior('phabricator-transaction-comment-form', function(config) {

  var form = JX.$(config.formID);

  var getdata = function() {
    var obj = JX.DOM.convertFormToDictionary(form);
    obj.__preview__ = 1;
    return obj;
  };

  var onresponse = function(response) {
    var panel = JX.$(config.panelID);
    if (!response.xactions.length) {
      JX.DOM.hide(panel);
    } else {
      JX.DOM.setContent(
        JX.$(config.timelineID),
        [
          JX.$H(response.spacer),
          JX.$H(response.xactions.join(response.spacer))
        ]);
      JX.DOM.show(panel);
    }
  };

  if (config.showPreview) {
    var request = new JX.PhabricatorShapedRequest(
      config.actionURI,
      onresponse,
      getdata);
    var trigger = JX.bind(request, request.trigger);
    JX.DOM.listen(form, 'keydown', null, trigger);
    var always_trigger = function() {
      new JX.Request(config.actionURI, onresponse)
        .setData(getdata())
        .send();
    };
    JX.DOM.listen(form, 'shouldRefresh', null, always_trigger);

    request.start();
  }
});
