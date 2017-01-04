<?php

final class PhabricatorObjectListQuery extends Phobject {

  private $viewer;
  private $objectList;
  private $allowedTypes = array();
  private $allowPartialResults;
  private $suffixes = array();

  public function setAllowPartialResults($allow_partial_results) {
    $this->allowPartialResults = $allow_partial_results;
    return $this;
  }

  public function getAllowPartialResults() {
    return $this->allowPartialResults;
  }

  public function setSuffixes(array $suffixes) {
    $this->suffixes = $suffixes;
    return $this;
  }

  public function getSuffixes() {
    return $this->suffixes;
  }

  public function setAllowedTypes(array $allowed_types) {
    $this->allowedTypes = $allowed_types;
    return $this;
  }

  public function getAllowedTypes() {
    return $this->allowedTypes;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setObjectList($object_list) {
    $this->objectList = $object_list;
    return $this;
  }

  public function getObjectList() {
    return $this->objectList;
  }

  public function execute() {
    $names = $this->getObjectList();

    // First, normalize any internal whitespace so we don't get weird results
    // if linebreaks hit in weird spots.
    $names = preg_replace('/\s+/', ' ', $names);

    // Split the list on commas.
    $names = explode(',', $names);

    // Trim and remove empty tokens.
    foreach ($names as $key => $name) {
      $name = trim($name);

      if (!strlen($name)) {
        unset($names[$key]);
        continue;
      }

      $names[$key] = $name;
    }

    // Remove duplicates.
    $names = array_unique($names);

    $name_map = array();
    foreach ($names as $name) {
      $parts = explode(' ', $name);

      // If this looks like a monogram, ignore anything after the first token.
      // This allows us to parse "O123 Package Name" as though it was "O123",
      // which we can look up.
      if (preg_match('/^[A-Z]\d+\z/', $parts[0])) {
        $name_map[$parts[0]] = $name;
      } else {
        // For anything else, split it on spaces and use each token as a
        // value. This means "alincoln htaft", separated with a space instead
        // of with a comma, is two different users.
        foreach ($parts as $part) {
          $name_map[$part] = $part;
        }
      }
    }

    // If we're parsing with suffixes, strip them off any tokens and keep
    // track of them for later.
    $suffixes = $this->getSuffixes();
    if ($suffixes) {
      $suffixes = array_fuse($suffixes);
      $suffix_map = array();
      $stripped_map = array();
      foreach ($name_map as $key => $name) {
        $found_suffixes = array();
        do {
          $has_any_suffix = false;
          foreach ($suffixes as $suffix) {
            if (!$this->hasSuffix($name, $suffix)) {
              continue;
            }

            $key = $this->stripSuffix($key, $suffix);
            $name = $this->stripSuffix($name, $suffix);

            $found_suffixes[] = $suffix;
            $has_any_suffix = true;
            break;
          }
        } while ($has_any_suffix);

        $stripped_map[$key] = $name;
        $suffix_map[$key] = array_fuse($found_suffixes);
      }
      $name_map = $stripped_map;
    }

    $objects = $this->loadObjects(array_keys($name_map));

    $types = array();
    foreach ($objects as $name => $object) {
      $types[phid_get_type($object->getPHID())][] = $name;
    }

    $invalid = array();
    if ($this->getAllowedTypes()) {
      $allowed = array_fuse($this->getAllowedTypes());
      foreach ($types as $type => $names_of_type) {
        if (empty($allowed[$type])) {
          $invalid[] = $names_of_type;
        }
      }
    }
    $invalid = array_mergev($invalid);

    $missing = array();
    foreach ($name_map as $key => $name) {
      if (empty($objects[$key])) {
        $missing[$key] = $name;
      }
    }

    $result = array_unique(mpull($objects, 'getPHID'));

    // For values which are plain PHIDs of allowed types, let them through
    // unchecked. This can happen occur if subscribers or reviewers which the
    // revision author does not have permission to see are added by Herald
    // rules. Any actual edits will be checked later: users are not allowed
    // to add new reviewers they can't see, but they can touch a field which
    // contains them.
    foreach ($missing as $key => $value) {
      if (isset($allowed[phid_get_type($value)])) {
        unset($missing[$key]);
        $result[$key] = $value;
      }
    }

    // NOTE: We could couple this less tightly with Differential, but it is
    // currently the only thing that uses it, and we'd have to add a lot of
    // extra API to loosen this. It's not clear that this will be useful
    // elsewhere any time soon, so let's cross that bridge when we come to it.

    if (!$this->getAllowPartialResults()) {
      if ($invalid && $missing) {
        throw new DifferentialFieldParseException(
          pht(
            'The objects you have listed include objects of the wrong '.
            'type (%s) and objects which do not exist (%s).',
            implode(', ', $invalid),
            implode(', ', $missing)));
      } else if ($invalid) {
        throw new DifferentialFieldParseException(
          pht(
            'The objects you have listed include objects of the wrong '.
            'type (%s).',
            implode(', ', $invalid)));
      } else if ($missing) {
        throw new DifferentialFieldParseException(
          pht(
            'The objects you have listed include objects which do not '.
            'exist (%s).',
            implode(', ', $missing)));
      }
    }

    if ($suffixes) {
      foreach ($result as $key => $phid) {
        $result[$key] = array(
          'phid' => $phid,
          'suffixes' => idx($suffix_map, $key, array()),
        );
      }
    }

    return array_values($result);
  }

  private function loadObjects($names) {
    // First, try to load visible objects using monograms. This covers most
    // object types, but does not cover users or user email addresses.
    $query = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->withNames($names);

    $query->execute();
    $objects = $query->getNamedResults();

    $results = array();
    foreach ($names as $key => $name) {
      if (isset($objects[$name])) {
        $results[$name] = $objects[$name];
        unset($names[$key]);
      }
    }

    if ($names) {
      // We still have some symbols we haven't been able to resolve, so try to
      // load users. Try by username first...
      $users = id(new PhabricatorPeopleQuery())
        ->setViewer($this->getViewer())
        ->withUsernames($names)
        ->execute();

      $user_map = array();
      foreach ($users as $user) {
        $user_map[phutil_utf8_strtolower($user->getUsername())] = $user;
      }

      foreach ($names as $key => $name) {
        $normal_name = phutil_utf8_strtolower($name);
        if (isset($user_map[$normal_name])) {
          $results[$name] = $user_map[$normal_name];
          unset($names[$key]);
        }
      }
    }

    return $results;
  }

  private function hasSuffix($key, $suffix) {
    return (substr($key, -strlen($suffix)) === $suffix);
  }

  private function stripSuffix($key, $suffix) {
    if ($this->hasSuffix($key, $suffix)) {
      return substr($key, 0, -strlen($suffix));
    }

    return $key;
  }

}
