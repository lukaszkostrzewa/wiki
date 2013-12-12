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

		$lat = (float)$params['lat'];
		$lon = (float)$params['lon'];
		$myLocation = "GeomFromText('Point(". $lat ." ". $lon .")')";

		/*
			SELECT * 
			FROM table AS a
			WHERE ST_DWithin (mylocation, a.LatLong, 10000) -- 10km
			ORDER BY ST_Distance (mylocation, a.LatLong)
			LIMIT 20
		*/
		$this->addTables( 'uw_desired_photo' );
		//$this->addWhere( "ST_DWithin (". $myLocation .", dp_location, 10000)" );
		$this->addOption( 'LIMIT', 20 );
		//$this->addOption( 'ORDER BY', "ST_Distance (". $myLocation .", dp_location)" );

		$this->addFields( array(
			'dp_name',
			'x(dp_location) AS lat',
			'y(dp_location) AS lon'
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
		}
		wfProfileOut( __METHOD__ );
	}

	public function getCacheMode( $params ) {
		return 'public';
	}

	public function getAllowedParams() {
		return array(
			'lat' => array(
				ApiBase::PARAM_MIN => -90,
				ApiBase::PARAM_MAX => 90
			),
			'lon' => array(
				ApiBase::PARAM_MIN => -90,
				ApiBase::PARAM_MAX => 90
			)
		);
	}

	public function getParamDescription() {
		return array(
			'lat' => 'Latitude',
			'lon' => 'Longitude'
		);
	}

	public function getDescription() {
		return 'Get the closest points to given latitude and longitude';
	}

	public function getExamples() {
		return array(
			'api.php?action=query&list=nearestpoints&uplat=51.507222&uplon=-0.1275' 
				=> 'Get the closest points to london'
		);
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Extension:UploadWizard/API';
	}
}

