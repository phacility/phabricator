/**
 * @provides javelin-behavior-maniphest-transaction-preview
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           phabricator-shaped-request
 */

JX.behavior('maniphest-transaction-preview', function(config) {

  var comments = JX.$(config.comments);

  var callback = function(r) {
    JX.DOM.setContent(JX.$(config.preview), JX.$H(r));
  };

  var getdata = function() {
    return {
      comments : comments.value
    };
  }

  var request = new JX.PhabricatorShapedRequest(config.uri, callback, getdata);
  var trigger = JX.bind(request, request.trigger);

  JX.DOM.listen(comments, 'keydown', null, trigger);

  request.start();
});
