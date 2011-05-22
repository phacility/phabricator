/**
 * @requires javelin-install
 *           javelin-util
 *           javelin-request
 *           javelin-dom
 *           javelin-uri
 * @provides phabricator-drag-and-drop-file-upload
 * @javelin
 */

JX.install('PhabricatorDragAndDropFileUpload', {

  construct : function(node) {
    this._node = node;
  },

  events : ['willUpload', 'didUpload'],

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
      this._depth += delta;
      JX.DOM.alterClass(
        this._node,
        this.getActivatedClass(),
        (this._depth > 0));
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
            var file = files[ii];

            this.invoke('willUpload', file);

            var up_uri = JX.$U(this.getURI())
              .setQueryParam('name', file.name)
              .toString();

            new JX.Request(up_uri, JX.bind(this, function(r) {
                this.invoke('didUpload', r);
              }))
              .setFile(file)
              .send();
          }

          // Force depth to 0.
          this._updateDepth(-this._depth);
        }));
    }
  },
  properties: {
    URI : null,
    activatedClass : null
  }
});
