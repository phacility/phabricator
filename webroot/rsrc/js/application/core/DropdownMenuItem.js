/**
 * @requires javelin-install
 *           javelin-dom
 * @provides phabricator-menu-item
 * @javelin
 */

JX.install('PhabricatorMenuItem', {

  construct : function(name, action) {
    this.setName(name);
    this._action = action;
  },

  members : {
    _action : null,

    render : function() {
      if (this.getDisabled()) {
        return JX.$N('span', this.getName());
      } else {
        var attrs = { href : '#', meta : { item : this } };
        return JX.$N('a', attrs, this.getName());
      }
    },

    select : function() {
      this._action();
    }
  },

  properties : {
    name : '',
    disabled : false
  }

});
