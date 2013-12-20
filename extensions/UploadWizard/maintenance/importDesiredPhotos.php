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
	private $tableName = "uw_desired_photo";
	private $apiUrl = 'http://toolserver.org/~erfgoed/api/api.php?action=search&srwithoutimages=1&userlang=en&format=xml';

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Import places without images to database";
		$this->addOption( 'country', 'Country from which import places', false, true, 'c');
		$this->addOption( 'municipality', 'Municipality from which import places', false, true, 'm');
	}

	public function execute() {
		$country = $this->getOption('country');
		$municipality = $this->getOption('municipality');
		$options = '&srcountry=' . $country;
		$options .= '&srmunicipality=' . $municipality;
		$this->apiUrl .= $options;
		
		$this->dbr = wfGetDB(DB_MASTER);
		
		$this->output( "Cleaning database...\n" );
		// delete old entries
		if ($municipality != "") {
			$this->dbr->delete($this->tableName, array( 'dp_municipality' => $municipality ));
		} else if ($country != "") {
			$this->dbr->delete($this->tableName, array( 'dp_country' => $country ));
		} else {
			$this->dbr->delete($this->tableName, "*"); // delete all
		}

		$this->output( "Starting to import...\n" );
		$dbType = $this->dbr->getType(); // "mysql", "postgres" ("pgsql" or "PostgreSQL" in older versions), "sqlite"
		
		$monuments = simplexml_load_file($this->apiUrl);
		$hasNextPage = true;
		$hasAtLeastOne = false;
		$pages = 1;
		
		if ($dbType === "mysql") {
			$query = "INSERT IGNORE INTO ". $this->tableName ." (dp_location, dp_name, dp_article, dp_country, dp_municipality) VALUES ";
		} else if ($dbType === "sqlite") {
			$query = "";
		} else { // postgres
			$query = "";
		}
		
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
				
				$values = array();
				$location = "GeomFromText('POINT(". (float)$monument["lat"] ." ". (float)$monument["lon"] .")')";
				$name = "'".$monument["name"]."'"; // todo: encoding
				
				if($monument['monument_article'] != "") {
					$article = "'".$monument['monument_article']."'";
				} else {
					$article = $name;
				}
				
				$country = "'".$monument['country']."'";
				$municipality = "'".$monument['municipality']."'";
				array_push($values, $location, $name, $article, $country, $municipality);
				$query .= "(". implode(",", $values) ."),";
				$hasAtLeastOne = true;
			}
		}
		
		if($hasAtLeastOne) {
			$query = substr($query, 0, -1);
			$this->dbr->query($query);
			$this->output("Affected rows: " . $this->dbr->affectedRows());
		} else {
			$this->output("No monuments found.");
		}
	}
}

$maintClass = "ImportDesiredPhotos";
require_once RUN_MAINTENANCE_IF_MAIN;
