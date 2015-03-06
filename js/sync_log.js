cj(function($) {
  CRM.civiMailchimpSyncLog = function() {
    this.initialize();
  }

  CRM.civiMailchimpSyncLog.prototype = {
    initialize: function() {
      this.showHideLogMessageDetails();
      this.clearMessage();
    },
    showHideLogMessageDetails: function() {
      $(".show-hide").click(function(event) {
        event.preventDefault();
        $(this).next('div').toggle();
      });
    },
    clearMessage: function() {
      $(".clear-link").click(function(event) {
        event.preventDefault();
        var url = $(this).attr('href');
        var link_clicked = $(this);
        $.ajax({
          url: url
        })
        .error(function(xhr, textStatus, errorThrown) {
          alert("Request failed: " + textStatus);
        })
        .success(function(data) {
          if (data.status === 'error') {
            alert("Unable to clear CiviMailchimp log message: [" + data.code +"] " + data.message);
          }
          else {
            link_clicked.closest('.ui-notify-message').find('.icon.ui-notify-close').click();
          }
        });
      });
    },
  }

  var sync_log = new CRM.civiMailchimpSyncLog();
});
