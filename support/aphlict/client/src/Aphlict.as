package {

  import flash.net.*;
  import flash.utils.*;
  import flash.media.*;
  import flash.display.*;
  import flash.events.*;
  import flash.external.ExternalInterface;

  import com.phabricator.*;

  import vegas.strings.JSON;

  public class Aphlict extends Sprite {

    private var client:String;

    private var master:LocalConnection;
    private var recv:LocalConnection;
    private var send:LocalConnection;

    private var receiver:AphlictReceiver;
    private var loyalUntil:Number = 0;
    private var subjects:Array;
    private var frequency:Number = 100;

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

      this.master = null;
      this.receiver = new AphlictReceiver(this);
      this.subjects = [];

      this.send = new LocalConnection();

      this.recv = new LocalConnection();
      this.recv.client = this.receiver;
      for (var ii:Number = 0; ii < 32; ii++) {
        try {
          this.recv.connect('aphlict_subject_' + ii);
          this.client = 'aphlict_subject_' + ii;
        } catch (x:Error) {
          // Some other Aphlict client is holding that ID.
        }
      }

      if (!this.client) {
        // Too many clients open already, just exit.
        return;
      }

      this.usurp();
    }

    private function usurp():void {
      if (this.master) {
        for (var ii:Number = 0; ii < this.subjects.length; ii++) {
          if (this.subjects[ii] == this.client) {
            continue;
          }
          this.send.send(this.subjects[ii], 'remainLoyal');
        }
      } else if (this.loyalUntil < new Date().getTime()) {
        var recv:LocalConnection = new LocalConnection();
        recv.client = this.receiver;
        try {
          recv.connect('aphlict_master');
          this.master = recv;
          this.subjects = [this.client];

          this.connectToServer();

        } catch (x:Error) {
          // Can't become the master.
        }

        if (!this.master) {
          this.send.send('aphlict_master', 'becomeLoyal', this.client);
          this.remainLoyal();
        }
      }
      setTimeout(this.usurp, this.frequency);
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

          for (var ii:Number = 0; ii < this.subjects.length; ii++) {
            this.send.send(this.subjects[ii], 'receiveMessage', data);
          }
        } else {
          break;
        }
      } while (true);

    }

    public function remainLoyal():void {
      this.loyalUntil = new Date().getTime() + (2 * this.frequency);
    }

    public function becomeLoyal(subject:String):void {
      this.subjects.push(subject);
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