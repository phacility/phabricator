<?php

final class ManiphestTaskSubtaskController
  extends ManiphestController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $task = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$task) {
      return new Aphront404Response();
    }

    $cancel_uri = $task->getURI();

    $edit_engine = id(new ManiphestEditEngine())
      ->setViewer($viewer)
      ->setTargetObject($task);

    $subtype_map = $task->newEditEngineSubtypeMap();

    $subtype_options = $subtype_map->getCreateFormsForSubtype(
      $edit_engine,
      $task);

    if (!$subtype_options) {
      return $this->newDialog()
        ->setTitle(pht('No Forms'))
        ->appendParagraph(
          pht(
            'You do not have access to any forms which can be used to '.
            'create a subtask.'))
        ->addCancelButton($cancel_uri, pht('Close'));
    }

    if ($request->isFormPost()) {
      $form_key = $request->getStr('formKey');
      if (isset($subtype_options[$form_key])) {
        $subtask_uri = id(new PhutilURI("/task/edit/form/{$form_key}/"))
          ->setQueryParam('parent', $id)
          ->setQueryParam('template', $id)
          ->setQueryParam('status', ManiphestTaskStatus::getDefaultStatus());
        $subtask_uri = $this->getApplicationURI($subtask_uri);

        return id(new AphrontRedirectResponse())
          ->setURI($subtask_uri);
      }
    }

    $control = id(new AphrontFormRadioButtonControl())
      ->setName('formKey')
      ->setLabel(pht('Subtype'));

    foreach ($subtype_options as $key => $subtype_form) {
      $control->addButton(
        $key,
        $subtype_form->getDisplayName(),
        null);
    }

    $form = id(new AphrontFormView())
      ->setViewer($viewer)
      ->appendControl($control);

    return $this->newDialog()
      ->setTitle(pht('Choose Subtype'))
      ->appendForm($form)
      ->addSubmitButton(pht('Continue'))
      ->addCancelButton($cancel_uri);
  }

}
