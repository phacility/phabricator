/**
 * @requires javelin-stratcom
 *           javelin-request
 *           javelin-dom
 *           javelin-vector
 *           javelin-install
 *           javelin-util
 *           javelin-mask
 *           javelin-uri
 *           javelin-routable
 * @provides javelin-workflow
 * @javelin
 */

JX.install('Workflow', {
  construct : function(uri, data) {
    if (__DEV__) {
      if (!uri || uri == '#') {
        JX.$E(
          'new JX.Workflow(<?>, ...): '+
          'bogus URI provided when creating workflow.');
      }
    }
    this.setURI(uri);
    this.setData(data || {});
  },

  events : ['error', 'finally', 'submit', 'start'],

  statics : {
    _stack   : [],
    newFromForm : function(form, data, keep_enabled) {
      var pairs = JX.DOM.convertFormToListOfPairs(form);
      for (var k in data) {
        pairs.push([k, data[k]]);
      }

      var inputs;
      if (keep_enabled) {
        inputs = [];
      } else {
        // Disable form elements during the request
        inputs = [].concat(
          JX.DOM.scry(form, 'input'),
          JX.DOM.scry(form, 'button'),
          JX.DOM.scry(form, 'textarea'));
        for (var ii = 0; ii < inputs.length; ii++) {
          if (inputs[ii].disabled) {
            delete inputs[ii];
          } else {
            inputs[ii].disabled = true;
          }
        }
      }

      var workflow = new JX.Workflow(form.getAttribute('action'), {});

      workflow._form = form;

      workflow.setDataWithListOfPairs(pairs);
      workflow.setMethod(form.getAttribute('method'));

      var onfinally = JX.bind(workflow, function() {
        if (!this._keepControlsDisabled) {
          for (var ii = 0; ii < inputs.length; ii++) {
            inputs[ii] && (inputs[ii].disabled = false);
          }
        }
      });
      workflow.listen('finally', onfinally);

      return workflow;
    },
    newFromLink : function(link) {
      var workflow = new JX.Workflow(link.href);
      return workflow;
    },

    _push : function(workflow) {
      JX.Mask.show();
      JX.Workflow._stack.push(workflow);
    },
    _pop : function() {
      var dialog = JX.Workflow._stack.pop();
      (dialog.getCloseHandler() || JX.bag)();
      dialog._destroy();
      JX.Mask.hide();
    },
    _onlink: function(event) {
      // See T13302. When a user clicks a link in a dialog and that link
      // triggers a navigation event, we want to close the dialog as though
      // they had pressed a button.

      // When Quicksand is enabled, this is particularly relevant because
      // the dialog will stay in the foreground while the page content changes
      // in the background if we do not dismiss the dialog.

      // If this is a Command-Click, the link will open in a new window.
      var is_command = !!event.getRawEvent().metaKey;
      if (is_command) {
        return;
      }

      var link = event.getNode('tag:a');

      // If the link is an anchor, or does not go anywhere, ignore the event.
      var href = link.getAttribute('href');
      if (typeof href !== 'string') {
        return;
      }

      if (!href.length || href[0] === '#') {
        return;
      }

      // This link will open in a new window.
      if (link.target === '_blank') {
        return;
      }

      // This link is really a dialog button which we'll handle elsewhere.
      if (JX.Stratcom.hasSigil(link, 'jx-workflow-button')) {
        return;
      }

      // Close the dialog.
      JX.Workflow._pop();
    },
    _onbutton : function(event) {

      if (JX.Stratcom.pass()) {
        return;
      }

      // Get the button (which is sometimes actually another tag, like an <a />)
      // which triggered the event. In particular, this makes sure we get the
      // right node if there is a <button> with an <img /> inside it or
      // or something similar.
      var t = event.getNode('jx-workflow-button') ||
              event.getNode('tag:button');

      // If this button disables workflow (normally, because it is a file
      // download button) let the event through without modification.
      if (JX.Stratcom.getData(t).disableWorkflow) {
        return;
      }

      event.prevent();

      if (t.name == '__cancel__' || t.name == '__close__') {
        JX.Workflow._pop();
      } else {
        var form = event.getNode('jx-dialog');
        JX.Workflow._dosubmit(form, t);
      }
    },
    _onsyntheticsubmit : function(e) {
      if (JX.Stratcom.pass()) {
        return;
      }
      e.prevent();
      var form = e.getNode('jx-dialog');
      var button = JX.DOM.find(form, 'button', '__default__');
      JX.Workflow._dosubmit(form, button);
    },
    _dosubmit : function(form, button) {
      // Issue a DOM event first, so form-oriented handlers can act.
      var dom_event = JX.DOM.invoke(form, 'didWorkflowSubmit');
      if (dom_event.getPrevented()) {
        return;
      }

      var data = JX.DOM.convertFormToListOfPairs(form);
      data.push([button.name, button.value || true]);

      var active = JX.Workflow._getActiveWorkflow();

      active._form = form;

      var e = active.invoke('submit', {form: form, data: data});
      if (!e.getStopped()) {
        // NOTE: Don't remove the current dialog yet because additional
        // handlers may still want to access the nodes.

        // Disable whatever button the user clicked to prevent duplicate
        // submission mistakes when you accidentally click a button multiple
        // times. See T11145.
        button.disabled = true;

        active
          .setURI(form.getAttribute('action') || active.getURI())
          .setDataWithListOfPairs(data)
          .start();
      }
    },
    _getActiveWorkflow : function() {
      var stack = JX.Workflow._stack;
      return stack[stack.length - 1];
    },

    _onresizestart: function(e) {
      var self = JX.Workflow;
      if (self._resizing) {
        return;
      }

      var workflow = self._getActiveWorkflow();
      if (!workflow) {
        return;
      }

      e.kill();

      var form = JX.DOM.find(workflow._root, 'div', 'jx-dialog');
      var resize = e.getNodeData('jx-dialog-resize');
      var node_y = JX.$(resize.resizeY);

      var dim = JX.Vector.getDim(form);
      dim.y = JX.Vector.getDim(node_y).y;

      if (!form._minimumSize) {
        form._minimumSize = dim;
      }

      self._resizing = {
        min: form._minimumSize,
        form: form,
        startPos: JX.$V(e),
        startDim: dim,
        resizeY: node_y,
        resizeX: resize.resizeX
      };
    },

    _onmousemove: function(e) {
      var self = JX.Workflow;
      if (!self._resizing) {
        return;
      }

      var spec = self._resizing;
      var form = spec.form;
      var min = spec.min;

      var delta = JX.$V(e).add(-spec.startPos.x, -spec.startPos.y);
      var src_dim = spec.startDim;
      var dst_dim = JX.$V(src_dim.x + delta.x, src_dim.y + delta.y);

      if (dst_dim.x < min.x) {
        dst_dim.x = min.x;
      }

      if (dst_dim.y < min.y) {
        dst_dim.y = min.y;
      }

      if (spec.resizeX) {
        JX.$V(dst_dim.x, null).setDim(form);
      }

      if (spec.resizeY) {
        JX.$V(null, dst_dim.y).setDim(spec.resizeY);
      }
    },

    _onmouseup: function() {
      var self = JX.Workflow;
      if (!self._resizing) {
        return;
      }

      self._resizing = false;
    }
  },

  members : {
    _root : null,
    _pushed : false,
    _data : null,

    _form: null,
    _paused: 0,
    _nextCallback: null,
    _keepControlsDisabled: false,

    getSourceForm: function() {
      return this._form;
    },

    pause: function() {
      this._paused++;
      return this;
    },

    resume: function() {
      if (!this._paused) {
        JX.$E('Resuming a workflow which is not paused!');
      }

      this._paused--;

      if (!this._paused) {
        var next = this._nextCallback;
        this._nextCallback = null;
        if (next) {
          next();
        }
      }

      return this;
    },

    _onload : function(r) {
      this._destroy();

      // It is permissible to send back a falsey redirect to force a page
      // reload, so we need to take this branch if the key is present.
      if (r && (typeof r.redirect != 'undefined')) {
        // Before we redirect to file downloads, we close the dialog. These
        // redirects aren't real navigation events so we end up stuck in the
        // dialog otherwise.
        if (r.close) {
          this._pop();
        }

        // If we're redirecting, don't re-enable for controls.
        this._keepControlsDisabled = true;

        JX.$U(r.redirect).go();
      } else if (r && r.dialog) {
        this._push();
        this._root = JX.$N(
          'div',
          {className: 'jx-client-dialog'},
          JX.$H(r.dialog));
        JX.DOM.listen(
          this._root,
          'click',
          [['jx-workflow-button'], ['tag:button']],
          JX.Workflow._onbutton);
        JX.DOM.listen(
          this._root,
          'didSyntheticSubmit',
          [],
          JX.Workflow._onsyntheticsubmit);

        var onlink = JX.Workflow._onlink;
        JX.DOM.listen(this._root, 'click', 'tag:a', onlink);

        JX.DOM.listen(
          this._root,
          'mousedown',
          'jx-dialog-resize',
          JX.Workflow._onresizestart);

        // Note that even in the presence of a content frame, we're doing
        // everything here at top level: dialogs are fully modal and cover
        // the entire window.

        document.body.appendChild(this._root);

        var d = JX.Vector.getDim(this._root);
        var v = JX.Vector.getViewport();
        var s = JX.Vector.getScroll();

        // Normally, we position dialogs 100px from the top of the screen.
        // Use more space if the dialog is large (at least roughly the size
        // of the viewport).
        var offset = Math.min(Math.max(20, (v.y - d.y) / 2), 100);
        JX.$V(0, s.y + offset).setPos(this._root);

        try {
          JX.DOM.focus(JX.DOM.find(this._root, 'button', '__default__'));
          var inputs = JX.DOM.scry(this._root, 'input')
                         .concat(JX.DOM.scry(this._root, 'textarea'));
          var miny = Number.POSITIVE_INFINITY;
          var target = null;
          for (var ii = 0; ii < inputs.length; ++ii) {
            if (inputs[ii].type != 'hidden') {
              // Find the topleft-most displayed element.
              var p = JX.$V(inputs[ii]);
              if (p.y < miny) {
                 miny = p.y;
                 target = inputs[ii];
              }
            }
          }
          target && JX.DOM.focus(target);
        } catch (_ignored) {}

        // The `focus()` call may have scrolled the window. Scroll it back to
        // where it was before -- we want to focus the control, but not adjust
        // the scroll position.

        // Dialogs are window-level, so scroll the window explicitly.
        window.scrollTo(s.x, s.y);

      } else if (this.getHandler()) {
        this.getHandler()(r);
        this._pop();
      } else if (r) {
        if (__DEV__) {
          JX.$E('Response to workflow request went unhandled.');
        }
      }
    },
    _push : function() {
      if (!this._pushed) {
        this._pushed = true;
        JX.Workflow._push(this);
      }
    },
    _pop : function() {
      if (this._pushed) {
        this._pushed = false;
        JX.Workflow._pop();
      }
    },
    _destroy : function() {
      if (this._root) {
        JX.DOM.remove(this._root);
        this._root = null;
      }
    },

    start : function() {
      var next = JX.bind(this, this._send);

      this.pause();
      this._nextCallback = next;

      this.invoke('start', this);

      this.resume();
    },

    _send: function() {
      var uri = this.getURI();
      var method = this.getMethod();
      var r = new JX.Request(uri, JX.bind(this, this._onload));
      var list_of_pairs = this._data;
      list_of_pairs.push(['__wflow__', true]);
      r.setDataWithListOfPairs(list_of_pairs);
      r.setDataSerializer(this.getDataSerializer());
      if (method) {
        r.setMethod(method);
      }
      r.listen('finally', JX.bind(this, this.invoke, 'finally'));
      r.listen('error', JX.bind(this, function(error) {
        var e = this.invoke('error', error);
        if (e.getStopped()) {
          return;
        }
        // TODO: Default error behavior? On Facebook Lite, we just shipped the
        // user to "/error/". We could emit a blanket 'workflow-failed' type
        // event instead.
      }));
      r.send();
    },

    getRoutable: function() {
      var routable = new JX.Routable();
      routable.listen('start', JX.bind(this, function() {
        // Pass the event to allow other listeners to "start" to configure this
        // workflow before it fires.
        JX.Stratcom.pass(JX.Stratcom.context());
        this.start();
      }));
      this.listen('finally', JX.bind(routable, routable.done));
      return routable;
    },

    setData : function(dictionary) {
      this._data = [];
      for (var k in dictionary) {
        this._data.push([k, dictionary[k]]);
      }
      return this;
    },

    addData: function(key, value) {
      this._data.push([key, value]);
      return this;
    },

    setDataWithListOfPairs : function(list_of_pairs) {
      this._data = list_of_pairs;
      return this;
    }
  },

  properties : {
    handler : null,
    closeHandler : null,
    dataSerializer : null,
    method : null,
    URI : null
  },

  initialize : function() {

    function close_dialog_when_user_presses_escape(e) {
      if (e.getSpecialKey() != 'esc') {
        // Some key other than escape.
        return;
      }

      if (JX.Stratcom.pass()) {
        // Something else swallowed the event.
        return;
      }

      var active = JX.Workflow._getActiveWorkflow();
      if (!active) {
        // No active workflow.
        return;
      }

      // Note: the cancel button is actually an <a /> tag.
      var buttons = JX.DOM.scry(active._root, 'a', 'jx-workflow-button');
      if (!buttons.length) {
        // No buttons in the dialog.
        return;
      }

      var cancel = null;
      for (var ii = 0; ii < buttons.length; ii++) {
        if (buttons[ii].name == '__cancel__') {
          cancel = buttons[ii];
          break;
        }
      }

      if (!cancel) {
        // No 'Cancel' button.
        return;
      }

      JX.Workflow._pop();
      e.prevent();
    }

    JX.Stratcom.listen('keydown', null, close_dialog_when_user_presses_escape);

    JX.Stratcom.listen('mousemove', null, JX.Workflow._onmousemove);
    JX.Stratcom.listen('mouseup', null, JX.Workflow._onmouseup);
  }

});
