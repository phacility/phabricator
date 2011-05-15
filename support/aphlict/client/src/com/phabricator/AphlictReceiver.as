package com.phabricator {

  public class AphlictReceiver {

    private var core:Object;

    public function AphlictReceiver(core:Object) {
      this.core = core;
    }

    public function remainLoyal():void {
      this.core.remainLoyal();
    }

    public function becomeLoyal(subject:String):void {
      this.core.becomeLoyal(subject);
    }

    public function receiveMessage(msg:Object):void {
      this.core.receiveMessage(msg);
    }

  }

}