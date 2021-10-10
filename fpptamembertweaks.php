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
 * Implements hook_civicrm_alterContent().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterContent
 */
function fpptamembertweaks_civicrm_pageRun($page) {
  $pageName = $page->getVar('_name');
  if ($pageName == 'CRM_Contact_Page_View_UserDashBoard') {
    // Strip Active Memberships of any CPPT-type memberships.
    $activeMembers = $page->get_template_vars('activeMembers');
    foreach ($activeMembers as &$activeMember) {
      $hideRenew = TRUE;
      if (
        _fpptamembertweaks_is_current_year_renewal_open()
        && _fpptamembertweaks_membership_expires_current_year_or_before($activeMember)
      ) {
        $hideRenew = FALSE;
      }
      if ($hideRenew) {
        unset($activeMember['renewPageId']);
      }
    }
    $page->assign('activeMembers', $activeMembers);

    // Replace "Renew Now" in renew links with "Renew for YYYY", where YYYY is a
    // 4-digit representation of the coming year.
    // If we don't use addString(), this string may not be replaced using ts() in JavaScript.
    CRM_Core_Resources::singleton()->addString('Renew Now');
    $renewForNextYearLabel = E::ts('Renew for %1', [
      '1' => date('Y', strtotime('+1 year')),
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
function _fpptamembertweaks_is_current_year_renewal_open() {
  return (time() > _fpptamembertweaks_get_current_year_renewal_open_timestamp());
}

/**
 * For a given membership, determine whether end_date is on or before the last
 * day of the current year.
 * @param Array $membership An array of membership properties; this array is expected
 *  to have an element 'end_date' containing the value of civicrm_membership.end_date
 * @return Boolean
 */
function _fpptamembertweaks_membership_expires_current_year_or_before($membership) {
  $lastDayOfCurrentYear = strtotime('12/31');
  $endDate = strtotime($membership['end_date']);
  return ($endDate <= $lastDayOfCurrentYear);
}
