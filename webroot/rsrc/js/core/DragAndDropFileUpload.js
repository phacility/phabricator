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

  construct : function(target) {
    if (JX.DOM.isNode(target)) {
      this._node = target;
    } else {
      this._sigil = target;
    }
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
    },
    isPasteSupported : function() {
      // TODO: Needs to check if event.clipboardData is available.
      // Works in Chrome, doesn't work in Firefox 10.
      return !!window.FileList;
    }
  },

  members : {
    _node : null,
    _sigil: null,
    _depth : 0,
    _isEnabled: false,

    setIsEnabled: function(bool) {
      this._isEnabled = bool;
      return this;
    },

    getIsEnabled: function() {
      return this._isEnabled;
    },

    _updateDepth : function(delta) {
      if (this._depth === 0 && delta > 0) {
        this.invoke('didBeginDrag', this._getTarget());
      }

      this._depth += delta;

      if (this._depth === 0 && delta < 0) {
        this.invoke('didEndDrag', this._getTarget());
      }
    },

    _getTarget: function() {
      return this._target || this._node;
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

      // Firefox has some issues sometimes; implement this click handler so
      // the user can recover. See T5188.
      var on_click = JX.bind(this, function (e) {
        if (!this.getIsEnabled()) {
          return;
        }

        if (this._depth) {
          e.kill();
          // Force depth to 0.
          this._updateDepth(-this._depth);
        }
      });

      // We track depth so that the _node may have children inside of it and
      // not become unselected when they are dragged over.
      var on_dragenter = JX.bind(this, function(e) {
        if (!this.getIsEnabled()) {
          return;
        }

        if (!this._node) {
          var target = e.getNode(this._sigil);
          if (target !== this._target) {
            this._updateDepth(-this._depth);
            this._target = target;
          }
        }

        if (contains(this._getTarget(), e.getTarget())) {
          this._updateDepth(1);
        }

      });

      var on_dragleave = JX.bind(this, function(e) {
        if (!this.getIsEnabled()) {
          return;
        }

        if (!this._getTarget()) {
          return;
        }

        if (contains(this._getTarget(), e.getTarget())) {
          this._updateDepth(-1);
        }
      });

      var on_dragover = JX.bind(this, function(e) {
        if (!this.getIsEnabled()) {
          return;
        }

        // NOTE: We must set this, or Chrome refuses to drop files from the
        // download shelf.
        e.getRawEvent().dataTransfer.dropEffect = 'copy';
        e.kill();
      });

      var on_drop = JX.bind(this, function(e) {
        if (!this.getIsEnabled()) {
          return;
        }

        e.kill();

        var files = e.getRawEvent().dataTransfer.files;
        for (var ii = 0; ii < files.length; ii++) {
          this._sendRequest(files[ii]);
        }

        // Force depth to 0.
        this._updateDepth(-this._depth);
      });

      if (this._node) {
        JX.DOM.listen(this._node, 'click', null, on_click);
        JX.DOM.listen(this._node, 'dragenter', null, on_dragenter);
        JX.DOM.listen(this._node, 'dragleave', null, on_dragleave);
        JX.DOM.listen(this._node, 'dragover', null, on_dragover);
        JX.DOM.listen(this._node, 'drop', null, on_drop);
      } else {
        JX.Stratcom.listen('click', this._sigil, on_click);
        JX.Stratcom.listen('dragenter', this._sigil, on_dragenter);
        JX.Stratcom.listen('dragleave', this._sigil, on_dragleave);
        JX.Stratcom.listen('dragover', this._sigil, on_dragover);
        JX.Stratcom.listen('drop', this._sigil, on_drop);
      }

      if (JX.PhabricatorDragAndDropFileUpload.isPasteSupported() &&
          this._node) {
        JX.DOM.listen(
          this._node,
          'paste',
          null,
          JX.bind(this, function(e) {
            if (!this.getIsEnabled()) {
              return;
            }

            var clipboard = e.getRawEvent().clipboardData;
            if (!clipboard) {
              return;
            }

            // If there's any text on the clipboard, just let the event fire
            // normally, choosing the text over any images. See T5437 / D9647.
            var text = clipboard.getData('text/plain').toString();
            if (text.length) {
              return;
            }

            // Safari and Firefox have clipboardData, but no items. They
            // don't seem to provide a way to get image data directly yet.
            if (!clipboard.items) {
              return;
            }

            for (var ii = 0; ii < clipboard.items.length; ii++) {
              var item = clipboard.items[ii];
              if (!/^image\//.test(item.type)) {
                continue;
              }
              var spec = item.getAsFile();
              // pasted files don't have a name; see
              // https://code.google.com/p/chromium/issues/detail?id=361145
              if (!spec.name) {
                spec.name = 'pasted_file';
              }
              this._sendRequest(spec);
            }
          }));
      }

      this.setIsEnabled(true);
    },

    _sendRequest : function(spec) {
      var file = new JX.PhabricatorFileUpload()
        .setRawFileObject(spec)
        .setName(spec.name)
        .setTotalBytes(spec.size);

      var threshold = this.getChunkThreshold();
      if (threshold && (file.getTotalBytes() > threshold)) {
        // This is a large file, so we'll go through allocation so we can
        // pick up support for resume and chunking.
        this._allocateFile(file);
      } else {
        // If this file is smaller than the chunk threshold, skip the round
        // trip for allocation and just upload it directly.
        this._sendDataRequest(file);
      }
    },

    _allocateFile: function(file) {
      file
        .setStatus('allocate')
        .update();

      this.invoke('willUpload', file);

      var alloc_uri = this._getUploadURI(file)
        .setQueryParam('allocate', 1);

      new JX.Workflow(alloc_uri)
        .setHandler(JX.bind(this, this._didAllocateFile, file))
        .start();
    },

    _getUploadURI: function(file) {
      var uri = JX.$U(this.getURI())
        .setQueryParam('name', file.getName())
        .setQueryParam('length', file.getTotalBytes());

      if (this.getViewPolicy()) {
        uri.setQueryParam('viewPolicy', this.getViewPolicy());
      }

      if (file.getAllocatedPHID()) {
        uri.setQueryParam('phid', file.getAllocatedPHID());
      }

      return uri;
    },

    _didAllocateFile: function(file, r) {
      var phid = r.phid;
      var upload = r.upload;

      if (!upload) {
        if (phid) {
          this._completeUpload(file, r);
        } else {
          this._failUpload(file, r);
        }
        return;
      } else {
        if (phid) {
          // Start or resume a chunked upload.
          file.setAllocatedPHID(phid);
          this._loadChunks(file);
        } else {
          // Proceed with non-chunked upload.
          this._sendDataRequest(file);
        }
      }
    },

    _loadChunks: function(file) {
      file
        .setStatus('chunks')
        .update();

      var chunks_uri = this._getUploadURI(file)
        .setQueryParam('querychunks', 1);

      new JX.Workflow(chunks_uri)
        .setHandler(JX.bind(this, this._didLoadChunks, file))
        .start();
    },

    _didLoadChunks: function(file, r) {
      file.setChunks(r);
      this._uploadNextChunk(file);
    },

    _uploadNextChunk: function(file) {
      var chunks = file.getChunks();
      var chunk;
      for (var ii = 0; ii < chunks.length; ii++) {
        chunk = chunks[ii];
        if (!chunk.complete) {
          this._uploadChunk(file, chunk);
          break;
        }
      }
    },

    _uploadChunk: function(file, chunk, callback) {
      file
        .setStatus('upload')
        .update();

      var chunkup_uri = this._getUploadURI(file)
        .setQueryParam('uploadchunk', 1)
        .setQueryParam('__upload__', 1)
        .setQueryParam('byteStart', chunk.byteStart)
        .toString();

      var callback = JX.bind(this, this._didUploadChunk, file, chunk);

      var req = new JX.Request(chunkup_uri, callback);

      var seen_bytes = 0;
      var onprogress = JX.bind(this, function(progress) {
        file
          .addUploadedBytes(progress.loaded - seen_bytes)
          .update();

        seen_bytes = progress.loaded;
        this.invoke('progress', file);
      });

      req.listen('error', JX.bind(this, this._onUploadError, req, file));
      req.listen('uploadprogress', onprogress);

      var blob = file.getRawFileObject().slice(chunk.byteStart, chunk.byteEnd);

      req
        .setRawData(blob)
        .send();
    },

    _didUploadChunk: function(file, chunk, r) {
      file.didCompleteChunk(chunk);

      if (r.complete) {
        this._completeUpload(file, r);
      } else {
        this._uploadNextChunk(file);
      }
    },

    _sendDataRequest: function(file) {
      file
        .setStatus('uploading')
        .update();

      this.invoke('willUpload', file);

      var up_uri = this._getUploadURI(file)
        .setQueryParam('__upload__', 1)
        .toString();

      var onupload = JX.bind(this, function(r) {
        if (r.error) {
          this._failUpload(file, r);
        } else {
          this._completeUpload(file, r);
        }
      });

      var req = new JX.Request(up_uri, onupload);

      var onprogress = JX.bind(this, function(progress) {
        file
          .setTotalBytes(progress.total)
          .setUploadedBytes(progress.loaded)
          .update();

        this.invoke('progress', file);
      });

      req.listen('error', JX.bind(this, this._onUploadError, req, file));
      req.listen('uploadprogress', onprogress);

      req
        .setRawData(file.getRawFileObject())
        .send();
    },

    _completeUpload: function(file, r) {
      file
        .setID(r.id)
        .setPHID(r.phid)
        .setURI(r.uri)
        .setMarkup(r.html)
        .setStatus('done')
        .setTargetNode(this._getTarget())
        .update();

      this.invoke('didUpload', file);
    },

    _failUpload: function(file, r) {
      file
        .setStatus('error')
        .setError(r.error)
        .update();

      this.invoke('didError', file);
    },

    _onUploadError: function(req, file, error) {
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
    }

  },
  properties: {
    URI: null,
    activatedClass: null,
    viewPolicy: null,
    chunkThreshold: null
  }
});
