/**
 * @provides phabricator-diff-tree-view
 * @requires javelin-dom
 * @javelin
 */

JX.install('DiffTreeView', {

  construct: function() {
    this._keys = [];
    this._tree = this._newTreeNode(null, [], 0);
    this._nodes = {};
    this._paths = [];
  },

  members: {
    _node: null,
    _keys: null,
    _tree: null,
    _nodes: null,
    _dirty: false,
    _paths: null,
    _selectedPath: null,
    _focusedPath: null,

    getNode: function() {
      if (!this._node) {
        var attrs = {
          className: 'diff-tree-view'
        };

        this._node = JX.$N('ul', attrs);
      }

      if (this._dirty) {
        this.redraw();
      }

      return this._node;
    },

    addPath: function(path) {
      this._paths.push(path);

      var tree = this._getTree(this._tree, path.getPath(), 0);
      tree.pathObject = path;

      this._dirty = true;

      return this;
    },

    getPaths: function() {
      return this._paths;
    },

    setSelectedPath: function(path) {
      if (this._selectedPath) {
        this._selectedPath.setIsSelected(false);
        this._selectedPath = null;
      }

      if (path) {
        path.setIsSelected(true);
      }

      this._selectedPath = path;

      return this;
    },

    setFocusedPath: function(path) {
      if (this._focusedPath) {
        this._focusedPath.setIsFocused(false);
        this._focusedPath = null;
      }

      if (path) {
        path.setIsFocused(true);
      }

      this._focusedPath = path;

      return this;
    },

    redraw: function() {
      if (!this._dirty) {
        return;
      }
      this._dirty = false;

      var ii;

      // For nodes which don't have a path object yet, build one.
      var tree;
      var path;
      var trees = [];
      for (ii = 0; ii < this._keys.length; ii++) {
        var key = this._keys[ii];
        tree = this._nodes[key];
        path = tree.pathObject;

        if (!path) {
          path = new JX.DiffPathView()
            .setPath(tree.parts);

          path.getIcon()
            .setIcon('fa-folder-open-o')
            .setColor('grey');

          tree.pathObject = path;
        }

        trees.push(tree);
      }

      for (ii = 0; ii < trees.length; ii++) {
        tree = trees[ii];
        tree.displayRoot = null;
        tree.displayPath = null;
        tree.displayHide = false;
      }

      var child;
      for (ii = 0; ii < trees.length; ii++) {
        tree = trees[ii];

        if (tree.childCount !== 1) {
          continue;
        }

        for (var k in tree.children) {
          if (tree.children.hasOwnProperty(k)) {
            child = tree.children[k];
            break;
          }
        }

        if (child.pathObject.getChangeset()) {
          continue;
        }

        child.displayRoot = tree.displayRoot || tree;
      }

      for (ii = 0; ii < trees.length; ii++) {
        tree = trees[ii];

        if (!tree.displayRoot) {
          continue;
        }

        if (!tree.displayRoot.displayPath) {
          tree.displayRoot.displayPath = [
            tree.displayRoot.parts[tree.displayRoot.parts.length - 1]
          ];
        }

        tree.displayRoot.displayPath.push(tree.parts[tree.parts.length - 1]);
        tree.displayHide = true;
      }

      for (ii = 0; ii < trees.length; ii++) {
        tree = trees[ii];
        path = tree.pathObject;

        path.setHidden(!!tree.displayHide);

        if (tree.displayPath) {
          path.setDisplayPath(tree.displayPath.join('/'));
        } else {
          path.setDisplayPath(null);
        }
      }

      for (ii = 0; ii < trees.length; ii++) {
        tree = trees[ii];

        if (!tree.parent) {
          tree.depth = 0;
        } else {
          // If this node was collapsed into the parent node, don't increase
          // the tree depth.
          if (tree.displayHide) {
            tree.depth = tree.parent.depth;
          } else {
            tree.depth = tree.parent.depth + 1;
          }
        }

        path = tree.pathObject;

        if (tree.childCount > 0) {
          path.setIsDirectory(true);
        }

        path.setDepth((tree.depth - 1));
      }

      var nodes = [];
      for (ii = 0; ii < trees.length; ii++) {
        tree = trees[ii];
        nodes.push(tree.pathObject.getNode());
      }

      JX.DOM.setContent(this.getNode(), nodes);
    },

    _getTree: function(root, path, ii) {
      if (ii >= path.length) {
        return root;
      }

      var part = path[ii];

      if (!root.children.hasOwnProperty(part)) {
        root.children[part] = this._newTreeNode(root, path, ii);
        root.childCount++;
      }

      return this._getTree(root.children[part], path, ii + 1);
    },

    _newTreeNode: function(parent, path, ii) {
      var key;
      var parts;
      if (path.length) {
        parts = path.slice(0, ii + 1);
        key = parts.join('/');
        this._keys.push(key);
      } else {
        parts = [];
        key = null;
      }

      var node = {
        parent: parent,
        nodeKey: key,
        parts: parts,
        children: {},
        pathObject: null,
        childCount: 0,
        depth: 0
      };

      if (key !== null) {
        this._nodes[key] = node;
      }

      return node;
    }

  }

});
