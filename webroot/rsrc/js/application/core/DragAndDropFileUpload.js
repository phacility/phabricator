/**
 * @requires javelin-install
 *           javelin-util
 *           javelin-request
 *           javelin-dom
 *           javelin-uri
 *           phabricator-file-upload
 * @provides phabricator-drag-and-drop-file-upload
 * @javelin
 */

JX.install('PhabricatorDragAndDropFileUpload', {

  construct : function(node) {
    this._node = node;
  },

  events : [
    'didBeginDrag',
    'didEndDrag',
    'willUpload',
    'progress',
    'didUpload',
    'didError'],

  statics : {
    isSupported : function() {
      // TODO: Is there a better capability test for this? This seems okay in
      // Safari, Firefox and Chrome.

      return !!window.FileList;
    }
  },

  members : {
    _node : null,
    _depth : 0,
    _updateDepth : function(delta) {
      if (this._depth == 0 && delta > 0) {
        JX.log('begin: ' + this._depth + ' @ ' + delta);
        this.invoke('didBeginDrag');
      }

      this._depth += delta;

      if (this._depth == 0 && delta < 0) {
        JX.log('end: ' + this._depth + ' @ ' + delta);
        this.invoke('didEndDrag');
      }
    },

    start : function() {

      // TODO: move this to JX.DOM.contains()?
      function contains(container, child) {
        do {
          if (child === container) {
            return true;
          }
          child = child.parentNode;
        } while (child);

        return false;
      }

      // We track depth so that the _node may have children inside of it and
      // not become unselected when they are dragged over.
      JX.DOM.listen(
        this._node,
        'dragenter',
        null,
        JX.bind(this, function(e) {
          if (contains(this._node, e.getTarget())) {
            this._updateDepth(1);
          }
        }));

      JX.DOM.listen(
        this._node,
        'dragleave',
        null,
        JX.bind(this, function(e) {
          if (contains(this._node, e.getTarget())) {
            this._updateDepth(-1);
          }
        }));

      JX.DOM.listen(
        this._node,
        'dragover',
        null,
        function(e) {
          e.kill();
        });

      JX.DOM.listen(
        this._node,
        'drop',
        null,
        JX.bind(this, function(e) {
          e.kill();

          var files = e.getRawEvent().dataTransfer.files;
          for (var ii = 0; ii < files.length; ii++) {
            this._sendRequest(files[ii]);
          }

          // Force depth to 0.
          this._updateDepth(-this._depth);
        }));
    },
    _sendRequest : function(spec) {
      var file = new JX.PhabricatorFileUpload()
        .setName(spec.name)
        .setTotalBytes(spec.size)
        .setStatus('uploading')
        .update();

      this.invoke('willUpload', file);

      var up_uri = JX.$U(this.getURI())
        .setQueryParam('name', file.getName())
        .setQueryParam('__upload__', 1)
        .toString();

      var onupload = JX.bind(this, function(r) {
        if (r.error) {
          file
            .setStatus('error')
            .setError(r.error)
            .update();

          this.invoke('didError', file);
        } else {
          file
            .setID(r.id)
            .setPHID(r.phid)
            .setURI(r.uri)
            .setMarkup(r.html)
            .setStatus('done')
            .update();

          this.invoke('didUpload', file);
        }
      });

      var req = new JX.Request(up_uri, onupload);

      var onerror = JX.bind(this, function(error) {
        file.setStatus('error');

        if (error) {
          file.setError(error.code + ': ' + error.info);
        } else {
          var xhr = req.getTransport();
          if (xhr.responseText) {
            file.setError('Server responded: ' + xhr.responseText);
          }
        }

        file.update();
        this.invoke('didError', file);
      });

      var onprogress = JX.bind(this, function(progress) {
        file
          .setTotalBytes(progress.total)
          .setUploadedBytes(progress.loaded)
          .update();

        this.invoke('progress', file);
      });

      req.listen('error', onerror);
      req.listen('uploadprogress', onprogress);

      req
        .setRawData(spec)
        .send();
    }
  },
  properties: {
    URI : null,
    activatedClass : null
  }
});
