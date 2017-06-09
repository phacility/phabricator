<?php

final class AphrontDialogView
  extends AphrontView
  implements AphrontResponseProducerInterface {

  private $title;
  private $shortTitle;
  private $submitButton;
  private $cancelURI;
  private $cancelText = 'Cancel';
  private $submitURI;
  private $hidden = array();
  private $class;
  private $renderAsForm = true;
  private $formID;
  private $footers = array();
  private $isStandalone;
  private $method = 'POST';
  private $disableWorkflowOnSubmit;
  private $disableWorkflowOnCancel;
  private $width      = 'default';
  private $errors = array();
  private $flush;
  private $validationException;
  private $objectList;
  private $resizeX;
  private $resizeY;


  const WIDTH_DEFAULT = 'default';
  const WIDTH_FORM    = 'form';
  const WIDTH_FULL    = 'full';

  public function setMethod($method) {
    $this->method = $method;
    return $this;
  }

  public function setIsStandalone($is_standalone) {
    $this->isStandalone = $is_standalone;
    return $this;
  }

  public function setErrors(array $errors) {
    $this->errors = $errors;
    return $this;
  }

  public function getIsStandalone() {
    return $this->isStandalone;
  }

  public function setSubmitURI($uri) {
    $this->submitURI = $uri;
    return $this;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function getTitle() {
    return $this->title;
  }

  public function setShortTitle($short_title) {
    $this->shortTitle = $short_title;
    return $this;
  }

  public function getShortTitle() {
    return $this->shortTitle;
  }

  public function setResizeY($resize_y) {
    $this->resizeY = $resize_y;
    return $this;
  }

  public function getResizeY() {
    return $this->resizeY;
  }

  public function setResizeX($resize_x) {
    $this->resizeX = $resize_x;
    return $this;
  }

  public function getResizeX() {
    return $this->resizeX;
  }

  public function addSubmitButton($text = null) {
    if (!$text) {
      $text = pht('Okay');
    }

    $this->submitButton = $text;
    return $this;
  }

  public function addCancelButton($uri, $text = null) {
    if (!$text) {
      $text = pht('Cancel');
    }

    $this->cancelURI = $uri;
    $this->cancelText = $text;
    return $this;
  }

  public function addFooter($footer) {
    $this->footers[] = $footer;
    return $this;
  }

  public function addHiddenInput($key, $value) {
    if (is_array($value)) {
      foreach ($value as $hidden_key => $hidden_value) {
        $this->hidden[] = array($key.'['.$hidden_key.']', $hidden_value);
      }
    } else {
      $this->hidden[] = array($key, $value);
    }
    return $this;
  }

  public function setClass($class) {
    $this->class = $class;
    return $this;
  }

  public function setFlush($flush) {
    $this->flush = $flush;
    return $this;
  }

  public function setRenderDialogAsDiv() {
    // TODO: This API is awkward.
    $this->renderAsForm = false;
    return $this;
  }

  public function setFormID($id) {
    $this->formID = $id;
    return $this;
  }

  public function setWidth($width) {
    $this->width = $width;
    return $this;
  }

  public function setObjectList(PHUIObjectItemListView $list) {
    $this->objectList = true;
    $box = id(new PHUIObjectBoxView())
      ->setObjectList($list);
    return $this->appendChild($box);
  }

  public function appendParagraph($paragraph) {
    return $this->appendChild(
      phutil_tag(
        'p',
        array(
          'class' => 'aphront-dialog-view-paragraph',
        ),
        $paragraph));
  }

  public function appendList(array $items) {
    $listitems = array();
    foreach ($items as $item) {
      $listitems[] = phutil_tag(
        'li',
        array(
          'class' => 'remarkup-list-item',
        ),
        $item);
    }
    return $this->appendChild(
      phutil_tag(
        'ul',
        array(
          'class' => 'remarkup-list',
        ),
        $listitems));
  }

  public function appendForm(AphrontFormView $form) {
    return $this->appendChild($form->buildLayoutView());
  }

  public function setDisableWorkflowOnSubmit($disable_workflow_on_submit) {
    $this->disableWorkflowOnSubmit = $disable_workflow_on_submit;
    return $this;
  }

  public function getDisableWorkflowOnSubmit() {
    return $this->disableWorkflowOnSubmit;
  }

  public function setDisableWorkflowOnCancel($disable_workflow_on_cancel) {
    $this->disableWorkflowOnCancel = $disable_workflow_on_cancel;
    return $this;
  }

  public function getDisableWorkflowOnCancel() {
    return $this->disableWorkflowOnCancel;
  }

  public function setValidationException(
    PhabricatorApplicationTransactionValidationException $ex = null) {
    $this->validationException = $ex;
    return $this;
  }

  public function render() {
    require_celerity_resource('aphront-dialog-view-css');

    $buttons = array();
    if ($this->submitButton) {
      $meta = array();
      if ($this->disableWorkflowOnSubmit) {
        $meta['disableWorkflow'] = true;
      }

      $buttons[] = javelin_tag(
        'button',
        array(
          'name' => '__submit__',
          'sigil' => '__default__',
          'type' => 'submit',
          'meta' => $meta,
        ),
        $this->submitButton);
    }

    if ($this->cancelURI) {
      $meta = array();
      if ($this->disableWorkflowOnCancel) {
        $meta['disableWorkflow'] = true;
      }

      $buttons[] = javelin_tag(
        'a',
        array(
          'href'  => $this->cancelURI,
          'class' => 'button button-grey',
          'name'  => '__cancel__',
          'sigil' => 'jx-workflow-button',
          'meta' => $meta,
        ),
        $this->cancelText);
    }

    if (!$this->hasViewer()) {
      throw new Exception(
        pht(
          'You must call %s when rendering an %s.',
          'setViewer()',
          __CLASS__));
    }

    $classes = array();
    $classes[] = 'aphront-dialog-view';
    $classes[] = $this->class;
    if ($this->flush) {
      $classes[] = 'aphront-dialog-flush';
    }

    switch ($this->width) {
      case self::WIDTH_FORM:
      case self::WIDTH_FULL:
        $classes[] = 'aphront-dialog-view-width-'.$this->width;
        break;
      case self::WIDTH_DEFAULT:
        break;
      default:
        throw new Exception(
          pht(
            "Unknown dialog width '%s'!",
            $this->width));
    }

    if ($this->isStandalone) {
      $classes[] = 'aphront-dialog-view-standalone';
    }

    if ($this->objectList) {
      $classes[] = 'aphront-dialog-object-list';
    }

    $attributes = array(
      'class'   => implode(' ', $classes),
      'sigil'   => 'jx-dialog',
      'role'    => 'dialog',
    );

    $form_attributes = array(
      'action'  => $this->submitURI,
      'method'  => $this->method,
      'id'      => $this->formID,
    );

    $hidden_inputs = array();
    $hidden_inputs[] = phutil_tag(
      'input',
      array(
        'type' => 'hidden',
        'name' => '__dialog__',
        'value' => '1',
      ));

    foreach ($this->hidden as $desc) {
      list($key, $value) = $desc;
      $hidden_inputs[] = javelin_tag(
        'input',
        array(
          'type' => 'hidden',
          'name' => $key,
          'value' => $value,
          'sigil' => 'aphront-dialog-application-input',
        ));
    }

    if (!$this->renderAsForm) {
      $buttons = array(
        phabricator_form(
          $this->getViewer(),
          $form_attributes,
          array_merge($hidden_inputs, $buttons)),
      );
    }

    $children = $this->renderChildren();

    $errors = $this->errors;

    $ex = $this->validationException;
    $exception_errors = null;
    if ($ex) {
      foreach ($ex->getErrors() as $error) {
        $errors[] = $error->getMessage();
      }
    }

    if ($errors) {
      $children = array(
        id(new PHUIInfoView())->setErrors($errors),
        $children,
      );
    }

    $header = new PHUIHeaderView();
    $header->setHeader($this->title);

    $footer = null;
    if ($this->footers) {
      $footer = phutil_tag(
        'div',
        array(
          'class' => 'aphront-dialog-foot',
        ),
        $this->footers);
    }

    $resize = null;
    if ($this->resizeX || $this->resizeY) {
      $resize = javelin_tag(
        'div',
        array(
          'class' => 'aphront-dialog-resize',
          'sigil' => 'jx-dialog-resize',
          'meta' => array(
            'resizeX' => $this->resizeX,
            'resizeY' => $this->resizeY,
          ),
        ));
    }

    $tail = null;
    if ($buttons || $footer) {
      $tail = phutil_tag(
        'div',
        array(
          'class' => 'aphront-dialog-tail grouped',
        ),
        array(
          $buttons,
          $footer,
          $resize,
        ));
    }

    $content = array(
      phutil_tag(
        'div',
        array(
          'class' => 'aphront-dialog-head',
        ),
        $header),
      phutil_tag('div',
        array(
          'class' => 'aphront-dialog-body phabricator-remarkup grouped',
        ),
        $children),
      $tail,
    );

    if ($this->renderAsForm) {
      return phabricator_form(
        $this->getViewer(),
        $form_attributes + $attributes,
        array($hidden_inputs, $content));
    } else {
      return javelin_tag(
        'div',
        $attributes,
        $content);
    }
  }


/* -(  AphrontResponseProducerInterface  )----------------------------------- */


  public function produceAphrontResponse() {
    return id(new AphrontDialogResponse())
      ->setDialog($this);
  }

}
