<?php

final class PhabricatorProjectEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'projects.project';

  public function getEngineName() {
    return pht('Projects');
  }

  public function getSummaryHeader() {
    return pht('Configure Project Forms');
  }

  public function getSummaryText() {
    return pht('Configure forms for creating projects.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  protected function newEditableObject() {
    return PhabricatorProject::initializeNewProject($this->getViewer());
  }

  protected function newObjectQuery() {
    return id(new PhabricatorProjectQuery())
      ->needSlugs(true);
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create New Project');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit %s', $object->getName());
  }

  protected function getObjectEditShortText($object) {
    return $object->getName();
  }

  protected function getObjectCreateShortText() {
    return pht('Create Project');
  }

  protected function getObjectViewURI($object) {
    return $object->getURI();
  }

  protected function getCreateNewObjectPolicy() {
    return $this->getApplication()->getPolicy(
      ProjectCreateProjectsCapability::CAPABILITY);
  }

  protected function newBuiltinEngineConfigurations() {
    $configuration = head(parent::newBuiltinEngineConfigurations());

    // TODO: This whole method is clumsy, and the ordering for the custom
    // field is especially clumsy. Maybe try to make this more natural to
    // express.

    $configuration
      ->setFieldOrder(
        array(
          'name',
          'std:project:internal:description',
          'icon',
          'color',
          'slugs',
          'subscriberPHIDs',
        ));

    return array(
      $configuration,
    );
  }

  protected function buildCustomEditFields($object) {
    $slugs = mpull($object->getSlugs(), 'getSlug');
    $slugs = array_fuse($slugs);
    unset($slugs[$object->getPrimarySlug()]);
    $slugs = array_values($slugs);

    return array(
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setTransactionType(PhabricatorProjectTransaction::TYPE_NAME)
        ->setIsRequired(true)
        ->setDescription(pht('Project name.'))
        ->setConduitDescription(pht('Rename the project'))
        ->setConduitTypeDescription(pht('New project name.'))
        ->setValue($object->getName()),
      id(new PhabricatorIconSetEditField())
        ->setKey('icon')
        ->setLabel(pht('Icon'))
        ->setTransactionType(PhabricatorProjectTransaction::TYPE_ICON)
        ->setIconSet(new PhabricatorProjectIconSet())
        ->setDescription(pht('Project icon.'))
        ->setConduitDescription(pht('Change the project icon.'))
        ->setConduitTypeDescription(pht('New project icon.'))
        ->setValue($object->getIcon()),
      id(new PhabricatorSelectEditField())
        ->setKey('color')
        ->setLabel(pht('Color'))
        ->setTransactionType(PhabricatorProjectTransaction::TYPE_COLOR)
        ->setOptions(PhabricatorProjectIconSet::getColorMap())
        ->setDescription(pht('Project tag color.'))
        ->setConduitDescription(pht('Change the project tag color.'))
        ->setConduitTypeDescription(pht('New project tag color.'))
        ->setValue($object->getColor()),
      id(new PhabricatorStringListEditField())
        ->setKey('slugs')
        ->setLabel(pht('Additional Hashtags'))
        ->setTransactionType(PhabricatorProjectTransaction::TYPE_SLUGS)
        ->setDescription(pht('Additional project slugs.'))
        ->setConduitDescription(pht('Change project slugs.'))
        ->setConduitTypeDescription(pht('New list of slugs.'))
        ->setValue($slugs),
    );
  }

}
