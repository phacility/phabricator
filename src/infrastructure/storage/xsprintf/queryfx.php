<?php

function queryfx(AphrontDatabaseConnection $conn, $sql /* , ... */) {
  $argv = func_get_args();
  $query = call_user_func_array('qsprintf', $argv);

  $conn->setLastActiveEpoch(time());
  $conn->executeQuery($query);
}

function queryfx_all(AphrontDatabaseConnection $conn, $sql /* , ... */) {
  $argv = func_get_args();
  call_user_func_array('queryfx', $argv);
  return $conn->selectAllResults();
}

function queryfx_one(AphrontDatabaseConnection $conn, $sql /* , ... */) {
  $argv = func_get_args();
  $ret = call_user_func_array('queryfx_all', $argv);
  if (count($ret) > 1) {
    throw new AphrontCountQueryException(
      pht('Query returned more than one row.'));
  } else if (count($ret)) {
    return reset($ret);
  }
  return null;
}
