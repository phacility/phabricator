<?php

final class PhabricatorProjectBoardDeleteController
  extends PhabricatorProjectController {

  private $id;
  private $projectID;

  public function willProcessRequest(array $data) {
    $this->projectID = $data['projectID'];
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withIDs(array($this->projectID))
      ->executeOne();

    if (!$project) {
      return new Aphront404Response();
    }

    $columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withProjectPHIDs(array($project->getPHID()))
      ->withStatuses(array(PhabricatorProjectColumn::STATUS_ACTIVE))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT))
      ->execute();

    if (!$columns) {
      return new Aphront404Response();
    }

    $columns = mpull($columns, null, 'getSequence');
    $columns = mfilter($columns, 'isDefaultColumn', true);
    ksort($columns);
    $options = mpull($columns, 'getName', 'getPHID');

    $view_uri = $this->getApplicationURI('/board/'.$this->projectID.'/');
    $error_view = null;
    if ($request->isFormPost()) {
      $columns = mpull($columns, null, 'getPHID');
      $column_phid = $request->getStr('columnPHID');
      $column = $columns[$column_phid];

      $has_task_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
        $column_phid,
        PhabricatorEdgeConfig::TYPE_COLUMN_HAS_OBJECT);

      if ($has_task_phids) {
        $error_view = id(new AphrontErrorView())
          ->setTitle(pht('Column has Tasks!'))
          ->setErrors(array(pht('A column can not be deleted if it has tasks '.
                                'in it. Please remove the tasks and try '.
                                'again.')));
      } else {
        $column->setStatus(PhabricatorProjectColumn::STATUS_DELETED);
        $column->save();

        return id(new AphrontRedirectResponse())->setURI($view_uri);
      }
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild($error_view)
      ->appendChild(id(new AphrontFormSelectControl())
        ->setName('columnPHID')
        ->setValue(head_key($options))
        ->setOptions($options)
        ->setLabel(pht('Column')));

    $title = pht('Delete Column');
    $submit = $title;

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue($submit)
        ->addCancelButton($view_uri));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      $project->getName(),
      $this->getApplicationURI('view/'.$project->getID().'/'));
    $crumbs->addTextCrumb(
      pht('Board'),
      $this->getApplicationURI('board/'.$project->getID().'/'));
    $crumbs->addTextCrumb($title);

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }
}
