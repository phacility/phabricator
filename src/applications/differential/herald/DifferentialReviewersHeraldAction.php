<?php

abstract class DifferentialReviewersHeraldAction
  extends HeraldAction {

  const DO_NO_TARGETS = 'do.no-targets';
  const DO_AUTHORS = 'do.authors';
  const DO_INVALID = 'do.invalid';
  const DO_ALREADY_REVIEWERS = 'do.already-reviewers';
  const DO_PERMISSION = 'do.permission';
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
    if (!$phids) {
      $this->logEffect(self::DO_NO_TARGETS);
      return;
    }

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
    }

    if (!$phids) {
      return;
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

    $already = array();
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

      $already[] = $phid;
      unset($phids[$phid]);
    }

    if ($already) {
      $this->logEffect(self::DO_ALREADY_REVIEWERS, $already);
    }

    if (!$phids) {
      return;
    }

    $targets = id(new PhabricatorObjectQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withPHIDs($phids)
      ->execute();
    $targets = mpull($targets, null, 'getPHID');

    $invalid = array();
    foreach ($phids as $phid) {
      if (empty($targets[$phid])) {
        $invalid[] = $phid;
        unset($phids[$phid]);
      }
    }

    if ($invalid) {
      $this->logEffect(self::DO_INVALID, $invalid);
    }

    if (!$phids) {
      return;
    }

    $no_access = array();
    foreach ($targets as $phid => $target) {
      if (!($target instanceof PhabricatorUser)) {
        continue;
      }

      $can_view = PhabricatorPolicyFilter::hasCapability(
        $target,
        $object,
        PhabricatorPolicyCapability::CAN_VIEW);
      if ($can_view) {
        continue;
      }

      $no_access[] = $phid;
      unset($phids[$phid]);
    }

    if ($no_access) {
      $this->logEffect(self::DO_PERMISSION, $no_access);
    }

    if (!$phids) {
      return;
    }

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
      self::DO_NO_TARGETS => array(
        'icon' => 'fa-ban',
        'color' => 'grey',
        'name' => pht('No Targets'),
      ),
      self::DO_AUTHORS => array(
        'icon' => 'fa-user',
        'color' => 'grey',
        'name' => pht('Revision Author'),
      ),
      self::DO_INVALID => array(
        'icon' => 'fa-ban',
        'color' => 'red',
        'name' => pht('Invalid Targets'),
      ),
      self::DO_ALREADY_REVIEWERS => array(
        'icon' => 'fa-user',
        'color' => 'grey',
        'name' => pht('Already Reviewers'),
      ),
      self::DO_PERMISSION => array(
        'icon' => 'fa-ban',
        'color' => 'red',
        'name' => pht('No Permission'),
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
      case self::DO_NO_TARGETS:
        return pht('Rule lists no targets.');
      case self::DO_AUTHORS:
        return pht(
          'Declined to add revision author as reviewer: %s.',
          $this->renderHandleList($data));
      case self::DO_INVALID:
        return pht(
          'Declined to act on %s invalid target(s): %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
      case self::DO_ALREADY_REVIEWERS:
        return pht(
          '%s target(s) were already reviewers: %s.',
          new PhutilNumber(count($data)),
          $this->renderHandleList($data));
      case self::DO_PERMISSION:
        return pht(
          '%s target(s) do not have permission to see the revision: %s.',
          new PhutilNumber(count($data)),
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
