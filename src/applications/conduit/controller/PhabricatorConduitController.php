<?php

abstract class PhabricatorConduitController extends PhabricatorController {

  protected function buildSideNavView() {
    $viewer = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new PhabricatorConduitSearchEngine())
      ->setViewer($viewer)
      ->addNavigationItems($nav->getMenu());

    $nav->addLabel('Logs');
    $nav->addFilter('log', pht('Call Logs'));

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView()->getMenu();
  }

  protected function renderExampleBox(ConduitAPIMethod $method, $params) {
    $viewer = $this->getViewer();

    $arc_example = id(new PHUIPropertyListView())
      ->addRawContent($this->renderExample($method, 'arc', $params));

    $curl_example = id(new PHUIPropertyListView())
      ->addRawContent($this->renderExample($method, 'curl', $params));

    $php_example = id(new PHUIPropertyListView())
      ->addRawContent($this->renderExample($method, 'php', $params));

    $panel_uri = id(new PhabricatorConduitTokensSettingsPanel())
      ->setViewer($viewer)
      ->setUser($viewer)
      ->getPanelURI();

    $panel_link = phutil_tag(
      'a',
      array(
        'href' => $panel_uri,
      ),
      pht('Conduit API Tokens'));

    $panel_link = phutil_tag('strong', array(), $panel_link);

    $messages = array(
      pht(
        'Use the %s panel in Settings to generate or manage API tokens.',
        $panel_link),
    );

    if ($params === null) {
      $messages[] = pht(
        'If you submit parameters, these examples will update to show '.
        'exactly how to encode the parameters you submit.');
    }

    $info_view = id(new PHUIInfoView())
      ->setErrors($messages)
      ->setSeverity(PHUIInfoView::SEVERITY_NOTICE);

    $tab_group = id(new PHUITabGroupView())
      ->addTab(
        id(new PHUITabView())
          ->setName(pht('arc call-conduit'))
          ->setKey('arc')
          ->appendChild($arc_example))
      ->addTab(
        id(new PHUITabView())
          ->setName(pht('cURL'))
          ->setKey('curl')
          ->appendChild($curl_example))
      ->addTab(
        id(new PHUITabView())
          ->setName(pht('PHP'))
          ->setKey('php')
          ->appendChild($php_example));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Examples'))
      ->setInfoView($info_view)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addTabGroup($tab_group);
  }

  private function renderExample(
    ConduitAPIMethod $method,
    $kind,
    $params) {

    switch ($kind) {
      case 'arc':
        $example = $this->buildArcanistExample($method, $params);
        break;
      case 'php':
        $example = $this->buildPHPExample($method, $params);
        break;
      case 'curl':
        $example = $this->buildCURLExample($method, $params);
        break;
      default:
        throw new Exception(pht('Conduit client "%s" is not known.', $kind));
    }

    return $example;
  }

  private function buildArcanistExample(
    ConduitAPIMethod $method,
    $params) {

    $parts = array();

    $parts[] = '$ echo ';
    if ($params === null) {
      $parts[] = phutil_tag('strong', array(), '<json-parameters>');
    } else {
      $params = $this->simplifyParams($params);
      $params = id(new PhutilJSON())->encodeFormatted($params);
      $params = trim($params);
      $params = csprintf('%s', $params);
      $parts[] = phutil_tag('strong', array('class' => 'real'), $params);
    }

    $parts[] = ' | ';
    $parts[] = 'arc call-conduit ';

    $parts[] = '--conduit-uri ';
    $parts[] = phutil_tag(
      'strong',
      array('class' => 'real'),
      PhabricatorEnv::getURI('/'));
    $parts[] = ' ';

    $parts[] = '--conduit-token ';
    $parts[] = phutil_tag('strong', array(), '<conduit-token>');
    $parts[] = ' ';
    $parts[] = '--';
    $parts[] = ' ';

    $parts[] = $method->getAPIMethodName();

    return $this->renderExampleCode($parts);
  }

  private function buildPHPExample(
    ConduitAPIMethod $method,
    $params) {

    $parts = array();

    $libphutil_path = 'path/to/arcanist/support/init/init-script.php';

    $parts[] = '<?php';
    $parts[] = "\n\n";

    $parts[] = 'require_once ';
    $parts[] = phutil_var_export($libphutil_path);
    $parts[] = ";\n\n";

    $parts[] = '$api_token = "';
    $parts[] = phutil_tag('strong', array(), pht('<api-token>'));
    $parts[] = "\";\n";

    $parts[] = '$api_parameters = ';
    if ($params === null) {
      $parts[] = 'array(';
      $parts[] = phutil_tag('strong', array(), pht('<parameters>'));
      $parts[] = ');';
    } else {
      $params = $this->simplifyParams($params);
      $params = phutil_var_export($params);
      $parts[] = phutil_tag('strong', array('class' => 'real'), $params);
      $parts[] = ';';
    }
    $parts[] = "\n\n";

    $parts[] = '$client = new ConduitClient(';
    $parts[] = phutil_tag(
      'strong',
      array('class' => 'real'),
      phutil_var_export(PhabricatorEnv::getURI('/')));
    $parts[] = ");\n";

    $parts[] = '$client->setConduitToken($api_token);';
    $parts[] = "\n\n";

    $parts[] = '$result = $client->callMethodSynchronous(';
    $parts[] = phutil_tag(
      'strong',
      array('class' => 'real'),
      phutil_var_export($method->getAPIMethodName()));
    $parts[] = ', ';
    $parts[] = '$api_parameters';
    $parts[] = ");\n";

    $parts[] = 'print_r($result);';

    return $this->renderExampleCode($parts);
  }

  private function buildCURLExample(
    ConduitAPIMethod $method,
    $params) {

    $call_uri = '/api/'.$method->getAPIMethodName();

    $parts = array();

    $linebreak = array('\\', phutil_tag('br'), '    ');

    $parts[] = '$ curl ';
    $parts[] = phutil_tag(
      'strong',
      array('class' => 'real'),
      csprintf('%R', PhabricatorEnv::getURI($call_uri)));
    $parts[] = ' ';
    $parts[] = $linebreak;

    $parts[] = '-d api.token=';
    $parts[] = phutil_tag('strong', array(), 'api-token');
    $parts[] = ' ';
    $parts[] = $linebreak;

    if ($params === null) {
      $parts[] = '-d ';
      $parts[] = phutil_tag('strong', array(), 'param');
      $parts[] = '=';
      $parts[] = phutil_tag('strong', array(), 'value');
      $parts[] = ' ';
      $parts[] = $linebreak;
      $parts[] = phutil_tag('strong', array(), '...');
    } else {
      $lines = array();
      $params = $this->simplifyParams($params);

      foreach ($params as $key => $value) {
        $pieces = $this->getQueryStringParts(null, $key, $value);
        foreach ($pieces as $piece) {
          $lines[] = array(
            '-d ',
            phutil_tag('strong', array('class' => 'real'), $piece),
          );
        }
      }

      $parts[] = phutil_implode_html(array(' ', $linebreak), $lines);
    }

    return $this->renderExampleCode($parts);
  }

  private function renderExampleCode($example) {
    require_celerity_resource('conduit-api-css');

    return phutil_tag(
      'div',
      array(
        'class' => 'PhabricatorMonospaced conduit-api-example-code',
      ),
      $example);
  }

  private function simplifyParams(array $params) {
    foreach ($params as $key => $value) {
      if ($value === null) {
        unset($params[$key]);
      }
    }
    return $params;
  }

  private function getQueryStringParts($prefix, $key, $value) {
    if ($prefix === null) {
      $head = phutil_escape_uri($key);
    } else {
      $head = $prefix.'['.phutil_escape_uri($key).']';
    }

    if (!is_array($value)) {
      return array(
        $head.'='.phutil_escape_uri($value),
      );
    }

    $results = array();
    foreach ($value as $subkey => $subvalue) {
      $subparts = $this->getQueryStringParts($head, $subkey, $subvalue);
      foreach ($subparts as $subpart) {
        $results[] = $subpart;
      }
    }

    return $results;
  }

}
