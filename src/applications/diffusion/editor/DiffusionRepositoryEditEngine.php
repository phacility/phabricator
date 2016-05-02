<?php

final class DiffusionRepositoryEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'diffusion.repository';

  private $versionControlSystem;

  public function setVersionControlSystem($version_control_system) {
    $this->versionControlSystem = $version_control_system;
    return $this;
  }

  public function getVersionControlSystem() {
    return $this->versionControlSystem;
  }

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Repositories');
  }

  public function getSummaryHeader() {
    return pht('Edit Repositories');
  }

  public function getSummaryText() {
    return pht('Creates and edits repositories.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

  protected function newEditableObject() {
    $viewer = $this->getViewer();
    $repository = PhabricatorRepository::initializeNewRepository($viewer);

    $vcs = $this->getVersionControlSystem();
    if ($vcs) {
      $repository->setVersionControlSystem($vcs);
    }

    return $repository;
  }

  protected function newObjectQuery() {
    return new PhabricatorRepositoryQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Repository');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Repository');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Repository: %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getDisplayName();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Repository');
  }

  protected function getObjectName() {
    return pht('Repository');
  }

  protected function getObjectViewURI($object) {
    return $object->getPathURI('manage/');
  }

  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      DiffusionCreateRepositoriesCapability::CAPABILITY);
  }

  protected function newPages($object) {
    $panels = DiffusionRepositoryManagementPanel::getAllPanels();

    $pages = array();
    $uris = array();
    foreach ($panels as $panel_key => $panel) {
      $panel->setRepository($object);

      $uris[$panel_key] = $panel->getPanelURI();

      $page = $panel->newEditEnginePage();
      if (!$page) {
        continue;
      }
      $pages[] = $page;
    }

    $basics_key = DiffusionRepositoryBasicsManagementPanel::PANELKEY;
    $basics_uri = $uris[$basics_key];

    $more_pages = array(
      id(new PhabricatorEditPage())
        ->setKey('encoding')
        ->setLabel(pht('Text Encoding'))
        ->setViewURI($basics_uri)
        ->setFieldKeys(
          array(
            'encoding',
          )),
      id(new PhabricatorEditPage())
        ->setKey('extensions')
        ->setLabel(pht('Extensions'))
        ->setIsDefault(true),
    );

    foreach ($more_pages as $page) {
      $pages[] = $page;
    }

    return $pages;
  }

  protected function buildCustomEditFields($object) {
    $viewer = $this->getViewer();

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($object)
      ->execute();

    $track_value = $object->getDetail('branch-filter', array());
    $track_value = array_keys($track_value);

    $autoclose_value = $object->getDetail('close-commits-filter', array());
    $autoclose_value = array_keys($autoclose_value);

    return array(
      id(new PhabricatorSelectEditField())
        ->setKey('vcs')
        ->setLabel(pht('Version Control System'))
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_VCS)
        ->setIsConduitOnly(true)
        ->setIsCopyable(true)
        ->setOptions(PhabricatorRepositoryType::getAllRepositoryTypes())
        ->setDescription(pht('Underlying repository version control system.'))
        ->setConduitDescription(
          pht(
            'Choose which version control system to use when creating a '.
            'repository.'))
        ->setConduitTypeDescription(pht('Version control system selection.'))
        ->setValue($object->getVersionControlSystem()),
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setIsRequired(true)
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_NAME)
        ->setDescription(pht('The repository name.'))
        ->setConduitDescription(pht('Rename the repository.'))
        ->setConduitTypeDescription(pht('New repository name.'))
        ->setValue($object->getName()),
      id(new PhabricatorTextEditField())
        ->setKey('callsign')
        ->setLabel(pht('Callsign'))
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_CALLSIGN)
        ->setDescription(pht('The repository callsign.'))
        ->setConduitDescription(pht('Change the repository callsign.'))
        ->setConduitTypeDescription(pht('New repository callsign.'))
        ->setValue($object->getCallsign()),
      id(new PhabricatorTextEditField())
        ->setKey('shortName')
        ->setLabel(pht('Short Name'))
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_SLUG)
        ->setDescription(pht('Short, unique repository name.'))
        ->setConduitDescription(pht('Change the repository short name.'))
        ->setConduitTypeDescription(pht('New short name for the repository.'))
        ->setValue($object->getRepositorySlug()),
      id(new PhabricatorRemarkupEditField())
        ->setKey('description')
        ->setLabel(pht('Description'))
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_DESCRIPTION)
        ->setDescription(pht('Repository description.'))
        ->setConduitDescription(pht('Change the repository description.'))
        ->setConduitTypeDescription(pht('New repository description.'))
        ->setValue($object->getDetail('description')),
      id(new PhabricatorTextEditField())
        ->setKey('encoding')
        ->setLabel(pht('Text Encoding'))
        ->setIsCopyable(true)
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_ENCODING)
        ->setDescription(pht('Default text encoding.'))
        ->setConduitDescription(pht('Change the default text encoding.'))
        ->setConduitTypeDescription(pht('New text encoding.'))
        ->setValue($object->getDetail('encoding')),
      id(new PhabricatorBoolEditField())
        ->setKey('allowDangerousChanges')
        ->setLabel(pht('Allow Dangerous Changes'))
        ->setIsCopyable(true)
        ->setIsConduitOnly(true)
        ->setOptions(
          pht('Prevent Dangerous Changes'),
          pht('Allow Dangerous Changes'))
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_DANGEROUS)
        ->setDescription(pht('Permit dangerous changes to be made.'))
        ->setConduitDescription(pht('Allow or prevent dangerous changes.'))
        ->setConduitTypeDescription(pht('New protection setting.'))
        ->setValue($object->shouldAllowDangerousChanges()),
      id(new PhabricatorSelectEditField())
        ->setKey('status')
        ->setLabel(pht('Status'))
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_ACTIVATE)
        ->setIsConduitOnly(true)
        ->setOptions(PhabricatorRepository::getStatusNameMap())
        ->setDescription(pht('Active or inactive status.'))
        ->setConduitDescription(pht('Active or deactivate the repository.'))
        ->setConduitTypeDescription(pht('New repository status.'))
        ->setValue($object->getStatus()),
      id(new PhabricatorTextEditField())
        ->setKey('defaultBranch')
        ->setLabel(pht('Default Branch'))
        ->setTransactionType(
          PhabricatorRepositoryTransaction::TYPE_DEFAULT_BRANCH)
        ->setIsCopyable(true)
        ->setDescription(pht('Default branch name.'))
        ->setConduitDescription(pht('Set the default branch name.'))
        ->setConduitTypeDescription(pht('New default branch name.'))
        ->setValue($object->getDetail('default-branch')),
      id(new PhabricatorTextAreaEditField())
        ->setIsStringList(true)
        ->setKey('trackOnly')
        ->setLabel(pht('Track Only'))
        ->setTransactionType(
          PhabricatorRepositoryTransaction::TYPE_TRACK_ONLY)
        ->setIsCopyable(true)
        ->setDescription(pht('Track only these branches.'))
        ->setConduitDescription(pht('Set the tracked branches.'))
        ->setConduitTypeDescription(pht('New tracked branchs.'))
        ->setValue($track_value),
      id(new PhabricatorTextAreaEditField())
        ->setIsStringList(true)
        ->setKey('autocloseOnly')
        ->setLabel(pht('Autoclose Only'))
        ->setTransactionType(
          PhabricatorRepositoryTransaction::TYPE_AUTOCLOSE_ONLY)
        ->setIsCopyable(true)
        ->setDescription(pht('Autoclose commits on only these branches.'))
        ->setConduitDescription(pht('Set the autoclose branches.'))
        ->setConduitTypeDescription(pht('New default tracked branchs.'))
        ->setValue($autoclose_value),
      id(new PhabricatorTextEditField())
        ->setKey('stagingAreaURI')
        ->setLabel(pht('Staging Area URI'))
        ->setTransactionType(
          PhabricatorRepositoryTransaction::TYPE_STAGING_URI)
        ->setIsCopyable(true)
        ->setDescription(pht('Staging area URI.'))
        ->setConduitDescription(pht('Set the staging area URI.'))
        ->setConduitTypeDescription(pht('New staging area URI.'))
        ->setValue($object->getStagingURI()),
      id(new PhabricatorDatasourceEditField())
        ->setKey('automationBlueprintPHIDs')
        ->setLabel(pht('Use Blueprints'))
        ->setTransactionType(
          PhabricatorRepositoryTransaction::TYPE_AUTOMATION_BLUEPRINTS)
        ->setIsCopyable(true)
        ->setDatasource(new DrydockBlueprintDatasource())
        ->setDescription(pht('Automation blueprints.'))
        ->setConduitDescription(pht('Change automation blueprints.'))
        ->setConduitTypeDescription(pht('New blueprint PHIDs.'))
        ->setValue($object->getAutomationBlueprintPHIDs()),
      id(new PhabricatorStringListEditField())
        ->setKey('symbolLanguages')
        ->setLabel(pht('Languages'))
        ->setTransactionType(
          PhabricatorRepositoryTransaction::TYPE_SYMBOLS_LANGUAGE)
        ->setIsCopyable(true)
        ->setDescription(
          pht('Languages which define symbols in this repository.'))
        ->setConduitDescription(
          pht('Change symbol languages for this repository.'))
        ->setConduitTypeDescription(
          pht('New symbol langauges.'))
        ->setValue($object->getSymbolLanguages()),
      id(new PhabricatorDatasourceEditField())
        ->setKey('symbolRepositoryPHIDs')
        ->setLabel(pht('Uses Symbols From'))
        ->setTransactionType(
          PhabricatorRepositoryTransaction::TYPE_SYMBOLS_SOURCES)
        ->setIsCopyable(true)
        ->setDatasource(new DiffusionRepositoryDatasource())
        ->setDescription(pht('Repositories to link symbols from.'))
        ->setConduitDescription(pht('Change symbol source repositories.'))
        ->setConduitTypeDescription(pht('New symbol repositories.'))
        ->setValue($object->getSymbolSources()),
      id(new PhabricatorBoolEditField())
        ->setKey('publish')
        ->setLabel(pht('Publish/Notify'))
        ->setTransactionType(
          PhabricatorRepositoryTransaction::TYPE_NOTIFY)
        ->setIsCopyable(true)
        ->setOptions(
          pht('Disable Notifications, Feed, and Herald'),
          pht('Enable Notifications, Feed, and Herald'))
        ->setDescription(pht('Configure how changes are published.'))
        ->setConduitDescription(pht('Change publishing options.'))
        ->setConduitTypeDescription(pht('New notification setting.'))
        ->setValue(!$object->getDetail('herald-disabled')),
      id(new PhabricatorBoolEditField())
        ->setKey('autoclose')
        ->setLabel(pht('Autoclose'))
        ->setTransactionType(
          PhabricatorRepositoryTransaction::TYPE_AUTOCLOSE)
        ->setIsCopyable(true)
        ->setOptions(
          pht('Disable Autoclose'),
          pht('Enable Autoclose'))
        ->setDescription(pht('Stop or resume autoclosing in this repository.'))
        ->setConduitDescription(pht('Change autoclose setting.'))
        ->setConduitTypeDescription(pht('New autoclose setting.'))
        ->setValue(!$object->getDetail('disable-autoclose')),
      id(new PhabricatorPolicyEditField())
        ->setKey('policy.push')
        ->setLabel(pht('Push Policy'))
        ->setAliases(array('push'))
        ->setIsCopyable(true)
        ->setCapability(DiffusionPushCapability::CAPABILITY)
        ->setPolicies($policies)
        ->setTransactionType(PhabricatorRepositoryTransaction::TYPE_PUSH_POLICY)
        ->setDescription(
          pht('Controls who can push changes to the repository.'))
        ->setConduitDescription(
          pht('Change the push policy of the repository.'))
        ->setConduitTypeDescription(pht('New policy PHID or constant.'))
        ->setValue($object->getPolicy(DiffusionPushCapability::CAPABILITY)),
    );
  }

}
