/**
 * Initialize a client-side view from the server. The main idea here is to
 * give server-side views a way to integrate with client-side views.
 *
 * The idea is that a client-side view will have an accompanying
 * thin server-side component. The server-side component renders a placeholder
 * element in the document, and then it will invoke this  behavior to initialize
 * the view into the placeholder.
 *
 * Because server-side views may be nested, we need to add complexity to
 * handle nesting properly.
 *
 * Assuming a server-side view design that looks like hierarchical views,
 * we have to handle structures like
 *
 * <server:component>
 *   <client:component id="1">
 *     <server:component>
 *       <client:component id="2">
 *       </client:component>
 *     </server:component>
 *   </client:component>
 * </server:component>
 *
 * This leads to a problem: Client component 1 needs to initialize the behavior
 * with its children, which includes client component 2. So client component
 * 2 must be rendered first. When client component 2 is rendered, it will also
 * initialize a copy of this behavior. If behaviors are run in the order they
 * are initialized, the child component will run before the parent, and its
 * placeholder won't exist yet.
 *
 * To solve this problem, placeholder behaviors are initialized with the token
 * of a containing view that must be rendered first (if any) and a token
 * representing it for its own children to depend on. This means the server code
 * is free to call initBehavior in any order.
 *
 * In Phabricator, AphrontJavelinView demonstrates how to handle this correctly.
 *
 * config: {
 *   id: Node id to replace.
 *   view: class of view, without the 'JX.' prefix.
 *   params: view parameters
 *   children: messy and loud, cute when drunk
 *   trigger_id: id of containing view that must be rendered first
 * }
 *
 * @provides javelin-behavior-view-placeholder
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-view-renderer
 *           javelin-install
 */



JX.behavior('view-placeholder', function(config) {
  JX.ViewPlaceholder.register(config.trigger_id, config.id, function() {
    var replace = JX.$(config.id);

    var children = config.children;
    if (typeof children === 'string') {
      children = JX.$H(children);
    }

    var view = new JX[config.view](config.params, children);
    var rendered = JX.ViewRenderer.render(view);

    JX.DOM.replace(replace, rendered);
  });
});

JX.install('ViewPlaceholder', {
  statics: {
    register: function(wait_on_token, token, cb) {
      var ready_q = [];
      var waiting;

      if (!wait_on_token || wait_on_token in JX.ViewPlaceholder.ready) {
        ready_q.push({token: token, cb: cb});
      } else {
        waiting = JX.ViewPlaceholder.waiting;
        waiting[wait_on_token] = waiting[wait_on_token] || [];
        waiting[wait_on_token].push({token: token, cb: cb});
      }

      while(ready_q.length) {
        var ready = ready_q.shift();

        waiting = JX.ViewPlaceholder.waiting[ready.token];
        if (waiting) {
          for (var ii = 0; ii < waiting.length; ii++) {
            ready_q.push(waiting[ii]);
          }
          delete JX.ViewPlaceholder.waiting[ready.token];
        }
        ready.cb();

        JX.ViewPlaceholder.ready[token] = true;
      }

    },
    ready: {},
    waiting: {}
  }
});
