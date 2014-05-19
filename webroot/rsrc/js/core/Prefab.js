/**
 * @provides phabricator-prefab
 * @requires javelin-install
 *           javelin-util
 *           javelin-dom
 *           javelin-typeahead
 *           javelin-tokenizer
 *           javelin-typeahead-preloaded-source
 *           javelin-typeahead-ondemand-source
 *           javelin-dom
 *           javelin-stratcom
 *           javelin-util
 * @javelin
 */

/**
 * Utilities for client-side rendering (the greatest thing in the world).
 */
JX.install('Prefab', {

  statics : {
    renderSelect : function(map, selected, attrs) {
      var select = JX.$N('select', attrs || {});
      for (var k in map) {
        select.options[select.options.length] = new Option(map[k], k);
        if (k == selected) {
          select.value = k;
        }
      }
      select.value = select.value || JX.keys(map)[0];
      return select;
    },

    /**
     * Build a Phabricator tokenizer out of a configuration with application
     * sorting, datasource and placeholder rules.
     *
     *   - `id` Root tokenizer ID (alternatively, pass `root`).
     *   - `root` Root tokenizer node (replaces `id`).
     *   - `src` Datasource URI.
     *   - `ondemand` Optional, use an ondemand source.
     *   - `value` Optional, initial value.
     *   - `limit` Optional, token limit.
     *   - `placeholder` Optional, placeholder text.
     *   - `username` Optional, username to sort first (i.e., viewer).
     *   - `icons` Optional, map of icons.
     *
     */
    buildTokenizer : function(config) {
      config.icons = config.icons || {};

      var root;

      try {
        root = config.root || JX.$(config.id);
      } catch (ex) {
        // If the root element does not exist, just return without building
        // anything. This happens in some cases -- like Conpherence -- where we
        // may load a tokenizer but not put it in the document.
        return;
      }

      var datasource;

      // Default to an ondemand source if no alternate configuration is
      // provided.
      var ondemand = true;
      if ('ondemand' in config) {
        ondemand = config.ondemand;
      }

      if (ondemand) {
        datasource = new JX.TypeaheadOnDemandSource(config.src);
      } else {
        datasource = new JX.TypeaheadPreloadedSource(config.src);
      }

      // Sort results so that the viewing user always comes up first; after
      // that, prefer unixname matches to realname matches.

      var sort_handler = function(value, list, cmp) {
        var priority_hits = {};
        var self_hits     = {};

        var tokens = this.tokenize(value);

        for (var ii = 0; ii < list.length; ii++) {
          var item = list[ii];
          if (!item.priority) {
            continue;
          }

          if (config.username && item.priority == config.username) {
            self_hits[item.id] = true;
          }

          for (var jj = 0; jj < tokens.length; jj++) {
            if (item.priority.substr(0, tokens[jj].length) == tokens[jj]) {
              priority_hits[item.id] = true;
            }
          }
        }

        list.sort(function(u, v) {
          if (self_hits[u.id] != self_hits[v.id]) {
            return self_hits[v.id] ? 1 : -1;
          }

          // If one result is open and one is closed, show the open result
          // first. The "!" tricks here are becaused closed values are display
          // strings, so the value is either `null` or some truthy string. If
          // we compare the values directly, we'll apply this rule to two
          // objects which are both closed but for different reasons, like
          // "Archived" and "Disabled".

          var u_open = !u.closed;
          var v_open = !v.closed;

          if (u_open != v_open) {
            if (u_open) {
              return -1;
            } else {
              return 1;
            }
          }

          if (priority_hits[u.id] != priority_hits[v.id]) {
            return priority_hits[v.id] ? 1 : -1;
          }

          // Sort users ahead of other result types.
          if (u.priorityType != v.priorityType) {
            if (u.priorityType == 'user') {
              return -1;
            }
            if (v.priorityType == 'user') {
              return 1;
            }
          }

          return cmp(u, v);
        });
      };

      var render_icon = function(icon) {
        return JX.$N(
          'span',
          {className: 'phui-icon-view phui-font-fa ' + icon});
      };

      datasource.setSortHandler(JX.bind(datasource, sort_handler));

      // Don't show any closed objects until the query is specific enough that
      // it only selects closed objects. Specifically, if the result list had
      // any open objects, remove all the closed objects from the list.
      var filter_handler = function(value, list) {
        // Look for any open result.
        var has_open = false;
        var ii;
        for (ii = 0; ii < list.length; ii++) {
          if (!list[ii].closed) {
            has_open = true;
            break;
          }
        }

        if (!has_open) {
          // Everything is closed, so just use it as-is.
          return list;
        }

        // Otherwise, only display the open results.
        var results = [];
        for (ii = 0; ii < list.length; ii++) {
          if (!list[ii].closed) {
            results.push(list[ii]);
          }
        }

        return results;
      };

      datasource.setFilterHandler(filter_handler);

      datasource.setTransformer(
        function(object) {
          var closed = object[9];
          var closed_ui;
          if (closed) {
            closed_ui = JX.$N(
              'div',
              {className: 'tokenizer-closed'},
              closed);
          }

          var icon = object[8];
          var icon_ui;
          if (icon) {
            icon_ui = render_icon(icon);
          }

          var display = JX.$N(
            'div',
            {className: 'tokenizer-result'},
            [icon_ui, object[0], closed_ui]);
          if (closed) {
            JX.DOM.alterClass(display, 'tokenizer-result-closed', true);
          }

          return {
            name: object[0],
            display: display,
            uri: object[1],
            id: object[2],
            priority: object[3],
            priorityType: object[7],
            icon: icon,
            closed: closed
          };
        });

      var typeahead = new JX.Typeahead(
        root,
        JX.DOM.find(root, 'input', 'tokenizer-input'));
      typeahead.setDatasource(datasource);

      var tokenizer = new JX.Tokenizer(root);
      tokenizer.setTypeahead(typeahead);
      tokenizer.setRenderTokenCallback(function(value, key) {
        var icon = datasource.getResult(key);
        if (icon) {
          icon = icon.icon;
        } else {
          icon = config.icons[key];
        }

        if (!icon) {
          return value;
        }

        icon = render_icon(icon);

        // TODO: Maybe we should render these closed tags in grey? Figure out
        // how we're going to use color.

        return [icon, value];
      });

      if (config.placeholder) {
        tokenizer.setPlaceholder(config.placeholder);
      }

      if (config.limit) {
        tokenizer.setLimit(config.limit);
      }

      if (config.value) {
        tokenizer.setInitialValue(config.value);
      }

      JX.Stratcom.addData(root, {'tokenizer' : tokenizer});

      return {
        tokenizer: tokenizer
      };
    }
  }

});
