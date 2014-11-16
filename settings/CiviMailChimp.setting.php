<?php

/*
 * Settings metadata file
 */
return array (
  'mailchimp_api_key' => array(
    'group_name' => 'CiviMailchimp Preferences',
    'group' => 'civimailchimp',
    'name' => 'mailchimp_api_key',
    'type' => 'String',
    'add' => '4.2',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => 'Mailchimp API Key',
    'description' => 'The API Key for communicating with Mailchimp.',
    'help_text' => '',
    'html_type' => 'Text',
    'html_attributes' => array(
      'size' => 50,
    ),
    'quick_form_type' => 'Element',
  ),
  'mailchimp_merge_fields' => array(
    'group_name' => 'CiviMailchimp Preferences',
    'group' => 'civimailchimp',
    'name' => 'mailchimp_merge_fields',
    'type' => 'Array',
    'add' => '4.2',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => 'Mailchimp Merge Fields',
    'description' => 'Custom merge fields declaration for Mailchimp lists.'
  ),
  'mailchimp_webhook_base_url' => array(
    'group_name' => 'CiviMailchimp Preferences',
    'group' => 'civimailchimp',
    'name' => 'mailchimp_webhook_base_url',
    'type' => 'String',
    'add' => '4.2',
    'is_domain' => 1,
    'is_contact' => 0,
    'title' => 'Mailchimp Webhook Base URL',
    'description' => 'A value entered here will override the default, which is the base url of this CiviCRM installation.',
    'help_text' => '',
    'html_type' => 'Text',
    'html_attributes' => array(
      'size' => 50,
    ),
    'quick_form_type' => 'Element',
  ),
);
