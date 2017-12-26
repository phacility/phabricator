<?php

final class PhabricatorRepositoryEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Repositories');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorRepositoryTransaction::TYPE_VCS;
    $types[] = PhabricatorRepositoryTransaction::TYPE_ACTIVATE;
    $types[] = PhabricatorRepositoryTransaction::TYPE_NAME;
    $types[] = PhabricatorRepositoryTransaction::TYPE_DESCRIPTION;
    $types[] = PhabricatorRepositoryTransaction::TYPE_ENCODING;
    $types[] = PhabricatorRepositoryTransaction::TYPE_DEFAULT_BRANCH;
    $types[] = PhabricatorRepositoryTransaction::TYPE_TRACK_ONLY;
    $types[] = PhabricatorRepositoryTransaction::TYPE_AUTOCLOSE_ONLY;
    $types[] = PhabricatorRepositoryTransaction::TYPE_UUID;
    $types[] = PhabricatorRepositoryTransaction::TYPE_SVN_SUBPATH;
    $types[] = PhabricatorRepositoryTransaction::TYPE_NOTIFY;
    $types[] = PhabricatorRepositoryTransaction::TYPE_AUTOCLOSE;
    $types[] = PhabricatorRepositoryTransaction::TYPE_PUSH_POLICY;
    $types[] = PhabricatorRepositoryTransaction::TYPE_DANGEROUS;
    $types[] = PhabricatorRepositoryTransaction::TYPE_ENORMOUS;
    $types[] = PhabricatorRepositoryTransaction::TYPE_SLUG;
    $types[] = PhabricatorRepositoryTransaction::TYPE_SERVICE;
    $types[] = PhabricatorRepositoryTransaction::TYPE_SYMBOLS_LANGUAGE;
    $types[] = PhabricatorRepositoryTransaction::TYPE_SYMBOLS_SOURCES;
    $types[] = PhabricatorRepositoryTransaction::TYPE_STAGING_URI;
    $types[] = PhabricatorRepositoryTransaction::TYPE_AUTOMATION_BLUEPRINTS;
    $types[] = PhabricatorRepositoryTransaction::TYPE_CALLSIGN;

    $types[] = PhabricatorTransactions::TYPE_EDGE;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorRepositoryTransaction::TYPE_VCS:
        return $object->getVersionControlSystem();
      case PhabricatorRepositoryTransaction::TYPE_ACTIVATE:
        return $object->isTracked();
      case PhabricatorRepositoryTransaction::TYPE_NAME:
        return $object->getName();
      case PhabricatorRepositoryTransaction::TYPE_DESCRIPTION:
        return $object->getDetail('description');
      case PhabricatorRepositoryTransaction::TYPE_ENCODING:
        return $object->getDetail('encoding');
      case PhabricatorRepositoryTransaction::TYPE_DEFAULT_BRANCH:
        return $object->getDetail('default-branch');
      case PhabricatorRepositoryTransaction::TYPE_TRACK_ONLY:
        return array_keys($object->getDetail('branch-filter', array()));
      case PhabricatorRepositoryTransaction::TYPE_AUTOCLOSE_ONLY:
        return array_keys($object->getDetail('close-commits-filter', array()));
      case PhabricatorRepositoryTransaction::TYPE_UUID:
        return $object->getUUID();
      case PhabricatorRepositoryTransaction::TYPE_SVN_SUBPATH:
        return $object->getDetail('svn-subpath');
      case PhabricatorRepositoryTransaction::TYPE_NOTIFY:
        return (int)!$object->getDetail('herald-disabled');
      case PhabricatorRepositoryTransaction::TYPE_AUTOCLOSE:
        return (int)!$object->getDetail('disable-autoclose');
      case PhabricatorRepositoryTransaction::TYPE_PUSH_POLICY:
        return $object->getPushPolicy();
      case PhabricatorRepositoryTransaction::TYPE_DANGEROUS:
        return $object->shouldAllowDangerousChanges();
      case PhabricatorRepositoryTransaction::TYPE_ENORMOUS:
        return $object->shouldAllowEnormousChanges();
      case PhabricatorRepositoryTransaction::TYPE_SLUG:
        return $object->getRepositorySlug();
      case PhabricatorRepositoryTransaction::TYPE_SERVICE:
        return $object->getAlmanacServicePHID();
      case PhabricatorRepositoryTransaction::TYPE_SYMBOLS_LANGUAGE:
        return $object->getSymbolLanguages();
      case PhabricatorRepositoryTransaction::TYPE_SYMBOLS_SOURCES:
        return $object->getSymbolSources();
      case PhabricatorRepositoryTransaction::TYPE_STAGING_URI:
        return $object->getDetail('staging-uri');
      case PhabricatorRepositoryTransaction::TYPE_AUTOMATION_BLUEPRINTS:
        return $object->getDetail('automation.blueprintPHIDs', array());
      case PhabricatorRepositoryTransaction::TYPE_CALLSIGN:
        return $object->getCallsign();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorRepositoryTransaction::TYPE_ACTIVATE:
      case PhabricatorRepositoryTransaction::TYPE_NAME:
      case PhabricatorRepositoryTransaction::TYPE_DESCRIPTION:
      case PhabricatorRepositoryTransaction::TYPE_ENCODING:
      case PhabricatorRepositoryTransaction::TYPE_DEFAULT_BRANCH:
      case PhabricatorRepositoryTransaction::TYPE_TRACK_ONLY:
      case PhabricatorRepositoryTransaction::TYPE_AUTOCLOSE_ONLY:
      case PhabricatorRepositoryTransaction::TYPE_UUID:
      case PhabricatorRepositoryTransaction::TYPE_SVN_SUBPATH:
      case PhabricatorRepositoryTransaction::TYPE_VCS:
      case PhabricatorRepositoryTransaction::TYPE_PUSH_POLICY:
      case PhabricatorRepositoryTransaction::TYPE_DANGEROUS:
      case PhabricatorRepositoryTransaction::TYPE_ENORMOUS:
      case PhabricatorRepositoryTransaction::TYPE_SERVICE:
      case PhabricatorRepositoryTransaction::TYPE_SYMBOLS_LANGUAGE:
      case PhabricatorRepositoryTransaction::TYPE_SYMBOLS_SOURCES:
      case PhabricatorRepositoryTransaction::TYPE_STAGING_URI:
      case PhabricatorRepositoryTransaction::TYPE_AUTOMATION_BLUEPRINTS:
        return $xaction->getNewValue();
      case PhabricatorRepositoryTransaction::TYPE_SLUG:
      case PhabricatorRepositoryTransaction::TYPE_CALLSIGN:
        $name = $xaction->getNewValue();
        if (strlen($name)) {
          return $name;
        }
        return null;
      case PhabricatorRepositoryTransaction::TYPE_NOTIFY:
      case PhabricatorRepositoryTransaction::TYPE_AUTOCLOSE:
        return (int)$xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorRepositoryTransaction::TYPE_VCS:
        $object->setVersionControlSystem($xaction->getNewValue());
        break;
      case PhabricatorRepositoryTransaction::TYPE_ACTIVATE:
        $active = $xaction->getNewValue();

        // The first time a repository is activated, clear the "new repository"
        // flag so we stop showing setup hints.
        if ($active) {
          $object->setDetail('newly-initialized', false);
        }

        $object->setDetail('tracking-enabled', $active);
        break;
      case PhabricatorRepositoryTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        break;
      case PhabricatorRepositoryTransaction::TYPE_DESCRIPTION:
        $object->setDetail('description', $xaction->getNewValue());
        break;
      case PhabricatorRepositoryTransaction::TYPE_DEFAULT_BRANCH:
        $object->setDetail('default-branch', $xaction->getNewValue());
        break;
      case PhabricatorRepositoryTransaction::TYPE_TRACK_ONLY:
        $object->setDetail(
          'branch-filter',
          array_fill_keys($xaction->getNewValue(), true));
        break;
      case PhabricatorRepositoryTransaction::TYPE_AUTOCLOSE_ONLY:
        $object->setDetail(
          'close-commits-filter',
          array_fill_keys($xaction->getNewValue(), true));
        break;
      case PhabricatorRepositoryTransaction::TYPE_UUID:
        $object->setUUID($xaction->getNewValue());
        break;
      case PhabricatorRepositoryTransaction::TYPE_SVN_SUBPATH:
        $object->setDetail('svn-subpath', $xaction->getNewValue());
        break;
      case PhabricatorRepositoryTransaction::TYPE_NOTIFY:
        $object->setDetail('herald-disabled', (int)!$xaction->getNewValue());
        break;
      case PhabricatorRepositoryTransaction::TYPE_AUTOCLOSE:
        $object->setDetail('disable-autoclose', (int)!$xaction->getNewValue());
        break;
      case PhabricatorRepositoryTransaction::TYPE_PUSH_POLICY:
        return $object->setPushPolicy($xaction->getNewValue());
      case PhabricatorRepositoryTransaction::TYPE_DANGEROUS:
        $object->setDetail('allow-dangerous-changes', $xaction->getNewValue());
        return;
      case PhabricatorRepositoryTransaction::TYPE_ENORMOUS:
        $object->setDetail('allow-enormous-changes', $xaction->getNewValue());
        return;
      case PhabricatorRepositoryTransaction::TYPE_SLUG:
        $object->setRepositorySlug($xaction->getNewValue());
        return;
      case PhabricatorRepositoryTransaction::TYPE_SERVICE:
        $object->setAlmanacServicePHID($xaction->getNewValue());
        return;
      case PhabricatorRepositoryTransaction::TYPE_SYMBOLS_LANGUAGE:
        $object->setDetail('symbol-languages', $xaction->getNewValue());
        return;
      case PhabricatorRepositoryTransaction::TYPE_SYMBOLS_SOURCES:
        $object->setDetail('symbol-sources', $xaction->getNewValue());
        return;
      case PhabricatorRepositoryTransaction::TYPE_STAGING_URI:
        $object->setDetail('staging-uri', $xaction->getNewValue());
        return;
      case PhabricatorRepositoryTransaction::TYPE_AUTOMATION_BLUEPRINTS:
        $object->setDetail(
          'automation.blueprintPHIDs',
          $xaction->getNewValue());
        return;
      case PhabricatorRepositoryTransaction::TYPE_CALLSIGN:
        $object->setCallsign($xaction->getNewValue());
        return;
      case PhabricatorRepositoryTransaction::TYPE_ENCODING:
        $object->setDetail('encoding', $xaction->getNewValue());
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorRepositoryTransaction::TYPE_AUTOMATION_BLUEPRINTS:
        DrydockAuthorization::applyAuthorizationChanges(
          $this->getActor(),
          $object->getPHID(),
          $xaction->getOldValue(),
          $xaction->getNewValue());
        break;
    }

  }

  protected function requireCapabilities(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorRepositoryTransaction::TYPE_ACTIVATE:
      case PhabricatorRepositoryTransaction::TYPE_NAME:
      case PhabricatorRepositoryTransaction::TYPE_DESCRIPTION:
      case PhabricatorRepositoryTransaction::TYPE_ENCODING:
      case PhabricatorRepositoryTransaction::TYPE_DEFAULT_BRANCH:
      case PhabricatorRepositoryTransaction::TYPE_TRACK_ONLY:
      case PhabricatorRepositoryTransaction::TYPE_AUTOCLOSE_ONLY:
      case PhabricatorRepositoryTransaction::TYPE_UUID:
      case PhabricatorRepositoryTransaction::TYPE_SVN_SUBPATH:
      case PhabricatorRepositoryTransaction::TYPE_VCS:
      case PhabricatorRepositoryTransaction::TYPE_NOTIFY:
      case PhabricatorRepositoryTransaction::TYPE_AUTOCLOSE:
      case PhabricatorRepositoryTransaction::TYPE_PUSH_POLICY:
      case PhabricatorRepositoryTransaction::TYPE_DANGEROUS:
      case PhabricatorRepositoryTransaction::TYPE_ENORMOUS:
      case PhabricatorRepositoryTransaction::TYPE_SLUG:
      case PhabricatorRepositoryTransaction::TYPE_SERVICE:
      case PhabricatorRepositoryTransaction::TYPE_SYMBOLS_SOURCES:
      case PhabricatorRepositoryTransaction::TYPE_SYMBOLS_LANGUAGE:
      case PhabricatorRepositoryTransaction::TYPE_STAGING_URI:
      case PhabricatorRepositoryTransaction::TYPE_AUTOMATION_BLUEPRINTS:
        PhabricatorPolicyFilter::requireCapability(
          $this->requireActor(),
          $object,
          PhabricatorPolicyCapability::CAN_EDIT);
        break;
    }
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    switch ($type) {
      case PhabricatorRepositoryTransaction::TYPE_AUTOCLOSE_ONLY:
      case PhabricatorRepositoryTransaction::TYPE_TRACK_ONLY:
        foreach ($xactions as $xaction) {
          foreach ($xaction->getNewValue() as $pattern) {
            // Check for invalid regular expressions.
            $regexp = PhabricatorRepository::extractBranchRegexp($pattern);
            if ($regexp !== null) {
              $ok = @preg_match($regexp, '');
              if ($ok === false) {
                $error = new PhabricatorApplicationTransactionValidationError(
                  $type,
                  pht('Invalid'),
                  pht(
                    'Expression "%s" is not a valid regular expression. Note '.
                    'that you must include delimiters.',
                    $regexp),
                  $xaction);
                $errors[] = $error;
                continue;
              }
            }

            // Check for formatting mistakes like `regex(...)` instead of
            // `regexp(...)`.
            $matches = null;
            if (preg_match('/^([^(]+)\\(.*\\)\z/', $pattern, $matches)) {
              switch ($matches[1]) {
                case 'regexp':
                  break;
                default:
                  $error = new PhabricatorApplicationTransactionValidationError(
                    $type,
                    pht('Invalid'),
                    pht(
                      'Matching function "%s(...)" is not recognized. Valid '.
                      'functions are: regexp(...).',
                      $matches[1]),
                    $xaction);
                  $errors[] = $error;
                  break;
              }
            }
          }
        }
        break;

      case PhabricatorRepositoryTransaction::TYPE_AUTOMATION_BLUEPRINTS:
        foreach ($xactions as $xaction) {
          $old = nonempty($xaction->getOldValue(), array());
          $new = nonempty($xaction->getNewValue(), array());

          $add = array_diff($new, $old);

          $invalid = PhabricatorObjectQuery::loadInvalidPHIDsForViewer(
            $this->getActor(),
            $add);
          if ($invalid) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'Some of the selected automation blueprints are invalid '.
                'or restricted: %s.',
                implode(', ', $invalid)),
              $xaction);
          }
        }
        break;

      case PhabricatorRepositoryTransaction::TYPE_VCS:
        $vcs_map = PhabricatorRepositoryType::getAllRepositoryTypes();
        $current_vcs = $object->getVersionControlSystem();

        if (!$this->getIsNewObject()) {
          foreach ($xactions as $xaction) {
            if ($xaction->getNewValue() == $current_vcs) {
              continue;
            }

            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Immutable'),
              pht(
                'You can not change the version control system an existing '.
                'repository uses. It can only be set when a repository is '.
                'first created.'),
              $xaction);
          }
        } else {
          $value = $object->getVersionControlSystem();
          foreach ($xactions as $xaction) {
            $value = $xaction->getNewValue();

            if (empty($vcs_map[$value])) {
              $errors[] = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid'),
                pht(
                  'Specified version control system must be a VCS '.
                  'recognized by Phabricator: %s.',
                  implode(', ', array_keys($vcs_map))),
                $xaction);
            }
          }

          if (!strlen($value)) {
            $error = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Required'),
              pht(
                'When creating a repository, you must specify a valid '.
                'underlying version control system: %s.',
                implode(', ', array_keys($vcs_map))),
              nonempty(last($xactions), null));
            $error->setIsMissingFieldError(true);
            $errors[] = $error;
          }
        }
        break;

      case PhabricatorRepositoryTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Repository name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;

      case PhabricatorRepositoryTransaction::TYPE_ACTIVATE:
        $status_map = PhabricatorRepository::getStatusMap();
        foreach ($xactions as $xaction) {
          $status = $xaction->getNewValue();
          if (empty($status_map[$status])) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'Repository status "%s" is not valid.',
                $status),
              $xaction);
          }
        }
        break;

      case PhabricatorRepositoryTransaction::TYPE_ENCODING:
        foreach ($xactions as $xaction) {
          // Make sure the encoding is valid by converting to UTF-8. This tests
          // that the user has mbstring installed, and also that they didn't
          // type a garbage encoding name. Note that we're converting from
          // UTF-8 to the target encoding, because mbstring is fine with
          // converting from a nonsense encoding.
          $encoding = $xaction->getNewValue();
          if (!strlen($encoding)) {
            continue;
          }

          try {
            phutil_utf8_convert('.', $encoding, 'UTF-8');
          } catch (Exception $ex) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'Repository encoding "%s" is not valid: %s',
                $encoding,
                $ex->getMessage()),
              $xaction);
          }
        }
        break;

      case PhabricatorRepositoryTransaction::TYPE_SLUG:
        foreach ($xactions as $xaction) {
          $old = $xaction->getOldValue();
          $new = $xaction->getNewValue();

          if (!strlen($new)) {
            continue;
          }

          if ($new === $old) {
            continue;
          }

          try {
            PhabricatorRepository::assertValidRepositorySlug($new);
          } catch (Exception $ex) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              $ex->getMessage(),
              $xaction);
            continue;
          }

          $other = id(new PhabricatorRepositoryQuery())
            ->setViewer(PhabricatorUser::getOmnipotentUser())
            ->withSlugs(array($new))
            ->executeOne();
          if ($other && ($other->getID() !== $object->getID())) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Duplicate'),
              pht(
                'The selected repository short name is already in use by '.
                'another repository. Choose a unique short name.'),
              $xaction);
            continue;
          }
        }
        break;

      case PhabricatorRepositoryTransaction::TYPE_CALLSIGN:
        foreach ($xactions as $xaction) {
          $old = $xaction->getOldValue();
          $new = $xaction->getNewValue();

          if (!strlen($new)) {
            continue;
          }

          if ($new === $old) {
            continue;
          }

          try {
            PhabricatorRepository::assertValidCallsign($new);
          } catch (Exception $ex) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              $ex->getMessage(),
              $xaction);
            continue;
          }

          $other = id(new PhabricatorRepositoryQuery())
            ->setViewer(PhabricatorUser::getOmnipotentUser())
            ->withCallsigns(array($new))
            ->executeOne();
          if ($other && ($other->getID() !== $object->getID())) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Duplicate'),
              pht(
                'The selected callsign ("%s") is already in use by another '.
                'repository. Choose a unique callsign.',
                $new),
              $xaction);
            continue;
          }
        }
        break;

      case PhabricatorRepositoryTransaction::TYPE_SYMBOLS_SOURCES:
        foreach ($xactions as $xaction) {
          $old = $object->getSymbolSources();
          $new = $xaction->getNewValue();

          // If the viewer is adding new repositories, make sure they are
          // valid and visible.
          $add = array_diff($new, $old);
          if (!$add) {
            continue;
          }

          $repositories = id(new PhabricatorRepositoryQuery())
            ->setViewer($this->getActor())
            ->withPHIDs($add)
            ->execute();
          $repositories = mpull($repositories, null, 'getPHID');

          foreach ($add as $phid) {
            if (isset($repositories[$phid])) {
              continue;
            }

            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'Repository ("%s") does not exist, or you do not have '.
                'permission to see it.',
                $phid),
              $xaction);
            break;
          }
        }
        break;

    }

    return $errors;
  }

  protected function didCatchDuplicateKeyException(
    PhabricatorLiskDAO $object,
    array $xactions,
    Exception $ex) {

    $errors = array();

    $errors[] = new PhabricatorApplicationTransactionValidationError(
      null,
      pht('Invalid'),
      pht(
        'The chosen callsign or repository short name is already in '.
        'use by another repository.'),
      null);

    throw new PhabricatorApplicationTransactionValidationException($errors);
  }

  protected function supportsSearch() {
    return true;
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    // If the repository does not have a local path yet, assign it one based
    // on its ID. We can't do this earlier because we won't have an ID yet.
    $local_path = $object->getLocalPath();
    if (!strlen($local_path)) {
      $local_key = 'repository.default-local-path';

      $local_root = PhabricatorEnv::getEnvConfig($local_key);
      $local_root = rtrim($local_root, '/');

      $id = $object->getID();
      $local_path = "{$local_root}/{$id}/";

      $object->setLocalPath($local_path);
      $object->save();
    }

    if ($this->getIsNewObject()) {
      // The default state of repositories is to be hosted, if they are
      // enabled without configuring any "Observe" URIs.
      $object->setHosted(true);
      $object->save();

      // Create this repository's builtin URIs.
      $builtin_uris = $object->newBuiltinURIs();
      foreach ($builtin_uris as $uri) {
        $uri->save();
      }

      id(new DiffusionRepositoryClusterEngine())
        ->setViewer($this->getActor())
        ->setRepository($object)
        ->synchronizeWorkingCopyAfterCreation();
    }

    $object->writeStatusMessage(
      PhabricatorRepositoryStatusMessage::TYPE_NEEDS_UPDATE,
      null);

    return $xactions;
  }

}
