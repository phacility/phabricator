<?php

class ManagementPanelUserDateRegistrationBox {
    private $user;
    
    public function __construct($user) {
        $this->user = $user;
    }
    
    public function getBox() {
        $userDateSelected = ManagementPanelStorageManager::getUserDateCreated($this->user);
        $userDateSelected = date('d-m-Y', $userDateSelected);
        $view = new PHUIInfoView();
        $view->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
        $view->setErrors(
            array(
                phutil_safe_html('User date registration: <b>' . $userDateSelected . '</b>'),
            ));
        return $view;
    }
}