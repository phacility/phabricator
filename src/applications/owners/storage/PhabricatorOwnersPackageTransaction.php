<?php

final class PhabricatorOwnersPackageTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_NAME = 'owners.name';
  const TYPE_PRIMARY = 'owners.primary';
  const TYPE_OWNERS = 'owners.owners';
  const TYPE_AUDITING = 'owners.auditing';
  const TYPE_DESCRIPTION = 'owners.description';
  const TYPE_PATHS = 'owners.paths';

  public function getApplicationName() {
    return 'owners';
  }

  public function getApplicationTransactionType() {
    return PhabricatorOwnersPackagePHIDType::TYPECONST;
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_PRIMARY:
        if ($old) {
          $phids[] = $old;
        }
        if ($new) {
          $phids[] = $new;
        }
        break;
      case self::TYPE_OWNERS:
        $add = array_diff($new, $old);
        foreach ($add as $phid) {
          $phids[] = $phid;
        }
        $rem = array_diff($old, $new);
        foreach ($rem as $phid) {
          $phids[] = $phid;
        }
        break;
    }

    return $phids;
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        return ($old === null);
    }
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();
    $author_phid = $this->getAuthorPHID();

    switch ($this->getTransactionType()) {
      case self::TYPE_NAME:
        if ($old === null) {
          return pht(
            '%s created this package.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s renamed this package from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
      case self::TYPE_PRIMARY:
        return pht(
          '%s changed the primary owner for this package from %s to %s.',
          $this->renderHandleLink($author_phid),
          $this->renderHandleLink($old),
          $this->renderHandleLink($new));
      case self::TYPE_OWNERS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);
        if ($add && !$rem) {
          return pht(
            '%s added %s owner(s): %s.',
            $this->renderHandleLink($author_phid),
            count($add),
            $this->renderHandleList($add));
        } else if ($rem && !$add) {
          return pht(
            '%s removed %s owner(s): %s.',
            $this->renderHandleLink($author_phid),
            count($rem),
            $this->renderHandleList($rem));
        } else {
          return pht(
            '%s changed %s package owner(s), added %s: %s; removed %s: %s.',
            $this->renderHandleLink($author_phid),
            count($add) + count($rem),
            count($add),
            $this->renderHandleList($add),
            count($rem),
            $this->renderHandleList($rem));
        }
      case self::TYPE_AUDITING:
        if ($new) {
          return pht(
            '%s enabled auditing for this package.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s disabled auditing for this package.',
            $this->renderHandleLink($author_phid));
        }
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s updated the description for this package.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_PATHS:
        // TODO: Flesh this out.
        return pht(
          '%s updated paths for this package.',
          $this->renderHandleLink($author_phid));
    }

    return parent::getTitle();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        return ($this->getOldValue() !== null);
      case self::TYPE_PATHS:
        return true;
    }

    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        $old = $this->getOldValue();
        $new = $this->getNewValue();

        return $this->renderTextCorpusChangeDetails(
          $viewer,
          $old,
          $new);
      case self::TYPE_PATHS:
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
            $viewer->renderHandle($row['repositoryPHID']),
            $row['path'],
          );
        }

        $table = id(new AphrontTableView($rows))
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

    return parent::renderChangeDetails($viewer);
  }

}
