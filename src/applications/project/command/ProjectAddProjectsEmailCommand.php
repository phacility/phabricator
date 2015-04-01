<?php

final class ProjectAddProjectsEmailCommand
  extends MetaMTAEmailTransactionCommand {

  public function getCommand() {
    return 'projects';
  }

  public function getCommandSyntax() {
    return '**!projects** //#project ...//';
  }

  public function getCommandSummary() {
    return pht('Add related projects.');
  }

  public function getCommandDescription() {
    return pht(
      'Add one or more projects to the object by listing their hashtags. '.
      'Separate projects with spaces. For example, use `!projects #ios '.
      '#feature` to add both related projects.'.
      "\n\n".
      'Projects which are invalid or unrecognized will be ignored. This '.
      'command has no effect if you do not specify any projects.');
  }

  public function getCommandAliases() {
    return array(
      'project',
    );
  }

  public function isCommandSupportedForObject(
    PhabricatorApplicationTransactionInterface $object) {
    return ($object instanceof PhabricatorProjectInterface);
  }

  public function buildTransactions(
    PhabricatorUser $viewer,
    PhabricatorApplicationTransactionInterface $object,
    PhabricatorMetaMTAReceivedMail $mail,
    $command,
    array $argv) {

    $project_phids = id(new PhabricatorObjectListQuery())
      ->setViewer($viewer)
      ->setAllowedTypes(
        array(
          PhabricatorProjectProjectPHIDType::TYPECONST,
        ))
      ->setObjectList(implode(' ', $argv))
      ->setAllowPartialResults(true)
      ->execute();

    $xactions = array();

    $type_project = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;
    $xactions[] = $object->getApplicationTransactionTemplate()
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue('edge:type', $type_project)
      ->setNewValue(
        array(
          '+' => array_fuse($project_phids),
        ));

    return $xactions;
  }

}
