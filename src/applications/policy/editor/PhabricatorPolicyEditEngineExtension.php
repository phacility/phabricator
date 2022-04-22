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
      PhabricatorTransactions::TYPE_INTERACT_POLICY => array(
        'key' => 'policy.interact',
        'aliases' => array('interact'),
        'capability' => PhabricatorPolicyCapability::CAN_INTERACT,
        'label' => pht('Interact Policy'),
        'description' => pht('Controls who can interact with the object.'),
        'description.conduit'
          => pht('Change the interaction policy of the object.'),
        'edit' => 'interact',
      ),
    );

    if ($object instanceof PhabricatorPolicyCodexInterface) {
      $codex = PhabricatorPolicyCodex::newFromObject(
        $object,
        $viewer);
    } else {
      $codex = null;
    }

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

      // Objects may present a policy value to the edit workflow that is
      // different from their nominal policy value: for example, when tasks
      // are locked, they appear as "Editable By: No One" to other applications
      // but we still want to edit the actual policy stored in the database
      // when we show the user a form with a policy control in it.

      if ($codex) {
        $policy_value = $codex->getPolicyForEdit($capability);
      } else {
        $policy_value = $object->getPolicy($capability);
      }

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
        ->setValue($policy_value);
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
