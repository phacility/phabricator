<?php

final class PhabricatorRepositoryTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_VCS = 'repo:vcs';
  const TYPE_ACTIVATE = 'repo:activate';
  const TYPE_NAME = 'repo:name';
  const TYPE_DESCRIPTION = 'repo:description';
  const TYPE_ENCODING = 'repo:encoding';
  const TYPE_DEFAULT_BRANCH = 'repo:default-branch';
  const TYPE_TRACK_ONLY = 'repo:track-only';
  const TYPE_AUTOCLOSE_ONLY = 'repo:autoclose-only';
  const TYPE_SVN_SUBPATH = 'repo:svn-subpath';
  const TYPE_NOTIFY = 'repo:notify';
  const TYPE_AUTOCLOSE = 'repo:autoclose';
  const TYPE_PUSH_POLICY = 'repo:push-policy';
  const TYPE_DANGEROUS = 'repo:dangerous';
  const TYPE_SLUG = 'repo:slug';
  const TYPE_SERVICE = 'repo:service';
  const TYPE_SYMBOLS_SOURCES = 'repo:symbol-source';
  const TYPE_SYMBOLS_LANGUAGE = 'repo:symbol-language';
  const TYPE_STAGING_URI = 'repo:staging-uri';
  const TYPE_AUTOMATION_BLUEPRINTS = 'repo:automation-blueprints';
  const TYPE_CALLSIGN = 'repo:callsign';

  // TODO: Clean up these legacy transaction types.
  const TYPE_SSH_LOGIN = 'repo:ssh-login';
  const TYPE_SSH_KEY = 'repo:ssh-key';
  const TYPE_SSH_KEYFILE = 'repo:ssh-keyfile';
  const TYPE_HTTP_LOGIN = 'repo:http-login';
  const TYPE_HTTP_PASS = 'repo:http-pass';
  const TYPE_CREDENTIAL = 'repo:credential';
  const TYPE_PROTOCOL_HTTP = 'repo:serve-http';
  const TYPE_PROTOCOL_SSH = 'repo:serve-ssh';
  const TYPE_HOSTING = 'repo:hosting';
  const TYPE_LOCAL_PATH = 'repo:local-path';
  const TYPE_REMOTE_URI = 'repo:remote-uri';
  const TYPE_UUID = 'repo:uuid';

  public function getApplicationName() {
    return 'repository';
  }

  public function getApplicationTransactionType() {
    return PhabricatorRepositoryRepositoryPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_PUSH_POLICY:
      case self::TYPE_SERVICE:
        if ($old) {
          $phids[] = $old;
        }
        if ($new) {
          $phids[] = $new;
        }
        break;
      case self::TYPE_SYMBOLS_SOURCES:
      case self::TYPE_AUTOMATION_BLUEPRINTS:
        if ($old) {
          $phids = array_merge($phids, $old);
        }
        if ($new) {
          $phids = array_merge($phids, $new);
        }
        break;
    }

    return $phids;
  }

  public function shouldHide() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_REMOTE_URI:
      case self::TYPE_SSH_LOGIN:
      case self::TYPE_SSH_KEY:
      case self::TYPE_SSH_KEYFILE:
      case self::TYPE_HTTP_LOGIN:
      case self::TYPE_HTTP_PASS:
        // Hide null vs empty string changes.
        return (!strlen($old) && !strlen($new));
      case self::TYPE_LOCAL_PATH:
      case self::TYPE_NAME:
        // Hide these on create, they aren't interesting and we have an
        // explicit "create" transaction.
        if (!strlen($old)) {
          return true;
        }
        break;
    }

    return parent::shouldHide();
  }

  public function getIcon() {
    switch ($this->getTransactionType()) {
      case self::TYPE_VCS:
        return 'fa-plus';
    }
    return parent::getIcon();
  }

  public function getColor() {
    switch ($this->getTransactionType()) {
      case self::TYPE_VCS:
        return 'green';
    }
    return parent::getIcon();
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_VCS:
        return pht(
          '%s created this repository.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_ACTIVATE:
        // TODO: Old versions of this transaction use a boolean value, but
        // should be migrated.
        $is_deactivate =
          (!$new) ||
          ($new == PhabricatorRepository::STATUS_INACTIVE);

        if (!$is_deactivate) {
          return pht(
            '%s activated this repository.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s deactivated this repository.',
            $this->renderHandleLink($author_phid));
        }
      case self::TYPE_NAME:
        return pht(
          '%s renamed this repository from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s updated the description of this repository.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_ENCODING:
        if (strlen($old) && !strlen($new)) {
          return pht(
            '%s removed the "%s" encoding configured for this repository.',
            $this->renderHandleLink($author_phid),
            $old);
        } else if (strlen($new) && !strlen($old)) {
          return pht(
            '%s set the encoding for this repository to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        } else {
          return pht(
            '%s changed the repository encoding from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
      case self::TYPE_DEFAULT_BRANCH:
        if (!strlen($new)) {
          return pht(
            '%s removed "%s" as the default branch.',
            $this->renderHandleLink($author_phid),
            $old);
        } else if (!strlen($old)) {
          return pht(
            '%s set the default branch to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        } else {
          return pht(
            '%s changed the default branch from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
        break;
      case self::TYPE_TRACK_ONLY:
        if (!$new) {
          return pht(
            '%s set this repository to track all branches.',
            $this->renderHandleLink($author_phid));
        } else if (!$old) {
          return pht(
            '%s set this repository to track branches: %s.',
            $this->renderHandleLink($author_phid),
            implode(', ', $new));
        } else {
          return pht(
            '%s changed track branches from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            implode(', ', $old),
            implode(', ', $new));
        }
        break;
      case self::TYPE_AUTOCLOSE_ONLY:
        if (!$new) {
          return pht(
            '%s set this repository to autoclose on all branches.',
            $this->renderHandleLink($author_phid));
        } else if (!$old) {
          return pht(
            '%s set this repository to autoclose on branches: %s.',
            $this->renderHandleLink($author_phid),
            implode(', ', $new));
        } else {
          return pht(
            '%s changed autoclose branches from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            implode(', ', $old),
            implode(', ', $new));
        }
        break;
      case self::TYPE_UUID:
        if (!strlen($new)) {
          return pht(
            '%s removed "%s" as the repository UUID.',
            $this->renderHandleLink($author_phid),
            $old);
        } else if (!strlen($old)) {
          return pht(
            '%s set the repository UUID to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        } else {
          return pht(
            '%s changed the repository UUID from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
        break;
      case self::TYPE_SVN_SUBPATH:
        if (!strlen($new)) {
          return pht(
            '%s removed "%s" as the Import Only path.',
            $this->renderHandleLink($author_phid),
            $old);
        } else if (!strlen($old)) {
          return pht(
            '%s set the repository to import only "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        } else {
          return pht(
            '%s changed the import path from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
        break;
      case self::TYPE_NOTIFY:
        if ($new) {
          return pht(
            '%s enabled notifications and publishing for this repository.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s disabled notifications and publishing for this repository.',
            $this->renderHandleLink($author_phid));
        }
        break;
      case self::TYPE_AUTOCLOSE:
        if ($new) {
          return pht(
            '%s enabled autoclose for this repository.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s disabled autoclose for this repository.',
            $this->renderHandleLink($author_phid));
        }
        break;
      case self::TYPE_REMOTE_URI:
        if (!strlen($old)) {
          return pht(
            '%s set the remote URI for this repository to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        } else if (!strlen($new)) {
          return pht(
            '%s removed the remote URI for this repository.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s changed the remote URI for this repository from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
        break;
      case self::TYPE_SSH_LOGIN:
        return pht(
          '%s updated the SSH login for this repository.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_SSH_KEY:
        return pht(
          '%s updated the SSH key for this repository.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_SSH_KEYFILE:
        return pht(
          '%s updated the SSH keyfile for this repository.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_HTTP_LOGIN:
        return pht(
          '%s updated the HTTP login for this repository.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_HTTP_PASS:
        return pht(
          '%s updated the HTTP password for this repository.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_LOCAL_PATH:
        return pht(
          '%s changed the local path from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
      case self::TYPE_HOSTING:
        if ($new) {
          return pht(
            '%s changed this repository to be hosted on Phabricator.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s changed this repository to track a remote elsewhere.',
            $this->renderHandleLink($author_phid));
        }
      case self::TYPE_PROTOCOL_HTTP:
        return pht(
          '%s changed the availability of this repository over HTTP from '.
          '"%s" to "%s".',
          $this->renderHandleLink($author_phid),
          PhabricatorRepository::getProtocolAvailabilityName($old),
          PhabricatorRepository::getProtocolAvailabilityName($new));
      case self::TYPE_PROTOCOL_SSH:
        return pht(
          '%s changed the availability of this repository over SSH from '.
          '"%s" to "%s".',
          $this->renderHandleLink($author_phid),
          PhabricatorRepository::getProtocolAvailabilityName($old),
          PhabricatorRepository::getProtocolAvailabilityName($new));
      case self::TYPE_PUSH_POLICY:
        return pht(
          '%s changed the push policy of this repository from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $this->renderPolicyName($old, 'old'),
          $this->renderPolicyName($new, 'new'));
      case self::TYPE_DANGEROUS:
        if ($new) {
          return pht(
            '%s disabled protection against dangerous changes.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s enabled protection against dangerous changes.',
            $this->renderHandleLink($author_phid));
        }
      case self::TYPE_SLUG:
        if (strlen($old) && !strlen($new)) {
          return pht(
            '%s removed the short name of this repository.',
            $this->renderHandleLink($author_phid));
        } else if (strlen($new) && !strlen($old)) {
          return pht(
            '%s set the short name of this repository to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        } else {
          return pht(
            '%s changed the short name of this repository from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
      case self::TYPE_SERVICE:
        if (strlen($old) && !strlen($new)) {
          return pht(
            '%s moved storage for this repository from %s to local.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($old));
        } else if (!strlen($old) && strlen($new)) {
          // TODO: Possibly, we should distinguish between automatic assignment
          // on creation vs explicit adjustment.
          return pht(
            '%s set storage for this repository to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($new));
        } else {
          return pht(
            '%s moved storage for this repository from %s to %s.',
            $this->renderHandleLink($author_phid),
            $this->renderHandleLink($old),
            $this->renderHandleLink($new));
        }
      case self::TYPE_SYMBOLS_SOURCES:
        return pht(
          '%s changed symbol sources from %s to %s.',
          $this->renderHandleLink($author_phid),
          empty($old) ? pht('None') : $this->renderHandleList($old),
          empty($new) ? pht('None') : $this->renderHandleList($new));

      case self::TYPE_SYMBOLS_LANGUAGE:
        return pht('%s changed indexed languages from %s to %s.',
          $this->renderHandleLink($author_phid),
          $old ? implode(', ', $old) : pht('Any'),
          $new ? implode(', ', $new) : pht('Any'));

      case self::TYPE_STAGING_URI:
        if (!$old) {
          return pht(
            '%s set "%s" as the staging area for this repository.',
            $this->renderHandleLink($author_phid),
            $new);
        } else if (!$new) {
          return pht(
            '%s removed "%s" as the staging area for this repository.',
            $this->renderHandleLink($author_phid),
            $old);
        } else {
          return pht(
            '%s changed the staging area for this repository from '.
            '"%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }

      case self::TYPE_AUTOMATION_BLUEPRINTS:
        $add = array_diff($new, $old);
        $rem = array_diff($old, $new);

        if ($add && $rem) {
          return pht(
            '%s changed %s automation blueprint(s), '.
            'added %s: %s; removed %s: %s.',
            $this->renderHandleLink($author_phid),
            new PhutilNumber(count($add) + count($rem)),
            new PhutilNumber(count($add)),
            $this->renderHandleList($add),
            new PhutilNumber(count($rem)),
            $this->renderHandleList($rem));
        } else if ($add) {
          return pht(
            '%s added %s automation blueprint(s): %s.',
            $this->renderHandleLink($author_phid),
            new PhutilNumber(count($add)),
            $this->renderHandleList($add));
        } else {
          return pht(
            '%s removed %s automation blueprint(s): %s.',
            $this->renderHandleLink($author_phid),
            new PhutilNumber(count($rem)),
            $this->renderHandleList($rem));
        }

      case self::TYPE_CALLSIGN:
        if ($old === null) {
          return pht(
            '%s set the callsign for this repository to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        } else if ($new === null) {
          return pht(
            '%s removed the callsign ("%s") for this repository.',
            $this->renderHandleLink($author_phid),
            $old);
        } else {
          return pht(
            '%s changed the callsign for this repository from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }

    }

    return parent::getTitle();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        return true;
    }
    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    return $this->renderTextCorpusChangeDetails(
      $viewer,
      $this->getOldValue(),
      $this->getNewValue());
  }

}
