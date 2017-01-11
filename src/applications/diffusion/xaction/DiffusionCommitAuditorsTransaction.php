<?php

final class DiffusionCommitAuditorsTransaction
  extends DiffusionCommitTransactionType {

  const TRANSACTIONTYPE = 'diffusion.commit.auditors';

  public function generateOldValue($object) {
    $auditors = $object->getAudits();
    return mpull($auditors, 'getAuditStatus', 'getAuditorPHID');
  }

  public function generateNewValue($object, $value) {
    $actor = $this->getActor();

    $auditors = $this->generateOldValue($object);
    $old_auditors = $auditors;

    $request_status = PhabricatorAuditStatusConstants::AUDIT_REQUESTED;

    $rem = idx($value, '-', array());
    foreach ($rem as $phid) {
      unset($auditors[$phid]);
    }

    $add = idx($value, '+', array());
    $add_map = array();
    foreach ($add as $phid) {
      $add_map[$phid] = $request_status;
    }

    $set = idx($value, '=', null);
    if ($set !== null) {
      foreach ($set as $phid) {
        $add_map[$phid] = $request_status;
      }

      $auditors = array();
    }

    foreach ($add_map as $phid => $new_status) {
      $old_status = idx($old_auditors, $phid);

      if ($old_status) {
        $auditors[$phid] = $old_status;
        continue;
      }

      $auditors[$phid] = $new_status;
    }

    return $auditors;
  }

  public function getTransactionHasEffect($object, $old, $new) {
    ksort($old);
    ksort($new);
    return ($old !== $new);
  }

  public function applyExternalEffects($object, $value) {
    $src_phid = $object->getPHID();

    $old = $this->generateOldValue($object);
    $new = $value;

    $auditors = $object->getAudits();
    $auditors = mpull($auditors, null, 'getAuditorPHID');

    $rem = array_diff_key($old, $new);
    foreach ($rem as $phid => $status) {
      $auditor = idx($auditors, $phid);
      if ($auditor) {
        $auditor->delete();
      }
    }

    $this->updateAudits($object, $new);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $rem = array_diff_key($old, $new);
    $add = array_diff_key($new, $old);
    $rem_phids = array_keys($rem);
    $add_phids = array_keys($add);
    $total_count = count($rem) + count($add);

    if ($rem && $add) {
      return pht(
        '%s edited %s auditor(s), removed %s: %s; added %s: %s.',
        $this->renderAuthor(),
        new PhutilNumber($total_count),
        phutil_count($rem_phids),
        $this->renderHandleList($rem_phids),
        phutil_count($add_phids),
        $this->renderHandleList($add_phids));
    } else if ($add) {
      return pht(
        '%s added %s auditor(s): %s.',
        $this->renderAuthor(),
        phutil_count($add_phids),
        $this->renderHandleList($add_phids));
    } else {
      return pht(
        '%s removed %s auditor(s): %s.',
        $this->renderAuthor(),
        phutil_count($rem_phids),
        $this->renderHandleList($rem_phids));
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $rem = array_diff_key($old, $new);
    $add = array_diff_key($new, $old);
    $rem_phids = array_keys($rem);
    $add_phids = array_keys($add);
    $total_count = count($rem) + count($add);

    if ($rem && $add) {
      return pht(
        '%s edited %s auditor(s) for %s, removed %s: %s; added %s: %s.',
        $this->renderAuthor(),
        new PhutilNumber($total_count),
        $this->renderObject(),
        phutil_count($rem_phids),
        $this->renderHandleList($rem_phids),
        phutil_count($add_phids),
        $this->renderHandleList($add_phids));
    } else if ($add) {
      return pht(
        '%s added %s auditor(s) for %s: %s.',
        $this->renderAuthor(),
        phutil_count($add_phids),
        $this->renderObject(),
        $this->renderHandleList($add_phids));
    } else {
      return pht(
        '%s removed %s auditor(s) for %s: %s.',
        $this->renderAuthor(),
        phutil_count($rem_phids),
        $this->renderObject(),
        $this->renderHandleList($rem_phids));
    }
  }

  public function validateTransactions($object, array $xactions) {
    $actor = $this->getActor();
    $errors = array();

    if (!$xactions) {
      return $errors;
    }

    $author_phid = $object->getAuthorPHID();
    $can_author_close_key = 'audit.can-author-close-audit';
    $can_author_close = PhabricatorEnv::getEnvConfig($can_author_close_key);

    $old = $this->generateOldValue($object);
    foreach ($xactions as $xaction) {
      $new = $this->generateNewValue($object, $xaction->getNewValue());

      $add = array_diff_key($new, $old);
      if (!$add) {
        continue;
      }

      $objects = id(new PhabricatorObjectQuery())
        ->setViewer($actor)
        ->withPHIDs(array_keys($add))
        ->execute();
      $objects = mpull($objects, null, 'getPHID');

      foreach ($add as $phid => $status) {
        if (!isset($objects[$phid])) {
          $errors[] = $this->newInvalidError(
            pht(
              'Auditor "%s" is not a valid object.',
              $phid),
            $xaction);
          continue;
        }

        switch (phid_get_type($phid)) {
          case PhabricatorPeopleUserPHIDType::TYPECONST:
          case PhabricatorOwnersPackagePHIDType::TYPECONST:
          case PhabricatorProjectProjectPHIDType::TYPECONST:
            break;
          default:
            $errors[] = $this->newInvalidError(
              pht(
                'Auditor "%s" must be a user, a package, or a project.',
                $phid),
              $xaction);
            continue 2;
        }

        $is_self = ($phid === $author_phid);
        if ($is_self && !$can_author_close) {
          $errors[] = $this->newInvalidError(
            pht('The author of a commit can not be an auditor.'),
            $xaction);
          continue;
        }
      }
    }

    return $errors;
  }

}
