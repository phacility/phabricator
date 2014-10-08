<?php

abstract class ChatLogConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorChatLogApplication');
  }

}
