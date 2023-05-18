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

    $types[] = PhabricatorTransactions::TYPE_EDGE;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
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
    if ($local_path === null || !strlen($local_path)) {
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
