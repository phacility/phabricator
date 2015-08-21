<?php

abstract class PhabricatorSQLPatchList extends Phobject {

  abstract public function getNamespace();
  abstract public function getPatches();

  /**
   * Examine a directory for `.php` and `.sql` files and build patch
   * specifications for them.
   */
  protected function buildPatchesFromDirectory($directory) {
    $patch_list = Filesystem::listDirectory(
      $directory,
      $include_hidden = false);

    sort($patch_list);
    $patches = array();

    foreach ($patch_list as $patch) {
      $matches = null;
      if (!preg_match('/\.(sql|php)$/', $patch, $matches)) {
        throw new Exception(
          pht(
            'Unknown patch "%s" in "%s", expected ".php" or ".sql" suffix.',
            $patch,
            $directory));
      }

      $patches[$patch] = array(
        'type' => $matches[1],
        'name' => rtrim($directory, '/').'/'.$patch,
      );
    }

    return $patches;
  }

  final public static function buildAllPatches() {
    $patch_lists = id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getNamespace')
      ->execute();

    $specs = array();
    $seen_namespaces = array();

    foreach ($patch_lists as $patch_list) {
      $last_key = null;
      foreach ($patch_list->getPatches() as $key => $patch) {
        if (!is_array($patch)) {
          throw new Exception(
            pht(
              "%s '%s' has a patch '%s' which is not an array.",
              __CLASS__,
              get_class($patch_list),
              $key));
        }

        $valid = array(
          'type'    => true,
          'name'    => true,
          'after'   => true,
          'legacy'  => true,
          'dead'    => true,
        );

        foreach ($patch as $pkey => $pval) {
          if (empty($valid[$pkey])) {
            throw new Exception(
              pht(
                "%s '%s' has a patch, '%s', with an unknown property, '%s'.".
                "Patches must have only valid keys: %s.",
                __CLASS__,
                get_class($patch_list),
                $key,
                $pkey,
                implode(', ', array_keys($valid))));
          }
        }

        if (is_numeric($key)) {
          throw new Exception(
            pht(
              "%s '%s' has a patch with a numeric key, '%s'. ".
              "Patches must use string keys.",
              __CLASS__,
              get_class($patch_list),
              $key));
        }

        if (strpos($key, ':') !== false) {
          throw new Exception(
            pht(
              "%s '%s' has a patch with a colon in the key name, '%s'. ".
              "Patch keys may not contain colons.",
              __CLASS__,
              get_class($patch_list),
              $key));
        }

        $namespace = $patch_list->getNamespace();
        $full_key = "{$namespace}:{$key}";

        if (isset($specs[$full_key])) {
          throw new Exception(
            pht(
              "%s '%s' has a patch '%s' which duplicates an ".
              "existing patch key.",
              __CLASS__,
              get_class($patch_list),
              $key));
        }

        $patch['key']     = $key;
        $patch['fullKey'] = $full_key;
        $patch['dead']    = (bool)idx($patch, 'dead', false);

        if (isset($patch['legacy'])) {
          if ($namespace != 'phabricator') {
            throw new Exception(
              pht(
                "Only patches in the '%s' namespace may contain '%s' keys.",
                'phabricator',
                'legacy'));
          }
        } else {
          $patch['legacy'] = false;
        }

        if (!array_key_exists('after', $patch)) {
          if ($last_key === null) {
            throw new Exception(
              pht(
                "Patch '%s' is missing key 'after', and is the first patch ".
                "in the patch list '%s', so its application order can not be ".
                "determined implicitly. The first patch in a patch list must ".
                "list the patch or patches it depends on explicitly.",
                $full_key,
                get_class($patch_list)));
          } else {
            $patch['after'] = array($last_key);
          }
        }
        $last_key = $full_key;

        foreach ($patch['after'] as $after_key => $after) {
          if (strpos($after, ':') === false) {
            $patch['after'][$after_key] = $namespace.':'.$after;
          }
        }

        $type = idx($patch, 'type');
        if (!$type) {
          throw new Exception(
            pht(
              "Patch '%s' is missing key '%s'. Every patch must have a type.",
              "{$namespace}:{$key}",
              'type'));
        }

        switch ($type) {
          case 'db':
          case 'sql':
          case 'php':
            break;
          default:
            throw new Exception(
              pht(
                "Patch '%s' has unknown patch type '%s'.",
                "{$namespace}:{$key}",
                $type));
        }

        $specs[$full_key] = $patch;
      }
    }

    foreach ($specs as $key => $patch) {
      foreach ($patch['after'] as $after) {
        if (empty($specs[$after])) {
          throw new Exception(
            pht(
              "Patch '%s' references nonexistent dependency, '%s'. ".
              "Patches may only depend on patches which actually exist.",
              $key,
              $after));
        }
      }
    }

    $patches = array();
    foreach ($specs as $full_key => $spec) {
      $patches[$full_key] = new PhabricatorStoragePatch($spec);
    }

    // TODO: Detect cycles?

    return $patches;
  }

}
