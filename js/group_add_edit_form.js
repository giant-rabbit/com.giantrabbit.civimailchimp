CRM.$(function($) {
  CRM.civiMailchimp.interestGroups = function() {
    this.initialize();
  }

  CRM.civiMailchimp.interestGroups.prototype = {
    initialize: function() {
      this.list_field = $('#mailchimp_list');
      this.interest_groups_field = $('#mailchimp_interest_groups');
      this.interest_groups_field_wrapper = $('.crm-group-form-block-mailchimp_interest_groups');
      this.filtered_interest_groups = [];
      this.interest_group_options = '';

      this.list_field.change($.proxy(this.updateField, this));
      this.updateField();

      $('#mailchimp-settings').insertBefore('table + .crm-submit-buttons');
    },
    updateField: function() {
      this.clearOptions(); 
      this.filterInterestGroups();
      this.formatNewOptions();
      this.replaceOptions();
      if (this.interest_group_options) {
        this.interest_groups_field_wrapper.show();
      }
      else {
        this.interest_groups_field_wrapper.hide();
      }
    },
    clearOptions: function() {
      this.interest_group_options = '';
      this.interest_groups_field.empty().val("");
      if (this.interest_groups_field.select2) {
        this.interest_groups_field.select2("val", "");
      }
    },
    filterInterestGroups: function() {
      this.filtered_interest_groups = CRM.civiMailchimp.interest_groups_lookup[this.list_field.val()];
    },
    formatNewOptions: function() {
      var interest_groups = this;
      if (this.filtered_interest_groups) {
        $.each(this.filtered_interest_groups, function(key, value) {
          interest_groups.interest_group_options += "<option value='" + key + "'>" + value + "</option>";
        });
      }
    },
    replaceOptions: function() {
      this.interest_groups_field.append(this.interest_group_options);
    }, 
  }

  var interest_groups = new CRM.civiMailchimp.interestGroups();

});
