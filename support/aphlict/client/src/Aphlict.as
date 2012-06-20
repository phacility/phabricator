package {

  import flash.net.*;
  import flash.utils.*;
  import flash.media.*;
  import flash.display.*;
  import flash.events.*;
  import flash.external.ExternalInterface;

  import vegas.strings.JSON;

  public class Aphlict extends Sprite {

    private var client:String;

    private var socket:Socket;
    private var readBuffer:ByteArray;

    private var remoteServer:String;
    private var remotePort:Number;

    public function Aphlict() {
      super();

      ExternalInterface.addCallback('connect', this.externalConnect);
      ExternalInterface.call(
        'JX.Stratcom.invoke',
        'aphlict-component-ready',
        null,
        {});
    }

    public function externalConnect(server:String, port:Number):void {
      this.externalInvoke('connect');

      this.remoteServer = server;
      this.remotePort   = port;

      this.connectToServer();
    }


    public function connectToServer():void {
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

          this.receiveMessage(data);
        } else {
          break;
        }
      } while (true);

    }

    public function receiveMessage(msg:Object):void {
      this.externalInvoke('receive', msg);
    }

    public function externalInvoke(type:String, object:Object = null):void {
      ExternalInterface.call('JX.Aphlict.didReceiveEvent', type, object);
    }

    public function log(message:String):void {
      ExternalInterface.call('console.log', message);
    }

  }

}
