<?php

class VacationStorageManager {

  public function trackTime($user, $numHours, $numMinutes, $day, $month, $year) {
      $userID = $user->getID();
      $numMinutesToTrack = $this->getNumMinutesToTrack($numHours, $numMinutes);
      
      $timestampWhenTrackedFor = strtotime($year . '-' . $month . '-' . $day);
      
      $dao = new VacationVacationDay();
      $connection = id($dao)->establishConnection('w');
      $guard = AphrontWriteGuard::beginScopedUnguardedWrites();
      $dao->openTransaction();
      
      queryfx($connection, 'INSERT INTO vacation_day SET 
        numMinutes = %d, realDateWhenTracked = %d, userID = %d, dateWhenTrackedFor = %d', 
          $numMinutesToTrack, time(), $userID, $timestampWhenTrackedFor);
      
      $dao->saveTransaction();
      unset($guard);
  }
  
  public static function getNumMinutesTrackedToday($user) {
      $todayTimestamp = VacationTimeUtils::getTodayTimestamp();
      $dao = new VacationVacationDay();
      $connection = id($dao)->establishConnection('w');

      $rows = queryfx_all(
          $connection,
          'SELECT numMinutes FROM vacation_day WHERE dateWhenTrackedFor = %d AND userID = %d',
          $todayTimestamp,
          $user->getID());
      
      $totalMinutes = 0;
      foreach ($rows as $row) {
          $totalMinutes += $row['numMinutes'];
      }
      return $totalMinutes;
  }

  public static function getNumMinutesTrackedFromDate($user, $date) {
    $dao = new VacationVacationDay();
    $connection = id($dao)->establishConnection('w');

    $rows = queryfx_all(
        $connection,
        'SELECT numMinutes FROM vacation_day WHERE dateWhenTrackedFor = %d AND userID = %d',
        $date,
        $user->getID());
    
    $totalMinutes = 0;
    foreach ($rows as $row) {
        $totalMinutes += $row['numMinutes'];
    }
    return $totalMinutes;
}
  
  private function getNumMinutesToTrack($numHours, $numMinutes) {
      return $numHours * 60 + $numMinutes;
  }
}
