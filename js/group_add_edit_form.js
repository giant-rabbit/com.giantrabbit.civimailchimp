CRM.$(function($) {
  var list_field = $('#mailchimp_list');
  var interest_groups_field = $('#mailchimp_interest_groups');
  var interest_groups_field_wrapper = $('.crm-group-form-block-mailchimp_interest_groups');
  var interest_groups_lookup = CRM.civiMailchimp.interest_groups_lookup;
  var list_id = '';
  var interest_groups = '';
  var interest_group_options = '';

  $('#mailchimp-settings').insertAfter('h3 + table.form-layout-compressed');

  updateInterestGroupsField();

  list_field.change(function() {
    updateInterestGroupsField();
  });

  function updateInterestGroupsField() {
    list_id = list_field.val();
    clearInterestGroupsField();
    interest_group_options = replaceInterestGroupSelectOptions(list_id);
    if (interest_group_options) {
      interest_groups_field_wrapper.show();
    }
    else {
      interest_groups_field_wrapper.hide();
    }
  }

  function replaceInterestGroupSelectOptions(list_id) {
    interest_groups = filterInterestGroups(list_id);
    interest_group_options = formatSelectOptions(interest_groups);
    interest_groups_field.append(interest_group_options);
    return interest_group_options;
  }

  function clearInterestGroupsField() {
    interest_groups_field.empty().val("");
    if (interest_groups_field.select2) {
      interest_groups_field.select2("val", "");
    }
  }

  function filterInterestGroups(mailchimp_list) {
    return interest_groups_lookup[mailchimp_list];
  }

  function formatSelectOptions(interest_groups) {
    interest_group_options = '';
    if (interest_groups) {
      $.each(interest_groups, function(key, value) {
        interest_group_options += "<option value='" + key + "'>" + value + "</option>";
      });
    }
    return interest_group_options;
  }
});
