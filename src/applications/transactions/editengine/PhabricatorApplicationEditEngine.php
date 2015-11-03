<?php

abstract class PhabricatorApplicationEditEngine extends Phobject {

  private $viewer;
  private $controller;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function setController(PhabricatorController $controller) {
    $this->controller = $controller;
    $this->setViewer($controller->getViewer());
    return $this;
  }

  final public function getController() {
    return $this->controller;
  }

  final protected function buildEditFields($object) {
    $viewer = $this->getViewer();
    $editor = $object->getApplicationTransactionEditor();

    $types = $editor->getTransactionTypesForObject($object);
    $types = array_fuse($types);

    $fields = $this->buildCustomEditFields($object);

    if ($object instanceof PhabricatorPolicyInterface) {
      $policies = id(new PhabricatorPolicyQuery())
        ->setViewer($viewer)
        ->setObject($object)
        ->execute();

      $map = array(
        PhabricatorTransactions::TYPE_VIEW_POLICY => array(
          'key' => 'policy.view',
          'aliases' => array('view'),
          'capability' => PhabricatorPolicyCapability::CAN_VIEW,
        ),
        PhabricatorTransactions::TYPE_EDIT_POLICY => array(
          'key' => 'policy.edit',
          'aliases' => array('edit'),
          'capability' => PhabricatorPolicyCapability::CAN_EDIT,
        ),
        PhabricatorTransactions::TYPE_JOIN_POLICY => array(
          'key' => 'policy.join',
          'aliases' => array('join'),
          'capability' => PhabricatorPolicyCapability::CAN_JOIN,
        ),
      );

      foreach ($map as $type => $spec) {
        if (empty($types[$type])) {
          continue;
        }

        $capability = $spec['capability'];
        $key = $spec['key'];
        $aliases = $spec['aliases'];

        $policy_field = id(new PhabricatorPolicyEditField())
          ->setKey($key)
          ->setAliases($aliases)
          ->setCapability($capability)
          ->setPolicies($policies)
          ->setTransactionType($type)
          ->setValue($object->getPolicy($capability));
        $fields[] = $policy_field;

        if ($object instanceof PhabricatorSpacesInterface) {
          if ($capability == PhabricatorPolicyCapability::CAN_VIEW) {
            $type_space = PhabricatorTransactions::TYPE_SPACE;
            if (isset($types[$type_space])) {
              $space_field = id(new PhabricatorSpaceEditField())
                ->setKey('spacePHID')
                ->setAliases(array('space', 'policy.space'))
                ->setTransactionType($type_space)
                ->setValue($object->getSpacePHID());
              $fields[] = $space_field;

              $policy_field->setSpaceField($space_field);
            }
          }
        }
      }
    }

    $edge_type = PhabricatorTransactions::TYPE_EDGE;
    $object_phid = $object->getPHID();

    $project_edge_type = PhabricatorProjectObjectHasProjectEdgeType::EDGECONST;

    if ($object instanceof PhabricatorProjectInterface) {
      if (isset($types[$edge_type])) {
        if ($object_phid) {
          $project_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
            $object_phid,
            $project_edge_type);
          $project_phids = array_reverse($project_phids);
        } else {
          $project_phids = array();
        }

        $edge_field = id(new PhabricatorDatasourceEditField())
          ->setKey('projectPHIDs')
          ->setLabel(pht('Projects'))
          ->setDatasource(new PhabricatorProjectDatasource())
          ->setAliases(array('project', 'projects'))
          ->setTransactionType($edge_type)
          ->setMetadataValue('edge:type', $project_edge_type)
          ->setValue($project_phids);
        $fields[] = $edge_field;
      }
    }

    $subscribers_type = PhabricatorTransactions::TYPE_SUBSCRIBERS;

    if ($object instanceof PhabricatorSubscribableInterface) {
      if (isset($types[$subscribers_type])) {
        if ($object_phid) {
          $sub_phids = PhabricatorSubscribersQuery::loadSubscribersForPHID(
            $object_phid);
        } else {
          // TODO: Allow applications to provide default subscribers; Maniphest
          // does this at a minimum.
          $sub_phids = array();
        }

        $subscribers_field = id(new PhabricatorDatasourceEditField())
          ->setKey('subscriberPHIDs')
          ->setLabel(pht('Subscribers'))
          ->setDatasource(new PhabricatorMetaMTAMailableDatasource())
          ->setAliases(array('subscriber', 'subscribers'))
          ->setTransactionType($subscribers_type)
          ->setValue($sub_phids);
        $fields[] = $subscribers_field;
      }
    }

    return $fields;
  }

  abstract protected function newEditableObject();
  abstract protected function newObjectQuery();
  abstract protected function buildCustomEditFields($object);

  abstract protected function getObjectCreateTitleText($object);
  abstract protected function getObjectEditTitleText($object);
  abstract protected function getObjectCreateShortText($object);
  abstract protected function getObjectEditShortText($object);
  abstract protected function getObjectViewURI($object);

  protected function getObjectCreateCancelURI($object) {
    return $this->getController()->getApplicationURI();
  }

  protected function getObjectEditCancelURI($object) {
    return $this->getObjectViewURI($object);
  }

  protected function getObjectCreateButtonText($object) {
    return $this->getObjectCreateTitleText($object);
  }

  protected function getObjectEditButtonText($object) {
    return pht('Save Changes');
  }

  final public function buildResponse() {
    $controller = $this->getController();
    $viewer = $this->getViewer();
    $request = $controller->getRequest();

    $id = $request->getURIData('id');
    if ($id) {
      $object = $this->newObjectQuery()
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$object) {
        return new Aphront404Response();
      }

      $is_create = false;
    } else {
      $object = $this->newEditableObject();

      $is_create = true;
    }

    $fields = $this->buildEditFields($object);

    foreach ($fields as $field) {
      $field
        ->setViewer($viewer)
        ->setObject($object);
    }

    $validation_exception = null;
    if ($request->isFormPost()) {
      foreach ($fields as $field) {
        $field->readValueFromSubmit($request);
      }

      $template = $object->getApplicationTransactionTemplate();

      $xactions = array();
      foreach ($fields as $field) {
        $xactions[] = $field->generateTransaction(clone $template);
      }

      $editor = $object->getApplicationTransactionEditor()
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(false);

      try {

        $editor->applyTransactions($object, $xactions);

        return id(new AphrontRedirectResponse())
          ->setURI($this->getObjectViewURI($object));
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
      }
    } else {
      if ($is_create) {
        foreach ($fields as $field) {
          $field->readValueFromRequest($request);
        }
      } else {
        foreach ($fields as $field) {
          $field->readValueFromObject($object);
        }
      }
    }

    $box = id(new PHUIObjectBoxView())
      ->setUser($viewer);

    $crumbs = $controller->buildApplicationCrumbsForEditEngine();

    if ($is_create) {
      $header_text = $this->getObjectCreateTitleText($object);

      $crumbs->addTextCrumb(
        $this->getObjectCreateShortText($object));

      $cancel_uri = $this->getObjectCreateCancelURI($object);
      $submit_button = $this->getObjectCreateButtonText($object);
    } else {
      $header_text = $this->getObjectEditTitleText($object);

      $crumbs->addTextCrumb(
        $this->getObjectEditShortText($object),
        $this->getObjectViewURI($object));

      $cancel_uri = $this->getObjectEditCancelURI($object);
      $submit_button = $this->getObjectEditButtonText($object);
    }

    $box->setHeaderText($header_text);

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    foreach ($fields as $field) {
      $field->appendToForm($form);
    }

    $form->appendControl(
      id(new AphrontFormSubmitControl())
        ->addCancelButton($cancel_uri)
        ->setValue($submit_button));

    $box->appendChild($form);

    if ($validation_exception) {
      $box->setValidationException($validation_exception);
    }

    return $controller->newPage()
      ->setTitle($header_text)
      ->setCrumbs($crumbs)
      ->appendChild($box);
  }


}
