<?php

class ManagementPanelStorageManager {

  public static function getUserID($userPhid){
    $dao = new PhabricatorUser();
    $connection = id($dao)->establishConnection('w');
       
    $userID = queryfx_one(
        $connection,
        'SELECT id FROM user WHERE phid = %s', $userPhid);

    return $userID['id'];    

  }

  public static function getUserName($userID){
    $dao = new PhabricatorUser();
    $connection = id($dao)->establishConnection('w');
       
    $userName = queryfx_one(
        $connection,
        'SELECT realName FROM user WHERE id = %s', $userID);

    return $userName['realName'];    

  }

  public static function getUserDateCreated($userID){
    $dao = new PhabricatorUser();
    $connection = id($dao)->establishConnection('w');
       
    $userName = queryfx_one(
        $connection,
        'SELECT dateCreated FROM user WHERE id = %s', $userID);

    return $userName['dateCreated'];    
  }

  
  private function getNumMinutesToTrack($numHours, $numMinutes) {
      return $numHours * 60 + $numMinutes;
  }
}
