<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_CiviMailchimp_Form_Sync extends CRM_Core_Form {
  function buildQuickForm() {

    $this->add('select', 'group', 'Group', $this->getCiviMailchimpGroupOptions(), TRUE);
    $this->addButtons(
      array(
        array(
          'type' => 'submit',
          'name' => ts('Submit'),
          'isDefault' => TRUE,
        ),
      )
    );

    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  function postProcess() {
    $values = $this->exportValues();
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettings::findByGroupId($values['group']);
    $contacts = self::forceCiviToMailchimpSync($mailchimp_sync_setting);
    $mailchimp_members = self::forceMailchimpToCiviSync($mailchimp_sync_setting);

    parent::postProcess();
    CRM_Core_Session::setStatus(ts("%1 contacts were synced to Mailchimp and %2  Mailchimp members were synced to CiviCRM.", array(1 => count($contacts), 2 => count($mailchimp_members))), ts('CiviMailchimp Force Sync Successful'), 'success');
    CRM_Utils_System::redirect($this->controller->_entryURL);
  }

  static function forceCiviToMailchimpSync($mailchimp_sync_setting) {
    $contacts = CRM_Contact_BAO_Group::getGroupContacts($mailchimp_sync_setting->civicrm_group_id);
    foreach ($contacts as $contact) {
      $contact = CRM_CiviMailchimp_Utils::getContactById($contact['contact_id']);
      CRM_CiviMailchimp_Utils::forceSubscribeContactToMailchimpList($contact, $mailchimp_sync_setting);
    }

    return $contacts;
  }

  static function forceMailchimpToCiviSync($mailchimp_sync_setting) {
    $mailchimp_members = CRM_CiviMailchimp_Utils::getAllMembersOfMailchimpList($mailchimp_sync_setting->mailchimp_list_id);
    foreach ($mailchimp_members as $mailchimp_member) {
      CRM_CiviMailchimp_Page_Webhook::mailchimpWebhookSubscribe($mailchimp_member);
    }

    return $mailchimp_members;
  }

  function getCiviMailchimpGroupOptions() {
    $query = "
      SELECT
        civimailchimp_sync_settings.civicrm_group_id,
        civicrm_group.title
      FROM
        civimailchimp_sync_settings
      JOIN
        civicrm_group ON (civimailchimp_sync_settings.civicrm_group_id = civicrm_group.id);
    ";
    $result = CRM_Core_DAO::executeQuery($query);
    $options[''] = ' - select a group - ';
    while ($result->fetch()) {
      $options[$result->civicrm_group_id] = $result->title;
    }

    return $options;
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }
}
