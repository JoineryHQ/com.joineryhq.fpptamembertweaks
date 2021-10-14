(function($) {
  // Replace any membership 'renew now' links with the appropriate label as given
  // by PHP in crm.vars. (This should be "Renew for YYYY" where "YYYY" represents
  // the next calendar year.)

  // We use ts() here because we have to do string replacement, and we want to be
  // sure we're replacing the right string even if that string has been altered
  // with Word Replacements or i18n.
  var tsRenewNow = ts('Renew Now');
  CRM.$('.crm-dashboard-civimember div#memberships table td:nth-child(6) a, .crm-dashboard-civimember div#ltype table td:nth-child(5) a').each(function(idx, el){
    // Find each of these links and replace the appropriate text in the link html.
    el = CRM.$(el);
    el.html(el.html().replace(tsRenewNow, CRM.vars.fpptamembertweaks.renewForNextYearLabel));
  });
})(CRM.$ || cj);
