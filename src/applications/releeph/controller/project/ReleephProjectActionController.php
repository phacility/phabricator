<?php

final class ReleephProjectActionController extends ReleephController {

  private $action;

  public function willProcessRequest(array $data) {
    parent::willProcessRequest($data);
    $this->action = $data['action'];
  }

  public function processRequest() {
    $request = $this->getRequest();

    $action = $this->action;
    $rph_project = $this->getReleephProject();

    switch ($action) {
      case 'deactivate':
        if ($request->isDialogFormPost()) {
          $rph_project->deactivate($request->getUser())->save();
          return id(new AphrontRedirectResponse())->setURI('/releeph');
        }

        $dialog = id(new AphrontDialogView())
          ->setUser($request->getUser())
          ->setTitle('Really deactivate Releeph Project?')
          ->appendChild(hsprintf(
            '<p>Really deactivate the Releeph project <i>%s</i>?',
            $rph_project->getName()))
          ->appendChild(hsprintf(
            '<p style="margin-top:1em">It will still exist, but '.
            'will be hidden from the list of active projects.</p>'))
          ->addSubmitButton('Deactivate Releeph Project')
          ->addCancelButton($request->getRequestURI());

        return id(new AphrontDialogResponse())->setDialog($dialog);

      case 'activate':
        $rph_project->setIsActive(1)->save();
        return id(new AphrontRedirectResponse())->setURI('/releeph');

      case 'delete':
        if ($request->isDialogFormPost()) {
          $rph_project->delete();
          return id(new AphrontRedirectResponse())
            ->setURI('/releeph/project/inactive');
        }

        $dialog = id(new AphrontDialogView())
          ->setUser($request->getUser())
          ->setTitle('Really delete Releeph Project?')
          ->appendChild(hsprintf(
            '<p>Really delete the "%s" Releeph project? '.
              'This cannot be undone!</p>',
            $rph_project->getName()))
          ->addSubmitButton('Delete Releeph Project')
          ->addCancelButton($request->getRequestURI());
        return id(new AphrontDialogResponse())->setDialog($dialog);

    }
  }
}
