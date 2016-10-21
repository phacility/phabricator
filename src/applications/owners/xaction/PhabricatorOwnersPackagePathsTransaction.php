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
      $new[$key]['excluded'] = (int)idx($info, 'excluded');
    }
    return $new;
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

    $diffs = PhabricatorOwnersPath::getTransactionValueChanges($old, $new);
    list($rem, $add) = $diffs;

    $set = PhabricatorOwnersPath::getSetFromTransactionValue($rem);
    foreach ($paths as $path) {
      $ref = $path->getRef();
      if (PhabricatorOwnersPath::isRefInSet($ref, $set)) {
        $path->delete();
      }
    }

    foreach ($add as $ref) {
      $path = PhabricatorOwnersPath::newFromRef($ref)
        ->setPackageID($object->getID())
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
      $rows[$key] = array(
        $row['change'],
        $row['excluded'] ? pht('Exclude') : pht('Include'),
        $this->renderHandle($row['repositoryPHID']),
        $row['path'],
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
