<?php

final class DiffusionURIEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Diffusion URIs');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorRepositoryURITransaction::TYPE_URI;
    $types[] = PhabricatorRepositoryURITransaction::TYPE_IO;
    $types[] = PhabricatorRepositoryURITransaction::TYPE_DISPLAY;

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
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorRepositoryURITransaction::TYPE_URI:
        $object->setURI($xaction->getNewValue());
        break;
      case PhabricatorRepositoryURITransaction::TYPE_IO:
        $object->setIOType($xaction->getNewValue());
        break;
      case PhabricatorRepositoryURITransaction::TYPE_DISPLAY:
        $object->setDisplayType($xaction->getNewValue());
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
      case PhabricatorRepositoryURITransaction::TYPE_URI:
        $missing = $this->validateIsEmptyTextField(
          $object->getURI(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Repository URI is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
          break;
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
                'Value "%s" is not a valid display setting for this URI. '.
                'Available types for this URI are: %s.',
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
              ->withPHIDs(array($object->getRepositoryPHID()))
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
                implode(', ', array_keys($available))));
          }
        }
        break;
    }

    return $errors;
  }

}
