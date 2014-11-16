<?php

require_once 'CRM/Core/Page.php';

class CRM_CiviMailchimp_Page_Webhook extends CRM_Core_Page {
  function run() {
    dd("Webhook!");
    parent::run();
  }
}
