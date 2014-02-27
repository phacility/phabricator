/**
 * A View is a composable wrapper on JX.$N, allowing abstraction of higher-order
 * views and a consistent pattern of parameterization. It is intended
 * to be used either directly or as a building block for a syntactic sugar layer
 * for concise expression of markup patterns.
 *
 * @provides javelin-view
 * @requires javelin-install
 *           javelin-util
 */
JX.install('View', {
  construct : function(attrs, children) {
    this._attributes = JX.copy({}, this.getDefaultAttributeValues());
    JX.copy(this._attributes, attrs);

    this._rawChildren = {};
    this._childKeys = [];

    if (children) {
      this.addChildren(JX.$AX(children));
    }

    this.setName(this.__class__.__readable__);
  },
  events: [
    'change'
  ],

  properties: {
    'name': null
  },

  members : {
    _attributes : null,
    _rawChildren : null,
    _childKeys: null, // keys of rawChildren, kept ordered.
    _nextChildKey: 0, // next key to use for a new child

    /*
     * Don't override.
     * TODO: Strongly typed attribute access (getIntAttr, getStringAttr...)?
     */
    getAttr : function(attrName) {
      return this._attributes[attrName];
    },

    /*
     * Don't override.
     */
    multisetAttr : function(attrs) {
      JX.copy(this._attributes, attrs);
      this.invoke('change');
      return this;
    },

    /*
     * Don't override.
     */
    setAttr : function(attrName, value) {
      this._attributes[attrName] = value;
      this.invoke('change');
      return this;
    },
    /*
     * Child views can override to specify default values for attributes.
     */
    getDefaultAttributeValues : function() {
      return {};
    },

    /**
     * Don't override.
     */
    getAllAttributes: function() {
      return JX.copy({}, this._attributes);
    },

    /**
     * Get the children. Don't override.
     */
    getChildren : function() {
      var result = [];
      var should_repack = false;

      var ii;
      var key;

      for (ii = 0; ii < this._childKeys.length; ii++) {
        key = this._childKeys[ii];
        if (this._rawChildren[key] === undefined) {
          should_repack = true;
        } else {
          result.push(this._rawChildren[key]);
        }
      }

      if (should_repack) {
        var new_child_keys = [];
        for (ii = 0; ii < this._childKeys.length; ii++) {
          key = this._childKeys[ii];
          if (this._rawChildren[key] !== undefined) {
            new_child_keys.push(key);
          }
        }

        this._childKeys = new_child_keys;
      }

      return result;
    },

    /**
     * Add children to the view. Returns array of removal handles.
     * Don't override.
     */
    addChildren : function(children) {
      var result = [];
      for (var ii = 0; ii < children.length; ii++) {
        result.push(this._addChild(children[ii]));
      }
      this.invoke('change');
      return result;
    },

    /**
     * Add a single child view to the view.
     * Returns a removal handle, i.e. an object that has a method remove(),
     * that removes the added child from the view.
     *
     * Don't override.
     */
    addChild: function(child) {
      var result = this._addChild(child);
      this.invoke('change');
      return result;
    },

    _addChild: function(child) {
      var key = this._nextChildKey++;
      this._rawChildren[key] = child;
      this._childKeys.push(key);

      return {
        remove: JX.bind(this, this._removeChild, key)
      };
    },

    _removeChild: function(child_key) {
      delete this._rawChildren[child_key];
      this.invoke('change');
    },

    /**
     * Accept visitors. This allows adding new behaviors to Views without
     * having to change View classes themselves.
     *
     * This implements a post-order traversal over the tree of views. Children
     * are processed before parents, and for convenience the results of the
     * visitor on the children are passed to it when processing the parent.
     *
     * The visitor parameter is a callable which receives two parameters.
     * The first parameter is the view to visit. The second parameter is an
     * array of the results of visiting the view's children.
     *
     * Don't override.
     */
    accept: function(visitor) {
      var results = [];
      var children = this.getChildren();
      for(var ii = 0; ii < children.length; ii++) {
        var result;
        if (children[ii].accept) {
          result = children[ii].accept(visitor);
        } else {
          result = children[ii];
        }
        results.push(result);
      }
      return visitor(this, results);
    },

    /**
     * Given the already-rendered children, return the rendered result of
     * this view.
     * By default, just pass the children through.
     */
    render: function(rendered_children) {
      return rendered_children;
    }
  }
});
