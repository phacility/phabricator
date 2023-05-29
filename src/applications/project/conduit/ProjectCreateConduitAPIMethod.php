<?php

final class ProjectCreateConduitAPIMethod extends ProjectConduitAPIMethod {

  public function getAPIMethodName() {
    return 'project.create';
  }

  public function getMethodDescription() {
    return pht('Create a project.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_FROZEN;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method is frozen and will eventually be deprecated. New code '.
      'should use "project.edit" instead.');
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
    $type_name = PhabricatorProjectNameTransaction::TRANSACTIONTYPE;

    $name = $request->getValue('name');
    if ($name === null || !strlen(name)) {
      throw new Exception(pht('Field "name" must be non-empty.'));
    }

    $members = $request->getValue('members');
    if ($members === null) {
      $members = array();
    }
    $xactions = array();

    $xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType($type_name)
      ->setNewValue($name);

    if ($request->getValue('icon')) {
      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(
            PhabricatorProjectIconTransaction::TRANSACTIONTYPE)
        ->setNewValue($request->getValue('icon'));
    }

    if ($request->getValue('color')) {
      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(
          PhabricatorProjectColorTransaction::TRANSACTIONTYPE)
        ->setNewValue($request->getValue('color'));
    }

    if ($request->getValue('tags')) {
      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(
            PhabricatorProjectSlugsTransaction::TRANSACTIONTYPE)
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
