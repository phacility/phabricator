/**
 * @provides javelin-behavior-global-drag-and-drop
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-uri
 *           javelin-mask
 *           phabricator-drag-and-drop-file-upload
 */

JX.behavior('global-drag-and-drop', function(config, statics) {
  if (!JX.PhabricatorDragAndDropFileUpload.isSupported()) {
    return;
  }

  function init() {
    statics.pending = 0;
    statics.files = [];
    statics.errors = false;
    statics.enabled = true;

    if (config.ifSupported) {
      JX.$(config.ifSupported).style.display = '';
    }

    var page = JX.$('phabricator-standard-page');
    statics.drop = new JX.PhabricatorDragAndDropFileUpload(page)
      .setURI(config.uploadURI)
      .setViewPolicy(config.viewPolicy)
      .setChunkThreshold(config.chunkThreshold);

    install_extra_listeners();

    statics.drop.start();

    return true;
  }

  function install_extra_listeners() {
    statics.drop.listen('didBeginDrag', function() {
      if (!statics.enabled) {
        return;
      }
      JX.Mask.show('global-upload-mask');
      JX.DOM.show(JX.$(config.instructions));
    });

    statics.drop.listen('didEndDrag', function() {
      if (!statics.enabled) {
        return;
      }
      JX.Mask.hide('global-upload-mask');
      JX.DOM.hide(JX.$(config.instructions));
    });

    statics.drop.listen('willUpload', function() {
      if (!statics.enabled) {
        return;
      }
      statics.pending++;
    });

    statics.drop.listen('didUpload', function(f) {
      if (!statics.enabled) {
        return;
      }
      statics.files.push(f);

      statics.pending--;
      if (statics.pending === 0 && !statics.errors) {
        // If whatever the user dropped in has finished uploading, send them to
        // their uploads.
        var uri;
        var is_submit = !!config.submitURI;

        if (is_submit) {
          uri = JX.$U(config.submitURI);
        } else {
          uri = JX.$U(config.browseURI);
        }

        var ids = [];
        for (var ii = 0; ii < statics.files.length; ii++) {
          ids.push(statics.files[ii].getID());
        }
        uri.setQueryParam('h', ids.join(','));

        statics.files = [];

        if (is_submit) {
          new JX.Workflow(uri)
            .start();
        } else {
          uri.go();
        }
      }
    });

    statics.drop.listen('didError', function() {
      if (!statics.enabled) {
        return;
      }
      statics.pending--;
      statics.errors = true;
    });

    JX.Stratcom.listen(
      'quicksand-redraw',
      null,
      function (e) {
        var data = e.getData();
        var toggle = data.newResponse.globalDragAndDrop;
        statics.enabled = toggle;
        statics.drop.setIsEnabled(toggle);
      });
  }

  statics.init = statics.init || init();

});
