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
    $mailchimp_export_url = CRM_CiviMailchimp_Utils::formatMailchimpExportApiUrl($mailchimp_sync_setting->mailchimp_list_id);
    list($contacts, $mailchimp_members) = $this->processForcedSync($mailchimp_sync_setting, $mailchimp_export_url);
    parent::postProcess();
    CRM_Core_Session::setStatus(ts("%1 contacts were synced to Mailchimp and %2  Mailchimp members were synced to CiviCRM.", array(1 => count($contacts), 2 => count($mailchimp_members))), ts('CiviMailchimp Force Sync Successful'), 'success');
    CRM_Utils_System::redirect($this->controller->_entryURL);
  }

  function processForcedSync($mailchimp_sync_setting, $mailchimp_export_url) {
    $contacts = self::forceCiviToMailchimpSync($mailchimp_sync_setting);
    $mailchimp_members = self::forceMailchimpToCiviSync($mailchimp_export_url, $mailchimp_sync_setting);

    return array($contacts, $mailchimp_members);
  }

  static function forceCiviToMailchimpSync($mailchimp_sync_setting) {
    $contacts = CRM_CiviMailchimp_Utils::getActiveGroupMembers($mailchimp_sync_setting->civicrm_group_id);
    $skipped_contacts = 0;
    foreach ($contacts as $key => $contact_id) {
      $contact = CRM_CiviMailchimp_Utils::getContactById($contact_id);
      if ($contact->is_deleted != 1) {
        $email = CRM_CiviMailchimp_Utils::determineMailchimpEmailForContact($contact);
        if ($email === NULL) {
          ++$skipped_contacts;
          unset($contacts[$key]);
        }
        else {
          $merge_fields = CRM_CiviMailchimp_Utils::getMailchimpMergeFields($mailchimp_sync_setting->mailchimp_list_id);
          $merge_vars = CRM_CiviMailchimp_Utils::formatMailchimpMergeVars($merge_fields, $contact);
          CRM_CiviMailchimp_Utils::subscribeContactToMailchimpList($mailchimp_sync_setting->mailchimp_list_id, $email, $merge_vars);
        }
      }
    }
    if ($skipped_contacts > 0) {
      $message = ts('%1 records were not synced to Mailchimp because they did not have a valid email address.', array(1 => $skipped_contacts));
      CRM_CiviMailchimp_BAO_SyncLog::saveMessage('error', 'civicrm_to_mailchimp', $message);
    }

    return $contacts;
  }

  static function forceMailchimpToCiviSync($mailchimp_export_url, $mailchimp_sync_setting) {
    $mailchimp_members = CRM_CiviMailchimp_Utils::getAllMembersOfMailchimpList($mailchimp_export_url, $mailchimp_sync_setting->mailchimp_list_id);
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
