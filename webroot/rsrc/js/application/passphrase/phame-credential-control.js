/**
 * @provides javelin-behavior-passphrase-credential-control
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-util
 *           javelin-uri
 * @javelin
 */

JX.behavior('passphrase-credential-control', function() {

  JX.Stratcom.listen(
    'click',
    'passphrase-credential-add',
    function(e) {
      var control = e.getNode('passphrase-credential-control');
      var data = e.getNodeData('passphrase-credential-control');

      var uri = JX.$U('/passphrase/edit/');
      uri.setQueryParam('type', data.type);
      uri.setQueryParam('username', data.username);

      new JX.Workflow(uri)
        .setHandler(JX.bind(null, onadd, control))
        .start();

      e.kill();
    });

  function onadd(control, response) {
    var select = JX.DOM.find(control, 'select', 'passphrase-credential-select');

    var data = JX.Stratcom.getData(control);

    // If this allows the user to select "No Credential" (`allowNull`),
    // put the new credential in the menu below the "No Credential" option.

    // Otherwise, remove the "(No Existing Credentials)" if it exists and
    // put the new credential at the top.

    var target = 0;
    for (var ii = 0; ii < select.options.length; ii++) {
      if (!select.options[ii].value) {
        if (!data.allowNull) {
          select.remove(ii);
        } else {
          target = ii + 1;
        }
        break;
      }
    }

    select.add(
      JX.$N('option', {value: response.phid}, response.name),
      select.options[target] || null);

    select.value = response.phid;
    select.disabled = null;
  }

});
