/**
 * @provides javelin-behavior-dark-console
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-util
 *           javelin-dom
 *           javelin-request
 *           phabricator-keyboard-shortcut
 */

JX.behavior('dark-console', function(config, statics) {
  var root = statics.root || setup_console();

  config.key = config.key || root.getAttribute('data-console-key');
  add_request(config);

  // Do first-time setup.
  function setup_console() {
    statics.root = JX.$('darkconsole');
    statics.req = {all: {}, current: null};
    statics.tab = {all: {}, current: null};

    statics.el = {};

    statics.el.reqs = JX.$N('div', {className: 'dark-console-requests'});
    statics.root.appendChild(statics.el.reqs);

    statics.el.tabs = JX.$N('div', {className: 'dark-console-tabs'});
    statics.root.appendChild(statics.el.tabs);

    statics.el.panel = JX.$N('div', {className: 'dark-console-panel'});
    statics.root.appendChild(statics.el.panel);

    statics.el.load = JX.$N('div', {className: 'dark-console-load'});
    statics.root.appendChild(statics.el.load);

    statics.cache = {};

    statics.visible = config.visible;
    statics.selected = config.selected;

    install_shortcut();

    if (config.headers) {
      // If the main page had profiling enabled, also enable it for any Ajax
      // requests.
      JX.Request.listen('open', function(r) {
        for (var k in config.headers) {
          r.getTransport().setRequestHeader(k, config.headers[k]);
        }
      });
    }

    return statics.root;
  }


  // Add a new request to the console (initial page load, or new Ajax response).
  function add_request(config) {

    // Ignore DarkConsole data requests.
    if (config.uri.match(new RegExp('^/~/data/'))) {
      return;
    }

    var attr = {
      className: 'dark-console-request',
      sigil: 'dark-console-request',
      title: config.uri,
      meta: config,
      href: '#'
    };

    var link = JX.$N('a', attr, config.uri);
    statics.el.reqs.appendChild(link);
    statics.req.all[config.key] = link;

    if (!statics.req.current) {
      select_request(config.key);
    }
  }


  // Select a request (on load, or when the user clicks one).
  function select_request(key) {
    var req = statics.req;

    if (req.current) {
      JX.DOM.alterClass(req.all[req.current], 'dark-selected', false);
    }
    statics.req.current = key;
    JX.DOM.alterClass(req.all[req.current], 'dark-selected', true);

    if (statics.visible) {
      draw_request(key);
    }
  }

  // When the user clicks a request, select it.
  JX.Stratcom.listen('click', 'dark-console-request', function(e) {
    e.kill();
    select_request(e.getNodeData('dark-console-request').key);
  });


  // After the user selects a request, draw its tabs.
  function draw_request(key) {
    var cache = statics.cache;

    if (cache[key]) {
      render_request(key);
      return;
    }

    new JX.Request(
      '/~/data/' + key + '/',
      function(r) {
        cache[key] = r;
        if (statics.req.current == key) {
          render_request(key);
        }
      })
    .send();

    show_loading();
  }

  // Show the loading indicator.
  function show_loading() {
    JX.DOM.hide(statics.el.tabs);
    JX.DOM.hide(statics.el.panel);
    JX.DOM.show(statics.el.load);
  }

  // Hide the loading indicator.
  function hide_loading() {
    JX.DOM.show(statics.el.tabs);
    JX.DOM.show(statics.el.panel);
    JX.DOM.hide(statics.el.load);
  }

  function render_request(key) {
    var data = statics.cache[key];

    statics.tab.all = {};

    var links = [];
    var first = null;
    for (var ii = 0; ii < data.tabs.length; ii++) {
      var tab = data.tabs[ii];
      var attr = {
        className: 'dark-console-tab',
        sigil: 'dark-console-tab',
        meta: tab,
        href: '#'
      };

      var bullet = null;
      if (tab.color) {
        bullet = JX.$N('span', {style: {color: tab.color}}, "\u2022");
      }

      var link = JX.$N('a', attr, [bullet, ' ', tab.name]);
      links.push(link);
      statics.tab.all[tab['class']] = link;
      first = first || tab['class'];
    }

    JX.DOM.setContent(statics.el.tabs, links);

    if (statics.tab.current in statics.tab.all) {
      select_tab(statics.tab.current);
    } else if (statics.selected in statics.tab.all) {
      select_tab(statics.selected);
    } else {
      select_tab(first);
    }

    hide_loading();
  }

  function select_tab(tclass) {
    var tabs = statics.tab;

    if (tabs.current) {
      JX.DOM.alterClass(tabs.current, 'dark-selected', false);
    }
    tabs.current = tabs.all[tclass];
    JX.DOM.alterClass(tabs.current, 'dark-selected', true);

    if (tclass != statics.selected) {
      // Save user preference.
      new JX.Request('/~/', JX.bag)
        .setData({ tab : tclass })
        .send();
    }

    draw_panel();
  }

  // When the user clicks a tab, select it.
  JX.Stratcom.listen('click', 'dark-console-tab', function(e) {
    e.kill();
    select_tab(e.getNodeData('dark-console-tab')['class']);
  });

  function draw_panel() {
    var data = statics.cache[statics.req.current];
    var tclass = JX.Stratcom.getData(statics.tab.current)['class'];
    var html = data.panel[tclass];

    var div = JX.$N('div', {className: 'dark-console-panel-core'}, JX.$H(html));
    JX.DOM.setContent(statics.el.panel, div);
  }

  function install_shortcut() {
    var desc = 'Toggle visibility of DarkConsole.';
    new JX.KeyboardShortcut('`', desc)
      .setHandler(function(manager) {
        statics.visible = !statics.visible;

        if (statics.visible) {
          JX.DOM.show(root);
          if (statics.req.current) {
            draw_request(statics.req.current);
          }
        } else {
          JX.DOM.hide(root);
        }

        // Save user preference.
        new JX.Request('/~/', JX.bag)
          .setData({visible: statics.visible ? 1 : 0})
          .send();

        // Force resize listeners to take effect.
        JX.Stratcom.invoke('resize');
      })
      .register();
  }

});
