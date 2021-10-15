(function($) {
  var membershipTypePriceFieldSelector = '#price_' + CRM.vars.fpptamembertweaks.membershipTypeFieldId;
  // If membership_type field is not <select>, this may break; so try to print a warning in the console.
  if (!$(membershipTypePriceFieldSelector).is('select')) {
    if(window.console) {
      console.log('WARNING (fpptamembertweaks): membership type element type is not "select". Custom handling may not perform as expected.');
    }
  }

  /**
   * Based on the selected membership type price option, determine the selected membership_type_id.
   * @returns String
   */
  var getSelectedMembershipTypeId = function getselectedMembershipTypeId() {
    var selectedOptionValue = $(membershipTypePriceFieldSelector).val();
    var selectedMembershipTypeId = 0;
    if (selectedOptionValue) {
      selectedMembershipTypeId = $(membershipTypePriceFieldSelector).data().priceFieldValues[selectedOptionValue].membership_type_id;
    }
    return selectedMembershipTypeId;
  }

  /**
   * Compare selected options to determine whether it represents a disallowed org/member_type,
   * based on values passed to us from the extension PHP.
   * @returns {Boolean}
   */
  var isDisallowedMembershipTypeForOrg = function isDisallowedMembershipTypeForOrg(membershipTypeId, orgCid) {
    var disallowed = false;
    if(
      typeof CRM.vars.fpptamembertweaks.disallowedMembershipTypeMessagesPerOrg != 'undefined'
      && typeof CRM.vars.fpptamembertweaks.disallowedMembershipTypeMessagesPerOrg[orgCid] != 'undefined'
      && typeof CRM.vars.fpptamembertweaks.disallowedMembershipTypeMessagesPerOrg[orgCid][membershipTypeId] != 'undefined'
    ) {
      var disallowed = true;
    }
    return disallowed;
  }

  /**
   * Disable (or enable) submissions on this form; if disabling, display a message.
   * @param bool isDisable If true, disable and print message; otherwise enable.
   * @param string message Error message to display for user.
   */
  var disableSubmissions = function disableSubmissions(isDisable, message) {
    $('div#fpptamembertweaks_disallowed_warning').remove();
    if(isDisable) {
      $('button[type="submit"]').prop("disabled",true);
      $('select#onbehalfof_id').closest('div.crm-section').after('<div id="fpptamembertweaks_disallowed_warning" class="crm-inline-error">' + message + '</div>');
    }
    else {
      $('button[type="submit"]').prop("disabled",false);
    }
  }

  /**
   * Change handler to validate org/member_type selections.
   */
  var validateOrgMembershipSelection = function validateOrgMembershipSelection() {
    var orgCid = $('select#onbehalfof_id').val();
    var selectedMembershipTypeId = getSelectedMembershipTypeId();
    var onBehalfIsNew = $('input[name="org_option"]:checked').val() * 1;

    if (!onBehalfIsNew && isDisallowedMembershipTypeForOrg(selectedMembershipTypeId, orgCid)) {
      disableSubmissions(true, CRM.vars.fpptamembertweaks.disallowedMembershipTypeMessagesPerOrg[orgCid][selectedMembershipTypeId]);
    }
    else {
      disableSubmissions(false);
    }
  }

  // Add change handler to relevant org/member_type fields
  $('input[name="org_option"]').change(validateOrgMembershipSelection);
  $('select#onbehalfof_id').change(validateOrgMembershipSelection);
  $(membershipTypePriceFieldSelector).change(validateOrgMembershipSelection);
})(CRM.$ || cj);
