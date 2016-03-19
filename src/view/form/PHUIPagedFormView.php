<?php

/**
 * @task page   Managing Pages
 */
final class PHUIPagedFormView extends AphrontView {

  private $name = 'pages';
  private $pages = array();
  private $selectedPage;
  private $choosePage;
  private $nextPage;
  private $prevPage;
  private $complete;
  private $cancelURI;

  protected function canAppendChild() {
    return false;
  }


/* -(  Managing Pages  )----------------------------------------------------- */


  /**
   * @task page
   */
  public function addPage($key, PHUIFormPageView $page) {
    if (isset($this->pages[$key])) {
      throw new Exception(pht("Duplicate page with key '%s'!", $key));
    }
    $this->pages[$key] = $page;
    $page->setPagedFormView($this, $key);

    $this->selectedPage = null;
    $this->complete = null;

    return $this;
  }


  /**
   * @task page
   */
  public function getPage($key) {
    if (!$this->pageExists($key)) {
      throw new Exception(pht("No page '%s' exists!", $key));
    }
    return $this->pages[$key];
  }


  /**
   * @task page
   */
  public function pageExists($key) {
    return isset($this->pages[$key]);
  }


  /**
   * @task page
   */
  protected function getPageIndex($key) {
    $page = $this->getPage($key);

    $index = 0;
    foreach ($this->pages as $target_page) {
      if ($page === $target_page) {
        break;
      }
      $index++;
    }

    return $index;
  }


  /**
   * @task page
   */
  protected function getPageByIndex($index) {
    foreach ($this->pages as $page) {
      if (!$index) {
        return $page;
      }
      $index--;
    }

    throw new Exception(pht("Requesting out-of-bounds page '%s'.", $index));
  }

  protected function getLastIndex() {
    return count($this->pages) - 1;
  }

  protected function isFirstPage(PHUIFormPageView $page) {
    return ($this->getPageIndex($page->getKey()) === 0);

  }

  protected function isLastPage(PHUIFormPageView $page) {
    return ($this->getPageIndex($page->getKey()) === (count($this->pages) - 1));
  }

  public function getSelectedPage() {
    return $this->selectedPage;
  }

  public function readFromObject($object) {
    return $this->processForm($is_request = false, $object);
  }

  public function writeToResponse($response) {
    foreach ($this->pages as $page) {
      $page->validateResponseType($response);
      $response = $page->writeToResponse($page);
    }

    return $response;
  }

  public function readFromRequest(AphrontRequest $request) {
    $this->choosePage = $request->getStr($this->getRequestKey('page'));
    $this->nextPage = $request->getStr('__submit__');
    $this->prevPage = $request->getStr('__back__');

    return $this->processForm($is_request = true, $request);
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }


  public function getValue($page, $key, $default = null) {
    return $this->getPage($page)->getValue($key, $default);
  }

  public function setValue($page, $key, $value) {
    $this->getPage($page)->setValue($key, $value);
    return $this;
  }

  private function processForm($is_request, $source) {
    if ($this->pageExists($this->choosePage)) {
      $selected = $this->getPage($this->choosePage);
    } else {
      $selected = $this->getPageByIndex(0);
    }

    $is_attempt_complete = false;
    if ($this->prevPage) {
      $prev_index = $this->getPageIndex($selected->getKey()) - 1;
      $index = max(0, $prev_index);
      $selected = $this->getPageByIndex($index);
    } else if ($this->nextPage) {
      $next_index = $this->getPageIndex($selected->getKey()) + 1;
      if ($next_index > $this->getLastIndex()) {
        $is_attempt_complete = true;
      }
      $index = min($this->getLastIndex(), $next_index);
      $selected = $this->getPageByIndex($index);
    }

    $validation_error = false;
    $found_selected = false;
    foreach ($this->pages as $key => $page) {
      if ($is_request) {
        if ($key === $this->choosePage) {
          $page->readFromRequest($source);
        } else {
          $page->readSerializedValues($source);
        }
      } else {
        $page->readFromObject($source);
      }

      if (!$found_selected) {
        $page->adjustFormPage();
      }

      if ($page === $selected) {
        $found_selected = true;
      }

      if (!$found_selected || $is_attempt_complete) {
        if (!$page->isValid()) {
          $selected = $page;
          $validation_error = true;
          break;
        }
      }
    }

    if ($is_attempt_complete && !$validation_error) {
      $this->complete = true;
    } else {
      $this->selectedPage = $selected;
    }

    return $this;
  }

  public function isComplete() {
    return $this->complete;
  }

  public function getRequestKey($key) {
    return $this->name.':'.$key;
  }

  public function setCancelURI($cancel_uri) {
    $this->cancelURI = $cancel_uri;
    return $this;
  }

  public function getCancelURI() {
    return $this->cancelURI;
  }

  public function render() {
    $form = id(new AphrontFormView())
      ->setUser($this->getUser());

    $selected_page = $this->getSelectedPage();
    if (!$selected_page) {
      throw new Exception(pht('No selected page!'));
    }

    $form->addHiddenInput(
      $this->getRequestKey('page'),
      $selected_page->getKey());

    $errors = array();

    foreach ($this->pages as $page) {
      if ($page == $selected_page) {
        $errors = $page->getPageErrors();
        continue;
      }
      foreach ($page->getSerializedValues() as $key => $value) {
        $form->addHiddenInput($key, $value);
      }
    }

    $submit = id(new PHUIFormMultiSubmitControl());

    if (!$this->isFirstPage($selected_page)) {
      $submit->addBackButton();
    } else if ($this->getCancelURI()) {
      $submit->addCancelButton($this->getCancelURI());
    }

    if ($this->isLastPage($selected_page)) {
      $submit->addSubmitButton(pht('Save'));
    } else {
      $submit->addSubmitButton(pht('Continue')." \xC2\xBB");
    }

    $form->appendChild($selected_page);
    $form->appendChild($submit);

    $box = id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setFormErrors($errors)
      ->setForm($form);

    if ($selected_page->getPageName()) {
      $header = id(new PHUIHeaderView())
        ->setHeader($selected_page->getPageName());
      $box->setHeader($header);
    }

    return $box;
  }

}
