<?php
namespace Limbonia\Ldap;

/**
 * Wrapper around an LDAP result and the associated LDAP link
 */
class Result
{
  /**
   * LDAP link identifier
   *
   * @var resource
   */
  protected $rLdapLink;

  /**
   * Internal LDAP result
   *
   * @var resource
   */
  protected $rLdapResult;

  /**
   * Recursively clean the specified LDAP entry so that we can use it more easily
   *
   * @param array $hEntry
   * @return array
   */
  protected static function cleanEntry($hEntry)
  {
    $hReturn = [];

    for ($i = 0; $i < $hEntry['count']; $i++)
    {
      if (is_null($hEntry[$i]))
      {
        continue;
      }

      if (is_array($hEntry[$i]))
      {
        $hSubtree = $hEntry[$i];

        //This condition should be superfluous so just take the recursive call
        //adapted to your situation in order to increase perf.
        if (!empty($hSubtree['dn']) and !isset($hReturn[$hSubtree['dn']]))
        {
          $hReturn[$hSubtree['dn']] = self::cleanEntry($hSubtree);
        }
        else
        {
          $hReturn[] = self::cleanEntry($hSubtree);
        }
      }
      else
      {
        $attribute = $hEntry[$i];

        if ($hEntry[$attribute]['count'] == 1)
        {
          $hReturn[$attribute] = $hEntry[$attribute][0];
        }
        else
        {
          for ($j = 0; $j < $hEntry[$attribute]['count'] - 1; $j++)
          {
            $hReturn[$attribute][] = $hEntry[$attribute][$j];
          }
        }
      }
    }

    return $hReturn;
  }

  /**
   * Create an LDAP Result object
   *
   * @param resource $rLdapLink - LDAP link resource
   * @param resource $rLdapResult - LDAP result resource
   */
  public function __construct($rLdapLink, $rLdapResult)
  {
    $this->rLdapLink = $rLdapLink;
    $this->rLdapResult = $rLdapResult;
  }

  /**
   * Free the current result
   */
  public function __destruct()
  {
    ldap_free_result($this->rLdapResult);
  }

  /**
   * Throw the appropriate Exception if an LDAP error has occurred
   *
   * @throws \Exception
   */
  protected function throwOnError()
  {
    Resource::throwOnLdapError($this->rLdapLink);
  }

  /**
   * Retrieve the LDAP pagination cookie
   *
   * @return array
   */
  public function pagedResultResponse()
  {
    $xCookie = null;
    $xEstimated = null;
    ldap_control_paged_result_response($this->rLdapLink, $this->rLdapResult, $xCookie, $xEstimated);
    return ['cookie' => $xCookie, 'estimated' => $xEstimated];
  }

  /**
   * Count the number of entries in a search
   *
   * @return integer
   */
  public function countEntries()
  {
    return ldap_count_entries($this->rLdapLink, $this->rLdapResult);
  }

  /**
   * Get all result entries
   *
   * @return array - Complete result information in a multi-dimensional array
   * @throws \Exception on error
   */
  public function getEntries()
  {
    $hEntries = ldap_get_entries($this->rLdapLink, $this->rLdapResult);

    if (false === $hEntries)
    {
      $this->throwOnError();
    }

    return self::cleanEntry($hEntries);
  }

  /**
   * Extract information from reference entry
   *
   * @return array
   */
  public function parseReference()
  {
    $aReferrals = null;
    ldap_parse_reference($this->rLdapLink, $this->rLdapResult, $aReferrals);
    return $aReferrals;
  }

  /**
   * Sort LDAP result entries on the client side
   *
   * @param string $sBy - String to sort the entries by
   * @return boolean
   */
  public function sort($sBy)
  {
    return ldap_sort($this->rLdapLink, $this->rLdapResult, $sBy);
  }
}