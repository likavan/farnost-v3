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
		'panel-oznam':   './editor/panel-oznam/index.js',
		'panel-vynimka': './editor/panel-vynimka/index.js',
		'panel-umysel':  './editor/panel-umysel/index.js',
		'panel-udalost': './editor/panel-udalost/index.js',
		'calendar':              './editor/calendar/index.js',
		'kostoly':               './editor/kostoly/index.js',
		'upratovacie':           './editor/upratovacie/index.js',
		'wizard':                './editor/wizard/index.js',
		'block-rozpis-snapshot': './editor/block-rozpis-snapshot/index.js',
	},
};
