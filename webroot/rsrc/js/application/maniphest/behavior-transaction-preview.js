/**
 * @provides javelin-behavior-maniphest-transaction-preview
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-util
 *           javelin-json
 *           javelin-stratcom
 *           phabricator-shaped-request
 */

JX.behavior('maniphest-transaction-preview', function(config) {

  var comments = JX.$(config.comments);
  var action = JX.$(config.action);

  var callback = function(r) {
    var panel = JX.$(config.preview);
    var data = getdata();
    var hide = true;
    for (var field in data) {
      if (field == 'action') {
        continue;
      }
      if (data[field]) {
        hide = false;
      }
    }
    if (hide) {
      JX.DOM.hide(panel);
    } else {
      JX.DOM.setContent(panel, JX.$H(r));
      JX.DOM.show(panel);
    }
  };

  var getdata = function() {
    var selected = action.value;

    var value = null;
    try {
      var control = JX.$(config.map[selected]);
      var input = ([]
        .concat(JX.DOM.scry(control, 'select'))
        .concat(JX.DOM.scry(control, 'input')))[0];
      if (JX.DOM.isType(input, 'input')) {
        // Avoid reading 'value'(s) out of the tokenizer free text input.
        if (input.type != 'hidden') {
          value = null;
        // Get the tokenizer and all that delicious data
        } else {
          var tokenizer_dom = JX.$(config.tokenizers[selected].id);
          var tokenizer     = JX.Stratcom.getData(tokenizer_dom).tokenizer;
          value = JX.JSON.stringify(JX.keys(tokenizer.getTokens()));
        }
      } else {
        value = input.value;
      }
    } catch (_ignored_) {
      // Ignored.
    }

    return {
      comments : comments.value,
      action : selected,
      value : value || ''
    };
  };

  var request = new JX.PhabricatorShapedRequest(config.uri, callback, getdata);
  var trigger = JX.bind(request, request.trigger);

  JX.DOM.listen(comments, 'keydown', null, trigger);
  JX.DOM.listen(action, 'change', null, trigger);

  request.start();
});
