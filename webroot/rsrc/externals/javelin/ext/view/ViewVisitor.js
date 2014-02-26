/**
 * @provides javelin-view-visitor
 * @requires javelin-install
 *           javelin-util
 *
 * Add new behaviors to views without changing the view classes themselves.
 *
 * Allows you to register specific visitor functions for certain view classes.
 * If no visitor is registered for a view class, the default_visitor is used.
 * If no default_visitor is invoked, a no-op visitor is used.
 *
 * Registered visitors should be functions with signature
 * function(view, results_of_visiting_children) {}
 * Children are visited before their containing parents, and the return values
 * of the visitor on the children are passed to the parent.
 *
 */

JX.install('ViewVisitor', {
  construct: function(default_visitor) {
    this._visitors = {};
    this._default = default_visitor || JX.bag;
  },
  members: {
    _visitors: null,
    _default: null,
    register: function(cls, visitor) {
      this._visitors[cls] = visitor;
    },
    visit: function(view, children) {
      var visitor = this._visitors[cls] || this._default;
      return visitor(view, children);
    }
  }
});
