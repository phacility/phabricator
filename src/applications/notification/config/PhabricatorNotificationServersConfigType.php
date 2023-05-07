<?php

final class PhabricatorNotificationServersConfigType
  extends PhabricatorJSONConfigType {

  const TYPEKEY = 'cluster.notifications';

  public function validateStoredValue(
    PhabricatorConfigOption $option,
    $value) {

    foreach ($value as $index => $spec) {
      if (!is_array($spec)) {
        throw $this->newException(
          pht(
            'Notification server configuration is not valid: each entry in '.
            'the list must be a dictionary describing a service, but '.
            'the value with index "%s" is not a dictionary.',
            $index));
      }
    }

    $has_admin = false;
    $has_client = false;
    $map = array();
    foreach ($value as $index => $spec) {
      try {
        PhutilTypeSpec::checkMap(
          $spec,
          array(
            'type' => 'string',
            'host' => 'string',
            'port' => 'int',
            'protocol' => 'string',
            'path' => 'optional string',
            'disabled' => 'optional bool',
          ));
      } catch (Exception $ex) {
        throw $this->newException(
          pht(
            'Notification server configuration has an invalid service '.
            'specification (at index "%s"): %s.',
            $index,
            $ex->getMessage()));
      }

      $type = $spec['type'];
      $host = $spec['host'];
      $port = $spec['port'];
      $protocol = $spec['protocol'];
      $disabled = idx($spec, 'disabled');

      switch ($type) {
        case 'admin':
          if (!$disabled) {
            $has_admin = true;
          }
          break;
        case 'client':
          if (!$disabled) {
            $has_client = true;
          }
          break;
        default:
          throw $this->newException(
            pht(
              'Notification server configuration describes an invalid '.
              'host ("%s", at index "%s") with an unrecognized type ("%s"). '.
              'Valid types are "%s" or "%s".',
              $host,
              $index,
              $type,
              'admin',
              'client'));
      }

      switch ($protocol) {
        case 'http':
        case 'https':
          break;
        default:
          throw $this->newException(
            pht(
              'Notification server configuration describes an invalid '.
              'host ("%s", at index "%s") with an invalid protocol ("%s"). '.
              'Valid protocols are "%s" or "%s".',
              $host,
              $index,
              $protocol,
              'http',
              'https'));
      }

      $path = idx($spec, 'path');
      if ($type == 'admin' && $path !== null && strlen($path)) {
        throw $this->newException(
          pht(
            'Notification server configuration describes an invalid host '.
            '("%s", at index "%s"). This is an "admin" service but it has a '.
            '"path" property. This property is only valid for "client" '.
            'services.',
            $host,
            $index));
      }

      // We can't guarantee that you didn't just give the same host two
      // different names in DNS, but this check can catch silly copy/paste
      // mistakes.
      $key = "{$host}:{$port}";
      if (isset($map[$key])) {
        throw $this->newException(
          pht(
            'Notification server configuration is invalid: it describes the '.
            'same host and port ("%s") multiple times. Each host and port '.
            'combination should appear only once in the list.',
            $key));
      }
      $map[$key] = true;
    }

    if ($value) {
      if (!$has_admin) {
        throw $this->newException(
          pht(
            'Notification server configuration is invalid: it does not '.
            'specify any enabled servers with type "admin". Notifications '.
            'require at least one active "admin" server.'));
      }

      if (!$has_client) {
        throw $this->newException(
          pht(
            'Notification server configuration is invalid: it does not '.
            'specify any enabled servers with type "client". Notifications '.
            'require at least one active "client" server.'));
      }
    }
  }

}
