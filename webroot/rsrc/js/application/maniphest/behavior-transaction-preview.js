/**
 * @provides javelin-behavior-maniphest-transaction-preview
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           phabricator-shaped-request
 */

JX.behavior('maniphest-transaction-preview', function(config) {

  var comments = JX.$(config.comments);
  var action = JX.$(config.action);

  var callback = function(r) {
    JX.DOM.setContent(JX.$(config.preview), JX.$H(r));
  };

  var getdata = function() {
    var selected = action.value;

    var value = null;
    try {
      var control = JX.$(config.map[selected]);
      var input = ([]
        .concat(JX.DOM.scry(control, 'select'))
        .concat(JX.DOM.scry(control, 'input')))[0];
      value = input.value;
      if (JX.DOM.isType(input, 'input') && input.type != 'hidden') {
        // Avoid reading 'value' out of the tokenizer free text input.
        value = null;
      }
    } catch (_ignored_) {
      // Ignored.
    }

    return {
      comments : comments.value,
      action : selected,
      value : value || ''
    };
  }

  var request = new JX.PhabricatorShapedRequest(config.uri, callback, getdata);
  var trigger = JX.bind(request, request.trigger);

  JX.DOM.listen(comments, 'keydown', null, trigger);
  JX.DOM.listen(action, 'change', null, trigger);

  request.start();
});
