<?php

final class PhabricatorApplicationDetailViewController
  extends PhabricatorApplicationsController{

  private $application;

  public function willProcessRequest(array $data) {
    $this->application = $data['application'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $selected = PhabricatorApplication::getByClass($this->application);

    if (!$selected) {
      return new Aphront404Response();
    }

    $title = $selected->getName();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Applications'))
        ->setHref($this->getApplicationURI()));

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $status_tag = id(new PhabricatorTagView())
            ->setType(PhabricatorTagView::TYPE_STATE);

    if ($selected->isInstalled()) {
      $status_tag->setName(pht('Installed'));
      $status_tag->setBackgroundColor(PhabricatorTagView::COLOR_GREEN);

    } else {
      $status_tag->setName(pht('Uninstalled'));
      $status_tag->setBackgroundColor(PhabricatorTagView::COLOR_RED);
    }

    if ($selected->isBeta()) {
      $beta_tag = id(new PhabricatorTagView())
              ->setType(PhabricatorTagView::TYPE_STATE)
              ->setName(pht('Beta'))
              ->setBackgroundColor(PhabricatorTagView::COLOR_GREY);
      $header->addTag($beta_tag);
    }


    $header->addTag($status_tag);

    $properties = $this->buildPropertyView($selected);
    $actions = $this->buildActionView($user, $selected);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $actions,
        $properties,
      ),
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function buildPropertyView(PhabricatorApplication $selected) {
    $properties = id(new PhabricatorPropertyListView())
              ->addProperty(
                pht('Description'), $selected->getShortDescription());

    return $properties;
  }

  private function buildActionView(
    PhabricatorUser $user, PhabricatorApplication $selected) {

    $view = id(new PhabricatorActionListView())
          ->setUser($user);

    if ($selected->canUninstall()) {
      if ($selected->isInstalled()) {
        $view->addAction(
               id(new PhabricatorActionView())
               ->setName(pht('Uninstall'))
               ->setIcon('delete')
               ->setWorkflow(true)
               ->setHref(
                $this->getApplicationURI(get_class($selected).'/uninstall/')));
      } else {
        $view->addAction(
               id(new PhabricatorActionView())
               ->setName(pht('Install'))
               ->setIcon('new')
               ->setWorkflow(true)
               ->setHref(
                 $this->getApplicationURI(get_class($selected).'/install/')));
      }
    } else {
      $view->addAction(
             id(new PhabricatorActionView())
             ->setName(pht('Uninstall'))
             ->setIcon('delete')
             ->setWorkflow(true)
             ->setDisabled(true)
             ->setHref(
               $this->getApplicationURI(get_class($selected).'/uninstall/')));
    }
    return $view;
  }

}
