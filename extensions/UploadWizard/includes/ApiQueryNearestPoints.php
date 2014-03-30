<?php
/**
 *
 *
 * Copyright © 2013 Lukasz Kostrzewa <lukasz.kostrzewa@uj.edu.pl>
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
 */

/**
 * Query module to get the closest points
 *
 * @ingroup API
 */
class ApiQueryNearestPoints extends ApiQueryBase {
	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'up' );
	}

	public function execute() {
		wfProfileIn( __METHOD__ );
		$params = $this->extractRequestParams();

		$x1 = (float)$params['swlat'];
		$y1 = (float)$params['swlon'];
		$x2 = (float)$params['nelat'];
		$y2 = (float)$params['nelon'];
		$poly = "GeomFromText('POLYGON(($x1 $y1, $x2 $y1, $x2 $y2, $x1 $y2, $x1 $y1))')";
		
		$dbType = $this->getDB()->getType();
		if ($dbType == "mysql") {
			$this->addWhere( "MBRCONTAINS($poly, dp_location)" );
		} else if ($dbType == "sqlite") {
		} else { // postgres
		}

		$this->addTables( 'uw_desired_photo' );
		//$this->addWhere( "ST_DWithin (". $myLocation .", dp_location, 10000)" );
		$this->addOption( 'LIMIT', 40 );
		//$this->addOption( 'ORDER BY', "ST_Distance (". $myLocation .", dp_location)" );

		$this->addFields( array(
			'dp_name',
			'x(dp_location) AS lat',
			'y(dp_location) AS lon',
			'dp_article'
		) );

		wfProfileIn( __METHOD__ . '-sql' );
		$res = $this->select( __METHOD__ );
		wfProfileOut( __METHOD__ . '-sql' );

		$result = $this->getResult();

		$count = 0;

		foreach ( $res as $row ) {
			$path = 'm' . $count++;

			$result->addValue(
				$path,
				'name',
				$row->dp_name
			);
			$result->addValue(
				$path,
				'lat',
				$row->lat
			);
			$result->addValue(
				$path,
				'lon',
				$row->lon
			);
			$result->addValue(
				$path,
				'article',
				$row->dp_article
			);
		}
		wfProfileOut( __METHOD__ );
	}

	public function getCacheMode( $params ) {
		return 'public';
	}

	public function getAllowedParams() {
		return array(
			'swlat' => array(
				ApiBase::PARAM_MIN => -90,
				ApiBase::PARAM_MAX => 90
			),
			'swlon' => array(
				ApiBase::PARAM_MIN => -180,
				ApiBase::PARAM_MAX => 180
			),
			'nelat' => array(
				ApiBase::PARAM_MIN => -90,
				ApiBase::PARAM_MAX => 90
			),
			'nelon' => array(
				ApiBase::PARAM_MIN => -180,
				ApiBase::PARAM_MAX => 180
			)
		);
	}

	public function getParamDescription() {
		return array(
			'swlat' => 'Latitude of south-west point',
			'swlon' => 'Longitude of south-west point',
			'nelat' => 'Latitude of north-east point',
			'nelon' => 'Longitude of north-east point'
		);
	}

	public function getDescription() {
		return 'Get the closest points to given latitude and longitude';
	}

	public function getExamples() {
		return array(
			'api.php?action=query&list=nearestpoints&uplat=51.507222&uplon=-0.1275' 
				=> 'Get the closest points to London'
		);
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:UploadWizard/API';
	}
}

