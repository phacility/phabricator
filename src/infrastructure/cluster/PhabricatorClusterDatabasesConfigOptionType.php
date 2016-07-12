<?php

final class PhabricatorClusterDatabasesConfigOptionType
  extends PhabricatorConfigJSONOptionType {

  public function validateOption(PhabricatorConfigOption $option, $value) {
    if (!is_array($value)) {
      throw new Exception(
        pht(
          'Database cluster configuration is not valid: value must be a '.
          'list of database hosts.'));
    }

    foreach ($value as $index => $spec) {
      if (!is_array($spec)) {
        throw new Exception(
          pht(
            'Database cluster configuration is not valid: each entry in the '.
            'list must be a dictionary describing a database host, but '.
            'the value with index "%s" is not a dictionary.',
            $index));
      }
    }

    $masters = array();
    $map = array();
    foreach ($value as $index => $spec) {
      try {
        PhutilTypeSpec::checkMap(
          $spec,
          array(
            'host' => 'string',
            'role' => 'string',
            'port' => 'optional int',
            'user' => 'optional string',
            'pass' => 'optional string',
            'disabled' => 'optional bool',
          ));
      } catch (Exception $ex) {
        throw new Exception(
          pht(
            'Database cluster configuration has an invalid host '.
            'specification (at index "%s"): %s.',
            $index,
            $ex->getMessage()));
      }

      $role = $spec['role'];
      $host = $spec['host'];
      $port = idx($spec, 'port');

      switch ($role) {
        case 'master':
        case 'replica':
          break;
        default:
          throw new Exception(
            pht(
              'Database cluster configuration describes an invalid '.
              'host ("%s", at index "%s") with an unrecognized role ("%s"). '.
              'Valid roles are "%s" or "%s".',
              $spec['host'],
              $index,
              $spec['role'],
              'master',
              'replica'));
      }

      if ($role === 'master') {
        $masters[] = $host;
      }

      // We can't guarantee that you didn't just give the same host two
      // different names in DNS, but this check can catch silly copy/paste
      // mistakes.
      $key = "{$host}:{$port}";
      if (isset($map[$key])) {
        throw new Exception(
          pht(
            'Database cluster configuration is invalid: it describes the '.
            'same host ("%s") multiple times. Each host should appear only '.
            'once in the list.',
            $host));
      }
      $map[$key] = true;
    }

    if (count($masters) > 1) {
      throw new Exception(
        pht(
          'Database cluster configuration is invalid: it describes multiple '.
          'masters. No more than one host may be a master. Hosts currently '.
          'configured as masters: %s.',
          implode(', ', $masters)));
    }
  }

}
