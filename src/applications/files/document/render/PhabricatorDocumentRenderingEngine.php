<?php

abstract class PhabricatorDocumentRenderingEngine
  extends Phobject {

  private $request;
  private $controller;
  private $activeEngine;
  private $ref;

  final public function setRequest(AphrontRequest $request) {
    $this->request = $request;
    return $this;
  }

  final public function getRequest() {
    if (!$this->request) {
      throw new PhutilInvalidStateException('setRequest');
    }

    return $this->request;
  }

  final public function setController(PhabricatorController $controller) {
    $this->controller = $controller;
    return $this;
  }

  final public function getController() {
    if (!$this->controller) {
      throw new PhutilInvalidStateException('setController');
    }

    return $this->controller;
  }

  final protected function getActiveEngine() {
    return $this->activeEngine;
  }

  final protected function getRef() {
    return $this->ref;
  }

  final public function newDocumentView(PhabricatorDocumentRef $ref) {
    $request = $this->getRequest();
    $viewer = $request->getViewer();

    $engines = PhabricatorDocumentEngine::getEnginesForRef($viewer, $ref);

    $engine_key = $this->getSelectedDocumentEngineKey();
    if (!isset($engines[$engine_key])) {
      $engine_key = head_key($engines);
    }
    $engine = $engines[$engine_key];

    $lines = $this->getSelectedLineRange();
    if ($lines) {
      $engine->setHighlightedLines(range($lines[0], $lines[1]));
    }

    $encode_setting = $request->getStr('encode');
    if (phutil_nonempty_string($encode_setting)) {
      $engine->setEncodingConfiguration($encode_setting);
    }

    $highlight_setting = $request->getStr('highlight');
    if (phutil_nonempty_string($highlight_setting)) {
      $engine->setHighlightingConfiguration($highlight_setting);
    }

    $blame_setting = ($request->getStr('blame') !== 'off');
    $engine->setBlameConfiguration($blame_setting);

    $views = array();
    foreach ($engines as $candidate_key => $candidate_engine) {
      $label = $candidate_engine->getViewAsLabel($ref);
      if ($label === null) {
        continue;
      }

      $view_uri = $this->newRefViewURI($ref, $candidate_engine);

      $view_icon = $candidate_engine->getViewAsIconIcon($ref);
      $view_color = $candidate_engine->getViewAsIconColor($ref);
      $loading = $candidate_engine->newLoadingContent($ref);

      $views[] = array(
        'viewKey' => $candidate_engine->getDocumentEngineKey(),
        'icon' => $view_icon,
        'color' => $view_color,
        'name' => $label,
        'engineURI' => $this->newRefRenderURI($ref, $candidate_engine),
        'viewURI' => $view_uri,
        'loadingMarkup' => hsprintf('%s', $loading),
        'canEncode' => $candidate_engine->canConfigureEncoding($ref),
        'canHighlight' => $candidate_engine->canConfigureHighlighting($ref),
        'canBlame' => $candidate_engine->canBlame($ref),
      );
    }

    $viewport_id = celerity_generate_unique_node_id();
    $control_id = celerity_generate_unique_node_id();
    $icon = $engine->newDocumentIcon($ref);

    $config = array(
      'controlID' => $control_id,
    );

    $this->willStageRef($ref);

    if ($engine->shouldRenderAsync($ref)) {
      $content = $engine->newLoadingContent($ref);
      $config['next'] = 'render';
    } else {
      $this->willRenderRef($ref);
      $content = $engine->newDocument($ref);

      if ($engine->canBlame($ref)) {
        $config['next'] = 'blame';
      }
    }

    Javelin::initBehavior('document-engine', $config);

    $viewport = phutil_tag(
      'div',
      array(
        'id' => $viewport_id,
      ),
      $content);

    $meta = array(
      'viewportID' => $viewport_id,
      'viewKey' => $engine->getDocumentEngineKey(),
      'views' => $views,
      'encode' => array(
        'icon' => 'fa-font',
        'name' => pht('Change Text Encoding...'),
        'uri' => '/services/encoding/',
        'value' => $encode_setting,
      ),
      'highlight' => array(
        'icon' => 'fa-lightbulb-o',
        'name' => pht('Highlight As...'),
        'uri' => '/services/highlight/',
        'value' => $highlight_setting,
      ),
      'blame' => array(
        'icon' => 'fa-backward',
        'hide' => pht('Hide Blame'),
        'show' => pht('Show Blame'),
        'uri' => $ref->getBlameURI(),
        'enabled' => $blame_setting,
        'value' => null,
      ),
      'coverage' => array(
        'labels' => array(
          // TODO: Modularize this properly, see T13125.
          array(
            'C' => pht('Covered'),
            'U' => pht('Not Covered'),
            'N' => pht('Not Executable'),
            'X' => pht('Not Reachable'),
          ),
        ),
      ),
    );

    $view_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('View Options'))
      ->setIcon('fa-file-image-o')
      ->setColor(PHUIButtonView::GREY)
      ->setID($control_id)
      ->setMetadata($meta)
      ->setDropdown(true)
      ->addSigil('document-engine-view-dropdown');

    $header = id(new PHUIHeaderView())
      ->setHeaderIcon($icon)
      ->setHeader($ref->getName())
      ->addActionLink($view_button);

    return id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setHeader($header)
      ->appendChild($viewport);
  }

  final public function newRenderResponse(PhabricatorDocumentRef $ref) {
    $this->willStageRef($ref);
    $this->willRenderRef($ref);

    $request = $this->getRequest();
    $viewer = $request->getViewer();

    $engines = PhabricatorDocumentEngine::getEnginesForRef($viewer, $ref);
    $engine_key = $this->getSelectedDocumentEngineKey();
    if (!isset($engines[$engine_key])) {
      return $this->newErrorResponse(
        pht(
          'The engine ("%s") is unknown, or unable to render this document.',
          $engine_key));
    }
    $engine = $engines[$engine_key];

    $this->activeEngine = $engine;

    $encode_setting = $request->getStr('encode');
    if (phutil_nonempty_string($encode_setting)) {
      $engine->setEncodingConfiguration($encode_setting);
    }

    $highlight_setting = $request->getStr('highlight');
    if (phutil_nonempty_string($highlight_setting)) {
      $engine->setHighlightingConfiguration($highlight_setting);
    }

    $blame_setting = ($request->getStr('blame') !== 'off');
    $engine->setBlameConfiguration($blame_setting);

    try {
      $content = $engine->newDocument($ref);
    } catch (Exception $ex) {
      return $this->newErrorResponse($ex->getMessage());
    }

    return $this->newContentResponse($content);
  }

  public function newErrorResponse($message) {
    $container = phutil_tag(
      'div',
      array(
        'class' => 'document-engine-error',
      ),
      array(
        id(new PHUIIconView())
          ->setIcon('fa-exclamation-triangle red'),
        ' ',
        $message,
      ));

    return $this->newContentResponse($container);
  }

  private function newContentResponse($content) {
    $request = $this->getRequest();
    $viewer = $request->getViewer();
    $controller = $this->getController();

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())
        ->setContent(
          array(
            'markup' => hsprintf('%s', $content),
          ));
    }

    $crumbs = $this->newCrumbs();
    $crumbs->setBorder(true);

    $content_frame = id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($content);

    $page_frame = id(new PHUITwoColumnView())
      ->setFooter($content_frame);

    $title = array();
    $ref = $this->getRef();
    if ($ref) {
      $title = array(
        $ref->getName(),
        pht('Standalone'),
      );
    } else {
      $title = pht('Document');
    }

    return $controller->newPage()
      ->setCrumbs($crumbs)
      ->setTitle($title)
      ->appendChild($page_frame);
  }

  protected function newCrumbs() {
    $engine = $this->getActiveEngine();
    $controller = $this->getController();

    $crumbs = $controller->buildApplicationCrumbsForEditEngine();

    $ref = $this->getRef();

    $this->addApplicationCrumbs($crumbs, $ref);

    if ($ref) {
      $label = $engine->getViewAsLabel($ref);
      if ($label) {
        $crumbs->addTextCrumb($label);
      }
    }

    return $crumbs;
  }

  public function getRefViewURI(
    PhabricatorDocumentRef $ref,
    PhabricatorDocumentEngine $engine) {
    return $this->newRefViewURI($ref, $engine);
  }

  abstract protected function newRefViewURI(
    PhabricatorDocumentRef $ref,
    PhabricatorDocumentEngine $engine);

  abstract protected function newRefRenderURI(
    PhabricatorDocumentRef $ref,
    PhabricatorDocumentEngine $engine);

  protected function getSelectedDocumentEngineKey() {
    return $this->getRequest()->getURIData('engineKey');
  }

  protected function getSelectedLineRange() {
    return $this->getRequest()->getURILineRange('lines', 1000);
  }

  protected function addApplicationCrumbs(
    PHUICrumbsView $crumbs,
    PhabricatorDocumentRef $ref = null) {
    return;
  }

  protected function willStageRef(PhabricatorDocumentRef $ref) {
    return;
  }

  protected function willRenderRef(PhabricatorDocumentRef $ref) {
    return;
  }

}
