/**
 * @requires javelin-install
 *           javelin-dom
 * @provides phabricator-menu-item
 * @javelin
 */

JX.install('PhabricatorMenuItem', {

  construct : function(name, action, href) {
    this.setName(name);
    this.setHref(href || '#');
    this._action = action;
  },

  members : {
    _action : null,

    render : function() {
      if (this.getDisabled()) {
        return JX.$N('span', this.getName());
      } else {
        var attrs = { href : this.getHref(), meta : { item : this } };
        return JX.$N('a', attrs, this.getName());
      }
    },

    select : function() {
      this._action();
    }
  },

  properties : {
    name : '',
    href : '',
    disabled : false
  }

});
