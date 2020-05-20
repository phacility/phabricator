<?php

final class PhortuneOrderSummaryView
  extends PhortuneOrderView {

  private $resumeURI;
  private $printable;

  public function setResumeURI($resume_uri) {
    $this->resumeURI = $resume_uri;
    return $this;
  }

  public function getResumeURI() {
    return $this->resumeURI;
  }

  public function setPrintable($printable) {
    $this->printable = $printable;
    return $this;
  }

  public function getPrintable() {
    return $this->printable;
  }

  public function render() {
    $is_printable = $this->getPrintable();

    $content = array();

    if ($is_printable) {
      $content[] = $this->newContactHeader();
    }

    $content[] = $this->newMessagesView();
    $content[] = $this->newDetailsView();
    $content[] = $this->newDescriptionView();
    $content[] = $this->newItemsView();
    $content[] = $this->newChargesView();

    if ($is_printable) {
      $content[] = $this->newContactFooter();
    }

    return $content;
  }

  private function newMessagesView() {
    $viewer = $this->getViewer();
    $order = $this->getOrder();

    $messages = array();
    $severity = null;

    $resume_uri = $this->getResumeURI();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $order,
      PhabricatorPolicyCapability::CAN_EDIT);

    $can_merchant = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $order->getMerchant(),
      PhabricatorPolicyCapability::CAN_EDIT);

    switch ($order->getStatus()) {
      case PhortuneCart::STATUS_READY:
        if ($order->getIsInvoice()) {
          $severity = PHUIInfoView::SEVERITY_NOTICE;
          $messages[] = pht('This invoice is ready for payment.');
        }
        break;
      case PhortuneCart::STATUS_PURCHASING:
        if ($can_edit) {
          if ($resume_uri) {
            $messages[] = pht(
              'The checkout process has been started, but not yet completed. '.
              'You can continue checking out by clicking %s, or cancel the '.
              'order, or contact the merchant for assistance.',
              phutil_tag('strong', array(), pht('Continue Checkout')));
          } else {
            $messages[] = pht(
              'The checkout process has been started, but an error occurred. '.
              'You can cancel the order or contact the merchant for '.
              'assistance.');
          }
        }
        break;
      case PhortuneCart::STATUS_CHARGED:
        if ($can_edit) {
          $messages[] = pht(
            'You have been charged, but processing could not be completed. '.
            'You can cancel your order, or contact the merchant for '.
            'assistance.');
        }
        break;
      case PhortuneCart::STATUS_HOLD:
        if ($can_edit) {
          $messages[] = pht(
            'Payment for this order is on hold. You can click %s to check '.
            'for updates, cancel the order, or contact the merchant for '.
            'assistance.',
            phutil_tag('strong', array(), pht('Update Status')));
        }
        break;
      case PhortuneCart::STATUS_REVIEW:
        if ($can_merchant) {
          $messages[] = pht(
            'This order has been flagged for manual review. Review the order '.
            'and choose %s to accept it or %s to reject it.',
            phutil_tag('strong', array(), pht('Accept Order')),
            phutil_tag('strong', array(), pht('Refund Order')));
        } else if ($can_edit) {
          $messages[] = pht(
            'This order requires manual processing and will complete once '.
            'the merchant accepts it.');
        }
        break;
      case PhortuneCart::STATUS_PURCHASED:
        $severity = PHUIInfoView::SEVERITY_SUCCESS;
        $messages[] = pht('This purchase has been completed.');
        break;
    }

    if (!$messages) {
      return null;
    }

    if ($severity === null) {
      $severity = PHUIInfoView::SEVERITY_WARNING;
    }

    $messages_view = id(new PHUIInfoView())
      ->setSeverity($severity)
      ->appendChild($messages);

    $is_printable = $this->getPrintable();
    if ($is_printable) {
      $messages_view = phutil_tag(
        'div',
        array(
          'class' => 'phortune-invoice-status',
        ),
        $messages_view);
    }

    return $messages_view;
  }

  private function newDetailsView() {
    $viewer = $this->getViewer();
    $order = $this->getOrder();
    $is_printable = $this->getPrintable();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer)
      ->setObject($order);

    $account_phid = $order->getAccountPHID();
    $author_phid = $order->getAuthorPHID();
    $merchant_phid = $order->getMerchantPHID();

    $handles = $viewer->loadHandles(
      array(
        $account_phid,
        $author_phid,
        $merchant_phid,
      ));

    if ($is_printable) {
      $account_link = $handles[$account_phid]->getFullName();
      $author_link = $handles[$author_phid]->getFullName();
      $merchant_link = $handles[$merchant_phid]->getFullName();
    } else {
      $account_link = $handles[$account_phid]->renderLink();
      $author_link = $handles[$author_phid]->renderLink();
      $merchant_link = $handles[$merchant_phid]->renderLink();
    }

    if ($is_printable) {
      $view->addProperty(pht('Order Name'), $order->getName());
    }

    $view->addProperty(pht('Account'), $account_link);
    $view->addProperty(pht('Authorized By'), $author_link);
    $view->addProperty(pht('Merchant'), $merchant_link);

    $view->addProperty(
      pht('Order Status'),
      PhortuneCart::getNameForStatus($order->getStatus()));
    $view->addProperty(
      pht('Created'),
      phabricator_datetime($order->getDateCreated(), $viewer));
    $view->addProperty(
      pht('Updated'),
      phabricator_datetime($order->getDateModified(), $viewer));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($view);
  }

  private function newChargesView() {
    $viewer = $this->getViewer();
    $order = $this->getOrder();

    $charges = id(new PhortuneChargeQuery())
      ->setViewer($viewer)
      ->withCartPHIDs(array($order->getPHID()))
      ->needCarts(true)
      ->execute();

    $charges_table = id(new PhortuneChargeTableView())
      ->setUser($viewer)
      ->setCharges($charges)
      ->setShowOrder(false);

    $charges_view = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Charges'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($charges_table);

    return $charges_view;
  }

  private function newDescriptionView() {
    $viewer = $this->getViewer();
    $order = $this->getOrder();

    return id(new PhortuneOrderDescriptionView())
      ->setViewer($viewer)
      ->setOrder($order);
  }

  private function newItemsView() {
    $viewer = $this->getViewer();
    $order = $this->getOrder();

    return id(new PhortuneOrderItemsView())
      ->setViewer($viewer)
      ->setOrder($order);
  }

  private function newContactHeader() {
    $viewer = $this->getViewer();
    $order = $this->getOrder();

    $merchant = id(new PhortuneMerchantQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($order->getMerchant()->getPHID()))
      ->needProfileImage(true)
      ->executeOne();

    $merchant_name = $merchant->getName();
    $merchant_image = $merchant->getProfileImageURI();

    $account = $order->getAccount();
    $account_name = $account->getBillingName();

    $account_contact = $account->getBillingAddress();
    if (strlen($account_contact)) {
      $account_contact = new PHUIRemarkupView(
        $viewer,
        $account_contact);
    }

    $merchant_contact = $merchant->getContactInfo();
    if (strlen($merchant_contact)) {
      $merchant_contact = new PHUIRemarkupView(
        $viewer,
        $merchant->getContactInfo());
    }

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
          'alt' => $merchant_name,
          'src' => $merchant_image,
        )));

    $to_title = phutil_tag(
      'div',
      array(
        'class' => 'phortune-mini-header',
      ),
      pht('Bill To:'));

    $bill_to = phutil_tag(
      'td',
      array(
        'class' => 'phortune-invoice-to',
        'width' => '50%',
      ),
      array(
        $to_title,
        phutil_tag('strong', array(), $account_name),
        phutil_tag('br', array()),
        $account_contact,
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
        phutil_tag('strong', array(), $merchant_name),
        phutil_tag('br', array()),
        $merchant_contact,
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

    return array(
      $logo,
      $contact,
    );
  }

  private function newContactFooter() {
    $viewer = $this->getViewer();
    $order = $this->getOrder();

    $merchant = $order->getMerchant();
    $footer = $merchant->getInvoiceFooter();

    if (!strlen($footer)) {
      return null;
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'phortune-invoice-footer',
      ),
      $footer);
  }

}
