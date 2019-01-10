<?php

abstract class PhabricatorMailExternalMessage
  extends Phobject {

  final public function getMessageType() {
    return $this->getPhobjectClassConstant('MESSAGETYPE');
  }

}
