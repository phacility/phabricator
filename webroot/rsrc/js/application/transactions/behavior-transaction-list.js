/**
 * @provides javelin-behavior-phabricator-transaction-list
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-dom
 *           javelin-uri
 *           phabricator-textareautils
 */

JX.behavior('phabricator-transaction-list', function() {

  JX.Stratcom.listen(
    'click',
    [['transaction-edit'], ['transaction-remove'], ['transaction-raw']],
    function(e) {
      if (!e.isNormalClick()) {
        return;
      }

      e.prevent();

      var anchor = e.getNodeData('tag:a').anchor;
      var uri = JX.$U(window.location).setFragment(anchor);

      JX.Workflow.newFromLink(e.getNode('tag:a'))
        .setHandler(function() {
          // In most cases, `uri` is on the same page (just at a new anchor),
          // so we have to call reload() explicitly to get the browser to
          // refresh the page. It would be nice to just issue a server-side
          // redirect instead, but there isn't currently an easy way to do
          // that without complexity and/or a semi-open redirect.
          uri.go();
          window.location.reload();
        })
        .start();
    });

  JX.Stratcom.listen(
    'click',
    'transaction-quote',
    function(e) {
      e.prevent();

      var data = e.getNodeData('transaction-quote');
      var ref = data.ref || '';

      new JX.Workflow(data.uri)
        .setData({ref: ref})
        .setHandler(function(r) {
          var textarea = JX.$(data.targetID);

          JX.DOM.scrollTo(textarea);

          var value = textarea.value;
          if (value.length) {
            value += '\n\n';
          }
          value += r.quoteText;
          value += '\n\n';
          textarea.value = value;

          JX.TextAreaUtils.setSelectionRange(
            textarea,
            textarea.value.length,
            textarea.value.length);
        })
        .start();
    });

});
