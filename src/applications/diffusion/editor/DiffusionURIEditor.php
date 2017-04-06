<?php

final class DiffusionURIEditor
  extends PhabricatorApplicationTransactionEditor {

  private $repository;
  private $repositoryPHID;

  public function getEditorApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Diffusion URIs');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorRepositoryURITransaction::TYPE_REPOSITORY;
    $types[] = PhabricatorRepositoryURITransaction::TYPE_URI;
    $types[] = PhabricatorRepositoryURITransaction::TYPE_IO;
    $types[] = PhabricatorRepositoryURITransaction::TYPE_DISPLAY;
    $types[] = PhabricatorRepositoryURITransaction::TYPE_CREDENTIAL;
    $types[] = PhabricatorRepositoryURITransaction::TYPE_DISABLE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorRepositoryURITransaction::TYPE_URI:
        return $object->getURI();
      case PhabricatorRepositoryURITransaction::TYPE_IO:
        return $object->getIOType();
      case PhabricatorRepositoryURITransaction::TYPE_DISPLAY:
        return $object->getDisplayType();
      case PhabricatorRepositoryURITransaction::TYPE_REPOSITORY:
        return $object->getRepositoryPHID();
      case PhabricatorRepositoryURITransaction::TYPE_CREDENTIAL:
        return $object->getCredentialPHID();
      case PhabricatorRepositoryURITransaction::TYPE_DISABLE:
        return (int)$object->getIsDisabled();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorRepositoryURITransaction::TYPE_URI:
      case PhabricatorRepositoryURITransaction::TYPE_IO:
      case PhabricatorRepositoryURITransaction::TYPE_DISPLAY:
      case PhabricatorRepositoryURITransaction::TYPE_REPOSITORY:
      case PhabricatorRepositoryURITransaction::TYPE_CREDENTIAL:
        return $xaction->getNewValue();
      case PhabricatorRepositoryURITransaction::TYPE_DISABLE:
        return (int)$xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorRepositoryURITransaction::TYPE_URI:
        if (!$this->getIsNewObject()) {
          $old_uri = $object->getEffectiveURI();
        } else {
          $old_uri = null;

          // When creating a URI via the API, we may not have processed the
          // repository transaction yet. Attach the repository here to make
          // sure we have it for the calls below.
          if ($this->repository) {
            $object->attachRepository($this->repository);
          }
        }

        $object->setURI($xaction->getNewValue());

        // If we've changed the domain or protocol of the URI, remove the
        // current credential. This improves behavior in several cases:

        // If a user switches between protocols with different credential
        // types, like HTTP and SSH, the old credential won't be valid anyway.
        // It's cleaner to remove it than leave a bad credential in place.

        // If a user switches hosts, the old credential is probably not
        // correct (and potentially confusing/misleading). Removing it forces
        // users to double check that they have the correct credentials.

        // If an attacker can't see a symmetric credential like a username and
        // password, they could still potentially capture it by changing the
        // host for a URI that uses it to `evil.com`, a server they control,
        // then observing the requests. Removing the credential prevents this
        // kind of escalation.

        // Since port and path changes are less likely to fall among these
        // cases, they don't trigger a credential wipe.

        $new_uri = $object->getEffectiveURI();
        if ($old_uri) {
          $new_proto = ($old_uri->getProtocol() != $new_uri->getProtocol());
          $new_domain = ($old_uri->getDomain() != $new_uri->getDomain());
          if ($new_proto || $new_domain) {
            $object->setCredentialPHID(null);
          }
        }
        break;
      case PhabricatorRepositoryURITransaction::TYPE_IO:
        $object->setIOType($xaction->getNewValue());
        break;
      case PhabricatorRepositoryURITransaction::TYPE_DISPLAY:
        $object->setDisplayType($xaction->getNewValue());
        break;
      case PhabricatorRepositoryURITransaction::TYPE_REPOSITORY:
        $object->setRepositoryPHID($xaction->getNewValue());
        $object->attachRepository($this->repository);
        break;
      case PhabricatorRepositoryURITransaction::TYPE_CREDENTIAL:
        $object->setCredentialPHID($xaction->getNewValue());
        break;
      case PhabricatorRepositoryURITransaction::TYPE_DISABLE:
        $object->setIsDisabled($xaction->getNewValue());
        break;
    }
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorRepositoryURITransaction::TYPE_URI:
      case PhabricatorRepositoryURITransaction::TYPE_IO:
      case PhabricatorRepositoryURITransaction::TYPE_DISPLAY:
      case PhabricatorRepositoryURITransaction::TYPE_REPOSITORY:
      case PhabricatorRepositoryURITransaction::TYPE_CREDENTIAL:
      case PhabricatorRepositoryURITransaction::TYPE_DISABLE:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    switch ($type) {
      case PhabricatorRepositoryURITransaction::TYPE_REPOSITORY:
        // Save this, since we need it to validate TYPE_IO transactions.
        $this->repositoryPHID = $object->getRepositoryPHID();

        $missing = $this->validateIsEmptyTextField(
          $object->getRepositoryPHID(),
          $xactions);
        if ($missing) {
          // NOTE: This isn't being marked as a missing field error because
          // it's a fundamental, required property of the URI.
          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht(
              'When creating a repository URI, you must specify which '.
              'repository the URI will belong to.'),
            nonempty(last($xactions), null));
          break;
        }

        $viewer = $this->getActor();

        foreach ($xactions as $xaction) {
          $repository_phid = $xaction->getNewValue();

          // If this isn't changing anything, let it through as-is.
          if ($repository_phid == $object->getRepositoryPHID()) {
            continue;
          }

          if (!$this->getIsNewObject()) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'The repository a URI is associated with is immutable, and '.
                'can not be changed after the URI is created.'),
              $xaction);
            continue;
          }

          $repository = id(new PhabricatorRepositoryQuery())
            ->setViewer($viewer)
            ->withPHIDs(array($repository_phid))
            ->requireCapabilities(
              array(
                PhabricatorPolicyCapability::CAN_VIEW,
                PhabricatorPolicyCapability::CAN_EDIT,
              ))
            ->executeOne();
          if (!$repository) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'To create a URI for a repository ("%s"), it must exist and '.
                'you must have permission to edit it.',
                $repository_phid),
              $xaction);
            continue;
          }

          $this->repository = $repository;
          $this->repositoryPHID = $repository_phid;
        }
        break;
      case PhabricatorRepositoryURITransaction::TYPE_CREDENTIAL:
        $viewer = $this->getActor();
        foreach ($xactions as $xaction) {
          $credential_phid = $xaction->getNewValue();

          if ($credential_phid == $object->getCredentialPHID()) {
            continue;
          }

          // Anyone who can edit a URI can remove the credential.
          if ($credential_phid === null) {
            continue;
          }

          $credential = id(new PassphraseCredentialQuery())
            ->setViewer($viewer)
            ->withPHIDs(array($credential_phid))
            ->executeOne();
          if (!$credential) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'You can only associate a credential ("%s") with a repository '.
                'URI if it exists and you have permission to see it.',
                $credential_phid),
              $xaction);
            continue;
          }
        }
        break;
      case PhabricatorRepositoryURITransaction::TYPE_URI:
        $missing = $this->validateIsEmptyTextField(
          $object->getURI(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('A repository URI must have a nonempty URI.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
          break;
        }

        foreach ($xactions as $xaction) {
          $new_uri = $xaction->getNewValue();
          if ($new_uri == $object->getURI()) {
            continue;
          }

          try {
            PhabricatorRepository::assertValidRemoteURI($new_uri);
          } catch (Exception $ex) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              $ex->getMessage(),
              $xaction);
            continue;
          }
        }

        break;
      case PhabricatorRepositoryURITransaction::TYPE_IO:
        $available = $object->getAvailableIOTypeOptions();
        foreach ($xactions as $xaction) {
          $new = $xaction->getNewValue();

          if (empty($available[$new])) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'Value "%s" is not a valid IO setting for this URI. '.
                'Available types for this URI are: %s.',
                $new,
                implode(', ', array_keys($available))),
              $xaction);
            continue;
          }

          // If we are setting this URI to use "Observe", we must have no
          // other "Observe" URIs and must also have no "Read/Write" URIs.

          // If we are setting this URI to "Read/Write", we must have no
          // other "Observe" URIs. It's OK to have other "Read/Write" URIs.

          $no_observers = false;
          $no_readwrite = false;
          switch ($new) {
            case PhabricatorRepositoryURI::IO_OBSERVE:
              $no_readwrite = true;
              $no_observers = true;
              break;
            case PhabricatorRepositoryURI::IO_READWRITE:
              $no_observers = true;
              break;
          }

          if ($no_observers || $no_readwrite) {
            $repository = id(new PhabricatorRepositoryQuery())
              ->setViewer(PhabricatorUser::getOmnipotentUser())
              ->withPHIDs(array($this->repositoryPHID))
              ->needURIs(true)
              ->executeOne();
            $uris = $repository->getURIs();

            $observe_conflict = null;
            $readwrite_conflict = null;
            foreach ($uris as $uri) {
              // If this is the URI being edited, it can not conflict with
              // itself.
              if ($uri->getID() == $object->getID()) {
                continue;
              }

              $io_type = $uri->getIoType();

              if ($io_type == PhabricatorRepositoryURI::IO_READWRITE) {
                if ($no_readwrite) {
                  $readwite_conflict = $uri;
                  break;
                }
              }

              if ($io_type == PhabricatorRepositoryURI::IO_OBSERVE) {
                if ($no_observers) {
                  $observe_conflict = $uri;
                  break;
                }
              }
            }

            if ($observe_conflict) {
              if ($new == PhabricatorRepositoryURI::IO_OBSERVE) {
                $message = pht(
                  'You can not set this URI to use Observe IO because '.
                  'another URI for this repository is already configured '.
                  'in Observe IO mode. A repository can not observe two '.
                  'different remotes simultaneously. Turn off IO for the '.
                  'other URI first.');
              } else {
                $message = pht(
                  'You can not set this URI to use Read/Write IO because '.
                  'another URI for this repository is already configured '.
                  'in Observe IO mode. An observed repository can not be '.
                  'made writable. Turn off IO for the other URI first.');
              }

              $errors[] = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid'),
                $message,
                $xaction);
              continue;
            }

            if ($readwrite_conflict) {
              $message = pht(
                'You can not set this URI to use Observe IO because '.
                'another URI for this repository is already configured '.
                'in Read/Write IO mode. A repository can not simultaneously '.
                'be writable and observe a remote. Turn off IO for the '.
                'other URI first.');

              $errors[] = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid'),
                $message,
                $xaction);
              continue;
            }
          }
        }

        break;
      case PhabricatorRepositoryURITransaction::TYPE_DISPLAY:
        $available = $object->getAvailableDisplayTypeOptions();
        foreach ($xactions as $xaction) {
          $new = $xaction->getNewValue();

          if (empty($available[$new])) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'Value "%s" is not a valid display setting for this URI. '.
                'Available types for this URI are: %s.',
                $new,
                implode(', ', array_keys($available))));
          }
        }
        break;

      case PhabricatorRepositoryURITransaction::TYPE_DISABLE:
        $old = $object->getIsDisabled();
        foreach ($xactions as $xaction) {
          $new = $xaction->getNewValue();

          if ($old == $new) {
            continue;
          }

          if (!$object->isBuiltin()) {
            continue;
          }

          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid'),
            pht('You can not manually disable builtin URIs.'));
        }
        break;
    }

    return $errors;
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    // Synchronize the repository state based on the presence of an "Observe"
    // URI.
    $repository = $object->getRepository();

    $uris = id(new PhabricatorRepositoryURIQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withRepositories(array($repository))
      ->execute();

    // Reattach the current URIs to the repository: we're going to rebuild
    // the index explicitly below, and want to include any changes made to
    // this URI in the index update.
    $repository->attachURIs($uris);

    $observe_uri = null;
    foreach ($uris as $uri) {
      if ($uri->getIoType() != PhabricatorRepositoryURI::IO_OBSERVE) {
        continue;
      }

      $observe_uri = $uri;
      break;
    }

    $was_hosted = $repository->isHosted();

    if ($observe_uri) {
      $repository
        ->setHosted(false)
        ->setDetail('remote-uri', (string)$observe_uri->getEffectiveURI())
        ->setCredentialPHID($observe_uri->getCredentialPHID());
    } else {
      $repository
        ->setHosted(true)
        ->setDetail('remote-uri', null)
        ->setCredentialPHID(null);
    }

    $repository->save();

    // Explicitly update the URI index.
    $repository->updateURIIndex();

    $is_hosted = $repository->isHosted();

    // If we've swapped the repository from hosted to observed or vice versa,
    // reset all the cluster version clocks.
    if ($was_hosted != $is_hosted) {
      $cluster_engine = id(new DiffusionRepositoryClusterEngine())
        ->setViewer($this->getActor())
        ->setRepository($repository)
        ->synchronizeWorkingCopyAfterHostingChange();
    }

    $repository->writeStatusMessage(
      PhabricatorRepositoryStatusMessage::TYPE_NEEDS_UPDATE,
      null);

    return $xactions;
  }

}
