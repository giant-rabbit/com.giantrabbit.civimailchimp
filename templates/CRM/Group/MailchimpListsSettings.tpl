<div id="mailchimp-settings">
  <h3>Mailchimp Sync Settings</h3>
  {if !$mailchimp_lists}
    <div id="help">{ts}There are no Mailchimp Lists defined. Please ensure that your Mailchimp API settings are correct and that you have at least one List created in your Mailchimp account.{/ts}</div>
  {/if}
  <table class="form-layout">
    <tbody>
      <tr class="crm-group-form-block-{$form.mailchimp_list.name}">
        <td class="label">
          <label for="{$form.mailchimp_list.name}">{$form.mailchimp_list.label}</label>
        </td>
        <td>
          {$form.mailchimp_list.html}
        </td>
      </tr>
      <tr class="crm-group-form-block-{$form.mailchimp_interest_groups.name}">
        <td class="label">
          <label
for="{$form.mailchimp_interest_groups.name}">{$form.mailchimp_interest_groups.label}</label>
        </td>
        <td>
          {$form.mailchimp_interest_groups.html}
        </td>
      </tr>
    </tbody>
  </table>
</div>
