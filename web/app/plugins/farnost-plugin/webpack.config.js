/**
 * Webpack config rozširujúci defaults z @wordpress/scripts.
 *
 * Editor panely majú vlastné entry pointy v editor/, výsledky idú do build/.
 * Každý entry vyprodukuje aj sprievodný build/<entry>.asset.php so závislosťami,
 * ktorý PHP enqueuer číta pri registrácii skriptov.
 */

const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
	...defaultConfig,
	entry: {
		'panel-kostol':  './editor/panel-kostol/index.js',
		'panel-oznam':   './editor/panel-oznam/index.js',
		'panel-vynimka': './editor/panel-vynimka/index.js',
		'panel-umysel':  './editor/panel-umysel/index.js',
		'panel-udalost': './editor/panel-udalost/index.js',
	},
};
