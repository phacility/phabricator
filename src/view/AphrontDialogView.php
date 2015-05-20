<?php

final class AphrontDialogView extends AphrontView {

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
  private $headerColor = PHUIActionHeaderView::HEADER_LIGHTBLUE;
  private $footers = array();
  private $isStandalone;
  private $method = 'POST';
  private $disableWorkflowOnSubmit;
  private $disableWorkflowOnCancel;
  private $width      = 'default';
  private $errors = array();
  private $flush;
  private $validationException;


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

  public function setHeaderColor($color) {
    $this->headerColor = $color;
    return $this;
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
          'class' => 'button grey',
          'name'  => '__cancel__',
          'sigil' => 'jx-workflow-button',
          'meta' => $meta,
        ),
        $this->cancelText);
    }

    if (!$this->user) {
      throw new Exception(
        pht(
          'You must call %s when rendering an %s.',
          'setUser()',
          __CLASS__));
    }

    $more = $this->class;
    if ($this->flush) {
      $more .= ' aphront-dialog-flush';
    }

    switch ($this->width) {
      case self::WIDTH_FORM:
      case self::WIDTH_FULL:
        $more .= ' aphront-dialog-view-width-'.$this->width;
        break;
      case self::WIDTH_DEFAULT:
        break;
      default:
        throw new Exception("Unknown dialog width '{$this->width}'!");
    }

    if ($this->isStandalone) {
      $more .= ' aphront-dialog-view-standalone';
    }

    $attributes = array(
      'class'   => 'aphront-dialog-view '.$more,
      'sigil'   => 'jx-dialog',
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
      $buttons = array(phabricator_form(
        $this->user,
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

    $header = new PHUIActionHeaderView();
    $header->setHeaderTitle($this->title);
    $header->setHeaderColor($this->headerColor);

    $footer = null;
    if ($this->footers) {
      $footer = phutil_tag(
        'div',
        array(
          'class' => 'aphront-dialog-foot',
        ),
        $this->footers);
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
          'class' => 'aphront-dialog-body grouped',
        ),
        $children),
      $tail,
    );

    if ($this->renderAsForm) {
      return phabricator_form(
        $this->user,
        $form_attributes + $attributes,
        array($hidden_inputs, $content));
    } else {
      return javelin_tag(
        'div',
        $attributes,
        $content);
    }
  }

}
