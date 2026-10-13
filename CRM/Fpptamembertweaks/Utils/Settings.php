<?php

class CRM_Fpptamembertweaks_Utils_Settings {
  
  public static function get($name) {
    if (method_exists(static::class, 'get_' . $name)) {
      return call_user_func_array([static::class, 'get_' . $name], [$name]);
    }
    return \Civi::settings()->get($name);
  }

  public static function set($name, $value) {
    if (method_exists(static::class, 'set_' . $name)) {
      call_user_func_array([static::class, 'set_' . $name], [$value]);
    }
    else {
      \Civi::settings()->set($name, $value);
    }
  }

  public static function validateRenewalOpenDate($value) {
    // Ensure 'NN/NN' format.
    if (!preg_match('/^\d{2}\/\d{2}$/', $value)) {
      return FALSE;
    }

    // Ensure valid date which is not Feb 29 (we test with year 2001, which has no Feb. 29)
    [$month, $day] = array_map('intval', explode('/', $value));
    return checkdate($month, $day, 2001);
  }

  public static function setBadgeLayoutHasQrCode(int $badgeLayoutId, $bool) {
    $badgeLayoutIds = self::get_anoncheckin_qr_badge_layouts();
    if ($bool) {
      $badgeLayoutIds[] = $badgeLayoutId;
    }
    else {
      $badgeLayoutIds = array_diff($badgeLayoutIds, [$badgeLayoutId]);
    }
    self::set_anoncheckin_qr_badge_layouts($badgeLayoutIds);
  }

  private static function get_anoncheckin_qr_badge_layouts() {
    $ret = [];
    $value = \Civi::settings()->get('anoncheckin_qr_badge_layouts');
    if (!empty($value)) {
      $ret = explode(',', $value);
    }
    return $ret;
  }

  private static function set_anoncheckin_qr_badge_layouts(array $value) {
    \Civi::settings()->set('anoncheckin_qr_badge_layouts', implode(',', array_unique($value)));
  }
  
  public static function getMessageTemplateOptions() {
    $options = ['' => ts('- select -')] + CRM_Core_BAO_MessageTemplate::getMessageTemplates(FALSE);
    return $options;
  }

  public static function getTimezoneOptions() {
    $identifiers = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
    return ['' => ts('- select -')] + array_combine($identifiers, $identifiers);
  }
  
}
