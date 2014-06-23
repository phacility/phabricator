/**
 * @provides javelin-behavior-differential-add-reviewers-and-ccs
 * @requires javelin-behavior
 *           javelin-dom
 *           phabricator-prefab
 */

JX.behavior('differential-add-reviewers-and-ccs', function(config) {

  var dynamic = {};
  for (var k in config.dynamic) {
    var props = config.dynamic[k];
    props.id = k;

    var tokenizer = JX.Prefab.buildTokenizer(props).tokenizer;
    tokenizer.start();

    dynamic[k] = {
      row : JX.$(props.row),
      tokenizer : tokenizer,
      actions : props.actions,
      labels: props.labels
    };
  }

  JX.DOM.listen(
    JX.$(config.select),
    'change',
    null,
    function() {
      var v = JX.$(config.select).value;
      for (var k in dynamic) {
        if (dynamic[k].actions[v]) {
          JX.DOM.show(dynamic[k].row);
          if (dynamic[k].labels) {
            var label_node = JX.DOM.find(dynamic[k].row, 'label');
            if (label_node) {
              JX.DOM.setContent(label_node, dynamic[k].labels[v]);
            }
          }
          dynamic[k].tokenizer.refresh();
        } else {
          JX.DOM.hide(dynamic[k].row);
        }
      }
    });
});
