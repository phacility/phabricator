/**
 * @requires javelin-install
 *           javelin-util
 *           javelin-dom
 *           javelin-typeahead-normalizer
 * @provides javelin-typeahead-source
 * @javelin
 */

JX.install('TypeaheadSource', {
  construct : function() {
    this._raw = {};
    this._lookup = {};
    this.setNormalizer(JX.TypeaheadNormalizer.normalize);
    this._excludeIDs = {};
  },

  events : ['waiting', 'resultsready', 'complete'],

  properties : {

    /**
     * Allows you to specify a function which will be used to normalize strings.
     * Strings are normalized before being tokenized, and before being sent to
     * the server. The purpose of normalization is to strip out irrelevant data,
     * like uppercase/lowercase, extra spaces, or punctuation. By default,
     * the @{JX.TypeaheadNormalizer} is used to normalize strings, but you may
     * want to provide a different normalizer, particularly if there are
     * special characters with semantic meaning in your object names.
     *
     * @param function
     */
    normalizer : null,

    /**
     * If a typeahead query should be processed before being normalized and
     * tokenized, specify a queryExtractor.
     *
     * @param function
     */
    queryExtractor : null,

    /**
     * Transformers convert data from a wire format to a runtime format. The
     * transformation mechanism allows you to choose an efficient wire format
     * and then expand it on the client side, rather than duplicating data
     * over the wire. The transformation is applied to objects passed to
     * addResult(). It should accept whatever sort of object you ship over the
     * wire, and produce a dictionary with these keys:
     *
     *    - **id**: a unique id for each object.
     *    - **name**: the string used for matching against user input.
     *    - **uri**: the URI corresponding with the object (must be present
     *      but need not be meaningful)
     *
     * You can also give:
     *    - **display**: the text or nodes to show in the DOM. Usually just the
     *      same as ##name##.
     *    - **tokenizable**: if you want to tokenize something other than the
     *      ##name##, for the typeahead to complete on, specify it here. A
     *      selected entry from the typeahead will still insert the ##name##
     *      into the input, but the ##tokenizable## field lets you complete on
     *      non-name things.
     *
     * The default transformer expects a three element list with elements
     * [name, uri, id]. It assigns the first element to both ##name## and
     * ##display##.
     *
     * @param function
     */
    transformer : null,

    /**
     * Configures the maximum number of suggestions shown in the typeahead
     * dropdown.
     *
     * @param int
     */
    maximumResultCount : 5,

    /**
     * Optional function which is used to sort results. Inputs are the input
     * string, the list of matches, and a default comparator. The function
     * should sort the list for display. This is the minimum useful
     * implementation:
     *
     *   function(value, list, comparator) {
     *     list.sort(comparator);
     *   }
     *
     * Alternatively, you may pursue more creative implementations.
     *
     * The `value` is a raw string; you can bind the datasource into the
     * function and use normalize() or tokenize() to parse it.
     *
     * The `list` is a list of objects returned from the transformer function,
     * see the `transformer` property. These are the objects in the list which
     * match the value.
     *
     * The `comparator` is a sort callback which implements sensible default
     * sorting rules (e.g., alphabetic order), which you can use as a fallback
     * if you just want to tweak the results (e.g., put some items at the top).
     *
     * The function is called after the user types some text, immediately before
     * the possible completion results are displayed to the user.
     *
     * @param function
     */
    sortHandler : null,

    /**
     * Optional function which is used to filter results before display. Inputs
     * are the input string and a list of matches. The function should
     * return a list of matches to display. This is the minimum useful
     * implementation:
     *
     *   function(value, list) {
     *     return list;
     *   }
     *
     * @param function
     */
    filterHandler : null

  },

  members : {
    _raw : null,
    _lookup : null,
    _excludeIDs : null,
    _changeListener : null,
    _startListener : null,

    bindToTypeahead : function(typeahead) {
      this._changeListener = typeahead.listen(
        'change',
        JX.bind(this, this.didChange)
      );
      this._startListener = typeahead.listen(
        'start',
        JX.bind(this, this.didStart)
      );
    },

    unbindFromTypeahead : function() {
      this._changeListener.remove();
      this._startListener.remove();
    },

    didChange : function() {
      return;
    },

    didStart : function() {
      return;
    },

    clearCache : function() {
      this._raw = {};
      this._lookup = {};
    },

    addExcludeID : function(id) {
      if (id) {
        this._excludeIDs[id] = true;
      }
    },

    removeExcludeID : function (id) {
      if (id) {
        delete this._excludeIDs[id];
      }
    },

    addResult : function(obj) {
      obj = (this.getTransformer() || this._defaultTransformer)(obj);

      if (obj.id in this._raw) {
        // We're already aware of this result. This will happen if someone
        // searches for "zeb" and then for "zebra" with a
        // TypeaheadRequestSource, for example, or the datasource just doesn't
        // dedupe things properly. Whatever the case, just ignore it.
        return;
      }

      if (__DEV__) {
        for (var k in {name : 1, id : 1, display : 1, uri : 1}) {
          if (!(k in obj)) {
            throw new Error(
              'JX.TypeaheadSource.addResult(): result must have ' +
              'properties \'name\', \'id\', \'uri\' and \'display\'.');
          }
        }
      }

      this._raw[obj.id] = obj;
      var t = this.tokenize(obj.tokenizable || obj.name);
      for (var jj = 0; jj < t.length; ++jj) {
        if (!this._lookup.hasOwnProperty(t[jj])) {
          this._lookup[t[jj]] = [];
        }
        this._lookup[t[jj]].push(obj.id);
      }
    },

    waitForResults : function() {
      this.invoke('waiting');
      return this;
    },


    /**
     * Get the raw state of a result by its ID. A number of other events and
     * mechanisms give a list of result IDs and limited additional data; if you
     * need to act on the full result data you can look it up here.
     *
     * @param scalar Result ID.
     * @return dict Corresponding raw result.
     */
    getResult : function(id) {
      return this._raw[id];
    },


    matchResults : function(value, partial) {

      // This table keeps track of the number of tokens each potential match
      // has actually matched. When we're done, the real matches are those
      // which have matched every token (so the value is equal to the token
      // list length).
      var match_count = {};

      // This keeps track of distinct matches. If the user searches for
      // something like "Chris C" against "Chris Cox", the "C" will match
      // both fragments. We need to make sure we only count distinct matches.
      var match_fragments = {};

      var matched = {};
      var seen = {};

      var query_extractor = this.getQueryExtractor();
      if (query_extractor) {
        value = query_extractor(value);
      }
      var t = this.tokenize(value);

      // Sort tokens by longest-first. We match each name fragment with at
      // most one token.
      t.sort(function(u, v) { return v.length - u.length; });

      for (var ii = 0; ii < t.length; ++ii) {
        // Do something reasonable if the user types the same token twice; this
        // is sort of stupid so maybe kill it?
        if (t[ii] in seen) {
          t.splice(ii--, 1);
          continue;
        }
        seen[t[ii]] = true;
        var fragment = t[ii];
        for (var name_fragment in this._lookup) {
          if (name_fragment.substr(0, fragment.length) === fragment) {
            if (!(name_fragment in matched)) {
              matched[name_fragment] = true;
            } else {
              continue;
            }
            var l = this._lookup[name_fragment];
            for (var jj = 0; jj < l.length; ++jj) {
              var match_id = l[jj];
              if (!match_fragments[match_id]) {
                match_fragments[match_id] = {};
              }
              if (!(fragment in match_fragments[match_id])) {
                match_fragments[match_id][fragment] = true;
                match_count[match_id] = (match_count[match_id] || 0) + 1;
              }
            }
          }
        }
      }

      var hits = [];
      for (var k in match_count) {
        if (match_count[k] == t.length && !this._excludeIDs[k]) {
          hits.push(k);
        }
      }

      this.filterAndSortHits(value, hits);

      var nodes = this.renderNodes(value, hits);
      this.invoke('resultsready', nodes, value, partial);
      if (!partial) {
        this.invoke('complete');
      }
    },

    filterAndSortHits : function(value, hits) {
      var objs = [];
      var ii;
      for (ii = 0; ii < hits.length; ii++) {
        objs.push(this._raw[hits[ii]]);
      }

      var default_comparator = function(u, v) {
         var key_u = u.sort || u.name;
         var key_v = v.sort || v.name;
         return key_u.localeCompare(key_v);
      };

      var filter_handler = this.getFilterHandler() || function(value, list) {
        return list;
      };

      objs = filter_handler(value, objs);

      var sort_handler = this.getSortHandler() || function(value, list, cmp) {
        list.sort(cmp);
      };

      sort_handler(value, objs, default_comparator);

      hits.splice(0, hits.length);
      for (ii = 0; ii < objs.length; ii++) {
        hits.push(objs[ii].id);
      }
    },

    renderNodes : function(value, hits) {
      var n = Math.min(this.getMaximumResultCount(), hits.length);
      var nodes = [];
      for (var kk = 0; kk < n; kk++) {
        nodes.push(this.createNode(this._raw[hits[kk]]));
      }
      return nodes;
    },

    createNode : function(data) {
      return JX.$N(
        'a',
        {
          sigil: 'typeahead-result',
          href: data.uri,
          name: data.name,
          rel: data.id,
          className: 'jx-result'
        },
        data.display
      );
    },

    normalize : function(str) {
      return this.getNormalizer()(str);
    },
    tokenize : function(str) {
      str = this.normalize(str);
      if (!str.length) {
        return [];
      }
      return str.split(/\s/g);
    },
    _defaultTransformer : function(object) {
      return {
        name : object[0],
        display : object[0],
        uri : object[1],
        id : object[2]
      };
    }
  }
});
