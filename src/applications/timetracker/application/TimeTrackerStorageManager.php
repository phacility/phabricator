<?php

class TimeTrackerStorageManager {

  public function trackTime($user, $numHours, $numMinutes, $day, $month, $year, $projectPHID) {
      $userID = $user->getID();
      $numMinutesToTrack = $this->getNumMinutesToTrack($numHours, $numMinutes);
      
      $timestampWhenTrackedFor = strtotime($year . '-' . $month . '-' . $day);
      
      $dao = new TimeTrackerTrackedTime();
      $connection = id($dao)->establishConnection('w');
      $guard = AphrontWriteGuard::beginScopedUnguardedWrites();
      $dao->openTransaction();
      
      queryfx($connection, 'INSERT INTO timetracker_trackedtime SET 
        numMinutes = %d, realDateWhenTracked = %d, userID = %d, dateWhenTrackedFor = %d, projectPHID = %s', 
          $numMinutesToTrack, time(), $userID, $timestampWhenTrackedFor, $projectPHID);
      
      $dao->saveTransaction();
      unset($guard);
  }
  
  public static function getNumMinutesTrackedToday($user) {
      $todayTimestamp = TimeTrackerTimeUtils::getTodayTimestamp();
      $dao = new TimeTrackerTrackedTime();
      $connection = id($dao)->establishConnection('w');

      $rows = queryfx_all(
          $connection,
          'SELECT numMinutes FROM timetracker_trackedtime WHERE dateWhenTrackedFor = %d AND userID = %d',
          $todayTimestamp,
          $user->getID());
      
      $totalMinutes = 0;
      foreach ($rows as $row) {
          $totalMinutes += $row['numMinutes'];
      }
      return $totalMinutes;
  }

  public static function getNumMinutesTrackedFromDate($user, $date, $projectPHID) {
    $dao = new TimeTrackerTrackedTime();
    $connection = id($dao)->establishConnection('w');

    $rows = queryfx_all(
        $connection,
        'SELECT numMinutes FROM timetracker_trackedtime WHERE dateWhenTrackedFor = %d AND userID = %d AND projectPHID = %s',
        $date,
        $user->getID(),
        $projectPHID);
    
    $totalMinutes = 0;
    foreach ($rows as $row) {
        $totalMinutes += $row['numMinutes'];
    }
    return $totalMinutes;
}

  
  private function getNumMinutesToTrack($numHours, $numMinutes){
      return $numHours * 60 + $numMinutes;
  }

  public static function getNameSelectedProject($projectPHID){
        $dao = new PhabricatorProject();
        $connection = id($dao)->establishConnection('w');
           
        $projectName = queryfx_one(
            $connection,
            'SELECT name FROM project WHERE phid = %s', $projectPHID);
    
        return $projectName['name'];       
  }

  public static function getLastProjectTracked($user){

    $dao = new TimeTrackerTrackedTime();
    $connection = id($dao)->establishConnection('w');
       
    $projectPHID = queryfx_one(
        $connection,
        'SELECT projectPHID FROM timetracker_trackedtime WHERE userID = %d ORDER BY dateWhenTrackedFor DESC LIMIT 1', $user->getID());

    return $projectPHID['projectPHID'];       
  }
}
