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
      var attrs = {
        href: this.getHref(),
        meta: { item: this },
        className: this.getSelected() ? 'phabricator-menu-item-selected' : null
      };

      if (this.getDisabled()) {
        return JX.$N('span', attrs, this.getName());
      } else {
        return JX.$N('a', attrs, this.getName());
      }
    },

    select : function() {
      this._action();
    }
  },

  properties : {
    name: '',
    href: '',
    disabled: false,
    selected: false
  }

});
