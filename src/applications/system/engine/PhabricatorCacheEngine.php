<?php

final class PhabricatorCacheEngine extends Phobject {

  public function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }

  public function updateObject($object) {
    $objects = array(
      $object->getPHID() => $object,
    );

    $new_objects = $objects;
    while (true) {
      $discovered_objects = array();
      $load = array();

      $extensions = PhabricatorCacheEngineExtension::getAllExtensions();
      foreach ($extensions as $key => $extension) {
        $discoveries = $extension->discoverLinkedObjects($this, $new_objects);
        if (!is_array($discoveries)) {
          throw new Exception(
            pht(
              'Cache engine extension "%s" did not return a list of linked '.
              'objects.',
              get_class($extension)));
        }

        foreach ($discoveries as $discovery) {
          if ($discovery === null) {
            // This is allowed because it makes writing extensions a lot
            // easier if they don't have to check that related PHIDs are
            // actually set to something.
            continue;
          }

          $is_phid = is_string($discovery);
          if ($is_phid) {
            $phid = $discovery;
          } else {
            $phid = $discovery->getPHID();
            if (!$phid) {
              throw new Exception(
                pht(
                  'Cache engine extension "%s" returned object (of class '.
                  '"%s") with no PHID.',
                  get_class($extension),
                  get_class($discovery)));
            }
          }

          if (isset($objects[$phid])) {
            continue;
          }

          if ($is_phid) {
            $load[$phid] = $phid;
          } else {
            $objects[$phid] = $discovery;
            $discovered_objects[$phid] = $discovery;

            // If another extension only knew about the PHID of this object,
            // we don't need to load it any more.
            unset($load[$phid]);
          }
        }
      }

      if ($load) {
        $load_objects = id(new PhabricatorObjectQuery())
          ->setViewer($this->getViewer())
          ->withPHIDs($load)
          ->execute();
        foreach ($load_objects as $phid => $loaded_object) {
          $objects[$phid] = $loaded_object;
          $discovered_objects[$phid] = $loaded_object;
        }
      }

      // If we didn't find anything new to update, we're all set.
      if (!$discovered_objects) {
        break;
      }

      $new_objects = $discovered_objects;
    }

    foreach ($extensions as $extension) {
      $extension->deleteCaches($this, $objects);
    }
  }

}
