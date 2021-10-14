<?php

require_once 'fpptamembertweaks.civix.php';
// phpcs:disable
use CRM_Fpptamembertweaks_ExtensionUtil as E;
// phpcs:enable

/**
 * Month-day (MM/DD) of the date each year on which membership renewals should
 * open.
 */
define('FPPTAMEMBERTWEAKS_RENEWAL_OPEN_DATE', '10/15');

/**
 * Implements hook_civicrm_pre().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_pre
 */
function fpptamembertweaks_civicrm_pre($op, $objectName, $objectId, &$params) {
  if ($objectName == 'Membership'
    && ($op == 'create' || $op == 'edit')
  ) {
    if (
      // If we're in the context of a front-end contribution page
      $_REQUEST['q'] == 'civicrm/contribute/transact'
      // and a payment processor is selected (i.e., this is not a "pay later" contribution)
      && (!$params['contribution']->is_pay_later)
      // and this is the primary member (i.e., not a related membership)
      && (empty($params['owner_membership_id']) || $params['owner_membership_id'] == $objectId)
      // and this is not a test transaction
      && (!$params['is_test'])
    ) {
      if (_fpptamembertweaks_membership_is_protected_type($objectRef)) {
        $params['end_date'] = _fpptamembertweaks_get_end_date_for_current_renewal_period();
      }
    }
  }
}

/**
 * Implements hook_civicrm_alterTemplateFile().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterTemplateFile
 */
function fpptamembertweaks_civicrm_alterTemplateFile($formName, &$form, $context, &$tplName) {
  if ($form->fpptamembertweaks_hide_form) {
    // Per calculations in the buildAmount hook, we have no membership types to
    // offer, so we will hide the form entirely. User-facing alert messages 
    // were set in the buildAmount hook.
    $tplName = '';
  }
}

/**
 * Implements hook_civicrm_buildAmount().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_buildAmount
 */
function fpptamembertweaks_civicrm_buildAmount( $pageType, &$form, &$amount ) {
  // Only on the contribution page main form:
  if (get_class($form) == 'CRM_Contribute_Form_Contribution_Main') {
    // Shorthand var for all membership types originally available on this form.
    $totalMembershipTypes = [];
    // Shorthand var for all disallowed membership types.
    $disallowedMembershipTypes = [];
    // Shorthand var for the actual disallowed memberships.
    $disallowedMemberships = [];
    // Loop through all price fields looking for any that are tied to memberships.
    foreach ($amount as $fieldId => &$field) {
      foreach($field['options'] as $optionId => $option) {
        $membershipTypeId = $option['membership_type_id'];
        if (!empty($option['membership_type_id'])) {
          // Note that we originally offered this membership type.
          $totalMembershipTypes[] = $membershipTypeId;
          // Get any memberships of this type for the current user.
          $userMembershipsOfType = _fpptamembertweaks_get_user_memberships_of_type($membershipTypeId);
          foreach ($userMembershipsOfType as $membership) {
            if (_fpptamembertweaks_is_renewal_disallowed($membership)) {
              // If this membership is not ready for renewal:
              // Note that this membership type should be removed.
              $disallowedMembershipTypes[] = $membershipTypeId;
              // Store the membership for reference below.
              $disallowedMemberships[] = $membership;
              // Remove the membership type as an option in the form.
              unset($field['options'][$optionId]);
            }
          }
        }
      }    
    }
    // Add user-facing messages for any disallowed memberships.
    foreach ($disallowedMemberships as $membership) { 
      $membershipTypeName = civicrm_api3('MembershipType', 'getvalue', [
        'return' => "name",
        'id' => $membership['membership_type_id'],
      ]);
      CRM_Core_Session::setStatus(ts('Your <strong>%1</strong> membership does not expire until %2. Please renew again after %3.', [
        '1' => $membershipTypeName,
        '2' => CRM_Utils_Date::customFormat($membership['end_date'], $config->dateformatTime),
        '3' => CRM_Utils_Date::customFormat(date('Y-m-d', _fpptamembertweaks_get_timestamp_when_membership_can_renew($membership)), $config->dateformatTime),
      ]));
    }
    
    if (!empty($totalMembershipTypes) && (count(array_unique($totalMembershipTypes)) == count(array_unique($disallowedMembershipTypes)))) {
      // If there are membership types on this form, but they've all been removed,
      // set a flag to hide the form. We'll do this in the alterTemplateFile hook.
      $form->fpptamembertweaks_hide_form = TRUE;
    }
  }
}

/**
 * Implements hook_civicrm_pageRun().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_pageRun
 */
function fpptamembertweaks_civicrm_pageRun($page) {
  $pageName = $page->getVar('_name');
  if ($pageName == 'CRM_Contact_Page_View_UserDashBoard') {
    // Hide renewal where appropriate for active memberships
    $activeMembers = $page->get_template_vars('activeMembers');
    foreach ($activeMembers as &$activeMember) {
      if (_fpptamembertweaks_is_renewal_disallowed($activeMember)) {
        unset($activeMember['renewPageId']);
      }
    }
    $page->assign('activeMembers', $activeMembers);

    // Hide renewal where appropriate for inactive memberships
    $inActiveMembers = $page->get_template_vars('inActiveMembers');
    foreach ($inActiveMembers as &$inActiveMember) {
      if (_fpptamembertweaks_is_renewal_disallowed($inActiveMember)) {
        unset($inActiveMember['renewPageId']);
      }
    }
    $page->assign('inActiveMembers', $inActiveMembers);

    // Replace "Renew Now" in renew links with "Renew for YYYY", where YYYY is a
    // 4-digit representation of the coming year.
    // If we don't use addString(), this string may not be replaced using ts() in JavaScript.
    CRM_Core_Resources::singleton()->addString('Renew Now');
    $renewForNextYearLabel = E::ts('Renew for %1', [
      '1' => date('Y', strtotime(_fpptamembertweaks_get_end_date_for_current_renewal_period())),
    ]);
    CRM_Core_Resources::singleton()->addVars('fpptamembertweaks', ['renewForNextYearLabel' => $renewForNextYearLabel]);
    CRM_Core_Resources::singleton()->addScriptFile('com.joineryhq.fpptamembertweaks', 'js/CRM_Contact_Page_View_UserDashBoard.js');
  }
}

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function fpptamembertweaks_civicrm_config(&$config) {
  _fpptamembertweaks_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
 */
function fpptamembertweaks_civicrm_xmlMenu(&$files) {
  _fpptamembertweaks_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function fpptamembertweaks_civicrm_install() {
  _fpptamembertweaks_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function fpptamembertweaks_civicrm_postInstall() {
  _fpptamembertweaks_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function fpptamembertweaks_civicrm_uninstall() {
  _fpptamembertweaks_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function fpptamembertweaks_civicrm_enable() {
  _fpptamembertweaks_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function fpptamembertweaks_civicrm_disable() {
  _fpptamembertweaks_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function fpptamembertweaks_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _fpptamembertweaks_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
 */
function fpptamembertweaks_civicrm_managed(&$entities) {
  _fpptamembertweaks_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_caseTypes
 */
function fpptamembertweaks_civicrm_caseTypes(&$caseTypes) {
  _fpptamembertweaks_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
 */
function fpptamembertweaks_civicrm_angularModules(&$angularModules) {
  _fpptamembertweaks_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
 */
function fpptamembertweaks_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _fpptamembertweaks_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function fpptamembertweaks_civicrm_entityTypes(&$entityTypes) {
  _fpptamembertweaks_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_thems().
 */
function fpptamembertweaks_civicrm_themes(&$themes) {
  _fpptamembertweaks_civix_civicrm_themes($themes);
}

/**
 * Get the unix timestamp of midnight beginning the day of FPPTAMEMBERTWEAKS_RENEWAL_OPEN_DATE
 * in the current year.
 * @return Boolean
 */
function _fpptamembertweaks_get_current_year_renewal_open_timestamp() {
  return strtotime(FPPTAMEMBERTWEAKS_RENEWAL_OPEN_DATE);
}

/**
 * Determine whether or not we have passed the renewals open date for the current year.
 * @return Boolean
 */
function _fpptamembertweaks_is_current_year_renewal_cutoff_passed() {
  return (time() >= _fpptamembertweaks_get_current_year_renewal_open_timestamp());
}

/**
 * For a given membership, determine whether end_date is after the last
 * day of the current year.
 * @param Array $membership An array of membership properties; this array is expected
 *  to have an element 'end_date' containing the value of civicrm_membership.end_date
 * @return Boolean
 */
function _fpptamembertweaks_membership_expires_after_current_year(array $membership) {
  $lastDayOfCurrentYear = strtotime('12/31');
  $endDate = strtotime($membership['end_date']);
  return ($endDate > $lastDayOfCurrentYear);
}

function _fpptamembertweaks_get_end_date_for_current_renewal_period() {
  // Is today on or after the cutoff date?
  if (_fpptamembertweaks_is_current_year_renewal_cutoff_passed()) {
    // End date for renewal period is dec. 31 of next year.
    $endDate = date('Y', strtotime('+1 year')) . '1231';
  }
  else {
    // End date for renewal period is dec. 31 of this year.
    $endDate = date('Y') . '1231';
  }
  return $endDate;
}

function _fpptamembertweaks_is_renewal_disallowed(array $membership) {
  // If the current time is less than the time when renewal is allowed, then disallow.
  $disallow = (time() < _fpptamembertweaks_get_timestamp_when_membership_can_renew($membership));
  return $disallow;
}


function _fpptamembertweaks_get_timestamp_when_membership_can_renew(array $membership) {
  if (!_fpptamembertweaks_membership_is_protected_type($membership)) {
    // If this is not a protected type, we'll let them renew anytime.
    $timestamp = time();
  }
  // Otherwise, they can renew after renewal-open-date in the year of their expiration.
  $expirationYear = date('Y', strtotime($membership['end_date']));
  return strtotime($expirationYear . '/' . FPPTAMEMBERTWEAKS_RENEWAL_OPEN_DATE);
}

/**
 * For a given membership, determine whether end_date is within the current year.
 * @param Array $membership An array of membership properties; this array is expected
 *  to have an element 'end_date' containing the value of civicrm_membership.end_date
 * @return Boolean
 */
function _fpptamembertweaks_membership_expires_in_current_year(array $membership) {
  $currentYear = date('Y');
  $membershipEndDateYear = date('Y', strtotime($membership['end_date']));
  return ($membershipEndDateYear == $currentYear);
}

function _fpptamembertweaks_membership_is_protected_type($membership) {
  // convert membership to array if it's an object.
  $membership = (array)$membership;
  // Ensure we're only doing this for associate and pension_board memberships.
  $protectedTypeIds = [1, 2];
  return (in_array(strtolower($membership['membership_type_id']), $protectedTypeIds));
}


function _fpptamembertweaks_get_user_memberships_of_type($membershipTypeId) {
  $cid = CRM_Core_Session::singleton()->getLoggedInContactID();
  $membershipGet = civicrm_api3('Membership', 'get', [
    'contact_id' => $cid,
    'membership_type_id' => $membershipTypeId,
  ]);
  return $membershipGet['values'];
  
}