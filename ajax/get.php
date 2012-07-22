<?php
/**
* @package shorty-tracking an ownCloud url shortener plugin addition
* @category internet
* @author Christian Reiner
* @copyright 2012-2012 Christian Reiner <foss@christian-reiner.info>
* @license GNU Affero General Public license (AGPL)
* @link information 
* @link repository https://svn.christian-reiner.info/svn/app/oc/shorty-tracking
*
* This library is free software; you can redistribute it and/or
* modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
* License as published by the Free Software Foundation; either
* version 3 of the license, or any later version.
*
* This library is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU AFFERO GENERAL PUBLIC LICENSE for more details.
*
* You should have received a copy of the GNU Affero General Public
* License along with this library.
* If not, see <http://www.gnu.org/licenses/>.
*
*/

/**
 * @file ajax/list.php
 * @brief Ajax method to retrieve a list of clicks to a Shorty
 * @returns (json) success/error state indicator
 * @returns (number) Total number of shortys in the list
 * @returns (json) Numeric array of all shortys, associative array of attributes as values for every single shorty contained
 * @author Christian Reiner
 */

// swallow any accidential output generated by php notices and stuff to preserve a clean JSON reply structure
OC_Shorty_Tools::ob_control ( TRUE );

//no apps or filesystem
$RUNTIME_NOSETUPFS = TRUE;

// Sanity checks
OCP\JSON::callCheck ( );
OCP\JSON::checkLoggedIn ( );
OCP\JSON::checkAppEnabled ( 'shorty' );
OCP\JSON::checkAppEnabled ( 'shorty-tracking' );

try
{
  define ('PAGE_SIZE', 100);
  $p_shorty = OC_Shorty_Type::req_argument ( 'shorty', OC_Shorty_Type::ID,      TRUE );
  $p_offset = OC_Shorty_Type::req_argument ( 'offset', OC_Shorty_Type::INTEGER, FALSE);

// ok, seems there is a problem in OCs pdo wrapper class (db class):
// it does not bind int values with the correct type
// instead I bind the values below using $query->bindValue where I can specify an explicit type
//   $param = array
//   (
//     ':shorty'     => $p_shorty,
// //     ':limit'      => (int)PAGE_SIZE,
//     ':limit'      => $query->bindValue(':limit', (int)PAGE_SIZE, PDO::PARAM_INT),
//   );
  if ($p_offset)
  {
    $query = OCP\DB::prepare ( OC_ShortyTracking_Query::CLICK_LIST_CHUNK );
    $query->bindValue(':offset', $p_offset);
  }
  else
    $query = OCP\DB::prepare ( OC_ShortyTracking_Query::CLICK_LIST_START );
  // bind query parameters directly see comment above where the "$param" array is commented out
  $query->bindValue(':shorty', $p_shorty);
  $query->bindValue(':limit', (int)PAGE_SIZE, PDO::PARAM_INT);
  // execute query
  $result = $query->execute();
  $chunk = $result->fetchAll();

  // retrieve stats of list
  $query = OCP\DB::prepare ( OC_ShortyTracking_Query::CLICK_LIST_STATS );
  $query->bindValue(':shorty', $p_shorty);
  $result = $query->execute();
  $stats  = $result->fetchAll();

  // swallow any accidential output generated by php notices and stuff to preserve a clean JSON reply structure
  OC_Shorty_Tools::ob_control ( FALSE );
  OCP\Util::writeLog( 'shorty-tracking', sprintf("Prepared list of clicks holding %s entries.",sizeof($chunk)), OC_Log::DEBUG );
  OCP\JSON::success ( array ( 'data'    => $chunk,
                              'count'   => sizeof($chunk),
                              'offset'  => $p_offset,
                              'rest'    => (empty($chunk)||($stats[0]['first']==$chunk[sizeof($chunk)-1]['id'])) ? FALSE : TRUE,
                              'stats'   => $stats,
                              'message' => sprintf('%s: %s',OC_ShortyTracking_L10n::t("Number of entries"), count($chunk)) ) );
} catch ( Exception $e ) { OC_Shorty_Exception::JSONerror($e); }
?>
