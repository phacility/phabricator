/**
 * @provides phabricator-diff-inline
 * @requires javelin-dom
 * @javelin
 */

JX.install('DiffInline', {

  construct : function(row) {
    this._row = row;

    var data = JX.Stratcom.getData(row);
    this._hidden = data.hidden || false;

    // TODO: Get smarter about this once we do more editing, this is pretty
    // hacky.
    var comment = JX.DOM.find(row, 'div', 'differential-inline-comment');
    this._id = JX.Stratcom.getData(comment).id;
  },

  properties: {
    changeset: null
  },

  members: {
    _id: null,
    _row: null,
    _hidden: false,

    setHidden: function(hidden) {
      this._hidden = hidden;

      JX.DOM.alterClass(this._row, 'inline-hidden', this._hidden);

      var op;
      if (hidden) {
        op = 'hide';
      } else {
        op = 'show';
      }

      var inline_uri = this._getChangesetList().getInlineURI();
      var comment_id = this._id;

      new JX.Workflow(inline_uri, {op: op, ids: comment_id})
        .setHandler(JX.bag)
        .start();
    },

    _getChangesetList: function() {
      var changeset = this.getChangeset();
      return changeset.getChangesetList();
    }
  }

});
