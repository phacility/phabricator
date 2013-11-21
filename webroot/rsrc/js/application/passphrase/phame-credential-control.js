/**
 * @provides javelin-behavior-passphrase-credential-control
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-workflow
 *           javelin-util
 */

JX.behavior('passphrase-credential-control', function(config) {

  JX.Stratcom.listen(
    'click',
    'passphrase-credential-add',
    function(e) {
      var control = e.getNode('passphrase-credential-control');
      var data = e.getNodeData('passphrase-credential-control');

      new JX.Workflow('/passphrase/edit/?type=' + data.type)
        .setHandler(JX.bind(null, onadd, control))
        .start();

      e.kill();
    });

  function onadd(control, response) {
    var select = JX.DOM.find(control, 'select', 'passphrase-credential-select');

    for (var ii = 0; ii < select.options.length; ii++) {
      if (!select.options[ii].value) {
        select.remove(ii);
        break;
      }
    }

    select.add(
      JX.$N('option', {value: response.phid}, response.name),
      select.options[0] || null);

    select.value = response.phid;
    select.disabled = null;
  }

});
