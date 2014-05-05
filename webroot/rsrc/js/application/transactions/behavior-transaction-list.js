/**
 * @provides javelin-behavior-phabricator-transaction-list
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-dom
 *           javelin-fx
 *           javelin-uri
 *           phabricator-textareautils
 */

JX.behavior('phabricator-transaction-list', function(config) {

  var list = JX.$(config.listID);
  var xaction_nodes = null;
  var next_anchor = config.nextAnchor;

  function get_xaction_nodes() {
    if (xaction_nodes === null) {
      xaction_nodes = {};
      var xactions = JX.DOM.scry(list, 'div', 'transaction');
      for (var ii = 0; ii < xactions.length; ii++) {
        xaction_nodes[JX.Stratcom.getData(xactions[ii]).phid] = xactions[ii];
      }
    }
    return xaction_nodes;
  }

  function ontransactions(response) {
    var fade_in = [];
    var first_new = null;

    var nodes = get_xaction_nodes();
    for (var phid in response.xactions) {
      var new_node = JX.$H(response.xactions[phid]).getFragment().firstChild;
      fade_in.push(new_node);

      if (nodes[phid]) {
        JX.DOM.replace(nodes[phid], new_node);
      } else {
        if (first_new === null) {
          first_new = new_node;
        }
        list.appendChild(new_node);

        // Add a spacer after new transactions.
        var spacer = JX.$H(response.spacer).getFragment().firstChild;
        list.appendChild(spacer);
        fade_in.push(spacer);

        next_anchor++;
      }
      nodes[phid] = new_node;
    }

    // Scroll to the first new transaction, if transactions were added.
    if (first_new) {
      JX.DOM.scrollTo(first_new);
    }

    // Make any new or updated transactions fade in.
    for (var ii = 0; ii < fade_in.length; ii++) {
      new JX.FX(fade_in[ii]).setDuration(500).start({opacity: [0, 1]});
    }
  }

  JX.Stratcom.listen(
    'click',
    [['transaction-edit'], ['transaction-remove']],
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
      new JX.Workflow(data.uri)
        .setData({ref: data.ref})
        .setHandler(function(r) {
          var textarea = JX.$(data.targetID);

          JX.DOM.scrollTo(textarea);

          var value = textarea.value;
          if (value.length) {
            value += "\n\n";
          }
          value += r.quoteText;
          value += "\n\n";
          textarea.value = value;

          JX.TextAreaUtils.setSelectionRange(
            textarea,
            textarea.value.length,
            textarea.value.length);
        })
        .start();
    });

  JX.Stratcom.listen(
    ['submit', 'didSyntheticSubmit'],
    'transaction-append',
    function(e) {
      var form = e.getTarget();
      if (JX.Stratcom.getData(form).objectPHID != config.objectPHID) {
        // This indicates there are several forms on the page, and the user
        // submitted a different one than the one we're in control of.
        return;
      }

      e.kill();

      JX.DOM.invoke(form, 'willSubmit');

      JX.Workflow.newFromForm(form, { anchor : next_anchor })
        .setHandler(function(response) {
          ontransactions(response);

          var e = JX.DOM.invoke(form, 'willClear');
          if (!e.getPrevented()) {
            var ii;
            var textareas = JX.DOM.scry(form, 'textarea');
            for (ii = 0; ii < textareas.length; ii++) {
              textareas[ii].value = '';
            }

            var inputs = JX.DOM.scry(form, 'input');
            for (ii = 0; ii < inputs.length; ii++) {
            switch (inputs[ii].type) {
              case 'password':
              case 'text':
                inputs[ii].value = '';
                break;
              case 'checkbox':
              case 'radio':
                inputs[ii].checked = false;
                break;
              }
            }

            var selects = JX.DOM.scry(form, 'select');
            var jj;
            for (ii = 0; ii < selects.length; ii++) {
              if (selects[ii].type == 'select-one') {
                selects[ii].selectedIndex = 0;
              } else {
               for (jj = 0; jj < selects[ii].options.length; jj++) {
                 selects[ii].options[jj].selected = false;
               }
              }
            }
          }
        })
        .start();

    });
});
