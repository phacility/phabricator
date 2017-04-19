/**
 * @provides javelin-diffusion-locate-file-source
 * @requires javelin-install
 *           javelin-dom
 *           javelin-typeahead-preloaded-source
 *           javelin-util
 * @javelin
 */

JX.install('DiffusionLocateFileSource', {

  extend: 'TypeaheadPreloadedSource',

  construct: function(uri) {
    JX.TypeaheadPreloadedSource.call(this, uri);
    this.cache = {};
  },

  members: {
    tree: null,
    limit: 20,
    cache: null,

    ondata: function(results) {
      this.tree = results.tree;

      if (this.lastValue !== null) {
        this.matchResults(this.lastValue);
      }

      this.setReady(true);
    },


    /**
     * Match a query and show results in the typeahead.
     */
    matchResults: function(value, partial) {
      // For now, just pretend spaces don't exist.
      var search = value.toLowerCase();
      search = search.replace(' ', '');

      var paths = this.findResults(search);

      var nodes = [];
      for (var ii = 0; ii < paths.length; ii++) {
        var path = paths[ii];
        var name = [];
        name.push(path.path.substr(0, path.pos));
        name.push(
          JX.$N('strong', {}, path.path.substr(path.pos, path.score)));

        var pos = path.score;
        var lower = path.path.toLowerCase();
        for (var jj = path.pos + path.score; jj < path.path.length; jj++) {
          if (lower.charAt(jj) == search.charAt(pos)) {
            pos++;
            name.push(JX.$N('strong', {}, path.path.charAt(jj)));
            if (pos == search.length) {
              break;
            }
          } else {
            name.push(path.path.charAt(jj));
          }
        }

        if (jj < path.path.length - 1 ) {
          name.push(path.path.substr(jj + 1));
        }

        var attr = {
          className: 'visual-only phui-icon-view phui-font-fa fa-file'
        };
        var icon = JX.$N('span', attr, '');

        nodes.push(
          JX.$N(
            'a',
            {
              sigil: 'typeahead-result',
              className: 'jx-result diffusion-locate-file',
              ref: path.path
            },
            [icon, name]));
      }

      this.invoke('resultsready', nodes, value);
      if (!partial) {
        this.invoke('complete');
      }
    },


    /**
     * Find the results matching a query.
     */
    findResults: function(search) {
      if (!search.length) {
        return [];
      }

      // We know that the results for "abc" are always a subset of the results
      // for "a" and "ab" -- and there's a good chance we already computed
      // those result sets. Find the longest cached result which is a prefix
      // of the search query.
      var best = 0;
      var start = this.tree;
      for (var k in this.cache) {
        if ((k.length <= search.length) &&
            (k.length > best) &&
            (search.substr(0, k.length) == k)) {
          best = k.length;
          start = this.cache[k];
        }
      }

      var matches;
      if (start === null) {
        matches = null;
      } else {
        matches = this.matchTree(start, search, 0);
      }

      // Save this tree in cache; throw the cache away after a few minutes.
      if (!(search in this.cache)) {
        this.cache[search] = matches;
        setTimeout(
          JX.bind(this, function() { delete this.cache[search]; }),
          1000 * 60 * 5);
      }

      if (!matches) {
        return [];
      }

      var paths = [];
      this.buildPaths(matches, paths, '', search, []);

      paths.sort(
        function(u, v) {
          if (u.score != v.score) {
            return (v.score - u.score);
          }

          if (u.pos != v.pos) {
            return (u.pos - v.pos);
          }

          return ((u.path > v.path) ? 1 : -1);
        });

      var num =  Math.min(paths.length, this.limit);
      var results = [];
      for (var ii = 0; ii < num; ii++) {
        results.push(paths[ii]);
      }

      return results;
    },


    /**
     * Select the subtree that matches a query.
     */
    matchTree: function(tree, value, pos) {
      var matches = null;
      for (var k in tree) {
        var p = pos;

        if (p != value.length) {
          p = this.matchString(k, value, pos);
        }

        var result;
        if (p == value.length) {
          result = tree[k];
        } else {
          if (tree == 1) {
            continue;
          } else {
            result = this.matchTree(tree[k], value, p);
            if (!result) {
              continue;
            }
          }
        }

        if (!matches) {
          matches = {};
        }
        matches[k] = result;
      }

      return matches;
    },


    /**
     * Look for the needle in a string, returning how much of it was found.
     */
    matchString: function(haystack, needle, pos) {
      var str = haystack.toLowerCase();
      var len = str.length;
      for (var ii = 0; ii < len; ii++) {
        if (str.charAt(ii) == needle.charAt(pos)) {
          pos++;
          if (pos == needle.length) {
            break;
          }
        }
      }
      return pos;
    },


    /**
     * Flatten a tree into paths.
     */
    buildPaths: function(matches, paths, prefix, search) {
      var first = search.charAt(0);

      for (var k in matches) {
        if (matches[k] == 1) {
          var path = prefix + k;
          var lower = path.toLowerCase();

          var best = 0;
          var pos = 0;
          for (var jj = 0; jj < lower.length; jj++) {
            if (lower.charAt(jj) != first) {
              continue;
            }

            var score = this.scoreMatch(lower, jj, search);
            if (score == -1) {
              break;
            }

            if (score > best) {
              best = score;
              pos = jj;
              if (best == search.length) {
                break;
              }
            }
          }

          paths.push({
            path: path,
            score: best,
            pos: pos
          });

        } else {
          this.buildPaths(matches[k], paths, prefix + k, search);
        }
      }
    },


    /**
     * Score a matching string by finding the longest prefix of the search
     * query it contains continguously.
     */
    scoreMatch: function(haystack, haypos, search) {
      var pos = 0;
      for (var ii = haypos; ii < haystack.length; ii++) {
        if (haystack.charAt(ii) == search.charAt(pos)) {
          pos++;
          if (pos == search.length) {
            return pos;
          }
        } else {
          ii++;
          break;
        }
      }

      var rem = pos;
      for (/* keep going */; ii < haystack.length; ii++) {
        if (haystack.charAt(ii) == search.charAt(rem)) {
          rem++;
          if (rem == search.length) {
            return pos;
          }
        }
      }

      return -1;
    }

  }
});
