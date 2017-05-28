/**
 * @provides javelin-behavior-dark-console
 * @requires javelin-behavior
 *           javelin-stratcom
 *           javelin-util
 *           javelin-dom
 *           javelin-request
 *           phabricator-keyboard-shortcut
 *           phabricator-darklog
 *           phabricator-darkmessage
 */

JX.behavior('dark-console', function(config, statics) {

  // Do first-time setup.
  function setup_console() {
    init_console(config.visible);

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

    // When the user clicks a tab, select it.
    JX.Stratcom.listen('click', 'dark-console-tab', function(e) {
      e.kill();
      select_tab(e.getNodeData('dark-console-tab')['class']);
    });

    JX.Stratcom.listen(
      'quicksand-redraw',
      null,
      function (e) {
        var data = e.getData();
        var new_console;
        if (data.fromServer) {
          new_console = JX.$('darkconsole');
          // The correct key has to be pulled from the rendered console
          statics.quicksand_key = new_console.getAttribute('data-console-key');
          statics.quicksand_color =
            new_console.getAttribute('data-console-color');
        } else {
          // we need to add a console holder back in since we blew it away
          new_console = JX.$N(
            'div',
            { id : 'darkconsole', class : 'dark-console' });
          JX.DOM.prependContent(
            JX.$('phabricator-standard-page-body'),
            new_console);
        }
        JX.DOM.replace(new_console, statics.root);
      });

    return statics.root;
  }

  function init_console(visible) {
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

    statics.visible = visible;

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

    var link = JX.$N('a', attr, [get_bullet(config.color), ' ', config.uri]);
    statics.el.reqs.appendChild(link);
    statics.req.all[config.key] = link;

    // When the user clicks a request, select it.
    JX.DOM.listen(
      link,
      'click',
      'dark-console-request',
      function(e) {
        e.kill();
        select_request(e.getNodeData('dark-console-request').key);
      });

    if (!statics.req.current) {
      select_request(config.key);
    }
  }

  function get_bullet(color) {
    if (!color) {
      return null;
    }
    return JX.$N('span', {style: {color: color}}, '\u2022');
  }

  // Select a request (on load, or when the user clicks one).
  function select_request(key) {
    if (statics.req.current) {
      JX.DOM.alterClass(
        statics.req.all[statics.req.current],
        'dark-selected',
        false);
    }
    statics.req.current = key;
    JX.DOM.alterClass(
      statics.req.all[statics.req.current],
      'dark-selected',
      true);

    if (statics.visible) {
      draw_request(key);
    }
  }

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

      var link = JX.$N('a', attr, [get_bullet(tab.color), ' ', tab.name]);
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
      statics.selected = tclass;
    }

    draw_panel();
  }

  function draw_panel() {
    var data = statics.cache[statics.req.current];
    var tclass = JX.Stratcom.getData(statics.tab.current)['class'];
    var html = data.panel[tclass];

    var div = JX.$N('div', {className: 'dark-console-panel-core'}, JX.$H(html));
    JX.DOM.setContent(statics.el.panel, div);

    var params = {
      panel: tclass
    };

    JX.Stratcom.invoke('darkconsole.draw', null, params);
  }

  function install_shortcut() {
    var desc = 'Toggle visibility of DarkConsole.';
    new JX.KeyboardShortcut('`', desc)
      .setHandler(function() {
        statics.visible = !statics.visible;

        if (statics.visible) {
          JX.DOM.show(statics.root);
          if (statics.req.current) {
            draw_request(statics.req.current);
          }
        } else {
          JX.DOM.hide(statics.root);
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

  statics.root = statics.root || setup_console();
  if (config.quicksand && statics.quicksand_key) {
    config.key = statics.quicksand_key;
    config.color = statics.quicksand_color;
    statics.quicksand_key = null;
    statics.quicksand_color = null;
  }
  config.key = config.key || statics.root.getAttribute('data-console-key');
  if (!('color' in config)) {
    config.color = statics.root.getAttribute('data-console-color');
  }
  add_request(config);


/* -(  Realtime Panel  )----------------------------------------------------- */


  if (!statics.realtime) {
    statics.realtime = true;

    var realtime_log = new JX.DarkLog();
    var realtime_id = 'dark-console-realtime-log';

    JX.Stratcom.listen('darkconsole.draw', null, function(e) {
      var data = e.getData();
      if (data.panel != 'DarkConsoleRealtimePlugin') {
        return;
      }

      var node = JX.$(realtime_id);
      realtime_log.setNode(node);
    });

    // If the panel is initially visible, try rendering.
    try {
      var node = JX.$(realtime_id);
      realtime_log.setNode(node);
    } catch (exception) {
      // Ignore.
    }

    var leader_log = function(event_name, type, is_leader, details) {
      var parts = [];
      if (is_leader === true) {
        parts.push('+');
      } else if (is_leader === false) {
        parts.push('-');
      } else {
        parts.push('~');
      }

      parts.push('[Leader/' + event_name + ']');

      if (type) {
        parts.push('(' + type + ')');
      }

      if (details) {
        parts.push(details);
      }

      parts = parts.join(' ');

      var message = new JX.DarkMessage()
        .setMessage(parts);

      realtime_log.addMessage(message);
    };

    JX.Leader.listen('onReceiveBroadcast', function(message, is_leader) {
      var json = JX.JSON.stringify(message.data);

      if (message.type == 'aphlict.status') {
        if (message.data == 'closed') {
          var ws = JX.Aphlict.getInstance().getWebsocket();
          if (ws) {
            var delay = ws.getReconnectDelay();
            json += ' [Reconnect: ' + delay + 'ms]';
          }
        }
      }

      leader_log('onReceiveBroadcast', message.type, is_leader, json);
    });

    JX.Leader.listen('onBecomeLeader', function() {
      leader_log('onBecomeLeader');
    });

    var action_log = function(action) {
      var message = new JX.DarkMessage()
        .setMessage('> ' + action);

      realtime_log.addMessage(message);
    };

    JX.Stratcom.listen('click', 'dark-console-realtime-action', function(e) {
      var node = e.getNode('dark-console-realtime-action');
      var data = JX.Stratcom.getData(node);

      action_log(data.label);

      var action = data.action;
      switch (action) {
        case 'reconnect':
          var ws = JX.Aphlict.getInstance().getWebsocket();
          if (ws) {
            ws.reconnect();
          }
          break;
        case 'replay':
          JX.Aphlict.getInstance().replay();
          break;
        case 'repaint':
          JX.Aphlict.getInstance().reconnect();
          break;
      }

    });

  }

});
