<?php

final class PhabricatorOwnersPackagePathsTransaction
  extends PhabricatorOwnersPackageTransactionType {

  const TRANSACTIONTYPE = 'owners.paths';

  public function generateOldValue($object) {
    $paths = $object->getPaths();
    return mpull($paths, 'getRef');
  }

  public function generateNewValue($object, $value) {
    $new = $value;

    foreach ($new as $key => $info) {
      $info['excluded'] = (int)idx($info, 'excluded');

      // The input has one "path" key with the display path.
      // Move it to "display", then normalize the value in "path".

      $display_path = $info['path'];
      $raw_path = rtrim($display_path, '/').'/';

      $info['path'] = $raw_path;
      $info['display'] = $display_path;

      $new[$key] = $info;
    }

    return $new;
  }

  public function getTransactionHasEffect($object, $old, $new) {
    list($add, $rem) = PhabricatorOwnersPath::getTransactionValueChanges(
      $old,
      $new);

    return ($add || $rem);
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if (!$xactions) {
      return $errors;
    }

    $old = mpull($object->getPaths(), 'getRef');
    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();

      // Check that we have a list of paths.
      if (!is_array($new)) {
        $errors[] = $this->newInvalidError(
          pht('Path specification must be a list of paths.'),
          $xaction);
        continue;
      }

      // Check that each item in the list is formatted properly.
      $type_exception = null;
      foreach ($new as $key => $value) {
        try {
          PhutilTypeSpec::checkMap(
            $value,
            array(
              'repositoryPHID' => 'string',
              'path' => 'string',
              'excluded' => 'optional wild',
            ));
        } catch (PhutilTypeCheckException $ex) {
          $errors[] = $this->newInvalidError(
            pht(
              'Path specification list contains invalid value '.
              'in key "%s": %s.',
              $key,
              $ex->getMessage()),
            $xaction);
          $type_exception = $ex;
        }
      }

      if ($type_exception) {
        continue;
      }

      // Check that any new paths reference legitimate repositories which
      // the viewer has permission to see.
      list($rem, $add) = PhabricatorOwnersPath::getTransactionValueChanges(
        $old,
        $new);

      if ($add) {
        $repository_phids = ipull($add, 'repositoryPHID');

        $repositories = id(new PhabricatorRepositoryQuery())
          ->setViewer($this->getActor())
          ->withPHIDs($repository_phids)
          ->execute();
        $repositories = mpull($repositories, null, 'getPHID');

        foreach ($add as $ref) {
          $repository_phid = $ref['repositoryPHID'];
          if (isset($repositories[$repository_phid])) {
            continue;
          }

          $errors[] = $this->newInvalidError(
            pht(
              'Path specification list references repository PHID "%s", '.
              'but that is not a valid, visible repository.',
              $repository_phid));
        }
      }
    }

    return $errors;
  }

  public function applyExternalEffects($object, $value) {
    $old = $this->generateOldValue($object);
    $new = $value;

    $paths = $object->getPaths();

    // We store paths in a normalized format with a trailing slash, regardless
    // of whether the user enters "path/to/file.c" or "src/backend/". Normalize
    // paths now.

    $display_map = array();
    $seen_map = array();
    foreach ($new as $key => $spec) {
      $raw_path = $spec['path'];
      $display_path = $spec['display'];

      // If the user entered two paths in the same repository which normalize
      // to the same value (like "src/main.c" and "src/main.c/"), discard the
      // duplicates.
      $repository_phid = $spec['repositoryPHID'];
      if (isset($seen_map[$repository_phid][$raw_path])) {
        unset($new[$key]);
        continue;
      }

      $new[$key]['path'] = $raw_path;
      $display_map[$raw_path] = $display_path;
      $seen_map[$repository_phid][$raw_path] = true;
    }

    $diffs = PhabricatorOwnersPath::getTransactionValueChanges($old, $new);
    list($rem, $add) = $diffs;

    $set = PhabricatorOwnersPath::getSetFromTransactionValue($rem);
    foreach ($paths as $path) {
      $ref = $path->getRef();
      if (PhabricatorOwnersPath::isRefInSet($ref, $set)) {
        $path->delete();
        continue;
      }

      // If the user has changed the display value for a path but the raw
      // storage value hasn't changed, update the display value.

      if (isset($display_map[$path->getPath()])) {
        $path
          ->setPathDisplay($display_map[$path->getPath()])
          ->save();
        continue;
      }
    }

    foreach ($add as $ref) {
      $path = PhabricatorOwnersPath::newFromRef($ref)
        ->setPackageID($object->getID())
        ->setPathDisplay($display_map[$ref['path']])
        ->save();
    }
  }

  public function getTitle() {
    // TODO: Flesh this out.
    return pht(
      '%s updated paths for this package.',
      $this->renderAuthor());
  }

  public function hasChangeDetailView() {
    return true;
  }

  public function newChangeDetailView() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $diffs = PhabricatorOwnersPath::getTransactionValueChanges($old, $new);
    list($rem, $add) = $diffs;

    $rows = array();
    foreach ($rem as $ref) {
      $rows[] = array(
        'class' => 'diff-removed',
        'change' => '-',
      ) + $ref;
    }

    foreach ($add as $ref) {
      $rows[] = array(
        'class' => 'diff-added',
        'change' => '+',
      ) + $ref;
    }

    $rowc = array();
    foreach ($rows as $key => $row) {
      $rowc[] = $row['class'];

      if (array_key_exists('display', $row)) {
        $display_path = $row['display'];
      } else {
        $display_path = $row['path'];
      }

      $rows[$key] = array(
        $row['change'],
        $row['excluded'] ? pht('Exclude') : pht('Include'),
        $this->renderHandle($row['repositoryPHID']),
        $display_path,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setViewer($this->getViewer())
      ->setRowClasses($rowc)
      ->setHeaders(
        array(
          null,
          pht('Type'),
          pht('Repository'),
          pht('Path'),
        ))
      ->setColumnClasses(
        array(
          null,
          null,
          null,
          'wide',
        ));

    return $table;
  }

}
