<?php

final class PhabricatorConduitConsoleController
  extends PhabricatorConduitController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $method_name = $request->getURIData('method');

    $method = id(new PhabricatorConduitMethodQuery())
      ->setViewer($viewer)
      ->withMethods(array($method_name))
      ->executeOne();
    if (!$method) {
      return new Aphront404Response();
    }

    $method->setViewer($viewer);

    $call_uri = '/api/'.$method->getAPIMethodName();

    $errors = array();

    $form = id(new AphrontFormView())
      ->setAction($call_uri)
      ->setUser($request->getUser())
      ->appendRemarkupInstructions(
        pht(
          'Enter parameters using **JSON**. For instance, to enter a '.
          'list, type: `%s`',
          '["apple", "banana", "cherry"]'));

    $params = $method->getParamTypes();
    foreach ($params as $param => $desc) {
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel($param)
          ->setName("params[{$param}]")
          ->setCaption($desc));
    }

    $must_login = !$viewer->isLoggedIn() &&
                  $method->shouldRequireAuthentication();
    if ($must_login) {
      $errors[] = pht(
        'Login Required: This method requires authentication. You must '.
        'log in before you can make calls to it.');
    } else {
      $form
        ->appendChild(
          id(new AphrontFormSelectControl())
            ->setLabel(pht('Output Format'))
            ->setName('output')
            ->setOptions(
              array(
                'human' => pht('Human Readable'),
                'json'  => pht('JSON'),
              )))
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->addCancelButton($this->getApplicationURI())
            ->setValue(pht('Call Method')));
    }

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($method->getAPIMethodName())
      ->setHeaderIcon('fa-tty');

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Call Method'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    $properties = $this->buildMethodProperties($method);

    $info_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('API Method: %s', $method->getAPIMethodName()))
      ->setFormErrors($errors)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($properties);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($method->getAPIMethodName());
    $crumbs->setBorder(true);

    $documentation_pages = $method->getDocumentationPages($viewer);

    $documentation_view = $this->newDocumentationView(
      $method,
      $documentation_pages);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(

        id(new PhabricatorAnchorView())
          ->setAnchorName('overview'),
        $info_box,

        id(new PhabricatorAnchorView())
          ->setAnchorName('documentation'),
        $documentation_view,

        id(new PhabricatorAnchorView())
          ->setAnchorName('call'),
        $form_box,

        id(new PhabricatorAnchorView())
          ->setAnchorName('examples'),
        $this->renderExampleBox($method, null),
      ));

    $title = $method->getAPIMethodName();

    $nav = $this->newNavigationView($method, $documentation_pages);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($view);
  }

  private function newDocumentationView(
    ConduitAPIMethod $method,
    array $documentation_pages) {
    assert_instances_of($documentation_pages, 'ConduitAPIDocumentationPage');

    $viewer = $this->getViewer();

    $description_properties = id(new PHUIPropertyListView());

    $description_properties->addTextContent(
      new PHUIRemarkupView($viewer, $method->getMethodDescription()));

    $description_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Method Description'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($description_properties);

    $view = array();
    $view[] = $description_box;

    foreach ($documentation_pages as $page) {
      $view[] = $page->newView();
    }

    return $view;
  }

  private function newNavigationView(
    ConduitAPIMethod $method,
    array $documentation_pages) {
    assert_instances_of($documentation_pages, 'ConduitAPIDocumentationPage');

    $console_uri = urisprintf(
      '/method/%s/',
      $method->getAPIMethodName());
    $console_uri = $this->getApplicationURI($console_uri);
    $console_uri = new PhutilURI($console_uri);

    $nav = id(new AphrontSideNavFilterView())
      ->setBaseURI($console_uri);

    $nav->selectFilter(null);

    $nav->newLink('overview')
      ->setHref('#overview')
      ->setName(pht('Overview'))
      ->setIcon('fa-list');

    $nav->newLink('documentation')
      ->setHref('#documentation')
      ->setName(pht('Documentation'))
      ->setIcon('fa-book');

    foreach ($documentation_pages as $page) {
      $nav->newLink($page->getAnchor())
        ->setHref('#'.$page->getAnchor())
        ->setName($page->getName())
        ->setIcon($page->getIconIcon())
        ->setIndented(true);
    }

    $nav->newLink('call')
      ->setHref('#call')
      ->setName(pht('Call Method'))
      ->setIcon('fa-play');

    $nav->newLink('examples')
      ->setHref('#examples')
      ->setName(pht('Examples'))
      ->setIcon('fa-folder-open-o');

    return $nav;
  }

  private function buildMethodProperties(ConduitAPIMethod $method) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView());

    $status = $method->getMethodStatus();
    $reason = $method->getMethodStatusDescription();

    switch ($status) {
      case ConduitAPIMethod::METHOD_STATUS_UNSTABLE:
        $stability_icon = 'fa-exclamation-triangle yellow';
        $stability_label = pht('Unstable Method');
        $stability_info = nonempty(
          $reason,
          pht(
            'This method is new and unstable. Its interface is subject '.
            'to change.'));
        break;
      case ConduitAPIMethod::METHOD_STATUS_DEPRECATED:
        $stability_icon = 'fa-exclamation-triangle red';
        $stability_label = pht('Deprecated Method');
        $stability_info = nonempty($reason, pht('This method is deprecated.'));
        break;
      case ConduitAPIMethod::METHOD_STATUS_FROZEN:
        $stability_icon = 'fa-archive grey';
        $stability_label = pht('Frozen Method');
        $stability_info = nonempty(
          $reason,
          pht('This method is frozen and will eventually be deprecated.'));
        break;
      default:
        $stability_label = null;
        break;
    }

    if ($stability_label) {
      $view->addProperty(
        pht('Stability'),
        array(
          id(new PHUIIconView())->setIcon($stability_icon),
          ' ',
          phutil_tag('strong', array(), $stability_label.':'),
          ' ',
          $stability_info,
        ));
    }

    $view->addProperty(
      pht('Returns'),
      $method->getReturnType());

    $error_types = $method->getErrorTypes();
    $error_types['ERR-CONDUIT-CORE'] = pht('See error message for details.');
    $error_description = array();
    foreach ($error_types as $error => $meaning) {
      $error_description[] = hsprintf(
        '<li><strong>%s:</strong> %s</li>',
        $error,
        $meaning);
    }
    $error_description = phutil_tag('ul', array(), $error_description);

    $view->addProperty(
      pht('Errors'),
      $error_description);

    $scope = $method->getRequiredScope();
    switch ($scope) {
      case ConduitAPIMethod::SCOPE_ALWAYS:
        $oauth_icon = 'fa-globe green';
        $oauth_description = pht(
          'OAuth clients may always call this method.');
        break;
      case ConduitAPIMethod::SCOPE_NEVER:
        $oauth_icon = 'fa-ban red';
        $oauth_description = pht(
          'OAuth clients may never call this method.');
        break;
      default:
        $oauth_icon = 'fa-unlock-alt blue';
        $oauth_description = pht(
          'OAuth clients may call this method after requesting access to '.
          'the "%s" scope.',
          $scope);
        break;
    }

    $view->addProperty(
      pht('OAuth Scope'),
      array(
        id(new PHUIIconView())->setIcon($oauth_icon),
        ' ',
        $oauth_description,
      ));

    return $view;
  }


}
