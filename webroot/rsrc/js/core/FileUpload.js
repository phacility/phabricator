/**
 * @requires javelin-install
 *           javelin-dom
 *           phabricator-notification
 * @provides phabricator-file-upload
 * @javelin
 */

JX.install('PhabricatorFileUpload', {

  construct : function() {
    this._notification = new JX.Notification();
  },

  properties : {
    name: null,
    totalBytes: null,
    uploadedBytes: null,
    rawFileObject: null,
    allocatedPHID: null,
    ID: null,
    PHID: null,
    URI: null,
    status: null,
    markup: null,
    error: null
  },

  members : {
    _notification : null,
    _chunks: null,
    _isResume: false,

    addUploadedBytes: function(bytes) {
      var uploaded = this.getUploadedBytes();
      this.setUploadedBytes(uploaded + bytes);
      return this;
    },

    setChunks: function(chunks) {
      var chunk;
      for (var ii = 0; ii < chunks.length; ii++) {
        chunk = chunks[ii];
        if (chunk.complete) {
          this.addUploadedBytes(chunk.byteEnd - chunk.byteStart);
          this._isResume = true;
        }
      }

      this._chunks = chunks;

      return this;
    },

    getChunks: function() {
      return this._chunks;
    },

    getRemainingChunks: function() {
      var chunks = this.getChunks();

      var result = [];
      for (var ii = 0; ii < chunks.length; ii++) {
        if (!chunks[ii].complete) {
          result.push(chunks[ii]);
        }
      }

      return result;
    },

    didCompleteChunk: function(chunk) {
      var chunks = this.getRemainingChunks();
      for (var ii = 0; ii < chunks.length; ii++) {
        if (chunks[ii].byteStart == chunk.byteStart) {
          if (chunks[ii].byteEnd == chunk.byteEnd) {
            if (!chunks[ii].complete) {
              chunks[ii].complete = true;
            }
            break;
          }
        }
      }

      return this;
    },

    update : function() {
      if (!this._notification) {
        return;
      }

      this._notification
        .setDuration(0)
        .show();

      var content;

      // TODO: This stuff needs some work for translations.

      switch (this.getStatus()) {
        case 'done':
          var link = JX.$N('a', {href: this.getURI()}, 'F' + this.getID());

          content = [
            JX.$N('strong', {}, ['Upload Complete (', link, ')']),
            JX.$N('br'),
            this.getName()
          ];

          this._notification
            .setContent(content)
            .alterClassName('jx-notification-done', true)
            .setDuration(12000);
          this._notification = null;
          break;
        case 'error':
          content = [
            JX.$N('strong', {}, 'Upload Failure'),
            JX.$N('br'),
            this.getName(),
            JX.$N('br'),
            JX.$N('br'),
            this.getError()
          ];

          this._notification
            .setContent(content)
            .alterClassName('jx-notification-error', true);
          this._notification = null;
          break;
        case 'allocate':
          content = 'Allocating "' + this.getName() + '"...';
          this._notification
            .setContent(content);
          break;
        case 'chunks':
          content = 'Loading chunks for "' + this.getName() + '"...';
          this._notification
            .setContent(content);
          break;
        default:
          var info = '';
          if (this.getTotalBytes()) {
            var p = this._renderPercentComplete();
            var f = this._renderFileSize();
            info = p + ' of ' + f;
          }

          var head;
          if (this._isResume) {
            head = 'Resuming:';
          } else if (this._chunks) {
            head = 'Uploading chunks:';
          } else {
            head = 'Uploading:';
          }

          info = [
            JX.$N('strong', {}, this.getName()),
            JX.$N('br'),
            head + ' ' + info];

          this._notification
            .setContent(info);
          break;
      }

      return this;
    },
    _renderPercentComplete : function() {
      if (!this.getTotalBytes()) {
        return null;
      }
      var ratio = this.getUploadedBytes() / this.getTotalBytes();
      return parseInt(100 * ratio, 10) + '%';
    },
    _renderFileSize : function() {
      if (!this.getTotalBytes()) {
        return null;
      }

      var s = 3;
      var n = this.getTotalBytes();
      while (s && n >= 1000) {
        n = Math.round(n / 100);
        n = n / 10;
        s--;
      }

      s = ['GB', 'MB', 'KB', 'bytes'][s];
      return n + ' ' + s;
    }
  }

});
