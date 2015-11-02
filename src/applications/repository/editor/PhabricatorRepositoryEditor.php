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
    $types[] = PhabricatorRepositoryTransaction::TYPE_REMOTE_URI;
    $types[] = PhabricatorRepositoryTransaction::TYPE_SSH_LOGIN;
    $types[] = PhabricatorRepositoryTransaction::TYPE_SSH_KEY;
    $types[] = PhabricatorRepositoryTransaction::TYPE_SSH_KEYFILE;
    $types[] = PhabricatorRepositoryTransaction::TYPE_HTTP_LOGIN;
    $types[] = PhabricatorRepositoryTransaction::TYPE_HTTP_PASS;
    $types[] = PhabricatorRepositoryTransaction::TYPE_LOCAL_PATH;
    $types[] = PhabricatorRepositoryTransaction::TYPE_HOSTING;
    $types[] = PhabricatorRepositoryTransaction::TYPE_PROTOCOL_HTTP;
    $types[] = PhabricatorRepositoryTransaction::TYPE_PROTOCOL_SSH;
    $types[] = PhabricatorRepositoryTransaction::TYPE_PUSH_POLICY;
    $types[] = PhabricatorRepositoryTransaction::TYPE_CREDENTIAL;
    $types[] = PhabricatorRepositoryTransaction::TYPE_DANGEROUS;
    $types[] = PhabricatorRepositoryTransaction::TYPE_CLONE_NAME;
    $types[] = PhabricatorRepositoryTransaction::TYPE_SERVICE;
    $types[] = PhabricatorRepositoryTransaction::TYPE_SYMBOLS_LANGUAGE;
    $types[] = PhabricatorRepositoryTransaction::TYPE_SYMBOLS_SOURCES;
    $types[] = PhabricatorRepositoryTransaction::TYPE_STAGING_URI;
    $types[] = PhabricatorRepositoryTransaction::TYPE_AUTOMATION_BLUEPRINTS;

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
      case PhabricatorRepositoryTransaction::TYPE_REMOTE_URI:
        return $object->getDetail('remote-uri');
      case PhabricatorRepositoryTransaction::TYPE_LOCAL_PATH:
        return $object->getDetail('local-path');
      case PhabricatorRepositoryTransaction::TYPE_HOSTING:
        return $object->isHosted();
      case PhabricatorRepositoryTransaction::TYPE_PROTOCOL_HTTP:
        return $object->getServeOverHTTP();
      case PhabricatorRepositoryTransaction::TYPE_PROTOCOL_SSH:
        return $object->getServeOverSSH();
      case PhabricatorRepositoryTransaction::TYPE_PUSH_POLICY:
        return $object->getPushPolicy();
      case PhabricatorRepositoryTransaction::TYPE_CREDENTIAL:
        return $object->getCredentialPHID();
      case PhabricatorRepositoryTransaction::TYPE_DANGEROUS:
        return $object->shouldAllowDangerousChanges();
      case PhabricatorRepositoryTransaction::TYPE_CLONE_NAME:
        return $object->getDetail('clone-name');
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
      case PhabricatorRepositoryTransaction::TYPE_REMOTE_URI:
      case PhabricatorRepositoryTransaction::TYPE_SSH_LOGIN:
      case PhabricatorRepositoryTransaction::TYPE_SSH_KEY:
      case PhabricatorRepositoryTransaction::TYPE_SSH_KEYFILE:
      case PhabricatorRepositoryTransaction::TYPE_HTTP_LOGIN:
      case PhabricatorRepositoryTransaction::TYPE_HTTP_PASS:
      case PhabricatorRepositoryTransaction::TYPE_LOCAL_PATH:
      case PhabricatorRepositoryTransaction::TYPE_VCS:
      case PhabricatorRepositoryTransaction::TYPE_HOSTING:
      case PhabricatorRepositoryTransaction::TYPE_PROTOCOL_HTTP:
      case PhabricatorRepositoryTransaction::TYPE_PROTOCOL_SSH:
      case PhabricatorRepositoryTransaction::TYPE_PUSH_POLICY:
      case PhabricatorRepositoryTransaction::TYPE_CREDENTIAL:
      case PhabricatorRepositoryTransaction::TYPE_DANGEROUS:
      case PhabricatorRepositoryTransaction::TYPE_CLONE_NAME:
      case PhabricatorRepositoryTransaction::TYPE_SERVICE:
      case PhabricatorRepositoryTransaction::TYPE_SYMBOLS_LANGUAGE:
      case PhabricatorRepositoryTransaction::TYPE_SYMBOLS_SOURCES:
      case PhabricatorRepositoryTransaction::TYPE_STAGING_URI:
      case PhabricatorRepositoryTransaction::TYPE_AUTOMATION_BLUEPRINTS:
        return $xaction->getNewValue();
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
        $object->setDetail('tracking-enabled', $xaction->getNewValue());
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
      case PhabricatorRepositoryTransaction::TYPE_REMOTE_URI:
        $object->setDetail('remote-uri', $xaction->getNewValue());
        break;
      case PhabricatorRepositoryTransaction::TYPE_LOCAL_PATH:
        $object->setDetail('local-path', $xaction->getNewValue());
        break;
      case PhabricatorRepositoryTransaction::TYPE_HOSTING:
        return $object->setHosted($xaction->getNewValue());
      case PhabricatorRepositoryTransaction::TYPE_PROTOCOL_HTTP:
        return $object->setServeOverHTTP($xaction->getNewValue());
      case PhabricatorRepositoryTransaction::TYPE_PROTOCOL_SSH:
        return $object->setServeOverSSH($xaction->getNewValue());
      case PhabricatorRepositoryTransaction::TYPE_PUSH_POLICY:
        return $object->setPushPolicy($xaction->getNewValue());
      case PhabricatorRepositoryTransaction::TYPE_CREDENTIAL:
        return $object->setCredentialPHID($xaction->getNewValue());
      case PhabricatorRepositoryTransaction::TYPE_DANGEROUS:
        $object->setDetail('allow-dangerous-changes', $xaction->getNewValue());
        return;
      case PhabricatorRepositoryTransaction::TYPE_CLONE_NAME:
        $object->setDetail('clone-name', $xaction->getNewValue());
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
      case PhabricatorRepositoryTransaction::TYPE_ENCODING:
        // Make sure the encoding is valid by converting to UTF-8. This tests
        // that the user has mbstring installed, and also that they didn't type
        // a garbage encoding name. Note that we're converting from UTF-8 to
        // the target encoding, because mbstring is fine with converting from
        // a nonsense encoding.
        $encoding = $xaction->getNewValue();
        if (strlen($encoding)) {
          try {
            phutil_utf8_convert('.', $encoding, 'UTF-8');
          } catch (Exception $ex) {
            throw new PhutilProxyException(
              pht(
                "Error setting repository encoding '%s': %s'",
                $encoding,
                $ex->getMessage()),
              $ex);
          }
        }
        $object->setDetail('encoding', $encoding);
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorRepositoryTransaction::TYPE_CREDENTIAL:
        // Adjust the object <-> credential edge for this repository.

        $old_phid = $xaction->getOldValue();
        $new_phid = $xaction->getNewValue();

        $editor = new PhabricatorEdgeEditor();

        $edge_type = PhabricatorObjectUsesCredentialsEdgeType::EDGECONST;
        $src_phid = $object->getPHID();

        if ($old_phid) {
          $editor->removeEdge($src_phid, $edge_type, $old_phid);
        }

        if ($new_phid) {
          $editor->addEdge($src_phid, $edge_type, $new_phid);
        }

        $editor->save();
        break;
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
      case PhabricatorRepositoryTransaction::TYPE_REMOTE_URI:
      case PhabricatorRepositoryTransaction::TYPE_SSH_LOGIN:
      case PhabricatorRepositoryTransaction::TYPE_SSH_KEY:
      case PhabricatorRepositoryTransaction::TYPE_SSH_KEYFILE:
      case PhabricatorRepositoryTransaction::TYPE_HTTP_LOGIN:
      case PhabricatorRepositoryTransaction::TYPE_HTTP_PASS:
      case PhabricatorRepositoryTransaction::TYPE_LOCAL_PATH:
      case PhabricatorRepositoryTransaction::TYPE_VCS:
      case PhabricatorRepositoryTransaction::TYPE_NOTIFY:
      case PhabricatorRepositoryTransaction::TYPE_AUTOCLOSE:
      case PhabricatorRepositoryTransaction::TYPE_HOSTING:
      case PhabricatorRepositoryTransaction::TYPE_PROTOCOL_HTTP:
      case PhabricatorRepositoryTransaction::TYPE_PROTOCOL_SSH:
      case PhabricatorRepositoryTransaction::TYPE_PUSH_POLICY:
      case PhabricatorRepositoryTransaction::TYPE_CREDENTIAL:
      case PhabricatorRepositoryTransaction::TYPE_DANGEROUS:
      case PhabricatorRepositoryTransaction::TYPE_CLONE_NAME:
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
      case PhabricatorRepositoryTransaction::TYPE_AUTOCLOSE:
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

      case PhabricatorRepositoryTransaction::TYPE_REMOTE_URI:
        foreach ($xactions as $xaction) {
          $new_uri = $xaction->getNewValue();
          try {
            PhabricatorRepository::assertValidRemoteURI($new_uri);
          } catch (Exception $ex) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              $ex->getMessage(),
              $xaction);
          }
        }
        break;

      case PhabricatorRepositoryTransaction::TYPE_CREDENTIAL:
        $ok = PassphraseCredentialControl::validateTransactions(
          $this->getActor(),
          $xactions);
        if (!$ok) {
          foreach ($xactions as $xaction) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'The selected credential does not exist, or you do not have '.
                'permission to use it.'),
              $xaction);
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
    }

    return $errors;
  }

}
