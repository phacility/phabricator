<?php

class MozLogger extends Phobject {

  public static function log($message, $type='mozphab.unspecified', $detail=array()) {

    // Create a master object based on detail and defaults
    $mozlog = self::merge_arrays(
      $detail,
      array(
        'Type' => $type,
        'Fields' => array('msg' => $message)
      )
    );
    $json = json_encode($mozlog, JSON_FORCE_OBJECT);

    // Write to error log
    error_log("$json\n", 0);

    // Return the $message so it can be used in exception calls
    return $message;
  }

  public static function merge_arrays($detailArray, $messageArray) {
    $server = str_replace(
      array('http:', 'https:', '/'),
      '',
      PhabricatorEnv::getEnvConfig('phabricator.base-uri')
    );
    $defaults = array(
      'Timestamp' => time(),
      'Type' => '',
      'Logger' => 'MozPhab',
      'Server' => $server,
      'Hostname' => gethostname(),
      'EnvVersion' => '1.0',
      'Severity' => '3',
      'Pid' => '0',
      'Fields' => array(
        'agent' => '',
        'errno' => '0',
        'method' => 'GET',
        'msg' => 'Message not provided',
        'path' => '',
        't' => '',
        'uid' => ''
      )
    );

    $merged = array_replace_recursive(
      $defaults,
      $detailArray,
      $messageArray
    );

    return $merged;
  }
}
