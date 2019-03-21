/**
 * @requires javelin-behavior
 *           trigger-rule-editor
 *           trigger-rule
 *           trigger-rule-type
 * @provides javelin-behavior-trigger-rule-editor
 * @javelin
 */

JX.behavior('trigger-rule-editor', function(config) {
  var form_node = JX.$(config.formNodeID);
  var table_node = JX.$(config.tableNodeID);
  var create_node = JX.$(config.createNodeID);
  var input_node = JX.$(config.inputNodeID);

  var editor = new JX.TriggerRuleEditor(form_node)
    .setTableNode(table_node)
    .setCreateButtonNode(create_node)
    .setInputNode(input_node);

  editor.start();

  var ii;

  for (ii = 0; ii < config.types.length; ii++) {
    var type = JX.TriggerRuleType.newFromDictionary(config.types[ii]);
    editor.addType(type);
  }

  if (config.rules.length) {
    for (ii = 0; ii < config.rules.length; ii++) {
      var rule = JX.TriggerRule.newFromDictionary(config.rules[ii]);
      editor.addRule(rule);
    }
  } else {
    // If the trigger doesn't have any rules yet, add an empty rule to start
    // with, so the user doesn't have to click "New Rule".
    editor.addRule(editor.newRule());
  }

});
