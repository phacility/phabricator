<?php

abstract class PhabricatorChatLogDAO extends PhabricatorLiskDAO {

  public function getApplicationName() {
    return 'chatlog';
  }

}
