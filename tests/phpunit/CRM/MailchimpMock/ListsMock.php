<?php
/**
* @file
* A virtual MailChimp Lists API implementation for use in testing.
*/

class CRM_MailchimpMock_ListsMock {

  /** @var string $errorMessage */
  public $errorMessage;

  public function __construct(CRM_MailchimpMock $master) {
  }

  /**
   * @see Mailchimp_Lists::memberInfo()
   */
  public function memberInfo($id, $emails) {
    $lists = $this->loadLists();

    $response = array(
      'success_count' => 0,
      'error_count' => 0,
      'data' => array(),
    );
    foreach ($lists as $list_data) {
      if (isset($list_data['data']['members'])) {
        $response['success_count'] += count($list_data['data']['members']);
        foreach ($emails as $email) {
          $email_address = $email['email'];
          if (isset($list_data['data']['members'][$email_address])) {
            $response['data'][] = $list_data['data']['members'][$email_address];
          }
        }
      }
    }
    return $response;
  }

  /**
   * @see Mailchimp_Lists::mergeVars()
   */
  public function mergeVars($id) {
    $lists = $this->loadLists();

    $response = array(
      'success_count' => 1,
      'error_count' => 0,
      'data' => array(),
    );
    foreach ($lists as $list_id => $list_data) {
      if (in_array($list_id, $id)) {
        $response['data'][] = array(
          'id' => $list_id,
          'name' => $list_data['name'],
          'merge_vars' => $list_data['merge_vars'],
        );
      }
    }
    return $response;
  }

  /**
   * @see Mailchimp_Lists::subscribe()
   */
  public function subscribe($id, $email, $merge_vars=null, $email_type='html', $double_optin=true, $update_existing=false, $replace_interests=true, $send_welcome=false) {
    $email_address = $email['email'];
    $lists = $this->loadLists();
    if (isset($lists[$id])) {
      $response = array(
        'email' => $email_address,
      );

      return $response;
    }
    else {
      throw new CRM_Core_Exception('The Mailchimp List ID does not exist.');
    }
  }

  /**
   * @see Mailchimp_Lists::unsubscribe()
   */
  public function unsubscribe($id, $email, $delete_member=false, $send_goodbye=true, $send_notify=true) {
    $lists = $this->loadLists();
    if (isset($lists[$id])) {
      $response = array(
        'complete' => true,
      );

      return $response;
    }
    else {
      throw new CRM_Core_Exception('The Mailchimp List ID does not exist.');
    }
  }

  /**
   * @see Mailchimp_Lists::updateMember()
   */
  public function updateMember($id, $email, $merge_vars, $email_type='', $replace_interests=TRUE) {
    $email_address = $email['email'];
    $lists = $this->loadLists();
    $response = array();
    if (isset($lists[$id])) {
      $response['email'] = $email_address;

      return $response;
    }
    else {
      throw new CRM_Core_Exception('The Mailchimp List ID does not exist.');
    }
  }

  /**
   * @see Mailchimp_Lists::webhookAdd()
   */
  public function webhookAdd($id, $url, $actions=array(), $sources=array()) {
    $webhooks = $this->defaultWebhooks();
    if (isset($webhooks[$id])) {
      $response = array(
        'id' => 'MailchimpTestWebhookA',
      );
    }
    else {
      $response = array(
        'status' => 'error',
        'code' => 200,
        'name' => 'List_DoesNotExist',
      );
    }
    return $response;
  }

  /**
   * @see Mailchimp_Lists::webhookDel()
   */
  public function webhookDel($id, $url) {
    $webhooks = $this->defaultWebhooks();
    if (isset($webhooks[$id])) {
      if ($url === $webhooks[$id]) {
        $response = array(
          'complete' => true,
        );
      }
      else {
        $response = array(
          'status' => 'error',
          'code' => 200,
          'name' => 'Invalid_URL',
        );
      }
    }
    else {
      $response = array(
        'status' => 'error',
        'code' => 200,
        'name' => 'List_DoesNotExist',
      );
    }
    civimailchimp_static('mailchimp_api_webhookDel', TRUE);
    return $response;
  }

  /**
   * @see Mailchimp_Lists::getList()
   */
  public function getList($filters=array(), $start=0, $limit=25, $sort_field='created', $sort_dir='DESC') {
    $lists = $this->loadLists();

    $response = array(
      'data' => array(),
      'total' => 0,
    );

    foreach ($lists as $list_id => $list_data) {
      $list_data['id'] = $list_id;
      $response['data'][] = $list_data;
      $response['total']++;
    }

    return $response;
  }

  /**
   * @see Mailchimp_Lists::interestGroupings()
   */
  public function interestGroupings($list_id, $counts=false) {
    $interest_groups = $this->defaultInterestGroups();
    $list_interest_groups = array();
    if (array_key_exists($list_id, $interest_groups)) {
      $list_interest_groups = $interest_groups[$list_id];
    }

    return $list_interest_groups;
  }

  /**
   * Loads list values, initializing if necessary.
   *
   * @return array
   *   Stored lists.
   */
  protected function loadLists() {
    $list_data = $this->defaultLists();

    return $list_data;
  }

  /**
   * Creates initial list values.
   *
   * @return array
   *   Basic lists.
   */
  protected function defaultLists() {
    $default_mergevars = array(
      array(
        'name' => 'Email',
        'order' => 0,
        'tag' => 'EMAIL',
        'req' => TRUE,
        'web_id' => 'test',
        'field_type' => 'text',
        'size' => 40,
        'default' => '',
        'public' => TRUE,
      ),
      array(
        'name' => 'First Name',
        'order' => 1,
        'tag' => 'FNAME',
        'req' => FALSE,
        'web_id' => 'test',
        'field_type' => 'text',
        'size' => 40,
        'default' => '',
        'public' => TRUE,
      ),
      array(
        'name' => 'Last Name',
        'order' => 2,
        'tag' => 'LNAME',
        'req' => FALSE,
        'web_id' => 'test',
        'field_type' => 'text',
        'size' => 40,
        'default' => '',
        'public' => TRUE,
      ),
    );
    $lists = array(
      'MailchimpListsTestListA' => array(
        'name' => 'Test List A',
        'data' => array(),
        'merge_vars' => $default_mergevars,
        'stats' => array(
          'group_count' => 3,
        ),
      ),
      'MailchimpListsTestListB' => array(
        'name' => 'Test List B',
        'data' => array(),
        'merge_vars' => $default_mergevars,
        'stats' => array(
          'group_count' => 0,
        ),
      ),
      'MailchimpListsTestListC' => array(
        'name' => 'Test List C',
        'data' => array(),
        'merge_vars' => $default_mergevars,
        'stats' => array(
          'group_count' => 0,
        ),
      ),
    );
    return $lists;
  }

  /**
   * Creates initial interest group values.
   *
   * @return array
   *   Basic interest groups.
   */
  protected function defaultInterestGroups() {
    $interest_groups = array(
      'MailchimpListsTestListA' => array(
        array(
          'id' => 'MailchimpTestInterestGroupingA',
          'name' => 'Test Interest Grouping A',
          'form_field' => 'checkboxes',
          'groups' => array(
            array(
              'id' => 'MailchimpTestInterestGroupA',
              'name' => 'Test Interest Group A',
            ),
            array(
              'id' => 'MailchimpTestInterestGroupB',
              'name' => 'Test Interest Group B',
            ),
            array(
              'id' => 'MailchimpTestInterestGroupC',
              'name' => 'Test Interest Group C',
            ),
          ),
        ),
      ),
    );

    return $interest_groups;
  }

  /**
   * Create initial list of webhooks.
   */
  protected function defaultWebhooks() {
    $webhook_url = CRM_CiviMailchimp_Utils::formatMailchimpWebhookUrl();
    $webhooks = array(
      'MailchimpListsTestListA' => $webhook_url,
    );

    return $webhooks;
  }
}
