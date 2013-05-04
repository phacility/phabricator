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
          ->setTitle(pht('Really deactivate Releeph Project?'))
          ->appendChild(phutil_tag(
            'p',
            array(),
            pht('Really deactivate the Releeph project: %s?',
            $rph_project->getName())))
          ->appendChild(phutil_tag(
            'p',
            array(),
            pht('It will still exist, but '.
            'will be hidden from the list of active projects.')))
          ->addSubmitButton(pht('Deactivate Releeph Project'))
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
          ->setTitle(pht('Really delete Releeph Project?'))
          ->appendChild(phutil_tag(
            'p',
            array(),
            pht('Really delete the Releeph project: %s? '.
              'This cannot be undone!'),
              $rph_project->getName()))
          ->setHeaderColor(PhabricatorActionHeaderView::HEADER_RED)
          ->addSubmitButton(pht('Delete'))
          ->addCancelButton($request->getRequestURI());
        return id(new AphrontDialogResponse())->setDialog($dialog);

    }
  }
}
