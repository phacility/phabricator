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
    name : null,
    totalBytes : null,
    uploadedBytes : null,
    ID : null,
    PHID : null,
    URI : null,
    status : null,
    markup : null,
    error : null
  },

  members : {
    _notification : null,

    update : function() {
      if (!this._notification) {
        return;
      }

      this._notification
        .setDuration(0)
        .show();

      switch (this.getStatus()) {
        case 'done':
          var link = JX.$N('a', {href: this.getURI()}, 'F' + this.getID());

          var content = [
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
          var content = [
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
        default:
          var info = '';
          if (this.getTotalBytes()) {
            var p = this._renderPercentComplete();
            var f = this._renderFileSize();
            info = ' (' + p + ' of ' + f + ')';
          }

          info = 'Uploading "' + this.getName() + '"' + info + '...';

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
      return parseInt(100 * ratio) + '%';
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

