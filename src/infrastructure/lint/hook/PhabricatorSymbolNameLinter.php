<?php

final class PhabricatorSymbolNameLinter extends ArcanistXHPASTLintNamingHook {

  public function lintSymbolName($type, $name, $default) {
    $matches = null;
    if ($type == 'class' &&
        preg_match('/^ConduitAPI_(.*)_Method$/', $name, $matches)) {
      if (preg_match('/^[a-z]+(_[a-z]+)?$/', $matches[1])) {
        // These are permitted since Conduit does reflectioney stuff to figure
        // out the method name from the class name.
        return null;
      } else {
        return 'Conduit method implementations should contain lowercase '.
               'letters only, with an underscore separating group and method '.
               'names for implementations, e.g. '.
               '"ConduitAPI_thing_info_Method".';
      }
    }

    return $default;
  }
}
