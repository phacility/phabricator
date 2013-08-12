<?php

/**
 * @task children   Managing Children
 */
abstract class AphrontView extends Phobject
  implements PhutilSafeHTMLProducerInterface {

  protected $user;
  protected $children = array();


/* -(  Configuration  )------------------------------------------------------ */


  /**
   * @task config
   */
  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }


  /**
   * @task config
   */
  protected function getUser() {
    return $this->user;
  }


/* -(  Managing Children  )-------------------------------------------------- */


  /**
   * Test if this View accepts children.
   *
   * By default, views accept children, but subclases may override this method
   * to prevent children from being appended. Doing so will cause
   * @{method:appendChild} to throw exceptions instead of appending children.
   *
   * @return bool   True if the View should accept children.
   * @task children
   */
  protected function canAppendChild() {
    return true;
  }


  /**
   * Append a child to the list of children.
   *
   * This method will only work if the view supports children, which is
   * determined by @{method:canAppendChild}.
   *
   * @param  wild   Something renderable.
   * @return this
   */
  final public function appendChild($child) {
    if (!$this->canAppendChild()) {
      $class = get_class($this);
      throw new Exception(
        pht("View '%s' does not support children.", $class));
    }

    $this->children[] = $child;

    return $this;
  }


  /**
   * Produce children for rendering.
   *
   * Historically, this method reduced children to a string representation,
   * but it no longer does.
   *
   * @return wild Renderable children.
   * @task
   */
  final protected function renderChildren() {
    return $this->children;
  }


  /**
   * Test if an element has no children.
   *
   * @return bool True if this element has children.
   * @task children
   */
  final public function hasChildren() {
    if ($this->children) {
      $this->children = $this->reduceChildren($this->children);
    }
    return (bool)$this->children;
  }


  /**
   * Reduce effectively-empty lists of children to be actually empty. This
   * recursively removes `null`, `''`, and `array()` from the list of children
   * so that @{method:hasChildren} can more effectively align with expectations.
   *
   * NOTE: Because View children are not rendered, a View which renders down
   * to nothing will not be reduced by this method.
   *
   * @param   list<wild>  Renderable children.
   * @return  list<wild>  Reduced list of children.
   * @task children
   */
  private function reduceChildren(array $children) {
    foreach ($children as $key => $child) {
      if ($child === null) {
        unset($children[$key]);
      } else if ($child === '') {
        unset($children[$key]);
      } else if (is_array($child)) {
        $child = $this->reduceChildren($child);
        if ($child) {
          $children[$key] = $child;
        } else {
          unset($children[$key]);
        }
      }
    }
    return $children;
  }


/* -(  Rendering  )---------------------------------------------------------- */


  abstract public function render();


/* -(  PhutilSafeHTMLProducerInterface  )------------------------------------ */


  public function producePhutilSafeHTML() {
    return $this->render();
  }

}
