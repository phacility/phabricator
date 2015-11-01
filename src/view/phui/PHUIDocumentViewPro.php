<?php

final class PHUIDocumentViewPro extends AphrontTagView {

  private $header;
  private $bookname;
  private $bookdescription;
  private $fluid;
  private $propertyList;
  private $toc;

  public function setHeader(PHUIHeaderView $header) {
    $header->setTall(true);
    $this->header = $header;
    return $this;
  }

  public function setBook($name, $description) {
    $this->bookname = $name;
    $this->bookdescription = $description;
    return $this;
  }

  public function setFluid($fluid) {
    $this->fluid = $fluid;
    return $this;
  }

  public function setPropertyList($view) {
    $this->propertyList = $view;
    return $this;
  }

  public function setToc(PHUIListView $toc) {
    $this->toc = $toc;
    return $this;
  }

  protected function getTagAttributes() {
    $classes = array();

    if ($this->fluid) {
      $classes[] = 'phui-document-fluid';
    }

    return array(
      'class' => $classes,
    );
  }

  protected function getTagContent() {
    require_celerity_resource('phui-document-view-css');
    require_celerity_resource('phui-document-view-pro-css');
    Javelin::initBehavior('phabricator-reveal-content');

    $classes = array();
    $classes[] = 'phui-document-view';
    $classes[] = 'phui-document-view-pro';

    $book = null;
    if ($this->bookname) {
      $book = pht('%s (%s)', $this->bookname, $this->bookdescription);
    }

    $main_content = $this->renderChildren();

    if ($book) {
      $this->header->setSubheader($book);
    }

    $table_of_contents = null;
    if ($this->toc) {
      $toc = array();
      $toc_id = celerity_generate_unique_node_id();
      $toc[] = id(new PHUIButtonView())
        ->setTag('a')
        ->setIconFont('fa-align-left')
        ->setColor(PHUIButtonView::SIMPLE)
        ->addClass('phui-document-toc')
        ->addSigil('jx-toggle-class')
        ->setMetaData(array(
          'map' => array(
            $toc_id => 'phui-document-toc-open',
          ),
        ));

      $toc[] = phutil_tag(
        'div',
        array(
          'class' => 'phui-list-sidenav phui-document-toc-list',
        ),
        $this->toc);

      $table_of_contents = phutil_tag(
        'div',
        array(
          'class' => 'phui-document-toc-container',
          'id' => $toc_id,
        ),
        $toc);
    }

    $content_inner = phutil_tag(
        'div',
        array(
          'class' => 'phui-document-inner',
        ),
        array(
          $table_of_contents,
          $this->header,
          $main_content,
        ));

    $content = phutil_tag(
        'div',
        array(
          'class' => 'phui-document-content',
        ),
        $content_inner);

    $view = phutil_tag(
      'div',
      array(
        'class' => implode(' ', $classes),
      ),
      $content);

    $list = null;
    if ($this->propertyList) {
      $list = phutil_tag_div('phui-document-properties', $this->propertyList);
    }

    return array($view, $list);
  }

}
