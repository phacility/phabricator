/**
 * @provides javelin-behavior-conpherence-drag-and-drop-photo
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-workflow
 *           phabricator-drag-and-drop-file-upload
 */

JX.behavior('conpherence-drag-and-drop-photo', function(config) {

  var target = JX.$(config.target);
  var form_pane = JX.$(config.form_pane);

  function onupload(f) {
    var data = {
      'file_id' : f.getID(),
      'action' : 'metadata'
    };

    var form = JX.DOM.find(form_pane, 'form');
    var workflow = JX.Workflow.newFromForm(form, data);
    workflow.start();
  }

  if (JX.PhabricatorDragAndDropFileUpload.isSupported()) {
    var drop = new JX.PhabricatorDragAndDropFileUpload(target)
      .setURI(config.upload_uri);
    drop.listen('didBeginDrag', function() {
      JX.DOM.alterClass(target, config.activated_class, true);
    });
    drop.listen('didEndDrag', function() {
      JX.DOM.alterClass(target, config.activated_class, false);
    });
    drop.listen('didUpload', onupload);
    drop.start();
  }

});
