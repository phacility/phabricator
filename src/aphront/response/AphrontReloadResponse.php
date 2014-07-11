<?php

/**
 * When actions happen over a JX.Workflow, we may want to reload the page
 * if the action is javascript-driven but redirect if it isn't. This preserves
 * query parameters in the javascript case. A reload response behaves like
 * a redirect response but causes a page reload when received via workflow.
 */
final class AphrontReloadResponse extends AphrontRedirectResponse {

  public function getURI() {
    if ($this->getRequest()->isAjax()) {
      return null;
    } else {
      return parent::getURI();
    }
  }

}
