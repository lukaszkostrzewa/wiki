<?php
/**
 * Imports places without images using http://toolserver.org/~erfgoed/api/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Maintenance
 * @author Lukasz Kostrzewa <lukasz.kostrzewa@uj.edu.pl>
 */

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = dirname( __FILE__ ) . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script to import places without images to the database.
 *
 * @ingroup Maintenance
 */
class ImportDesiredPhotos extends Maintenance {

	/**
	 * @var DatabaseBase
	 */
	private $dbr = null;
	private $apiUrl = 'http://toolserver.org/~erfgoed/api/api.php?action=search&srwithoutimages=1&userlang=en&format=xml';

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Import places without images to database";
		$this->addOption( 'country', 'Country from which import places', false, true, 'c');
		$this->addOption( 'municipality', 'Municipality from which import places', false, true, 'm');
	}

	public function execute() {
		header('Content-type: text/html; charset=utf-8');
		$options = '&srcountry=' . $this->getOption('country');
		$options .= '&srmunicipality=' . $this->getOption('municipality');
		$this->apiUrl .= $options;
		
		$this->dbr = wfGetDB(DB_MASTER);
		$this->output( "Starting to import...\n" );
		
		$monuments = simplexml_load_file($this->apiUrl);
		$hasNextPage = true;
		$pages = 1;
		$query = "INSERT IGNORE INTO uw_desired_photo (dp_location, dp_name) VALUES ";
		
		while ($hasNextPage) {
			$this->output("Importing page " . $pages++ . "\n");
			$hasNextPage = false;
			foreach ($monuments as $monument) {
				// continue node - contains link to next page
				if ($monument->getName() == 'continue') {
					$hasNextPage = true;
					$nextPageUrl = $this->apiUrl . "&srcontinue=" . $monument['srcontinue'];
					$monuments = simplexml_load_file($nextPageUrl);
					break;
				}
				
				// check if monument truly doesn't have an image
				if ($monument['image'] != "") {
					continue;
				}
				
				// check if monument has got latitude and longitude
				if ($monument['lat'] == "" || $monument['lon'] == "") {
					continue;
				}
				
				$location = "GeomFromText('POINT(". (float)$monument["lat"] ." ". (float)$monument["lon"] .")')";
				$name = $monument["name"]; // todo: encoding
				$query .= "(". $location .", '". $name ."'),";
			}
		}
		
		$query = substr($query, 0, -1);
		//echo $query . "\n";
		$this->dbr->query($query);
		$this->output("Affected rows: " . $this->dbr->affectedRows());
	}
}

$maintClass = "ImportDesiredPhotos";
require_once RUN_MAINTENANCE_IF_MAIN;
