/**
 * @provides javelin-behavior-maniphest-transaction-drag-and-drop
 * @requires javelin-behavior
 *           javelin-dom
 *           phabricator-drag-and-drop-file-upload
 */

JX.behavior('maniphest-transaction-drag-and-drop', function(config) {

  var files = [];

  var drop = new JX.PhabricatorDragAndDropFileUpload(JX.$(config.target))
    .setActivatedClass(config.activatedClass)
    .setURI(config.uri);

  drop.listen('didUpload', function(f) {
    files.push(f);
    redraw();
  });

  drop.start();

  function redraw() {
    var items = [];
    for (var ii = 0; ii < files.length; ii++) {
      items.push(JX.$N('div', {}, files[ii].name));
      items.push(JX.$N(
        'input',
        {
          type: "hidden",
          name: "files[" + files[ii].phid + "]",
          value: files[ii].phid
        }));
    }
    JX.DOM.setContent(JX.$(config.list), items);
  }

});

