package {

  import flash.display.Sprite;
  import flash.external.ExternalInterface;
  import flash.net.LocalConnection;


  public class Aphlict extends Sprite {

    /**
     * A transport channel used to receive data.
     */
    protected var recv:LocalConnection;

    /**
     * A transport channel used to send data.
     */
    protected var send:LocalConnection;


    public function Aphlict() {
      super();

      this.recv = new LocalConnection();
      this.recv.client = this;

      this.send = new LocalConnection();
    }

    final protected function externalInvoke(
      type:String,
      object:Object = null):void {

      ExternalInterface.call('JX.Aphlict.didReceiveEvent', type, object);
    }

    final protected function error(error:Error):void {
      this.externalInvoke('error', error.toString());
    }

    final protected function log(message:String):void {
      this.externalInvoke('log', message);
    }

  }

}
