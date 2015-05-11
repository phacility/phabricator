/**
 * @provides conpherence-thread-manager
 * @requires javelin-dom
 *           javelin-util
 *           javelin-stratcom
 *           javelin-install
 *           javelin-aphlict
 *           javelin-workflow
 *           javelin-router
 *           javelin-behavior-device
 *           javelin-vector
 */
JX.install('ConpherenceThreadManager', {

  construct : function() {
    if (__DEV__) {
      if (JX.ConpherenceThreadManager._instance) {
        JX.$E('ConpherenceThreadManager object is a singleton.');
      }
    }
    JX.ConpherenceThreadManager._instance = this;
    return this;
  },

  members: {
    _loadThreadURI: null,
    _loadedThreadID: null,
    _loadedThreadPHID: null,
    _latestTransactionID: null,
    _canEditLoadedThread: null,
    _updating:  null,
    _minimalDisplay: false,
    _willLoadThreadCallback: JX.bag,
    _didLoadThreadCallback: JX.bag,
    _didUpdateThreadCallback:  JX.bag,
    _willSendMessageCallback: JX.bag,
    _didSendMessageCallback: JX.bag,
    _willUpdateWorkflowCallback: JX.bag,
    _didUpdateWorkflowCallback: JX.bag,

    setLoadThreadURI: function(uri) {
      this._loadThreadURI = uri;
      return this;
    },

    getLoadThreadURI: function() {
      return this._loadThreadURI;
    },

    isThreadLoaded: function() {
      return Boolean(this._loadedThreadID);
    },

    isThreadIDLoaded: function(thread_id) {
      return this._loadedThreadID == thread_id;
    },

    getLoadedThreadID: function() {
      return this._loadedThreadID;
    },

    setLoadedThreadID: function(id) {
      this._loadedThreadID = id;
      return this;
    },

    getLoadedThreadPHID: function() {
      return this._loadedThreadPHID;
    },

    setLoadedThreadPHID: function(phid) {
      this._loadedThreadPHID = phid;
      return this;
    },

    getLatestTransactionID: function() {
      return this._latestTransactionID;
    },

    setLatestTransactionID: function(id) {
      this._latestTransactionID = id;
      return this;
    },

    setCanEditLoadedThread: function(bool) {
      this._canEditLoadedThread = bool;
      return this;
    },

    getCanEditLoadedThread: function() {
      if (this._canEditLoadedThread === null) {
        return false;
      }
      return this._canEditLoadedThread;
    },

    setMinimalDisplay: function(bool) {
      this._minimalDisplay = bool;
      return this;
    },

    setWillLoadThreadCallback: function(callback) {
      this._willLoadThreadCallback = callback;
      return this;
    },

    setDidLoadThreadCallback: function(callback) {
      this._didLoadThreadCallback = callback;
      return this;
    },

    setDidUpdateThreadCallback: function(callback) {
      this._didUpdateThreadCallback = callback;
      return this;
    },

    setWillSendMessageCallback: function(callback) {
      this._willSendMessageCallback = callback;
      return this;
    },

    setDidSendMessageCallback: function(callback) {
      this._didSendMessageCallback = callback;
      return this;
    },

    setWillUpdateWorkflowCallback: function(callback) {
      this._willUpdateWorkflowCallback = callback;
      return this;
    },

    setDidUpdateWorkflowCallback: function(callback) {
      this._didUpdateWorkflowCallback = callback;
      return this;
    },

    _getParams: function(base_params) {
      if (this._minimalDisplay) {
        base_params.minimal_display = true;
      }
      if (this._latestTransactionID) {
        base_params.latest_transaction_id = this._latestTransactionID;
      }
      return base_params;
    },

    start: function() {
      JX.Stratcom.listen(
        'aphlict-server-message',
        null,
        JX.bind(this, function(e) {
          var message = e.getData();

          if (message.type != 'message') {
            // Not a message event.
            return;
          }

          if (message.threadPHID != this._loadedThreadPHID) {
            // Message event for some thread other than the visible one.
            return;
          }

          if (message.messageID <= this._latestTransactionID) {
            // Message event for something we already know about.
            return;
          }

          // If this notification tells us about a message which is newer than
          // the newest one we know to exist, update our latest knownID so we
          // can properly update later.
          if (this._updating &&
              this._updating.threadPHID == this._loadedThreadPHID) {
            if (message.messageID > this._updating.knownID) {
              this._updating.knownID = message.messageID;
              // We're currently updating, so wait for the update to complete.
              // this.syncWorkflow has us covered in this case.
              if (this._updating.active) {
                return;
              }
            }
          }

          this._updateThread();
        }));
    },

    _shouldUpdateDOM: function(r) {
      if (this._updating &&
          this._updating.threadPHID == this._loadedThreadPHID) {

        if (r.non_update) {
          return false;
        }

        // we have a different, more current update in progress so
        // return early
        if (r.latest_transaction_id < this._updating.knownID) {
          return false;
        }
      }
      return true;
    },

    _markUpdated: function(r) {
      this._updating.knownID = r.latest_transaction_id;
      this._latestTransactionID = r.latest_transaction_id;
      JX.Stratcom.invoke(
        'conpherence-redraw-aphlict',
        null,
        r.aphlictDropdownData);
    },

    _updateThread: function() {
      var params = this._getParams({
        action: 'load',
      });

      var uri = '/conpherence/update/' + this._loadedThreadID + '/';

      var workflow = new JX.Workflow(uri)
        .setData(params)
        .setHandler(JX.bind(this, function(r) {
          if (this._shouldUpdateDOM(r)) {
            this._markUpdated(r);

            this._didUpdateThreadCallback(r);
          }
        }));

      this.syncWorkflow(workflow, 'finally');
    },

    syncWorkflow: function(workflow, stage) {
      this._updating = {
        threadPHID: this._loadedThreadPHID,
        knownID: this._latestTransactionID,
        active: true
      };
      workflow.listen(stage, JX.bind(this, function() {
        // TODO - do we need to handle if we switch threads somehow?
        var need_sync = this._updating &&
          (this._updating.knownID > this._latestTransactionID);
        if (need_sync) {
          return this._updateThread();
        }
        this._updating.active = false;
      }));
      workflow.start();
    },

    runUpdateWorkflowFromLink: function(link, params) {
      params = this._getParams(params);
      this._willUpdateWorkflowCallback();
      var workflow = new JX.Workflow.newFromLink(link)
        .setData(params)
        .setHandler(JX.bind(this, function(r) {
          if (this._shouldUpdateDOM(r)) {
            this._markUpdated(r);

            this._didUpdateWorkflowCallback(r);
          }
        }));
      this.syncWorkflow(workflow, params.stage);
    },

    loadThreadByID: function(thread_id, force_reload) {
      if (this.isThreadLoaded() &&
          this.isThreadIDLoaded(thread_id) &&
          !force_reload) {
        return;
      }

      this._willLoadThreadCallback();

      var params = {};
      // We pick a thread from the server if not specified
      if (thread_id) {
        params.id = thread_id;
      }
      params = this._getParams(params);

      var handler = JX.bind(this, function(r) {
        var client = JX.Aphlict.getInstance();
        if (client) {
          var old_subs = client.getSubscriptions();
          var new_subs = [];
          for (var ii = 0; ii < old_subs.length; ii++) {
            if (old_subs[ii] == this._loadedThreadPHID) {
              continue;
            } else {
              new_subs.push(old_subs[ii]);
            }
          }
          new_subs.push(r.threadPHID);
          client.clearSubscriptions(client.getSubscriptions());
          client.setSubscriptions(new_subs);
        }
        this._loadedThreadID = r.threadID;
        this._loadedThreadPHID = r.threadPHID;
        this._latestTransactionID = r.latestTransactionID;
        this._canEditLoadedThread = r.canEdit;

        JX.Stratcom.invoke(
          'conpherence-redraw-aphlict',
          null,
          r.aphlictDropdownData);

        this._didLoadThreadCallback(r);

        if (force_reload) {
          JX.Stratcom.invoke('hashchange');
        }
      });

      // should this be sync'd too?
      new JX.Workflow(this.getLoadThreadURI())
        .setData(params)
        .setHandler(handler)
        .start();
    },

    sendMessage: function(form, params) {
      // don't bother sending up text if there is nothing to submit
      var textarea = JX.DOM.find(form, 'textarea');
      if (!textarea.value.length) {
        return;
      }
      params = this._getParams(params);

      var keep_enabled = true;

      var workflow = JX.Workflow.newFromForm(form, params, keep_enabled)
        .setHandler(JX.bind(this, function(r) {
          if (this._shouldUpdateDOM(r)) {
            this._markUpdated(r);

            this._didSendMessageCallback(r);
          } else if (r.non_update) {
            this._didSendMessageCallback(r, true);
          }
        }));
      this.syncWorkflow(workflow, 'finally');

      this._willSendMessageCallback();
    },

    handleDraftKeydown: function(e) {
      var form = e.getNode('tag:form');
      var data = e.getNodeData('tag:form');

      if (!data.preview) {
        var uri = '/conpherence/update/' + this._loadedThreadID + '/';
        data.preview = new JX.PhabricatorShapedRequest(
          uri,
          JX.bag,
          JX.bind(this, function () {
            var data = JX.DOM.convertFormToDictionary(form);
            data.action = 'draft';
            data = this._getParams(data);
            return data;
          }));
      }
      data.preview.trigger();
    }
  },

  statics: {
    _instance: null,

    getInstance: function() {
      var self = JX.ConpherenceThreadManager;
      if (!self._instance) {
        return null;
      }
      return self._instance;
    }
  }

});
