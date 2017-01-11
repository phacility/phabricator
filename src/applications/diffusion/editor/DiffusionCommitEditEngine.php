<?php

final class DiffusionCommitEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'diffusion.commit';

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Commits');
  }

  public function getSummaryHeader() {
    return pht('Edit Commits');
  }

  public function getSummaryText() {
    return pht('Edit commits.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  protected function newEditableObject() {
    throw new PhutilMethodNotImplementedException();
  }

  protected function newObjectQuery() {
    return id(new DiffusionCommitQuery())
      ->needCommitData(true);
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Commit');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Commit');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Commit: %s', $object->getDisplayName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getDisplayName();
  }

  protected function getObjectName() {
    return pht('Commit');
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function getCreateNewObjectPolicy() {
    return PhabricatorPolicies::POLICY_NOONE;
  }

  protected function buildCustomEditFields($object) {
    $viewer = $this->getViewer();
    $data = $object->getCommitData();

    $fields = array();

    $reason = $data->getCommitDetail('autocloseReason', false);
    $reason = PhabricatorRepository::BECAUSE_AUTOCLOSE_FORCED;
    if ($reason !== false) {
      switch ($reason) {
        case PhabricatorRepository::BECAUSE_REPOSITORY_IMPORTING:
          $desc = pht('No, Repository Importing');
          break;
        case PhabricatorRepository::BECAUSE_AUTOCLOSE_DISABLED:
          $desc = pht('No, Autoclose Disabled');
          break;
        case PhabricatorRepository::BECAUSE_NOT_ON_AUTOCLOSE_BRANCH:
          $desc = pht('No, Not On Autoclose Branch');
          break;
        case PhabricatorRepository::BECAUSE_AUTOCLOSE_FORCED:
          $desc = pht('Yes, Forced Via bin/repository CLI Tool.');
          break;
        case null:
          $desc = pht('Yes');
          break;
        default:
          $desc = pht('Unknown');
          break;
      }

      $doc_href = PhabricatorEnv::getDoclink('Diffusion User Guide: Autoclose');
      $doc_link = phutil_tag(
        'a',
        array(
          'href' => $doc_href,
          'target' => '_blank',
        ),
        pht('Learn More'));

        $fields[] = id(new PhabricatorStaticEditField())
          ->setLabel(pht('Autoclose?'))
          ->setValue(array($desc, " \xC2\xB7 ", $doc_link));
    }

    return $fields;
  }

}
