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

    private var socket:Socket;
    private var readBuffer:ByteArray;


    public function AphlictMaster(server:String, port:Number) {
      super();

      this.remoteServer = server;
      this.remotePort   = port;

      // Connect to the Aphlict Server.
      this.recv.connect('aphlict_master');
      this.connectToServer();

      this.clients = new Dictionary();

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
      this.externalInvoke('connected');
    }

    private function didCloseSocket(event:Event):void {
      this.externalInvoke('close');
    }

    private function didIOErrorSocket(event:IOErrorEvent):void {
      this.externalInvoke('error', event.text);
    }

    private function didSecurityErrorSocket(event:SecurityErrorEvent):void {
      this.externalInvoke('error', event.text);
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
              this.log('Sending message to client: ' + client);
              this.send.send(client, 'receiveMessage', data);
            }
          } else {
            break;
          }
        } while (true);
      } catch (err:Error) {
        this.error(err);
      }
    }

  }

}
