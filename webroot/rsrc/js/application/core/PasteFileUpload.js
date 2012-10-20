/**
 * @requires javelin-install
 *           javelin-util
 *           javelin-request
 *           javelin-dom
 *           javelin-uri
 * @provides phabricator-paste-file-upload
 * @javelin
 */

JX.install('PhabricatorPasteFileUpload', {

  construct : function(node) {
    this._node = node;
  },

  events : ['willUpload', 'didUpload'],

  statics : {
    isSupported : function() {
      // TODO: Needs to check if event.clipboardData is available.
      // Works in Chrome, doesn't work in Firefox 10.
      return !!window.FileList;
    }
  },

  members : {
    _node : null,

    start : function() {
      JX.DOM.listen(
        this._node,
        'paste',
        null,
        JX.bind(this, function(e) {
          var clipboardData = e.getRawEvent().clipboardData;
          if (!clipboardData) {
            return;
          }
          for (var ii = 0; ii < clipboardData.types.length; ii++) {
            if (/^image\//.test(clipboardData.types[ii])) {
              var file = clipboardData.items[ii].getAsFile();

              this.invoke('willUpload', file);

              var up_uri = JX.$U(this.getURI())
                .setQueryParam('name', 'clipboard.png')
                .toString();

              new JX.Request(up_uri, JX.bind(this, function(r) {
                  this.invoke('didUpload', r);
                }))
                .setRawData(file)
                .send();

              e.kill();
              break;
            }
          }

        }));
    }
  },

  properties: {
    URI : null
  }
});
