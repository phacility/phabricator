package {

  import flash.events.Event;
  import flash.events.IOErrorEvent;
  import flash.events.ProgressEvent;
  import flash.events.SecurityErrorEvent;
  import flash.events.TimerEvent;
  import flash.net.Socket;
  import flash.utils.ByteArray;
  import flash.utils.Dictionary;
  import flash.utils.Timer;
  import vegas.strings.JSON;


  final public class AphlictMaster extends Aphlict {

    /**
     * The pool of connected clients.
     */
    private var clients:Dictionary;

    /**
     * A timer used to trigger periodic events.
     */
    private var timer:Timer;

    /**
     * The interval after which clients will be considered dead and removed
     * from the pool.
     */
    public static const PURGE_INTERVAL:Number = 3 * AphlictClient.INTERVAL;

    /**
     * The hostname for the Aphlict Server.
     */
    private var remoteServer:String;

    /**
     * The port number for the Aphlict Server.
     */
    private var remotePort:Number;

    /**
     * A dictionary mapping PHID to subscribed clients.
     */
    private var subscriptions:Dictionary;

    private var socket:Socket;
    private var readBuffer:ByteArray;

    private var status:String;
    private var statusCode:String;


    public function AphlictMaster(server:String, port:Number) {
      super();

      this.remoteServer = server;
      this.remotePort   = port;

      this.clients = new Dictionary();
      this.subscriptions = new Dictionary();

      // Connect to the Aphlict Server.
      this.recv.connect('aphlict_master');
      this.connectToServer();

      // Start a timer and regularly purge dead clients.
      this.timer = new Timer(AphlictMaster.PURGE_INTERVAL);
      this.timer.addEventListener(TimerEvent.TIMER, this.purgeClients);
      this.timer.start();
    }

    /**
     * Register a @{class:AphlictClient}.
     */
    public function register(client:String):void {
      if (!this.clients[client]) {
        this.log('Registering client: ' + client);
        this.clients[client] = new Date().getTime();

        this.send.send(client, 'setStatus', this.status, this.statusCode);
      }
    }

    /**
     * Purge stale client connections from the client pool.
     */
    private function purgeClients(event:TimerEvent):void {
      for (var client:String in this.clients) {
        var checkin:Number = this.clients[client];

        if (new Date().getTime() - checkin > AphlictMaster.PURGE_INTERVAL) {
          this.log('Purging client: ' + client);
          delete this.clients[client];

          this.log('Removing client subscriptions: ' + client);
          this.unsubscribeAll(client);
        }
      }
    }

    /**
     * Clients will regularly "ping" the master to let us know that they are
     * still alive. We will "pong" them back to let the client know that the
     * master is still alive.
     */
    public function ping(client:String):void {
      this.clients[client] = new Date().getTime();
      this.send.send(client, 'pong');
    }

    private function connectToServer():void {
      this.setStatusOnClients('connecting');

      var socket:Socket = new Socket();

      socket.addEventListener(Event.CONNECT,              didConnectSocket);
      socket.addEventListener(Event.CLOSE,                didCloseSocket);
      socket.addEventListener(ProgressEvent.SOCKET_DATA,  didReceiveSocket);

      socket.addEventListener(IOErrorEvent.IO_ERROR,      didIOErrorSocket);
      socket.addEventListener(
        SecurityErrorEvent.SECURITY_ERROR,
        didSecurityErrorSocket);

      socket.connect(this.remoteServer, this.remotePort);

      this.readBuffer = new ByteArray();
      this.socket = socket;
    }

    private function didConnectSocket(event:Event):void {
      this.setStatusOnClients('connected');

      // Send subscriptions
      var phids = new Array();
      for (var phid:String in this.subscriptions) {
        phids.push(phid);
      }

      if (phids.length) {
        this.sendSubscribeCommand(phids);
      }
    }

    private function didCloseSocket(event:Event):void {
      this.setStatusOnClients('error', 'error.flash.disconnected');
    }

    private function didIOErrorSocket(event:IOErrorEvent):void {
      this.externalInvoke('error', event.text);
    }

    private function didSecurityErrorSocket(event:SecurityErrorEvent):void {
      var text = event.text;

      // This is really gross but there doesn't seem to be anything else
      // on the object which gives us an error code.
      if (text.match(/^Error #2048/)) {
        this.setStatusOnClients('error', 'error.flash.xdomain');
      }

      this.error(text);
    }

    public function subscribe(client:String, phids:Array):void {
      var newPHIDs = new Array();

      for (var i:String in phids) {
        var phid = phids[i];
        if (!this.subscriptions[phid]) {
          this.subscriptions[phid] = new Dictionary();
          newPHIDs.push(phid);
        }
        this.subscriptions[phid][client] = true;
      }

      if (newPHIDs.length) {
        this.sendSubscribeCommand(newPHIDs);
      }
    }

    private function getSubscriptions(client:String):Array {
      var subscriptions = new Array();

      for (var phid:String in this.subscriptions) {
        var clients = this.subscriptions[phid];
        if (clients[client]) {
          subscriptions.push(phid);
        }
      }

      return subscriptions;
    }

    public function unsubscribeAll(client:String):void {
      this.unsubscribe(client, this.getSubscriptions(client));
    }

    public function unsubscribe(client:String, phids:Array):void {
      var oldPHIDs = new Array();

      for (var i:String in phids) {
        var phid = phids[i];

        if (!this.subscriptions[phid]) {
          continue;
        }

        delete this.subscriptions[phid][client];

        var empty = true;
        for (var key:String in this.subscriptions[phid]) {
          empty = false;
        }

        if (empty) {
          delete this.subscriptions[phid];
          oldPHIDs.push(phid);
        }
      }

      if (oldPHIDs.length) {
        this.sendUnsubscribeCommand(oldPHIDs);
      }
    }

    private function sendSubscribeCommand(phids:Array):void {
      var msg:Dictionary = new Dictionary();
      msg['command'] = 'subscribe';
      msg['data'] = phids;

      this.log('Sending subscribe command to server.');
      this.socket.writeUTF(vegas.strings.JSON.serialize(msg));
      this.socket.flush();
    }

    private function sendUnsubscribeCommand(phids:Array):void {
      var msg:Dictionary = new Dictionary();
      msg['command'] = 'unsubscribe';
      msg['data'] = phids;

      this.log('Sending subscribe command to server.');
      this.socket.writeUTF(vegas.strings.JSON.serialize(msg));
      this.socket.flush();
    }

    private function didReceiveSocket(event:Event):void {
      try {
        var b:ByteArray = this.readBuffer;
        this.socket.readBytes(b, b.length);

        do {
          b = this.readBuffer;
          b.position = 0;

          if (b.length <= 8) {
            break;
          }

          var msg_len:Number = parseInt(b.readUTFBytes(8), 10);
          if (b.length >= msg_len + 8) {
            var bytes:String = b.readUTFBytes(msg_len);
            var data:Object = vegas.strings.JSON.deserialize(bytes);
            var t:ByteArray = new ByteArray();
            t.writeBytes(b, msg_len + 8);
            this.readBuffer = t;

            // Send the message to all clients.
            for (var client:String in this.clients) {
              var subscribed = false;

              for (var i:String in data.subscribers) {
                var phid = data.subscribers[i];

                if (this.subscriptions[phid] &&
                    this.subscriptions[phid][client]) {
                  subscribed = true;
                  break;
                }
              }

              if (subscribed) {
                this.log('Sending message to client: ' + client);
                this.send.send(client, 'receiveMessage', data);
              }
            }
          } else {
            break;
          }
        } while (true);
      } catch (err:Error) {
        this.error(err);
      }
    }

    private function setStatusOnClients(
      status:String,
      code:String = null):void {

      this.status = status;
      this.statusCode = code;

      for (var client:String in this.clients) {
        this.send.send(client, 'setStatus', status, code);
      }
    }

  }

}
