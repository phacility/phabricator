/** @provides javelin-workflow-dev */

/**
 * @requires javelin-install javelin-vector javelin-dom
 * @provides javelin-mask
 * @javelin
 */

/**
 * Show a transparent "mask" over the page; used by Workflow to draw visual
 * attention to modal dialogs.
 */
JX.install('Mask', {
  statics : {
    _depth : 0,
    _mask : null,
    show : function() {
      if (!JX.Mask._depth) {
        JX.Mask._mask = JX.$N('div', {className: 'jx-mask'});
        document.body.appendChild(JX.Mask._mask);
        JX.$V.getDocument().setDim(JX.Mask._mask);
      }
      ++JX.Mask._depth;
    },
    hide : function() {
      --JX.Mask._depth;
      if (!JX.Mask._depth) {
        JX.DOM.remove(JX.Mask._mask);
        JX.Mask._mask = null;
      }
    }
  }
});
/**
 * @requires javelin-stratcom
 *           javelin-request
 *           javelin-dom
 *           javelin-vector
 *           javelin-install
 *           javelin-util
 *           javelin-mask
 * @provides javelin-workflow
 * @javelin
 */

JX.install('Workflow', {
  construct : function(uri, data) {
    if (__DEV__) {
      if (!uri || uri == '#') {
        throw new Error(
          'new JX.Workflow(<?>, ...): '+
          'bogus URI provided when creating workflow.');
      }
    }
    this.setURI(uri);
    this.setData(data || {});
  },

  events : ['error', 'finally', 'submit'],

  statics : {
    _stack   : [],
    newFromForm : function(form, data) {
      var inputs = [].concat(
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

      var workflow = new JX.Workflow(
        form.getAttribute('action'),
        JX.copy(data || {}, JX.DOM.serialize(form)));
      workflow.setMethod(form.getAttribute('method'));
      workflow.listen('finally', function() {
        for (var ii = 0; ii < inputs.length; ii++) {
          inputs[ii] && (inputs[ii].disabled = false);
        }
      });
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
    disable : function() {
      JX.Workflow._disabled = true;
    },
    _onbutton : function(event) {

      if (JX.Stratcom.pass()) {
        return;
      }

      if (JX.Workflow._disabled) {
        return;
      }
      var t = event.getTarget();
      if (t.name == '__cancel__' || t.name == '__close__') {
        JX.Workflow._pop();
      } else {

        var form = event.getNode('jx-dialog');
        var data = JX.DOM.serialize(form);
        data[t.name] = true;
        data.__wflow__ = true;

        var active = JX.Workflow._stack[JX.Workflow._stack.length - 1];
        var e = active.invoke('submit', {form: form, data: data});
        if (!e.getStopped()) {
          active._destroy();
          active
            .setURI(form.getAttribute('action') || active.getURI())
            .setData(data)
            .start();
        }
      }
      event.prevent();
    }
  },

  members : {
    _root : null,
    _pushed : false,
    _onload : function(r) {
      // It is permissible to send back a falsey redirect to force a page
      // reload, so we need to take this branch if the key is present.
      if (r && (typeof r.redirect != 'undefined')) {
        JX.go(r.redirect, true);
      } else if (r && r.dialog) {
        this._push();
        this._root = JX.$N(
          'div',
          {className: 'jx-client-dialog'},
          JX.HTML(r.dialog));
        JX.DOM.listen(
          this._root,
          'click',
          'tag:button',
          JX.Workflow._onbutton);
        document.body.appendChild(this._root);
        var d = JX.$V.getDim(this._root);
        var v = JX.$V.getViewport();
        var s = JX.$V.getScroll();
        JX.$V((v.x - d.x) / 2, s.y + 100).setPos(this._root);
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
      } else if (this.getHandler()) {
        this.getHandler()(r);
        this._pop();
      } else if (r) {
        if (__DEV__) {
          throw new Error('Response to workflow request went unhandled.');
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
      var uri = this.getURI();
      var method = this.getMethod();
      var r = new JX.Request(uri, JX.bind(this, this._onload));
      r.setData(this.getData());
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
    }
  },

  properties : {
    handler : null,
    closeHandler : null,
    data : null,
    dataSerializer : null,
    method : null,
    URI : null
  }

});
