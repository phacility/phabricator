<?php

abstract class PhabricatorSQLPatchList {

  abstract function getNamespace();
  abstract function getPatches();

  final public static function buildAllPatches() {
    $patch_lists = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorSQLPatchList')
      ->setConcreteOnly(true)
      ->selectAndLoadSymbols();

    $specs = array();
    $seen_namespaces = array();

    foreach ($patch_lists as $patch_class) {
      $patch_class = $patch_class['name'];
      $patch_list = newv($patch_class, array());

      $namespace = $patch_list->getNamespace();
      if (isset($seen_namespaces[$namespace])) {
        $prior = $seen_namespaces[$namespace];
        throw new Exception(
          "PatchList '{$patch_class}' has the same namespace, '{$namespace}', ".
          "as another patch list class, '{$prior}'. Each patch list MUST have ".
          "a unique namespace.");
      }

      $last_key = null;
      foreach ($patch_list->getPatches() as $key => $patch) {
        if (!is_array($patch)) {
          throw new Exception(
            "PatchList '{$patch_class}' has a patch '{$key}' which is not ".
            "an array.");
        }

        $valid = array(
          'type'    => true,
          'name'    => true,
          'after'   => true,
          'legacy'  => true,
        );

        foreach ($patch as $pkey => $pval) {
          if (empty($valid[$pkey])) {
            throw new Exception(
              "PatchList '{$patch_class}' has a patch, '{$key}', with an ".
              "unknown property, '{$pkey}'. Patches must have only valid ".
              "keys: ".implode(', ', array_keys($valid)).'.');
          }
        }

        if (is_numeric($key)) {
          throw new Exception(
            "PatchList '{$patch_class}' has a patch with a numeric key, ".
            "'{$key}'. Patches must use string keys.");
        }

        if (strpos($key, ':') !== false) {
          throw new Exception(
            "PatchList '{$patch_class}' has a patch with a colon in the ".
            "key name, '{$key}'. Patch keys may not contain colons.");
        }

        $full_key = "{$namespace}:{$key}";

        if (isset($specs[$full_key])) {
          throw new Exception(
            "PatchList '{$patch_class}' has a patch '{$key}' which ".
            "duplicates an existing patch key.");
        }

        $patch['key']     = $key;
        $patch['fullKey'] = $full_key;

        if (isset($patch['legacy'])) {
          if ($namespace != 'phabricator') {
            throw new Exception(
              "Only patches in the 'phabricator' namespace may contain ".
              "'legacy' keys.");
          }
        } else {
          $patch['legacy'] = false;
        }

        if (!array_key_exists('after', $patch)) {
          if ($last_key === null) {
            throw new Exception(
              "Patch '{$full_key}' is missing key 'after', and is the first ".
              "patch in the patch list '{$patch_class}', so its application ".
              "order can not be determined implicitly. The first patch in a ".
              "patch list must list the patch or patches it depends on ".
              "explicitly.");
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
            "Patch '{$namespace}:{$key}' is missing key 'type'. Every patch ".
            "must have a type.");
        }

        switch ($type) {
          case 'db':
          case 'sql':
          case 'php':
            break;
          default:
            throw new Exception(
              "Patch '{$namespace}:{$key}' has unknown patch type '{$type}'.");
        }

        $specs[$full_key] = $patch;
      }
    }

    foreach ($specs as $key => $patch) {
      foreach ($patch['after'] as $after) {
        if (empty($specs[$after])) {
          throw new Exception(
            "Patch '{$key}' references nonexistent dependency, '{$after}'. ".
            "Patches may only depend on patches which actually exist.");
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
