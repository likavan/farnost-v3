/**
 * Registrácia editor previewov pre dynamic site bloky.
 *
 * Bloky sú server-side only (PHP render_callback), ale Gutenberg/Site Editor
 * potrebuje JS metadata + edit komponentu aby vedel blok zobraziť v editori.
 * Edit komponent renderuje ServerSideRender ktorý zavolá REST endpoint
 * /wp-block-renderer/v1/block-renderer/<name> a vráti server-rendered HTML.
 */

import { registerBlockType } from '@wordpress/blocks';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

const BLOCKS = [
	{ name: 'farnost/banner',         title: 'Mimoriadny oznam (banner)' },
	{ name: 'farnost/feed',           title: 'Feed príspevkov' },
	{ name: 'farnost/main-nav',       title: 'Hlavné menu' },
	{ name: 'farnost/site-brand',     title: 'Brand farnosti (názov + kríž/logo)' },
	{ name: 'farnost/site-header',    title: 'Hlavička webu' },
	{ name: 'farnost/site-footer',    title: 'Päta webu' },
	{ name: 'farnost/mass-widget',    title: 'Widget — bohoslužby tento týždeň' },
	{ name: 'farnost/contact-widget', title: 'Widget — farský úrad' },
	{ name: 'farnost/quote-widget',   title: 'Widget — citát' },
	{ name: 'farnost/schedule-table', title: 'Týždenný rozpis omší (tabuľka)' },
	{ name: 'farnost/archive-list',   title: 'Archív oznamov (zoznam)' },
];

BLOCKS.forEach( ( b ) => {
	registerBlockType( b.name, {
		apiVersion: 3,
		title: __( b.title, 'farnost-plugin' ),
		category: 'farnost',
		icon: 'admin-site',
		supports: { html: false, customClassName: false },
		edit: () => (
			<div className="farnost-block-preview">
				<ServerSideRender block={ b.name } />
			</div>
		),
		save: () => null,
	} );
} );
