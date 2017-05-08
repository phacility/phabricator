/**
 * @provides phabricator-diff-changeset-list
 * @requires javelin-install
 * @javelin
 */

JX.install('DiffChangesetList', {

  construct: function() {

    var onload = JX.bind(this, this._ifawake, this._onload);
    JX.Stratcom.listen('click', 'differential-load', onload);

    var onmore = JX.bind(this, this._ifawake, this._onmore);
    JX.Stratcom.listen('click', 'show-more', onmore);
  },

  members: {
    _asleep: true,

    sleep: function() {
      this._asleep = true;
    },

    wake: function() {
      this._asleep = false;
    },

    isAsleep: function() {
      return this._asleep;
    },

    getChangesetForNode: function(node) {
      return JX.ChangesetViewManager.getForNode(node);
    },

    _ifawake: function(f) {
      // This function takes another function and only calls it if the
      // changeset list is awake, so we basically just ignore events when we
      // are asleep. This may move up the stack at some point as we do more
      // with Quicksand/Sheets.

      if (this.isAsleep()) {
        return;
      }

      return f.apply(this, [].slice.call(arguments, 1));
    },

    _onload: function(e) {
      var data = e.getNodeData('differential-load');

      // NOTE: We can trigger a load from either an explicit "Load" link on
      // the changeset, or by clicking a link in the table of contents. If
      // the event was a table of contents link, we let the anchor behavior
      // run normally.
      if (data.kill) {
        e.kill();
      }

      var node = JX.$(data.id);
      var changeset = this.getChangesetForNode(node);

      changeset.load();

      // TODO: Move this into Changeset.
      var routable = changeset.getRoutable();
      if (routable) {
        routable.setPriority(2000);
      }
    },

    _onmore: function(e) {
      e.kill();

      var node = e.getNode('differential-changeset');
      var changeset = this.getChangesetForNode(node);

      var data = e.getNodeData('show-more');
      var target = e.getNode('context-target');

      changeset.loadContext(data.range, target);
    }

  }

});
