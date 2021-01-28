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

      $patch_type = $matches[1];
      $patch_full_path = rtrim($directory, '/').'/'.$patch;

      $attributes = array();
      if ($patch_type === 'php') {
        $attributes = $this->getPHPPatchAttributes(
          $patch,
          $patch_full_path);
      }

      $patches[$patch] = array(
        'type' => $patch_type,
        'name' => $patch_full_path,
      ) + $attributes;
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

    $phases = PhabricatorStoragePatch::getPhaseList();
    $phases = array_fuse($phases);

    $default_phase = PhabricatorStoragePatch::getDefaultPhase();

    foreach ($patch_lists as $patch_list) {
      $last_keys = array_fill_keys(
        array_keys($phases),
        null);

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
          'phase' => true,
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

        if (!array_key_exists('phase', $patch)) {
          $patch['phase'] = $default_phase;
        }

        $patch_phase = $patch['phase'];

        if (!isset($phases[$patch_phase])) {
          throw new Exception(
            pht(
              'Storage patch "%s" specifies it should apply in phase "%s", '.
              'but this phase is unrecognized. Valid phases are: %s.',
              $full_key,
              $patch_phase,
              implode(', ', array_keys($phases))));
        }

        $last_key = $last_keys[$patch_phase];

        if (!array_key_exists('after', $patch)) {
          if ($last_key === null && $patch_phase === $default_phase) {
            throw new Exception(
              pht(
                "Patch '%s' is missing key 'after', and is the first patch ".
                "in the patch list '%s', so its application order can not be ".
                "determined implicitly. The first patch in a patch list must ".
                "list the patch or patches it depends on explicitly.",
                $full_key,
                get_class($patch_list)));
          } else {
            if ($last_key === null) {
              $patch['after'] = array();
            } else {
              $patch['after'] = array($last_key);
            }
          }
        }
        $last_keys[$patch_phase] = $full_key;

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

        $patch_phase = $patch['phase'];
        $after_phase = $specs[$after]['phase'];

        if ($patch_phase !== $after_phase) {
          throw new Exception(
            pht(
              'Storage patch "%s" executes in phase "%s", but depends on '.
              'patch "%s" which is in a different phase ("%s"). Patches '.
              'may not have dependencies across phases.',
              $key,
              $patch_phase,
              $after,
              $after_phase));
        }
      }
    }

    $patches = array();
    foreach ($specs as $full_key => $spec) {
      $patches[$full_key] = new PhabricatorStoragePatch($spec);
    }

    // TODO: Detect cycles?

    $patches = msortv($patches, 'newSortVector');

    return $patches;
  }

  private function getPHPPatchAttributes($patch_name, $full_path) {
    $data = Filesystem::readFile($full_path);

    $phase_list = PhabricatorStoragePatch::getPhaseList();
    $phase_map = array_fuse($phase_list);

    $attributes = array();

    $lines = phutil_split_lines($data, false);
    foreach ($lines as $line) {
      // Skip over the "PHP" line.
      if (preg_match('(^<\?)', $line)) {
        continue;
      }

      // Skip over blank lines.
      if (!strlen(trim($line))) {
        continue;
      }

      // If this is a "//" comment...
      if (preg_match('(^\s*//)', $line)) {
        $matches = null;
        if (preg_match('(^\s*//\s*@(\S+)(?:\s+(.*))?\z)', $line, $matches)) {
          $attr_key = $matches[1];
          $attr_value = trim(idx($matches, 2));

          switch ($attr_key) {
            case 'phase':
              $phase_name = $attr_value;

              if (!strlen($phase_name)) {
                throw new Exception(
                  pht(
                    'Storage patch "%s" specifies a "@phase" attribute with '.
                    'no phase value. Phase attributes must specify a value, '.
                    'like "@phase default".',
                    $patch_name));
              }

              if (!isset($phase_map[$phase_name])) {
                throw new Exception(
                  pht(
                    'Storage patch "%s" specifies a "@phase" value ("%s"), '.
                    'but this is not a recognized phase. Valid phases '.
                    'are: %s.',
                    $patch_name,
                    $phase_name,
                    implode(', ', $phase_list)));
              }

              if (isset($attributes['phase'])) {
                throw new Exception(
                  pht(
                    'Storage patch "%s" specifies a "@phase" value ("%s"), '.
                    'but it already has a specified phase ("%s"). Patches '.
                    'may not specify multiple phases.',
                    $patch_name,
                    $phase_name,
                    $attributes['phase']));
              }

              $attributes[$attr_key] = $phase_name;
              break;
            default:
              throw new Exception(
                pht(
                  'Storage patch "%s" specifies attribute "%s", but this '.
                  'attribute is unknown.',
                  $patch_name,
                  $attr_key));
          }
        }
        continue;
      }

      // If this is anything else, we're all done. Attributes must be marked
      // in the header of the file.
      break;
    }


    return $attributes;
  }

}
