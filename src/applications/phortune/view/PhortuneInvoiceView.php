<?php

final class PhortuneInvoiceView extends AphrontTagView {

  private $merchantName;
  private $merchantLogo;
  private $merchantContact;
  private $merchantFooter;
  private $accountName;
  private $accountContact;
  private $status;
  private $content;

  public function setMerchantName($name) {
    $this->merchantName = $name;
    return $this;
  }

  public function setMerchantLogo($logo) {
    $this->merchantLogo = $logo;
    return $this;
  }

  public function setMerchantContact($contact) {
    $this->merchantContact = $contact;
    return $this;
  }

  public function setMerchantFooter($footer) {
    $this->merchantFooter = $footer;
    return $this;
  }

  public function setAccountName($name) {
    $this->accountName = $name;
    return $this;
  }

  public function setAccountContact($contact) {
    $this->accountContact = $contact;
    return $this;
  }

  public function setStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'phortune-invoice-view';

    return array(
      'class' => implode(' ', $classes),
    );
  }

  protected function getTagContent() {
    require_celerity_resource('phortune-invoice-css');

    $logo = phutil_tag(
      'div',
      array(
        'class' => 'phortune-invoice-logo',
      ),
      phutil_tag(
        'img',
        array(
          'height' => '50',
          'width' => '50',
          'alt' => $this->merchantName,
          'src' => $this->merchantLogo,
        )));

    $to_title = phutil_tag(
      'div',
      array(
        'class' => 'phortune-mini-header',
      ),
      pht('To:'));

    $bill_to = phutil_tag(
      'td',
      array(
        'class' => 'phortune-invoice-to',
        'width' => '50%',
      ),
      array(
        $to_title,
        phutil_tag('strong', array(), $this->accountName),
        phutil_tag('br', array()),
        $this->accountContact,
      ));

    $from_title = phutil_tag(
      'div',
      array(
        'class' => 'phortune-mini-header',
      ),
      pht('From:'));

    $bill_from = phutil_tag(
      'td',
      array(
        'class' => 'phortune-invoice-from',
        'width' => '50%',
      ),
      array(
        $from_title,
        phutil_tag('strong', array(), $this->merchantName),
        phutil_tag('br', array()),
        $this->merchantContact,
      ));

    $contact = phutil_tag(
      'table',
      array(
        'class' => 'phortune-invoice-contact',
        'width' => '100%',
      ),
      phutil_tag(
        'tr',
        array(),
        array(
          $bill_to,
          $bill_from,
        )));

    $status = null;
    if ($this->status) {
      $status = phutil_tag(
        'div',
        array(
          'class' => 'phortune-invoice-status',
        ),
        $this->status);
    }

    $footer = phutil_tag(
      'div',
      array(
        'class' => 'phortune-invoice-footer',
      ),
      $this->merchantFooter);

    return array(
      $logo,
      $contact,
      $status,
      $this->content,
      $footer,
    );
  }
}
