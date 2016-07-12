<?php

final class ProjectCreateConduitAPIMethod extends ProjectConduitAPIMethod {

  public function getAPIMethodName() {
    return 'project.create';
  }

  public function getMethodDescription() {
    return pht('Create a project.');
  }

  protected function defineParamTypes() {
    return array(
      'name'       => 'required string',
      'members'    => 'optional list<phid>',
      'icon'       => 'optional string',
      'color'      => 'optional string',
      'tags'       => 'optional list<string>',
    );
  }

  protected function defineReturnType() {
    return 'dict';
  }

  protected function execute(ConduitAPIRequest $request) {
    $user = $request->getUser();

    $this->requireApplicationCapability(
      ProjectCreateProjectsCapability::CAPABILITY,
      $user);

    $project = PhabricatorProject::initializeNewProject($user);
    $type_name = PhabricatorProjectTransaction::TYPE_NAME;
    $members = $request->getValue('members');
    $xactions = array();

    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType($type_name)
      ->setNewValue($request->getValue('name'));

    if ($request->getValue('icon')) {
      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(PhabricatorProjectTransaction::TYPE_ICON)
        ->setNewValue($request->getValue('icon'));
    }

    if ($request->getValue('color')) {
      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(PhabricatorProjectTransaction::TYPE_COLOR)
        ->setNewValue($request->getValue('color'));
    }

    if ($request->getValue('tags')) {
      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(PhabricatorProjectTransaction::TYPE_SLUGS)
        ->setNewValue($request->getValue('tags'));
    }

    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue(
        'edge:type',
        PhabricatorProjectProjectHasMemberEdgeType::EDGECONST)
      ->setNewValue(
        array(
          '+' => array_fuse($members),
        ));

    $editor = id(new PhabricatorProjectTransactionEditor())
      ->setActor($user)
      ->setContinueOnNoEffect(true)
      ->setContentSource($request->newContentSource());

    $editor->applyTransactions($project, $xactions);

    return $this->buildProjectInfoDictionary($project);
  }

}
