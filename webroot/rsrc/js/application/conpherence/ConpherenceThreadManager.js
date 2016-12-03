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
    _transactionIDMap: null,
    _transactionCache: null,
    _canEditLoadedThread: null,
    _updating:  null,
    _messagesRootCallback: JX.bag,
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

    _updateTransactionIDMap: function(transactions) {
      var loaded_id = this.getLoadedThreadID();
      if (!this._transactionIDMap[loaded_id]) {
        this._transactionIDMap[this._loadedThreadID] = {};
      }
      var loaded_transaction_ids = this._transactionIDMap[loaded_id];
      var transaction;
      for (var ii = 0; ii < transactions.length; ii++) {
        transaction = transactions[ii];
        loaded_transaction_ids[JX.Stratcom.getData(transaction).id] = 1;
      }
      this._transactionIDMap[this._loadedThreadID] = loaded_transaction_ids;
      return this;
    },

    _updateTransactionCache: function(transactions) {
      var transaction;
      for (var ii = 0; ii < transactions.length; ii++) {
        transaction = transactions[ii];
        this._transactionCache[JX.Stratcom.getData(transaction).id] =
          transaction;
      }
      return this;
    },

    _getLoadedTransactions: function() {
      var loaded_id = this.getLoadedThreadID();
      var loaded_tx_ids = JX.keys(this._transactionIDMap[loaded_id]);
      loaded_tx_ids.sort(function (a, b) {
        var x = parseFloat(a);
        var y = parseFloat(b);
        if (x > y) {
          return 1;
        }
        if (x < y) {
          return -1;
        }
        return 0;
      });
      var transactions = [];
      for (var ii = 0; ii < loaded_tx_ids.length; ii++) {
        transactions.push(this._transactionCache[loaded_tx_ids[ii]]);
      }
      return transactions;
    },

    _deleteTransactionCaches: function(id) {
      delete this._transactionCache[id];
      delete this._transactionIDMap[this._loadedThreadID][id];

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

    setMessagesRootCallback: function(callback) {
      this._messagesRootCallback = callback;
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
      if (this._latestTransactionID) {
        base_params.latest_transaction_id = this._latestTransactionID;
      }
      return base_params;
    },

    start: function() {

      this._transactionIDMap = {};
      this._transactionCache = {};

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

      JX.Stratcom.listen(
        'click',
        'show-older-messages',
        JX.bind(this, function(e) {
          e.kill();
          var data = e.getNodeData('show-older-messages');

          var node = e.getNode('show-older-messages');
          JX.DOM.setContent(node, 'Loading...');
          JX.DOM.alterClass(
            node,
            'conpherence-show-more-messages-loading',
            true);

          new JX.Workflow(this._getMoreMessagesURI(), data)
            .setHandler(JX.bind(this, function(r) {
              this._deleteTransactionCaches(JX.Stratcom.getData(node).id);
              JX.DOM.remove(node);
              this._updateTransactions(r);
            })).start();
        }));
      JX.Stratcom.listen(
        'click',
        'show-newer-messages',
        JX.bind(this, function(e) {
          e.kill();
          var data = e.getNodeData('show-newer-messages');
          var node = e.getNode('show-newer-messages');
          JX.DOM.setContent(node, 'Loading...');
          JX.DOM.alterClass(
            node,
            'conpherence-show-more-messages-loading',
            true);

          new JX.Workflow(this._getMoreMessagesURI(), data)
          .setHandler(JX.bind(this, function(r) {
            this._deleteTransactionCaches(JX.Stratcom.getData(node).id);
            JX.DOM.remove(node);
            this._updateTransactions(r);
          })).start();
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

    _updateDOM: function(r) {
      this._updateTransactions(r);

      this._updating.knownID = r.latest_transaction_id;
      this._latestTransactionID = r.latest_transaction_id;
      JX.Stratcom.invoke(
        'conpherence-redraw-aphlict',
        null,
        r.aphlictDropdownData);
    },

    _updateTransactions: function(r) {
      var new_transactions = JX.$H(r.transactions).getFragment().childNodes;
      this._updateTransactionIDMap(new_transactions);
      this._updateTransactionCache(new_transactions);

      var transactions = this._getLoadedTransactions();

      JX.DOM.setContent(this._messagesRootCallback(), transactions);
    },

    cacheCurrentTransactions: function() {
      var root = this._messagesRootCallback();
      var transactions = JX.DOM.scry(
        root ,
        'div',
        'conpherence-transaction-view');
      this._updateTransactionIDMap(transactions);
      this._updateTransactionCache(transactions);
    },

    _updateThread: function() {
      var params = this._getParams({
        action: 'load',
      });

      var workflow = new JX.Workflow(this._getUpdateURI())
        .setData(params)
        .setHandler(JX.bind(this, function(r) {
          if (this._shouldUpdateDOM(r)) {
            this._updateDOM(r);
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
            this._updateDOM(r);
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
        this.cacheCurrentTransactions();

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
      var inputs = JX.DOM.scry(form, 'input');
      var block_empty = true;
      for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].type != 'hidden') {
          continue;
        }
        if (inputs[i].name == 'action' && inputs[i].value == 'join_room') {
          block_empty = false;
          continue;
        }
      }
      // don't bother sending up text if there is nothing to submit
      var textarea = JX.DOM.find(form, 'textarea');
      if (block_empty && !textarea.value.length) {
        return;
      }
      params = this._getParams(params);

      var keep_enabled = true;

      var workflow = JX.Workflow.newFromForm(form, params, keep_enabled)
        .setHandler(JX.bind(this, function(r) {
          if (this._shouldUpdateDOM(r)) {
            this._updateDOM(r);
            this._didSendMessageCallback(r);
          } else if (r.non_update) {
            this._didSendMessageCallback(r, true);
          }
        }));
      this.syncWorkflow(workflow, 'finally');
      textarea.value = '';

      this._willSendMessageCallback();
    },

    handleDraftKeydown: function(e) {
      var form = e.getNode('tag:form');
      var data = e.getNodeData('tag:form');

      if (!data.preview) {
        data.preview = new JX.PhabricatorShapedRequest(
          this._getUpdateURI(),
          JX.bag,
          JX.bind(this, function () {
            var data = JX.DOM.convertFormToDictionary(form);
            data.action = 'draft';
            data = this._getParams(data);
            return data;
          }));
      }
      data.preview.trigger();
    },

    _getUpdateURI: function() {
      return '/conpherence/update/' + this._loadedThreadID + '/';
    },

    _getMoreMessagesURI: function() {
      return '/conpherence/' + this._loadedThreadID + '/';
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
