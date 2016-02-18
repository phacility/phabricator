<?php

final class PhabricatorPolicyEditEngineExtension
  extends PhabricatorEditEngineExtension {

  const EXTENSIONKEY = 'policy.policy';

  public function getExtensionPriority() {
    return 250;
  }

  public function isExtensionEnabled() {
    return true;
  }

  public function getExtensionName() {
    return pht('Policies');
  }

  public function supportsObject(
    PhabricatorEditEngine $engine,
    PhabricatorApplicationTransactionInterface $object) {
    return ($object instanceof PhabricatorPolicyInterface);
  }

  public function buildCustomEditFields(
    PhabricatorEditEngine $engine,
    PhabricatorApplicationTransactionInterface $object) {

    $viewer = $engine->getViewer();

    $editor = $object->getApplicationTransactionEditor();
    $types = $editor->getTransactionTypesForObject($object);
    $types = array_fuse($types);

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($viewer)
      ->setObject($object)
      ->execute();

    $map = array(
      PhabricatorTransactions::TYPE_VIEW_POLICY => array(
        'key' => 'policy.view',
        'aliases' => array('view'),
        'capability' => PhabricatorPolicyCapability::CAN_VIEW,
        'label' => pht('View Policy'),
        'description' => pht('Controls who can view the object.'),
        'description.conduit' => pht('Change the view policy of the object.'),
        'edit' => 'view',
      ),
      PhabricatorTransactions::TYPE_EDIT_POLICY => array(
        'key' => 'policy.edit',
        'aliases' => array('edit'),
        'capability' => PhabricatorPolicyCapability::CAN_EDIT,
        'label' => pht('Edit Policy'),
        'description' => pht('Controls who can edit the object.'),
        'description.conduit' => pht('Change the edit policy of the object.'),
        'edit' => 'edit',
      ),
      PhabricatorTransactions::TYPE_JOIN_POLICY => array(
        'key' => 'policy.join',
        'aliases' => array('join'),
        'capability' => PhabricatorPolicyCapability::CAN_JOIN,
        'label' => pht('Join Policy'),
        'description' => pht('Controls who can join the object.'),
        'description.conduit' => pht('Change the join policy of the object.'),
        'edit' => 'join',
      ),
    );

    $fields = array();
    foreach ($map as $type => $spec) {
      if (empty($types[$type])) {
        continue;
      }

      $capability = $spec['capability'];
      $key = $spec['key'];
      $aliases = $spec['aliases'];
      $label = $spec['label'];
      $description = $spec['description'];
      $conduit_description = $spec['description.conduit'];
      $edit = $spec['edit'];

      $policy_field = id(new PhabricatorPolicyEditField())
        ->setKey($key)
        ->setLabel($label)
        ->setAliases($aliases)
        ->setIsCopyable(true)
        ->setCapability($capability)
        ->setPolicies($policies)
        ->setTransactionType($type)
        ->setEditTypeKey($edit)
        ->setDescription($description)
        ->setConduitDescription($conduit_description)
        ->setConduitTypeDescription(pht('New policy PHID or constant.'))
        ->setValue($object->getPolicy($capability));
      $fields[] = $policy_field;

      if ($object instanceof PhabricatorSpacesInterface) {
        if ($capability == PhabricatorPolicyCapability::CAN_VIEW) {
          $type_space = PhabricatorTransactions::TYPE_SPACE;
          if (isset($types[$type_space])) {
            $space_phid = PhabricatorSpacesNamespaceQuery::getObjectSpacePHID(
              $object);

            $space_field = id(new PhabricatorSpaceEditField())
              ->setKey('spacePHID')
              ->setLabel(pht('Space'))
              ->setEditTypeKey('space')
              ->setIsCopyable(true)
              ->setIsLockable(false)
              ->setIsReorderable(false)
              ->setAliases(array('space', 'policy.space'))
              ->setTransactionType($type_space)
              ->setDescription(pht('Select a space for the object.'))
              ->setConduitDescription(
                pht('Shift the object between spaces.'))
              ->setConduitTypeDescription(pht('New space PHID.'))
              ->setValue($space_phid);
            $fields[] = $space_field;

            $space_field->setPolicyField($policy_field);
            $policy_field->setSpaceField($space_field);
          }
        }
      }
    }

    return $fields;
  }

}
