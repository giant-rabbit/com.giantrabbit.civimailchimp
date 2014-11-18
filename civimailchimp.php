<?php

require_once 'civimailchimp.civix.php';
require_once 'vendor/autoload.php';

function civimailchimp_civicrm_contact_added_to_group($group, $contact) {
  $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettings::getSyncSettingsByGroupId($group->id);
  if ($mailchimp_sync_setting) {
    // queue subscribe.
  }
}

function civimailchimp_civicrm_contact_removed_from_group($group, $contact) {
  $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettings::getSyncSettingsByGroupId($group->id);
  if ($mailchimp_sync_setting) {
    // queue unsubscribe.
  }
}

function civimailchimp_civicrm_contact_updated($old_contact, $new_contact) {
  $mailchimp_sync_settings = CRM_CiviMailchimp_BAO_SyncSettings::findByContactId($new_contact->id);
  if ($mailchimp_sync_settings) {
    $contact_mailchimp_merge_fields_changed = FALSE;
    $old_email = CRM_CiviMailchimp_Utils::determineMailchimpEmailForContact($old_contact);
    $new_email = CRM_CiviMailchimp_Utils::determineMailchimpEmailForContact($new_contact);
    if ($old_email !== $new_email) {
      $contact_mailchimp_merge_fields_changed = TRUE;
    }
    $civicrm_fields_already_checked = array();
    foreach ($mailchimp_sync_settings as $mailchimp_sync_setting) {
      $merge_fields = CRM_CiviMailchimp_Utils::mailchimpMergeFields($mailchimp_sync_setting->mailchimp_list_id);
      if (!$contact_mailchimp_merge_fields_changed) {
        foreach ($merge_fields as $merge_field => $civicrm_field) {
          if (!isset($civicrm_fields_already_checked[$civicrm_field]) && $old_contact->$civicrm_field !== $new_contact->$civicrm_field) {
            $contact_mailchimp_merge_fields_changed = TRUE;
            continue;
          }
          $civicrm_fields_already_checked[$civicrm_field] = 'checked';
        }
      }
      if ($contact_mailchimp_merge_fields_changed) {
        $mailchimp_profile_data = array();
        foreach ($merge_fields as $merge_field => $civicrm_field) {
          $mailchimp_profile_data[$merge_field] = $civicrm_field;
        }
        // also include email and interest groups...
        // queue profile update.
      }
    }
  }
}

/**
 * Implementation of hook_civicrm_buildForm
 */
function civimailchimp_civicrm_buildForm($formName, &$form) {
  if ($formName === "CRM_Group_Form_Edit") {
    try {
      $mailchimp_lists = CRM_CiviMailchimp_Utils::getLists();
    }
    catch (Exception $e) {
      $mailchimp_lists = NULL;
      civimailchimp_catch_mailchimp_api_error($e);
    }
    if ($mailchimp_lists) {
      $group_id = $form->getVar('_id');
      $list_options = CRM_CiviMailchimp_Utils::formatListsAsSelectOptions($mailchimp_lists);
      $interest_groups_lookup = CRM_CiviMailchimp_Utils::formatInterestGroupsLookup($mailchimp_lists);
      $interest_groups_options = '';
      if ($group_id) {
        $mailchimp_sync_settings = CRM_CiviMailchimp_BAO_SyncSettings::findByGroupId($group_id);
        if ($mailchimp_sync_settings) {
          if (isset($interest_groups_lookup[$mailchimp_sync_settings->mailchimp_list_id])) { 
            $interest_groups_options = $interest_groups_lookup[$mailchimp_sync_settings->mailchimp_list_id];
          }
          civimailchimp_civicrm_setDefaults(&$form, $mailchimp_sync_settings);
        }
      }
      $form->add('select', 'mailchimp_list', ts('Mailchimp List'), $list_options, FALSE, array('class' => 'crm-select2'));
      $form->add('select', 'mailchimp_interest_groups', ts('Mailchimp Interest Groups'), $interest_groups_options, FALSE, array('multiple' => 'multiple', 'class' => 'crm-select2'));
      CRM_Core_Resources::singleton()
        ->addScriptFile('com.giantrabbit.civimailchimp', 'js/group_add_edit_form.js')
        ->addSetting(array('civiMailchimp' => array('interest_groups_lookup' => $interest_groups_lookup)));
    }
  }
}

/**
 * Sets default values for the Mailchimp sync settings for a group.
 */
function civimailchimp_civicrm_setDefaults(&$form, $mailchimp_sync_settings) {
  $mailchimp_interest_groups = '';
  if (!empty($mailchimp_sync_settings->mailchimp_interest_group_id)) {
    $mailchimp_interest_groups = implode(",", unserialize($mailchimp_sync_settings->mailchimp_interest_group_id));
  }
  $defaults = array(
    'mailchimp_list' => $mailchimp_sync_settings->mailchimp_list_id,
    'mailchimp_interest_groups' => $mailchimp_interest_groups,
  );
  $form->setDefaults($defaults);
}

/**
 * Implementation of hook_civicrm_validateForm
 */
function civimailchimp_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  if ($formName === "CRM_Group_Form_Edit") {
    if (!empty($fields['mailchimp_list'])) {
      $site_key = defined('CIVICRM_SITE_KEY') ? CIVICRM_SITE_KEY : NULL;
      if (!$site_key) {
        $doc_link = CRM_Utils_System::docURL2("Managing Scheduled Jobs", TRUE, NULL, NULL, NULL, "wiki");
        $errors['mailchimp_list'] = ts("A valid CiviCRM site key in civicrm.settings.php is required to configure a Group to sync with Mailchimp. More info on generating a site key at %1.", array(1 => $doc_link));
      }
      $mailchimp_sync_settings = CRM_CiviMailchimp_BAO_SyncSettings::findByListId($fields['mailchimp_list']);
      if ($mailchimp_sync_settings) {
        $errors['mailchimp_list'] = ts("Another CiviCRM Group is already configured to sync to this Mailchimp list. Please select another list.");
      }
    }
  }
}

/**
 * Implementation of hook_civicrm_postProcess
 */
function civimailchimp_civicrm_postProcess($formName, &$form) {
  if ($formName === "CRM_Group_Form_Edit") {
    // If the Mailchimp API call fails, the mailchimp_list field will not be
    // added to the form, so we want to retain the existing Mailchimp List
    // sync settings for the group, if the group is edited.
    if (isset($form->_elementIndex['mailchimp_list'])) {
      $params['civicrm_group_id'] = $form->getVar('_id');
      // When creating a new group, the group ID is only accessible from the
      // 'amtgID' session variable.
      if (empty($params['civicrm_group_id'])) {
        $params['civicrm_group_id'] = $form->get('amtgID');
      }
      if ($form->_submitValues['mailchimp_list']) {
        $params['mailchimp_list_id'] = $form->_submitValues['mailchimp_list'];
        $mailchimp_interest_groups = '';
        if (isset($form->_submitValues['mailchimp_interest_groups'])) {
          $mailchimp_interest_groups = serialize($form->_submitValues['mailchimp_interest_groups']);
        }
        $params['mailchimp_interest_group_id'] = $mailchimp_interest_groups;
        CRM_CiviMailchimp_BAO_SyncSettings::saveSettings($params);
      }
      else {
        CRM_CiviMailchimp_BAO_SyncSettings::deleteSettings($params);
      }
    }
  }
}

/**
 * Catch a Mailchimp API error and add an entry to the CiviCRM log file.
 */
function civimailchimp_catch_mailchimp_api_error($exception) {
  // If the Mailchimp API call fails, we want to still allow admins to
  // create or edit a group.
  $error = array(
    'code' => get_class($exception),
    'message' => $exception->getMessage(),
  );
  $message = ts("There was an error when trying to retrieve available Mailchimp Lists to sync to a group. {$error['code']}: {$error['message']}.");
  $session = CRM_Core_Session::singleton();
  $session->setStatus($message, ts("Mailchimp API Error"), 'alert', array('expires' => 0));
  CRM_Core_Error::debug_var('Fatal Error Details', $error);
  CRM_Core_Error::backtrace('backTrace', TRUE);
}

/**
 * Implements hook_civicrm_pre for GroupContact create.
 */
function civimailchimp_civicrm_pre_GroupContact_create($group_id, &$contact_ids) {
  // The create operation for GroupContact is thrown for every existing and
  // new group when a contact is saved, so we have to do some extra work
  // to determine whether the contact has just been added to the group or
  // not. We're storing the actually added Groups in a static variable for
  // use in the civicrm_post hook.
  foreach ($contact_ids as $contact_id) {
    $contact_added_to_group = CRM_CiviMailchimp_Utils::contactAddedToGroup($group_id, $contact_id);
    if ($contact_added_to_group) {
      $groups_contact_added_to = civimailchimp_static('groups_contact_added_to');
      $groups_contact_added_to[$group_id][] = $contact_id;
      civimailchimp_static('groups_contact_added_to', $groups_contact_added_to);
    }
  }
}

/**
 * Implements hook_civicrm_post for GroupContact create.
 */
function civimailchimp_civicrm_post_GroupContact_create($group_id, &$contact_ids) {
  $groups_contact_added_to = civimailchimp_static('groups_contact_added_to');
  if ($groups_contact_added_to) {
    foreach ($groups_contact_added_to as $group_id => $contact_ids) {
      $group = CRM_CiviMailchimp_Utils::getGroupById($group_id);
      foreach ($contact_ids as $contact_id) {
        $contact = CRM_CiviMailchimp_Utils::getContactById($contact_id);
        civimailchimp_civicrm_contact_added_to_group($group, $contact);
      }
    }
  }
}

/**
 * Implements hook_civicrm_post for GroupContact delete.
 */
function civimailchimp_civicrm_post_GroupContact_delete($group_id, &$contact_ids) {
  $group = CRM_CiviMailchimp_Utils::getGroupById($group_id);
  foreach ($contact_ids as $contact_id) {
    $contact = CRM_CiviMailchimp_Utils::getContactById($contact_id);
    civimailchimp_civicrm_contact_removed_from_group($group, $contact);
  }
}

/**
 * Implements hook_civicrm_pre for Individual and Organization edit.
 */
function civimailchimp_civicrm_pre_Contact_edit($contact_id, &$contact) {
  $old_contact = CRM_CiviMailchimp_Utils::getContactById($contact_id);
  civimailchimp_static('old_contact', $old_contact);
}

/**
 * Implements hook_civicrm_post for Individual and Organization edit.
 */
function civimailchimp_civicrm_post_Contact_edit($contact_id, &$contact) {
  $old_contact = civimailchimp_static('old_contact');
  $new_contact = $contact;
  civimailchimp_civicrm_contact_updated($old_contact, $new_contact);
}

/**
 * Implements hook_civicrm_post for Individual and Organization delete.
 */
function civimailchimp_civicrm_post_Contact_delete($contact_id, &$contact) {
  $params = array("contact_id" => $contact_id);
  $contact_groups = CRM_Contact_BAO_Group::getGroups($params);
  foreach ($contact_groups as $group) {
    civimailchimp_civicrm_contact_removed_from_group($group, $contact);
  }
}

/**
 * Implements hook_civicrm_pre for Group delete.
 */
function civimailchimp_civicrm_pre_Group_delete($group_id, &$group) {
  $mailchimp_sync_settings = CRM_CiviMailchimp_BAO_SyncSettings::findByGroupId($group_id);
  if ($mailchimp_sync_settings) {
    civimailchimp_static('mailchimp_sync_settings', $mailchimp_sync_settings);
  }
}

/**
 * Implements hook_civicrm_post for Group delete.
 */
function civimailchimp_civicrm_post_Group_delete($group_id, &$group) {
  $mailchimp_sync_settings = civimailchimp_static('mailchimp_sync_settings');
  if ($mailchimp_sync_settings) {
    CRM_CiviMailchimp_Utils::deleteWebhookFromMailchimpList($mailchimp_sync_settings->mailchimp_list_id);
  }
}

/**
 * Implementation of hook_civicrm_pre
 */
function civimailchimp_civicrm_pre($op, $object_name, $id, &$params) {
  if ($object_name === "Individual" || $object_name === "Organization") {
    $object_name = "Contact";
  }
  $function_name = "civimailchimp_civicrm_pre_{$object_name}_{$op}";
  if (is_callable($function_name))
  {
    call_user_func($function_name, $id, &$params);
  }
}

/**
 * Implementation of hook_civicrm_post
 */
function civimailchimp_civicrm_post($op, $object_name, $object_id, &$object) {
  dd("{$object_name} {$op}");
  if ($object_name === "Individual" || $object_name === "Organization") {
    $object_name = "Contact";
  }
  $function_name = "civimailchimp_civicrm_post_{$object_name}_{$op}";
  if (is_callable($function_name))
  {
    call_user_func($function_name, $object_id, &$object);
  }
}

/**
 * Stores a static variable of a certain name for later retrieval.
 */
function civimailchimp_static($name, $new_value = NULL) {
  static $data = NULL;
  if ($new_value != NULL) {
    $data[$name] = $new_value;
  }
  if (isset($data[$name])) {
    return $data[$name];
  }
}

/**
 * Implementation of hook_civicrm_config
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function civimailchimp_civicrm_config(&$config) {
  _civimailchimp_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function civimailchimp_civicrm_xmlMenu(&$files) {
  _civimailchimp_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function civimailchimp_civicrm_install() {
  _civimailchimp_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function civimailchimp_civicrm_uninstall() {
  _civimailchimp_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function civimailchimp_civicrm_enable() {
  _civimailchimp_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function civimailchimp_civicrm_disable() {
  _civimailchimp_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function civimailchimp_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _civimailchimp_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function civimailchimp_civicrm_managed(&$entities) {
  _civimailchimp_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_alterSettingsFolders
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function civimailchimp_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _civimailchimp_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implementation of hook_civicrm_navigationMenu
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
function civimailchimp_civicrm_navigationMenu(&$params) {
  $administer_nav_id = CRM_Core_DAO::getFieldValue('CRM_Core_BAO_Navigation', 'Administer', 'id', 'name');
  if ($administer_nav_id) {
    $weight = max(array_keys($params[$administer_nav_id]['child']));
    $params[$administer_nav_id]['child'][$weight+1] = array(
      'attributes' => array(
        'label' => 'CiviMailchimp',
        'name' => 'CiviMailchimp',
        'url' => NULL,
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'parentID' => $administer_nav_id,
        'navID' => NULL,
        'active' => 1,
      ),
      'child' => array(
        0 => array(
          'attributes' => array(
            'label' => 'Mailchimp Settings',
            'name' => 'Mailchimp Settings',
            'url' => 'civicrm/admin/mailchimp/settings?reset=1',
            'permission' => 'administer CiviCRM',
            'operator' => NULL,
            'parentID' => NULL,
            'navID' => NULL,
            'active' => 1,
          ),
        ),
        1 => array(
          'attributes' => array(
            'label' => 'Force Sync',
            'name' => 'Force Sync',
            'url' => 'civicrm/admin/mailchimp/sync?reset=1',
            'permission' => 'administer CiviCRM',
            'operator' => NULL,
            'parentID' => NULL,
            'navID' => NULL,
            'active' => 1,
          ),
        ),
      ),
    );
  }
}
