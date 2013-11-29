/**
 * Sort of an abstract class for map
 */
( function( mw, $ ) {

/**
 * Interface widget to show map containing missing photos in the user's neighborhood
 * @param {String|jQuery} selector where to put map
 */
mw.UploadWizardMap = function( selector ) {
	var _this = this;
	var div = _this.$selector = $( selector );
	var map;
	
	div.append('<div id="mwe-upwiz-map-needed-photos" class="mwe-map-needed-photos-container"></div>');

	_this.onLayoutReady = function() {
		L.Icon.Default.imagePath = "../Extensions/UploadWizard/Resources/leaflet/images/";
		map = L.map('mwe-upwiz-map-needed-photos');
		var osmUrl='http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
		var osm = new L.TileLayer(osmUrl);		
		map.addLayer(osm);
		map.locate({setView: true, maxZoom: 15});
	};
};

} )( mediaWiki, jQuery );
