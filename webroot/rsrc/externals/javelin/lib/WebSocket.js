/**
 * @requires javelin-install
 * @provides javelin-websocket
 * @javelin
 */

/**
 * Wraps a WebSocket.
 */
JX.install('WebSocket', {

  construct: function(uri) {
    this.setURI(uri);
    this._resetDelay();
  },

  properties: {
    URI: null,

    /**
     * Called when a connection is established or re-established after an
     * interruption.
     */
    openHandler: null,

    /**
     * Called when a message is received.
     */
    messageHandler: null,

    /**
     * Called when the connection is closed.
     *
     * You can return `true` to prevent the socket from reconnecting.
     */
    closeHandler: null
  },

  members: {
    /**
     * The underlying WebSocket.
     */
    _socket: null,

    /**
     * Is the socket connected?
     */
    _isOpen: false,

    /**
     * Has the caller asked us to close?
     *
     * By default, we reconnect when the connection is interrupted.
     * This stops us from reconnecting if @{method:close} was called.
     */
    _shouldClose: false,

    /**
     * Number of milliseconds to wait after a connection failure before
     * attempting to reconnect.
     */
    _delayUntilReconnect: null,


    /**
     * Open the connection.
     */
    open: function() {
      if (!window.WebSocket) {
        return;
      }

      this._shouldClose = false;

      this._socket = new WebSocket(this.getURI());
      this._socket.onopen = JX.bind(this, this._onopen);
      this._socket.onmessage = JX.bind(this, this._onmessage);
      this._socket.onclose = JX.bind(this, this._onclose);
    },


    /**
     * Send a message.
     *
     * If the connection is not currently open, this method has no effect and
     * the messages vanishes into the ether.
     */
    send: function(message) {
      if (this._isOpen) {
        this._socket.send(message);
      }
    },


    /**
     * Close the connection.
     */
    close: function() {
      if (!this._isOpen) {
        return;
      }
      this._shouldClose = true;
      this._socket.close();
    },


    /**
     * Disconnect abruptly, prompting a reconnect.
     */
    reconnect: function() {
      if (!this._isOpen) {
        return;
      }

      this._socket.close();
    },


    /**
     * Get the current reconnect delay (in milliseconds).
     */
    getReconnectDelay: function() {
      return this._delayUntilReconnect;
    },


    /**
     * Callback for connection open.
     */
    _onopen: function() {
      this._isOpen = true;

      // Since we connected successfully, reset the reconnect delay to 0.

      // This will make us try the first reconnect immediately after a
      // connection failure. This limits downtime in cases like a service
      // restart or a load balancer connection timeout.

      // We only do an immediate retry after a successful connection.
      this._delayUntilReconnect = 0;

      var handler = this.getOpenHandler();
      if (handler) {
        handler();
      }
    },


    /**
     * Reset the reconnect delay to its base value.
     */
    _resetDelay: function() {
      this._delayUntilReconnect = 2000;
    },


    /**
     * Callback for message received.
     */
    _onmessage: function(e) {
      var data = e.data;

      var handler = this.getMessageHandler();
      if (handler) {
        handler(data);
      }
    },


    /**
     * Callback for connection close.
     */
    _onclose: function() {
      this._isOpen = false;

      var done = false;

      var handler = this.getCloseHandler();
      if (handler) {
        done = handler();
      }

      // If we didn't explicitly see a close() call and the close handler
      // did not return `true` to stop the reconnect, wait a little while
      // and try to reconnect.
      if (!done && !this._shouldClose) {
        setTimeout(JX.bind(this, this._reconnect), this._delayUntilReconnect);
      }
    },


    /**
     * Reconnect an interrupted socket.
     */
    _reconnect: function() {
      // Increase the reconnect delay by a factor of 2. If we fail to open the
      // connection, the close handler will send us back here. We'll reconnect
      // more and more slowly until we eventually get a valid connection.
      if (!this._delayUntilReconnect) {
        this._resetDelay();
      } else {
        this._delayUntilReconnect = this._delayUntilReconnect * 2;
      }

      // Max out at 5 minutes between attempts.
      this._delayUntilReconnect = Math.min(this._delayUntilReconnect, 300000);
      this.open();
    }

  }
});
