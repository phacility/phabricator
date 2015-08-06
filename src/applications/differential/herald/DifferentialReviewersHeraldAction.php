<?php

abstract class DifferentialReviewersHeraldAction
  extends HeraldAction {

  const DO_AUTHORS = 'do.authors';
  const DO_ADD_REVIEWERS = 'do.add-reviewers';
  const DO_ADD_BLOCKING_REVIEWERS = 'do.add-blocking-reviewers';

  public function getActionGroupKey() {
    return HeraldApplicationActionGroup::ACTIONGROUPKEY;
  }

  public function supportsObject($object) {
    return ($object instanceof DifferentialRevision);
  }

  protected function applyReviewers(array $phids, $is_blocking) {
    $adapter = $this->getAdapter();
    $object = $adapter->getObject();

    $phids = array_fuse($phids);

    // Don't try to add revision authors as reviewers.
    $authors = array();
    foreach ($phids as $phid) {
      if ($phid == $object->getAuthorPHID()) {
        $authors[] = $phid;
        unset($phids[$phid]);
      }
    }

    if ($authors) {
      $this->logEffect(self::DO_AUTHORS, $authors);
      if (!$phids) {
        return;
      }
    }

    $reviewers = $object->getReviewerStatus();
    $reviewers = mpull($reviewers, null, 'getReviewerPHID');

    if ($is_blocking) {
      $new_status = DifferentialReviewerStatus::STATUS_BLOCKING;
    } else {
      $new_status = DifferentialReviewerStatus::STATUS_ADDED;
    }

    $new_strength = DifferentialReviewerStatus::getStatusStrength(
      $new_status);

    $current = array();
    foreach ($phids as $phid) {
      if (!isset($reviewers[$phid])) {
        continue;
      }

      // If we're applying a stronger status (usually, upgrading a reviewer
      // into a blocking reviewer), skip this check so we apply the change.
      $old_strength = DifferentialReviewerStatus::getStatusStrength(
        $reviewers[$phid]->getStatus());
      if ($old_strength <= $new_strength) {
        continue;
      }

      $current[] = $phid;
    }

    $allowed_types = array(
      PhabricatorPeopleUserPHIDType::TYPECONST,
      PhabricatorProjectProjectPHIDType::TYPECONST,
    );

    $targets = $this->loadStandardTargets($phids, $allowed_types, $current);
    if (!$targets) {
      return;
    }

    $phids = array_fuse(array_keys($targets));

    $value = array();
    foreach ($phids as $phid) {
      $value[$phid] = array(
        'data' => array(
          'status' => $new_status,
        ),
      );
    }

    $edgetype_reviewer = DifferentialRevisionHasReviewerEdgeType::EDGECONST;

    $xaction = $adapter->newTransaction()
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue('edge:type', $edgetype_reviewer)
      ->setNewValue(
        array(
          '+' => $value,
        ));

    $adapter->queueTransaction($xaction);

    if ($is_blocking) {
      $this->logEffect(self::DO_ADD_BLOCKING_REVIEWERS, $phids);
    } else {
      $this->logEffect(self::DO_ADD_REVIEWERS, $phids);
    }
  }

  protected function getActionEffectMap() {
    return array(
      self::DO_AUTHORS => array(
        'icon' => 'fa-user',
        'color' => 'grey',
        'name' => pht('Revision Author'),
      ),
      self::DO_ADD_REVIEWERS => array(
        'icon' => 'fa-user',
        'color' => 'green',
        'name' => pht('Added Reviewers'),
      ),
      self::DO_ADD_BLOCKING_REVIEWERS => array(
        'icon' => 'fa-user',
        'color' => 'green',
        'name' => pht('Added Blocking Reviewers'),
      ),
    );
  }

  protected function renderActionEffectDescription($type, $data) {
    switch ($type) {
      case self::DO_AUTHORS:
        return pht(
          'Declined to add revision author as reviewer: %s.',
          $this->renderHandleList($data));
      case self::DO_ADD_REVIEWERS:
        return pht(
          'Added %s reviewer(s): %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
      case self::DO_ADD_BLOCKING_REVIEWERS:
        return pht(
          'Added %s blocking reviewer(s): %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
    }
  }


}
