/**
 * @provides javelin-behavior-aphront-drag-and-drop-textarea
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-json
 *           phabricator-drag-and-drop-file-upload
 *           phabricator-textareautils
 */

JX.behavior('aphront-drag-and-drop-textarea', function(config) {

  var target = JX.$(config.target);

  var metadata_node = JX.$(config.remarkupMetadataID);
  var metadata_value = config.remarkupMetadataValue;

  function set_metadata(key, value) {
    metadata_value[key] = value;
    write_metadata();
  }

  function get_metadata(key, default_value) {
    if (metadata_value.hasOwnProperty(key)) {
      return metadata_value[key];
    }
    return default_value;
  }

  function write_metadata() {
    metadata_node.value = JX.JSON.stringify(metadata_value);
  }

  write_metadata();

  if (JX.PhabricatorDragAndDropFileUpload.isSupported()) {
    var drop = new JX.PhabricatorDragAndDropFileUpload(target)
      .setURI(config.uri)
      .setChunkThreshold(config.chunkThreshold);

    drop.listen('didBeginDrag', function() {
      JX.DOM.alterClass(target, config.activatedClass, true);
    });

    drop.listen('didEndDrag', function() {
      JX.DOM.alterClass(target, config.activatedClass, false);
    });

    drop.listen('didUpload', function(file) {
      JX.TextAreaUtils.insertFileReference(target, file);

      var phids = get_metadata('attachedFilePHIDs', []);
      phids.push(file.getPHID());
      set_metadata('attachedFilePHIDs', phids);
    });

    drop.start();
  }

});
