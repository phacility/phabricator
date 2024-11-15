<?php

class ManagementPanelUser {
  /* CAREFULLY!
  This is a forced decision (a crutch).
  This class is just a stub tool for example the class "PhabricatorUser", to use the functions described below.
  It is strongly discouraged to use it inside application other than this one, as it may break other phabricator applications. */
  private $userID;

  public function __construct($id) {
    $this->userID = $id;
}
  public function getID(){
    return $this->userID;
  }

  public function getDateCreated(){
    $dataCreated = ManagementPanelStorageManager::getUserDateCreated($this->userID);
    return $dataCreated;
  }
  

}
