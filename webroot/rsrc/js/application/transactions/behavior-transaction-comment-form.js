/**
 * @provides javelin-behavior-phabricator-transaction-comment-form
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-fx
 *           javelin-request
 *           phabricator-shaped-request
 */

JX.behavior('phabricator-transaction-comment-form', function(config) {

  var form = JX.$(config.formID);

  JX.DOM.listen(form, 'willSubmit', null, function (e) {
    e.kill();
    var preview = JX.$(config.panelID);
    preview.style.opacity = 0.5;
  });
  JX.DOM.listen(form, 'willClear', null, function(e) {
    JX.$(config.commentID).value = '';
    var preview = JX.$(config.panelID);
    new JX.FX(preview)
      .setDuration(500)
      .then(function () {
        new JX.FX(preview).setDuration(1000).start({opacity: [0, 1]});
      })
      .start({opacity: [0.5, 0]});
  });

  var getdata = function() {
    var obj = JX.DOM.convertFormToDictionary(form);
    obj.__preview__ = 1;

    if (config.draftKey) {
      obj.__draft__ = config.draftKey;
    }

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
          JX.$H(response.xactions.join(response.spacer)),
          JX.$H(response.spacer)
        ]);
      JX.DOM.show(panel);
    }
  };

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
});
