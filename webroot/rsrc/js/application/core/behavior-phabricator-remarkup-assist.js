/**
 * @provides javelin-behavior-phabricator-remarkup-assist
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-dom
 *           phabricator-textareautils
 */

JX.behavior('phabricator-remarkup-assist', function(config) {

  function update(area, l, m, r) {
    // Replace the selection with the entire assisted text.
    JX.TextAreaUtils.setSelectionText(area, l + m + r);

    // Now, select just the middle part. For instance, if the user clicked
    // "B" to create bold text, we insert '**bold**' but just select the word
    // "bold" so if they type stuff they'll be editing the bold text.
    var r = JX.TextAreaUtils.getSelectionRange(area);
    JX.TextAreaUtils.setSelectionRange(
      area,
      r.start + l.length,
      r.start + l.length + m.length);
  }

  function assist(area, action) {
    // If the user has some text selected, we'll try to use that (for example,
    // if they have a word selected and want to bold it). Otherwise we'll insert
    // generic text.
    var sel = JX.TextAreaUtils.getSelectionText(area);
    var r = JX.TextAreaUtils.getSelectionRange(area);

    switch (action) {
      case 'b':
        update(area, '**', sel || 'bold text', '**');
        break;
      case 'i':
        update(area, '//', sel || 'italic text', '//');
        break;
      case 'tt':
        update(area, '`', sel || 'monospaced text', '`');
        break;
      case 'ul':
      case 'ol':
        var ch = (action == 'ol') ? '  # ' : '  - ';
        if (sel) {
          sel = sel.split("\n");
        } else {
          sel = ["List Item"];
        }
        sel = sel.join("\n" + ch);
        update(area, ((r.start == 0) ? "" : "\n\n") + ch, sel, "\n\n");
        break;
      case 'code':
        sel = sel || "foreach ($list as $item) {\n  work_miracles($item);\n}";
        sel = sel.split("\n");
        sel = "  " + sel.join("\n  ");
        update(area, ((r.start == 0) ? "" : "\n\n"), sel, "\n\n");
        break;
    }
  }

  JX.Stratcom.listen(
    ['click'],
    'remarkup-assist',
    function(e) {
      var data = e.getNodeData('remarkup-assist');
      if (!data.action) {
        return;
      }

      e.kill();

      var root = e.getNode('remarkup-assist-control');
      var area = JX.DOM.find(root, 'textarea');

      assist(area, data.action);
    });

});
