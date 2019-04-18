<?php

abstract class PhabricatorDashboardObjectInstallWorkflow
  extends PhabricatorDashboardInstallWorkflow {

  abstract protected function newQuery();
  abstract protected function newConfirmDialog($object);
  abstract protected function newObjectSelectionForm($object);

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $target_identifier = null;

    $target_tokens = $request->getArr('target');
    if ($target_tokens) {
      $target_identifier = head($target_tokens);
    }

    if (!strlen($target_identifier)) {
      $target_identifier = $request->getStr('target');
    }

    if (!strlen($target_identifier)) {
      $target_identifier = $this->getMode();
    }

    $target = null;
    if (strlen($target_identifier)) {
      $targets = array();

      if (ctype_digit($target_identifier)) {
        $targets = $this->newQuery()
          ->setViewer($viewer)
          ->withIDs(array((int)$target_identifier))
          ->execute();
      }

      if (!$targets) {
        $targets = $this->newQuery()
          ->setViewer($viewer)
          ->withPHIDs(array($target_identifier))
          ->execute();
      }

      if ($targets) {
        $target = head($targets);
      }
    }

    if ($target) {
      $target_phid = $target->getPHID();
    } else {
      $target_phid = null;
    }

    if ($target) {
      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $target,
        PhabricatorPolicyCapability::CAN_EDIT);
    } else {
      $can_edit = null;
    }

    if ($request->isFormPost() && $target && $can_edit) {
      if ($request->getBool('confirm')) {
        return $this->installDashboard($target, null);
      } else {
        return $this->newConfirmDialog($target)
          ->addHiddenInput('confirm', 1)
          ->addHiddenInput('target', $target_phid);
      }
    }

    $errors = array();
    if (strlen($target_identifier)) {
      if (!$target) {
        $errors[] = pht('Choose a valid object.');
      } else if (!$can_edit) {
        $errors[] = pht(
          'You do not have permission to edit the selected object. '.
          'You can only install dashboards on objects you can edit.');
      }
    } else if ($request->getBool('pick')) {
      $errors[] = pht(
        'Choose an object to install this dashboard on.');
    }

    $form = $this->newObjectSelectionForm($target)
      ->addHiddenInput('pick', 1);

    return $this->newDialog()
      ->setTitle(pht('Add Dashboard to Project Menu'))
      ->setErrors($errors)
      ->appendForm($form)
      ->addSubmitButton(pht('Continue'));
  }
}
