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
      ->setContentSourceFromConduitRequest($request);

    $editor->applyTransactions($project, $xactions);

    return $this->buildProjectInfoDictionary($project);
  }

}
