<?php

require_once 'CiviTest/CiviUnitTestCase.php';
require_once __DIR__ . '/../../../../api/v3/CiviMailchimp.php';

/**
 * This test class includes any functions not part of a class.
 */
class CRM_CiviMailchimp_MiscTest extends CiviUnitTestCase {

  static function setUpBeforeClass() {
    // Use the Mailchimp API test class for all the tests.
    CRM_Core_BAO_Setting::setItem('CRM_MailchimpMock', 'CiviMailchimp Preferences', 'mailchimp_api_class');
  }

  function setUp() {
    parent::setUp();
  }

  function tearDown() {
    // Normally, we wouldn't want to truncate any tables as it makes running
    // the tests slower and opens the door for writing test that aren't self-
    // sufficient, but we're forced into this as CiviUnitTestCase forces a 
    // quickCleanup on civicrm_contact in its tearDown. :(
    $this->quickCleanup(array('civicrm_email', 'civicrm_queue_item'));
    civimailchimp_static('mailchimp_static_reset', NULL, TRUE);
    parent::tearDown();
  }

  function test_civimailchimp_civicrm_contact_added_to_group() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civimailchimp_civicrm_contact_added_to_group', $mailchimp_list_id);
    $group = CRM_CiviMailchimp_Utils::getGroupById($mailchimp_sync_setting->civicrm_group_id);
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_contact_added_to_group($group, $contact);
    $item = $queue->claimItem();
    $this->assertEquals('subscribeContactToMailchimpList', $item->data->arguments[0]);
    $this->assertEquals($mailchimp_list_id, $item->data->arguments[1]);
    $this->assertEquals($params['email'][0]['email'], $item->data->arguments[2]);
    $this->assertEquals($params['first_name'], $item->data->arguments[3]['FNAME']);
    $this->assertEquals($params['last_name'], $item->data->arguments[3]['LNAME']);
  }

  function test_civimailchimp_civicrm_contact_added_to_group_no_sync_settings() {
    $group_name = 'Test Group test_contact_added_to_group_no_sync_settings';
    $group_id = $this->groupCreate(array('name' => $group_name, 'title' => $group_name));
    $group = CRM_CiviMailchimp_Utils::getGroupById($group_id);
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_contact_added_to_group($group, $contact);
    $this->assertEquals(0, $queue->numberOfItems());
  }

  function test_civimailchimp_civicrm_contact_removed_from_group() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civimailchimp_civicrm_contact_removed_from_group', $mailchimp_list_id);
    $group = CRM_CiviMailchimp_Utils::getGroupById($mailchimp_sync_setting->civicrm_group_id);
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_contact_removed_from_group($group, $contact);
    $item = $queue->claimItem();
    $this->assertEquals('unsubscribeContactFromMailchimpList', $item->data->arguments[0]);
    $this->assertEquals($mailchimp_list_id, $item->data->arguments[1]);
    $this->assertEquals($params['email'][0]['email'], $item->data->arguments[2]);
  }

  function test_civimailchimp_civicrm_contact_removed_from_group_no_sync_settings() {
    $group_name = 'Test Group test_contact_removed_from_group_no_sync_settings';
    $group_id = $this->groupCreate(array('name' => $group_name, 'title' => $group_name));
    $group = CRM_CiviMailchimp_Utils::getGroupById($group_id);
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql', 
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_contact_removed_from_group($group, $contact);
    $this->assertEquals(0, $queue->numberOfItems());
  }

  function test_civimailchimp_civicrm_contact_updated_email_changed() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_contact_removed_from_group_email_changed', $mailchimp_list_id);
    $old_contact_params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $new_contact_params = $old_contact_params;
    $old_contact_created = CRM_Contact_BAO_Contact::create($old_contact_params);
    $old_contact = CRM_CiviMailchimp_Utils::getContactById($old_contact_created->id);
    $contact_ids = array($old_contact->id);
    CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $mailchimp_sync_setting->civicrm_group_id);
    $new_contact_params['contact_id'] = $old_contact->id;
    $new_contact_params['email'][0]['email'] = "updated_{$old_contact_params['email'][0]['email']}";
    $new_contact_created = CRM_Contact_BAO_Contact::create($new_contact_params);
    $new_contact = CRM_CiviMailchimp_Utils::getContactById($new_contact_created->id);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_contact_updated($old_contact, $new_contact);
    $item = $queue->claimItem();
    $this->assertEquals('updateContactProfileInMailchimp', $item->data->arguments[0]);
    $this->assertEquals($mailchimp_list_id, $item->data->arguments[1]);
    $this->assertEquals($old_contact_params['email'][0]['email'], $item->data->arguments[2]);
    $this->assertEquals($new_contact_params['first_name'], $item->data->arguments[3]['FNAME']);
    $this->assertEquals($new_contact_params['last_name'], $item->data->arguments[3]['LNAME']);
    $this->assertEquals($new_contact_params['email'][0]['email'], $item->data->arguments[3]['new-email']);
  }

  function test_civimailchimp_civicrm_contact_updated_name_changed() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_contact_removed_from_group_name_changed', $mailchimp_list_id);
    $old_contact_params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $new_contact_params = $old_contact_params;
    $old_contact_created = CRM_Contact_BAO_Contact::create($old_contact_params);
    $old_contact = CRM_CiviMailchimp_Utils::getContactById($old_contact_created->id);
    $contact_ids = array($old_contact->id,);
    CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $mailchimp_sync_setting->civicrm_group_id);
    $new_contact_params['contact_id'] = $old_contact->id;
    $new_contact_params['first_name'] = 'NewFirstName';
    $new_contact_created = CRM_Contact_BAO_Contact::create($new_contact_params);
    $new_contact = CRM_CiviMailchimp_Utils::getContactById($new_contact_created->id);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_contact_updated($old_contact, $new_contact);
    $item = $queue->claimItem();
    $this->assertEquals('updateContactProfileInMailchimp', $item->data->arguments[0]);
    $this->assertEquals($mailchimp_list_id, $item->data->arguments[1]);
    $this->assertEquals($old_contact_params['email'][0]['email'], $item->data->arguments[2]);
    $this->assertEquals($new_contact_params['first_name'], $item->data->arguments[3]['FNAME']);
    $this->assertEquals($new_contact_params['last_name'], $item->data->arguments[3]['LNAME']);
  }

  function test_civimailchimp_civicrm_contact_updated_nothing_changed() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_contact_removed_from_group_nothing_changed', $mailchimp_list_id);
    $old_contact_params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $new_contact_params = $old_contact_params;
    $old_contact_created = CRM_Contact_BAO_Contact::create($old_contact_params);
    $old_contact = CRM_CiviMailchimp_Utils::getContactById($old_contact_created->id);
    $contact_ids = array($old_contact->id,);
    CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $mailchimp_sync_setting->civicrm_group_id);
    $new_contact_params['contact_id'] = $old_contact->id;
    $new_contact_created = CRM_Contact_BAO_Contact::create($new_contact_params);
    $new_contact = CRM_CiviMailchimp_Utils::getContactById($new_contact_created->id);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_contact_updated($old_contact, $new_contact);
    $this->assertEquals(0, $queue->numberOfItems());
  }

  function test_civimailchimp_civicrm_contact_updated_no_sync_settings() {
    $old_contact_params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $new_contact_params = $old_contact_params;
    $old_contact_created = CRM_Contact_BAO_Contact::create($old_contact_params);
    $old_contact = CRM_CiviMailchimp_Utils::getContactById($old_contact_created->id);
    $new_contact_params['contact_id'] = $old_contact->id;
    $new_contact_params['first_name'] = 'NewFirstName';
    $new_contact_created = CRM_Contact_BAO_Contact::create($new_contact_params);
    $new_contact = CRM_CiviMailchimp_Utils::getContactById($new_contact_created->id);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_contact_updated($old_contact, $new_contact);
    $this->assertEquals(0, $queue->numberOfItems());
  }

  function test_civimailchimp_civicrm_buildForm() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civimailchimp_civicrm_buildForm', $mailchimp_list_id, $mailchimp_interest_groups);
    $formName = 'CRM_Group_Form_Edit';
    $form = $this->getMock('CRM_Core_Form');
    $form->expects($this->exactly(2))
      ->method('add')
      ->will($this->returnValue(TRUE));
    $form->expects($this->once())
      ->method('getVar')
      ->will($this->returnValue($mailchimp_sync_setting->civicrm_group_id));
    $form->expects($this->once())
      ->method('setDefaults')
      ->will($this->returnValue(TRUE));
    civimailchimp_civicrm_buildForm($formName, $form);
  }

  function test_civimailchimp_civicrm_buildForm_no_sync_settings() {
    $group_name = 'Test Group test_civimailchimp_civicrm_buildForm_no_sync_settings';
    $group_id = $this->groupCreate(array('name' => $group_name, 'title' => $group_name));
    $formName = 'CRM_Group_Form_Edit';
    $form = $this->getMock('CRM_Core_Form');
    $form->expects($this->exactly(2))
      ->method('add')
      ->will($this->returnValue(TRUE));
    $form->expects($this->once())
      ->method('getVar')
      ->will($this->returnValue($group_id));
    $form->expects($this->never())
      ->method('setDefaults')
      ->will($this->returnValue(TRUE));
    civimailchimp_civicrm_buildForm($formName, $form);
  }

  function test_civimailchimp_civicrm_buildForm_wrong_formName() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civimailchimp_civicrm_buildForm_wrong_formName', $mailchimp_list_id, $mailchimp_interest_groups);
    $formName = 'CRM_Group_Form_Edits';
    $form = $this->getMock('CRM_Core_Form');
    $form->expects($this->never())
      ->method('add')
      ->will($this->returnValue(TRUE));
    $form->expects($this->never())
      ->method('getVar')
      ->will($this->returnValue($mailchimp_sync_setting->civicrm_group_id));
    $form->expects($this->never())
      ->method('setDefaults')
      ->will($this->returnValue(TRUE));
    civimailchimp_civicrm_buildForm($formName, $form);
  }

  function test_civimailchimp_civicrm_setDefaults() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civimailchimp_civicrm_setDefaults', $mailchimp_list_id, $mailchimp_interest_groups);
    $form = $this->getMock('CRM_Core_Form');
    $form->expects($this->once())
      ->method('setDefaults')
      ->will($this->returnValue(TRUE));
    civimailchimp_civicrm_setDefaults($form, $mailchimp_sync_setting);
  }

  function test_civimailchimp_get_default_sync_settings_for_group() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group get_default_sync_settings_for_group', $mailchimp_list_id, $mailchimp_interest_groups);
     $mailchimp_sync_setting_with_interest_groups = CRM_CiviMailchimp_BAO_SyncSettings::findByGroupId($mailchimp_sync_setting->civicrm_group_id);
    $expected_defaults = array(
      'mailchimp_list' => $mailchimp_list_id,
      'mailchimp_interest_groups' => $mailchimp_interest_groups,
    );
    $defaults = civimailchimp_get_default_sync_settings_for_group($mailchimp_sync_setting_with_interest_groups);
    $this->assertEquals($expected_defaults, $defaults);
  }

  function test_civimailchimp_get_default_sync_settings_for_group_no_interest_groups() {
    $mailchimp_list_id = 'MailchimpListsTestListB';
    $mailchimp_interest_groups = array();
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group sync_settings_for_group_no_interest_groups', $mailchimp_list_id);
     $mailchimp_sync_setting_with_interest_groups = CRM_CiviMailchimp_BAO_SyncSettings::findByGroupId($mailchimp_sync_setting->civicrm_group_id);
    $expected_defaults = array(
      'mailchimp_list' => $mailchimp_list_id,
      'mailchimp_interest_groups' => $mailchimp_interest_groups,
    );
    $defaults = civimailchimp_get_default_sync_settings_for_group($mailchimp_sync_setting_with_interest_groups);
    $this->assertEquals($expected_defaults, $defaults);
  }

  function test_civimailchimp_civicrm_validateForm_same_group() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civimailchimp_civicrm_validateForm', $mailchimp_list_id, $mailchimp_interest_groups);
    $formName = 'CRM_Group_Form_Edit';
    $fields['mailchimp_list'] = $mailchimp_list_id;
    $files = array();
    $form = $this->getMock('CRM_Core_Form');
    $form->expects($this->once())
      ->method('getVar')
      ->will($this->returnValue($mailchimp_sync_setting->civicrm_group_id));
    $errors = array();
    civimailchimp_civicrm_validateForm($formName, $fields, $files, $form, $errors);
    $this->assertEmpty($errors);
  }

  function test_civimailchimp_civicrm_validateForm_no_sync_settings() {
    $mailchimp_list_id = 'MailchimpListsTestListB';
    $formName = 'CRM_Group_Form_Edit';
    $fields['mailchimp_list'] = $mailchimp_list_id;
    $files = array();
    $form = $this->getMock('CRM_Core_Form');
    $form->expects($this->never())
      ->method('getVar')
      ->will($this->returnValue(TRUE));
    $errors = array();
    civimailchimp_civicrm_validateForm($formName, $fields, $files, $form, $errors);
    $this->assertEmpty($errors);
  }

  function test_civimailchimp_civicrm_validateForm_different_group() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civimailchimp_civicrm_validateForm_different_group', $mailchimp_list_id, $mailchimp_interest_groups);
    $formName = 'CRM_Group_Form_Edit';
    $fields['mailchimp_list'] = $mailchimp_list_id;
    $files = array();
    $form = $this->getMock('CRM_Core_Form');
    $form->expects($this->once())
      ->method('getVar')
      ->will($this->returnValue(999999999));
    $errors = array();
    civimailchimp_civicrm_validateForm($formName, $fields, $files, $form, $errors);
    $this->assertEquals("Another CiviCRM Group is already configured to sync to this Mailchimp list. Please select another list.", $errors['mailchimp_list']);
  }

  function test_civimailchimp_civicrm_validateForm_different_formName() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group civicrm_validateForm_different_formName', $mailchimp_list_id, $mailchimp_interest_groups);
    $formName = 'CRM_Group_Form_Edits';
    $fields['mailchimp_list'] = $mailchimp_list_id;
    $files = array();
    $form = $this->getMock('CRM_Core_Form');
    $form->expects($this->never())
      ->method('getVar')
      ->will($this->returnValue(TRUE));
    $errors = array();
    civimailchimp_civicrm_validateForm($formName, $fields, $files, $form, $errors);
    $this->assertEmpty($errors);
  }

  function test_civimailchimp_civicrm_postProcess() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $group_name = 'Test Group civicrm_postProcess';
    $group_id = $this->groupCreate(array('name' => $group_name, 'title' => $group_name));
    $formName = 'CRM_Group_Form_Edit';
    $form = $this->getMock('CRM_Core_Form');
    $form->expects($this->once())
      ->method('getVar')
      ->will($this->returnValue($group_id));
    $form->expects($this->never())
      ->method('get')
      ->will($this->returnValue($group_id));
    $form->_elementIndex['mailchimp_list'] = TRUE;
    $form->_submitValues = array(
      'mailchimp_list' => $mailchimp_list_id,
      'mailchimp_interest_groups' => $mailchimp_interest_groups,
    );
    civimailchimp_civicrm_postProcess($formName, $form);
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettings::findByGroupId($group_id);
    $this->assertEquals($group_id, $mailchimp_sync_setting->civicrm_group_id);
    $this->assertEquals('MailchimpListsTestListA', $mailchimp_sync_setting->mailchimp_list_id);
    $this->assertArrayHasKey('MailchimpTestInterestGroupingA', $mailchimp_sync_setting->mailchimp_interest_groups);
    $this->assertEquals('MailchimpTestInterestGroupA', $mailchimp_sync_setting->mailchimp_interest_groups['MailchimpTestInterestGroupingA'][0]->mailchimp_interest_group_id);
    $this->assertEquals('MailchimpTestInterestGroupC', $mailchimp_sync_setting->mailchimp_interest_groups['MailchimpTestInterestGroupingA'][1]->mailchimp_interest_group_id);
  }

  function test_civimailchimp_civicrm_postProcess_different_formName() {
    $formName = 'CRM_Group_Form_Edits';
    $form = $this->getMock('CRM_Core_Form');
    $form->expects($this->never())
      ->method('getVar')
      ->will($this->returnValue(TRUE));
    $form->expects($this->never())
      ->method('get')
      ->will($this->returnValue(TRUE));
    $form->_elementIndex['mailchimp_list'] = TRUE;
    civimailchimp_civicrm_postProcess($formName, $form);
  }

  function test_civimailchimp_civicrm_postProcess_element_not_set() {
    $formName = 'CRM_Group_Form_Edit';
    $form = $this->getMock('CRM_Core_Form');
    $form->expects($this->never())
      ->method('getVar')
      ->will($this->returnValue(TRUE));
    $form->expects($this->never())
      ->method('get')
      ->will($this->returnValue(TRUE));
    civimailchimp_civicrm_postProcess($formName, $form);
  }

  function test_civimailchimp_civicrm_postProcess_id_not_set() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $group_name = 'Test Group CRM_Group_Form_Edit_id_not_set';
    $group_id = $this->groupCreate(array('name' => $group_name, 'title' => $group_name));
    $formName = 'CRM_Group_Form_Edit';
    $form = $this->getMock('CRM_Core_Form');
    $form->expects($this->once())
      ->method('getVar')
      ->will($this->returnValue(NULL));
    $form->expects($this->once())
      ->method('get')
      ->will($this->returnValue(TRUE));
    $form->_elementIndex['mailchimp_list'] = TRUE;
    $form->_submitValues = array(
      'mailchimp_list' => $mailchimp_list_id,
      'mailchimp_interest_groups' => $mailchimp_interest_groups,
    );
    civimailchimp_civicrm_postProcess($formName, $form);
  }

  function test_civimailchimp_civicrm_postProcess_no_interest_groups() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $group_name = 'Test Group postProcess_CRM_Group_Form_Edit_no_interest_groups';
    $group_id = $this->groupCreate(array('name' => $group_name, 'title' => $group_name));
    $formName = 'CRM_Group_Form_Edit';
    $form = $this->getMock('CRM_Core_Form');
    $form->expects($this->once())
      ->method('getVar')
      ->will($this->returnValue($group_id));
    $form->expects($this->never())
      ->method('get')
      ->will($this->returnValue($group_id));
    $form->_elementIndex['mailchimp_list'] = TRUE;
    $form->_submitValues = array(
      'mailchimp_list' => $mailchimp_list_id,
    );
    civimailchimp_civicrm_postProcess($formName, $form);
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettings::findByGroupId($group_id);
    $this->assertEquals($group_id, $mailchimp_sync_setting->civicrm_group_id);
    $this->assertEquals('MailchimpListsTestListA', $mailchimp_sync_setting->mailchimp_list_id);
    $this->assertEmpty($mailchimp_sync_setting->mailchimp_interest_groups);
  }

  function test_civimailchimp_civicrm_postProcess_no_list() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group postProcess_CRM_Group_Form_Edit_no_list', $mailchimp_list_id, $mailchimp_interest_groups);
    $formName = 'CRM_Group_Form_Edit';
    $form = $this->getMock('CRM_Core_Form');
    $form->expects($this->once())
      ->method('getVar')
      ->will($this->returnValue($mailchimp_sync_setting->civicrm_group_id));
    $form->expects($this->never())
      ->method('get')
      ->will($this->returnValue(TRUE));
    $form->_elementIndex['mailchimp_list'] = TRUE;
    $form->_submitValues = array(
      'mailchimp_list' => NULL,
    );
    civimailchimp_civicrm_postProcess($formName, $form);
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettings::findByGroupId($mailchimp_sync_setting->civicrm_group_id);
    $this->assertEmpty($mailchimp_sync_setting);
  }

  function test_civimailchimp_catch_mailchimp_api_error() {
    $exception = new CRM_Core_Exception("An exception was thrown!");
    $session = CRM_Core_Session::singleton();
    $messages = $session->getStatus(TRUE);
    civimailchimp_catch_mailchimp_api_error($exception);
    $messages = $session->getStatus();
    $this->assertEquals('There was an error when trying to retrieve available Mailchimp Lists to sync to a group. CRM_Core_Exception: An exception was thrown!.', $messages[0]['text']);
    $this->assertEquals('Mailchimp API Error', $messages[0]['title']);
    $this->assertEquals('alert', $messages[0]['type']);
    $this->assertEquals(0, $messages[0]['options']['expires']);
  }

  function test_civimailchimp_civicrm_pre_GroupContact_create_not_in_group() {
    $group_name = 'Test Group test_civimailchimp_civicrm_pre_GroupContact_create';
    $group_id = $this->groupCreate(array('name' => $group_name, 'title' => $group_name));
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $contact_ids = array($contact->id);
    civimailchimp_civicrm_pre_GroupContact_create($group_id, $contact_ids);
    $contacts_added_to_group = civimailchimp_static('contacts_added_to_group');
    $this->assertEquals($contact_ids, $contacts_added_to_group);
  }

  function test_civimailchimp_civicrm_pre_GroupContact_create_in_group() {
    $group_name = 'Test Group civicrm_pre_GroupContact_create_in_group';
    $group_id = $this->groupCreate(array('name' => $group_name, 'title' => $group_name));
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $contact_ids = array($contact->id);
    CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $group_id);
    civimailchimp_civicrm_pre_GroupContact_create($group_id, $contact_ids);
    $contacts_added_to_group = civimailchimp_static('contacts_added_to_group');
    $this->assertEmpty($contacts_added_to_group);
  }

  function test_civimailchimp_civicrm_post_GroupContact_create() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civimailchimp_civicrm_post_GroupContact_create', $mailchimp_list_id, $mailchimp_interest_groups);
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $contact_ids = array();
    civimailchimp_static('contacts_added_to_group', array($contact->id));
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_post_GroupContact_create($mailchimp_sync_setting->civicrm_group_id, $contact_ids);
    $item = $queue->claimItem();
    $this->assertEquals('subscribeContactToMailchimpList', $item->data->arguments[0]);
    $this->assertEquals($mailchimp_list_id, $item->data->arguments[1]);
    $this->assertEquals($params['email'][0]['email'], $item->data->arguments[2]);
    $this->assertEquals($params['first_name'], $item->data->arguments[3]['FNAME']);
    $this->assertEquals($params['last_name'], $item->data->arguments[3]['LNAME']);
  }

  function test_civimailchimp_civicrm_post_GroupContact_create_no_contacts() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civimailchimp_civicrm_post_GroupContact_create_no_contacts', $mailchimp_list_id, $mailchimp_interest_groups);
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $contact_ids = array();
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_post_GroupContact_create($mailchimp_sync_setting->civicrm_group_id, $contact_ids);
    $this->assertEquals(0, $queue->numberOfItems());
  }

  function test_civimailchimp_civicrm_post_GroupContact_delete() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civimailchimp_civicrm_post_GroupContact_delete', $mailchimp_list_id);
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $contact_ids = array($contact->id);
    CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $mailchimp_sync_setting->civicrm_group_id);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_post_GroupContact_delete($mailchimp_sync_setting->civicrm_group_id, $contact_ids);
    $item = $queue->claimItem();
    $this->assertEquals('unsubscribeContactFromMailchimpList', $item->data->arguments[0]);
    $this->assertEquals($mailchimp_list_id, $item->data->arguments[1]);
    $this->assertEquals($params['email'][0]['email'], $item->data->arguments[2]);
  }

  function test_civimailchimp_civicrm_pre_Email() {
    $op = 'edit';
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact_created = CRM_Contact_BAO_Contact::create($params);
    $contact = CRM_CiviMailchimp_Utils::getContactById($contact_created->id);
    $email_id = $contact->email[0]->id;
    $email['contact_id'] = $contact->id;
    civimailchimp_static('mailchimp_static_reset', NULL, TRUE);
    civimailchimp_civicrm_pre_Email($op, $email_id, $email);
    $old_contact_from_email = civimailchimp_static('old_contact_from_email');
    $this->assertEquals($contact->id, $old_contact_from_email->id);
  }

  function test_civimailchimp_civicrm_pre_Email_no_contact_id() {
    $op = 'edit';
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact_created = CRM_Contact_BAO_Contact::create($params);
    $contact = CRM_CiviMailchimp_Utils::getContactById($contact_created->id);
    $email_id = $contact->email[0]->id;
    $email['contact_id'] = NULL;
    civimailchimp_static('mailchimp_static_reset', NULL, TRUE);
    civimailchimp_civicrm_pre_Email($op, $email_id, $email);
    $old_contact_from_email = civimailchimp_static('old_contact_from_email');
    $this->assertEquals($contact->id, $old_contact_from_email->id);
  }

  function test_civimailchimp_civicrm_pre_Email_old_contact() {
    $op = 'edit';
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact_created = CRM_Contact_BAO_Contact::create($params);
    $contact = CRM_CiviMailchimp_Utils::getContactById($contact_created->id);
    $email_id = $contact->email[0]->id;
    $email['contact_id'] = $contact->id;
    civimailchimp_static('mailchimp_static_reset', NULL, TRUE);
    civimailchimp_static('old_contact', $contact);
    civimailchimp_civicrm_pre_Email($op, $email_id, $email);
    $old_contact_from_email = civimailchimp_static('old_contact_from_email');
    $this->assertEmpty($old_contact_from_email);
  }

  function test_civimailchimp_civicrm_post_Email() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civimailchimp_civicrm_post_Email', $mailchimp_list_id);
    $op = 'edit';
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact_created = CRM_Contact_BAO_Contact::create($params);
    $contact = CRM_CiviMailchimp_Utils::getContactById($contact_created->id);
    $contact->email[0]->email = "old_{$params['email'][0]['email']}";
    $contact_ids = array($contact->id);
    CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $mailchimp_sync_setting->civicrm_group_id);
    $email_id = $contact->email[0]->id;
    $email = array();
    civimailchimp_static('mailchimp_static_reset', NULL, TRUE);
    civimailchimp_static('old_contact_from_email', $contact);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_post_Email($op, $email_id, $email);
    $item = $queue->claimItem();
    $this->assertEquals('updateContactProfileInMailchimp', $item->data->arguments[0]);
    $this->assertEquals($mailchimp_list_id, $item->data->arguments[1]);
    $this->assertEquals($contact->email[0]->email, $item->data->arguments[2]);
    $this->assertEquals($params['email'][0]['email'], $item->data->arguments[3]['new-email']);
  }

  function test_civimailchimp_civicrm_post_Email_no_old_contact() {
    $op = 'edit';
    $email_id = NULL;
    $email = array();
    civimailchimp_static('mailchimp_static_reset', NULL, TRUE);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_post_Email($op, $email_id, $email);
    $this->assertEquals(0, $queue->numberOfItems());
  }

  function test_civimailchimp_civicrm_pre_Contact_edit() {
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    civimailchimp_civicrm_pre_Contact_edit($contact->id, $contact);
    $old_contact = civimailchimp_static('old_contact');
    $this->assertEquals($contact->id, $old_contact->id);
  }

  function test_civimailchimp_civicrm_post_Contact_edit() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civimailchimp_civicrm_post_Contact_edit', $mailchimp_list_id);
    $old_contact_params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $old_contact_created = CRM_Contact_BAO_Contact::create($old_contact_params);
    $old_contact = CRM_CiviMailchimp_Utils::getContactById($old_contact_created->id);
    $new_contact_params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $new_contact_params['contact_id'] = $old_contact->id;
    $new_contact_created = CRM_Contact_BAO_Contact::create($new_contact_params);
    $new_contact = CRM_CiviMailchimp_Utils::getContactById($new_contact_created->id);
    $contact_ids = array($old_contact->id);
    CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $mailchimp_sync_setting->civicrm_group_id);
    civimailchimp_static('mailchimp_static_reset', NULL, TRUE);
    civimailchimp_static('old_contact', $old_contact);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_post_Contact_edit($new_contact->id, $new_contact);
    $item = $queue->claimItem();
    $this->assertEquals('updateContactProfileInMailchimp', $item->data->arguments[0]);
    $this->assertEquals($mailchimp_list_id, $item->data->arguments[1]);
    $this->assertEquals($old_contact->email[0]->email, $item->data->arguments[2]);
    $this->assertEquals($new_contact->email[0]->email, $item->data->arguments[3]['new-email']);
  }

  function test_civimailchimp_civicrm_post_Contact_delete() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civimailchimp_civicrm_post_Contact_delete', $mailchimp_list_id);
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $contact_ids = array($contact->id);
    CRM_Contact_BAO_GroupContact::addContactsToGroup($contact_ids, $mailchimp_sync_setting->civicrm_group_id);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => TRUE,
    ));
    civimailchimp_civicrm_post_Contact_delete($contact->id, $contact);
    $item = $queue->claimItem();
    $this->assertEquals('unsubscribeContactFromMailchimpList', $item->data->arguments[0]);
    $this->assertEquals($mailchimp_list_id, $item->data->arguments[1]);
    $this->assertEquals($params['email'][0]['email'], $item->data->arguments[2]);
  }

  function test_civimailchimp_civicrm_pre_Group_delete() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civimailchimp_civicrm_pre_Group_delete', $mailchimp_list_id, $mailchimp_interest_groups);
    $group = array();
    civimailchimp_static('mailchimp_static_reset', NULL, TRUE);
    civimailchimp_civicrm_pre_Group_delete($mailchimp_sync_setting->civicrm_group_id, $group);
    $static_mailchimp_sync_setting = civimailchimp_static('mailchimp_sync_settings');
    $this->assertEquals($mailchimp_sync_setting->id, $static_mailchimp_sync_setting->id);
    $this->assertEquals($mailchimp_sync_setting->civicrm_group_id, $static_mailchimp_sync_setting->civicrm_group_id);
    $this->assertEquals($mailchimp_sync_setting->mailchimp_list_id, $static_mailchimp_sync_setting->mailchimp_list_id);
  }

  function test_civimailchimp_civicrm_pre_Group_delete_no_sync_settings() {
    $group_name = 'Test Group test_civimailchimp_civicrm_pre_Group_delete_no_sync';
    $group_id = $this->groupCreate(array('name' => $group_name, 'title' => $group_name));
    $group = array();
    civimailchimp_static('mailchimp_static_reset', NULL, TRUE);
    civimailchimp_civicrm_pre_Group_delete($group_id, $group);
    $static_mailchimp_sync_setting = civimailchimp_static('mailchimp_sync_settings');
    $this->assertEmpty($static_mailchimp_sync_setting);
  }

  function test_civimailchimp_civicrm_post_Group_delete() {
    $this->markTestIncomplete('This test is not finished yet.');
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civimailchimp_civicrm_post_Group_delete', $mailchimp_list_id, $mailchimp_interest_groups);
    civimailchimp_static('mailchimp_static_reset', NULL, TRUE);
    civimailchimp_static('mailchimp_sync_settings', $mailchimp_sync_setting);
    $group = array();
    $mailchimp = new CRM_MailchimpMock();
    $lists = $this->getMockBuilder('CRM_MailchimpMock_ListsMock')
      ->setConstructorArgs(array($mailchimp))
      ->getMock();
    $lists->expects($this->once())
      ->method('webhookDel')
      ->will($this->returnValue(TRUE));
    civimailchimp_civicrm_post_Group_delete($mailchimp_sync_setting->civicrm_group_id, $group);
  }

  function test_civicrm_api3_civi_mailchimp_sync_exception() {
    $mailchimp_list_id = 'MailchimpListsTestListA';
    $mailchimp_interest_groups = array(
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupA',
      'MailchimpTestInterestGroupingA_MailchimpTestInterestGroupC',
    );
    $mailchimp_sync_setting = CRM_CiviMailchimp_BAO_SyncSettingsTest::createTestGroupAndSyncSettings('Test Group test_civicrm_api3_civi_mailchimp_sync_exception', $mailchimp_list_id, $mailchimp_interest_groups);
    $merge_fields = CRM_CiviMailchimp_Utils::getMailchimpMergeFields();
    $params = CRM_CiviMailchimp_UtilsTest::sampleContactParams();
    $contact = CRM_Contact_BAO_Contact::create($params);
    $merge_vars = CRM_CiviMailchimp_Utils::formatMailchimpMergeVars($merge_fields, $contact);
    CRM_CiviMailchimp_Utils::addMailchimpSyncQueueItem('subscribeContactToMailchimpList', 'MailchimpListsTestListB', $params['email'][0]['email'], $merge_vars);
    $action = 'unsubscribeContactFromMailchimpList';
    CRM_CiviMailchimp_Utils::addMailchimpSyncQueueItem('unsubscribeContactFromMailchimpList', 'MailchimpListsTestListB', $params['email'][0]['email']);
    $job_params['records_to_process_per_run'] = 100;
    civicrm_api3_civi_mailchimp_sync($job_params);
    $queue = CRM_Queue_Service::singleton()->create(array(
      'type' => 'Sql',
      'name' => 'mailchimp-sync',
      'reset' => FALSE,
    ));
    $this->assertEquals(2, $queue->numberOfItems());
  }
}
