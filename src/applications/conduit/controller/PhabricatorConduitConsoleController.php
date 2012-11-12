<?php

/**
 * @group conduit
 */
final class PhabricatorConduitConsoleController
  extends PhabricatorConduitController {

  private $method;

  public function willProcessRequest(array $data) {
    $this->method = $data['method'];
  }

  public function processRequest() {

    $request = $this->getRequest();

    $methods = $this->getAllMethods();
    if (empty($methods[$this->method])) {
      return new Aphront404Response();
    }
    $this->setFilter('method/'.$this->method);

    $method_class = $methods[$this->method];
    $method_object = newv($method_class, array());

    $status = $method_object->getMethodStatus();
    $reason = $method_object->getMethodStatusDescription();

    $status_view = null;
    if ($status != ConduitAPIMethod::METHOD_STATUS_STABLE) {
      $status_view = new AphrontErrorView();
      switch ($status) {
        case ConduitAPIMethod::METHOD_STATUS_DEPRECATED:
          $status_view->setTitle('Deprecated Method');
          $status_view->appendChild(
            phutil_escape_html(
              nonempty(
                $reason,
                "This method is deprecated.")));
          break;
        case ConduitAPIMethod::METHOD_STATUS_UNSTABLE:
          $status_view->setSeverity(AphrontErrorView::SEVERITY_WARNING);
          $status_view->setTitle('Unstable Method');
          $status_view->appendChild(
            phutil_escape_html(
              nonempty(
                $reason,
                "This method is new and unstable. Its interface is subject ".
                "to change.")));
          break;
      }
    }

    $error_description = array();
    $error_types = $method_object->defineErrorTypes();
    if ($error_types) {
      $error_description[] = '<ul>';
      foreach ($error_types as $error => $meaning) {
        $error_description[] =
          '<li>'.
            '<strong>'.phutil_escape_html($error).':</strong> '.
            phutil_escape_html($meaning).
          '</li>';
      }
      $error_description[] = '</ul>';
      $error_description = implode("\n", $error_description);
    } else {
      $error_description = "This method does not raise any specific errors.";
    }

    $form = new AphrontFormView();
    $form
      ->setUser($request->getUser())
      ->setAction('/api/'.$this->method)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Description')
          ->setValue($method_object->getMethodDescription()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Returns')
          ->setValue($method_object->defineReturnType()))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Errors')
          ->setValue($error_description))
      ->appendChild(
        '<p class="aphront-form-instructions">Enter parameters using '.
        '<strong>JSON</strong>. For instance, to enter a list, type: '.
        '<tt>["apple", "banana", "cherry"]</tt>');

    $params = $method_object->defineParamTypes();
    foreach ($params as $param => $desc) {
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel($param)
          ->setName("params[{$param}]")
          ->setCaption(phutil_escape_html($desc)));
    }

    $form
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Output Format')
          ->setName('output')
          ->setOptions(
            array(
              'human' => 'Human Readable',
              'json'  => 'JSON',
            )))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Call Method'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Conduit API: '.phutil_escape_html($this->method));
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FULL);

    return $this->buildStandardPageResponse(
      array(
        $status_view,
        $panel,
      ),
      array(
        'title' => 'Conduit Console - '.$this->method,
      ));
  }

  private function getAllMethods() {
    $classes = $this->getAllMethodImplementationClasses();
    $methods = array();
    foreach ($classes as $class) {
      $name = ConduitAPIMethod::getAPIMethodNameFromClassName($class);
      $methods[$name] = $class;
    }
    return $methods;
  }
}
