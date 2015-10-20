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

    $call_uri = '/api/'.$method->getAPIMethodName();

    $status = $method->getMethodStatus();
    $reason = $method->getMethodStatusDescription();
    $errors = array();

    switch ($status) {
      case ConduitAPIMethod::METHOD_STATUS_DEPRECATED:
        $reason = nonempty($reason, pht('This method is deprecated.'));
        $errors[] = pht('Deprecated Method: %s', $reason);
        break;
      case ConduitAPIMethod::METHOD_STATUS_UNSTABLE:
        $reason = nonempty(
          $reason,
          pht(
            'This method is new and unstable. Its interface is subject '.
            'to change.'));
        $errors[] = pht('Unstable Method: %s', $reason);
        break;
    }

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
      ->setHeader($method->getAPIMethodName());

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Call Method'))
      ->setForm($form);

    $content = array();

    $properties = $this->buildMethodProperties($method);

    $info_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('API Method: %s', $method->getAPIMethodName()))
      ->setFormErrors($errors)
      ->appendChild($properties);

    $content[] = $info_box;
    $content[] = $form_box;
    $content[] = $this->renderExampleBox($method, null);

    $query = $method->newQueryObject();
    if ($query) {
      $orders = $query->getBuiltinOrders();

      $rows = array();
      foreach ($orders as $key => $order) {
        $rows[] = array(
          $key,
          $order['name'],
          implode(', ', $order['vector']),
        );
      }

      $table = id(new AphrontTableView($rows))
        ->setHeaders(
          array(
            pht('Key'),
            pht('Description'),
            pht('Columns'),
          ))
        ->setColumnClasses(
          array(
            'pri',
            '',
            'wide',
          ));
      $content[] = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Builtin Orders'))
        ->setTable($table);

      $columns = $query->getOrderableColumns();

      $rows = array();
      foreach ($columns as $key => $column) {
        $rows[] = array(
          $key,
          idx($column, 'unique') ? pht('Yes') : pht('No'),
        );
      }

      $table = id(new AphrontTableView($rows))
        ->setHeaders(
          array(
            pht('Key'),
            pht('Unique'),
          ))
        ->setColumnClasses(
          array(
            'pri',
            'wide',
          ));
      $content[] = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Column Orders'))
        ->setTable($table);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($method->getAPIMethodName());

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $content,
      ),
      array(
        'title' => $method->getAPIMethodName(),
      ));
  }

  private function buildMethodProperties(ConduitAPIMethod $method) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView());

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

    $view->addSectionHeader(
      pht('Description'), PHUIPropertyListView::ICON_SUMMARY);
    $view->addTextContent(
      new PHUIRemarkupView($viewer, $method->getMethodDescription()));

    return $view;
  }


}
