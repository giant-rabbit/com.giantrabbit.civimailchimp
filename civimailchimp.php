<?php

require_once 'civimailchimp.civix.php';
require_once 'vendor/autoload.php';

/**
 * Implementation of hook_civicrm_buildForm
 */
function civimailchimp_civicrm_buildForm($formName, &$form) {
  if ($formName === "CRM_Group_Form_Edit") {
    $mailchimp_lists = CRM_CiviMailchimp_Utils::get_lists();
    $select_options = CRM_CiviMailchimp_Utils::format_lists_as_select_options($mailchimp_lists);
    $interest_groups_lookup = CRM_CiviMailchimp_Utils::format_interest_groups_lookup($mailchimp_lists);
    $settings = json_encode(array('interest_groups_lookup' => $interest_groups_lookup));
    CRM_Core_Resources::singleton()
      ->addScript("CRM.civiMailchimp = {$settings};", 9, 'page-body')
      ->addScriptFile('com.giantrabbit.civimailchimp', 'js/group_add_edit_form.js', 10, 'page-body');
    $form->add('select', 'mailchimp_list', ts('Mailchimp List'), $select_options, FALSE, array('class' => 'crm-select2'));
    $form->add('select', 'mailchimp_interest_groups', ts('Mailchimp Interest Groups'), $interest_groups_lookup, FALSE, array('multiple' => 'multiple', 'class' => 'crm-select2'));
  }
}

/**
 * Implementation of hook_civicrm_postProcess
 */
function civimailchimp_civicrm_postProcess($formName, &$form) {
  if ($formName === "CRM_Group_Form_Edit") {
    $params['civicrm_group_id'] = $form->getVar('_id');
    if (isset($form->_submitValues['mailchimp_list'])) {
      $params['mailchimp_list_id'] = $form->_submitValues['mailchimp_list'];
      $mailchimp_interest_groups = '';
      if (isset($form->_submitValues['mailchimp_interest_groups'])) {
        $mailchimp_interest_groups = serialize($form->_submitValues['mailchimp_interest_groups']);
      }
      $params['mailchimp_interest_group_id'] = $mailchimp_interest_groups;
      CRM_CiviMailchimp_Utils::updateGroupMailchimpSettings($params);
    }
    else {
      CRM_CiviMailchimp_Utils::deleteGroupMailchimpSettings($params);
    }
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
