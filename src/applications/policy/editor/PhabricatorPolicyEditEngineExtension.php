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
        'edit' => 'view',
      ),
      PhabricatorTransactions::TYPE_EDIT_POLICY => array(
        'key' => 'policy.edit',
        'aliases' => array('edit'),
        'capability' => PhabricatorPolicyCapability::CAN_EDIT,
        'label' => pht('Edit Policy'),
        'description' => pht('Controls who can edit the object.'),
        'edit' => 'edit',
      ),
      PhabricatorTransactions::TYPE_JOIN_POLICY => array(
        'key' => 'policy.join',
        'aliases' => array('join'),
        'capability' => PhabricatorPolicyCapability::CAN_JOIN,
        'label' => pht('Join Policy'),
        'description' => pht('Controls who can join the object.'),
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
      $edit = $spec['edit'];

      $policy_field = id(new PhabricatorPolicyEditField())
        ->setKey($key)
        ->setLabel($label)
        ->setDescription($description)
        ->setAliases($aliases)
        ->setCapability($capability)
        ->setPolicies($policies)
        ->setTransactionType($type)
        ->setEditTypeKey($edit)
        ->setValue($object->getPolicy($capability));
      $fields[] = $policy_field;

      if (!($object instanceof PhabricatorSpacesInterface)) {
        if ($capability == PhabricatorPolicyCapability::CAN_VIEW) {
          $type_space = PhabricatorTransactions::TYPE_SPACE;
          if (isset($types[$type_space])) {
            $space_field = id(new PhabricatorSpaceEditField())
              ->setKey('spacePHID')
              ->setLabel(pht('Space'))
              ->setEditTypeKey('space')
              ->setDescription(
                pht('Shifts the object in the Spaces application.'))
              ->setIsReorderable(false)
              ->setAliases(array('space', 'policy.space'))
              ->setTransactionType($type_space)
              ->setValue($object->getSpacePHID());
            $fields[] = $space_field;

            $policy_field->setSpaceField($space_field);
          }
        }
      }
    }

    return $fields;
  }

}
